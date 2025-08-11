<?php
/**
 * GN ASL + WP All Import Sync & Logger (module)
 * - Prevents duplicate creation when SKU already exists (forces update)
 * - Updates stock/prices for primary/secondary locations
 * - Syncs combined stock status
 * - Detailed admin log with Clear + 5MB rotation
 */

namespace GN_ASL\ImportSync;

if ( ! defined('ABSPATH') ) exit;

final class Module {
    const LOG_MAX   = 5242880; // 5MB
    const LOG_FILE  = 'gn-asl-import.log'; // Stored in wp-content/uploads/

    public static function boot() {
        // Only run if WooCommerce is present.
        if ( ! function_exists('wc_get_product_id_by_sku') ) return;

        // Soft dependency on WP All Import: hooks won’t fire if WPAI isn’t active.
        add_action('pmxi_saved_post', [__CLASS__, 'on_saved_post'], 10, 4);
        add_filter('pmxi_article_data', [__CLASS__, 'force_update_when_sku_exists'], 10, 2);

        // Admin log page.
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        
        // Location names (in case user keeps your defaults).
        add_filter('gn_asl_primary_location_name', fn() => 'Sneakfreaks', 5);
        add_filter('gn_asl_secondary_location_name', fn() => 'Golden Sneakers', 5);
    }

    /** Get absolute path to log (uploads dir). */
    private static function log_path() : string {
        $uploads = wp_get_upload_dir();
        return trailingslashit($uploads['basedir']) . self::LOG_FILE;
    }

    /** Rotate if over 5MB. */
    private static function rotate_if_needed() : void {
        $file = self::log_path();
        if ( file_exists($file) && filesize($file) > self::LOG_MAX ) {
            @rename($file, $file . '.' . gmdate('Ymd-His') . '.1');
        }
    }

    /** Write to log. */
    private static function log(string $msg, array $ctx = []) : void {
        self::rotate_if_needed();
        $line = '[' . gmdate('Y-m-d H:i:s') . 'Z] ' . $msg;
        if ($ctx) $line .= ' ' . wp_json_encode($ctx, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $line .= PHP_EOL;
        @file_put_contents(self::log_path(), $line, FILE_APPEND);
    }

    /**
     * pmxi_saved_post — after WPAI creates/updates a record.
     *
     * WP All Import 4.8+ passes only three arguments to this action. The fourth
     * parameter $import_id is therefore optional for backwards compatibility
     * with older versions that still provided it.
     */
    public static function on_saved_post($post_id, $xml, $is_update, $import_id = 0) : void {
        $sku = get_post_meta($post_id, '_sku', true);
        if (empty($sku)) {
            self::log('Saved post has no SKU; skipping.', compact('post_id','import_id','is_update'));
            return;
        }

        $existing_id = wc_get_product_id_by_sku($sku);

        if ($existing_id && (int)$existing_id !== (int)$post_id) {
            // A duplicate was created—merge then trash the newcomer.
            self::log('Duplicate detected – merging into existing and trashing new post.', [
                'sku'       => $sku,
                'existing'  => (int)$existing_id,
                'created'   => (int)$post_id,
                'import_id' => (int)$import_id
            ]);

            self::copy_location_meta($post_id, $existing_id);
            self::copy_price_meta($post_id, $existing_id);
            self::sync_stock_status_from_locations($existing_id);

            wp_trash_post($post_id);

            self::log('Merged and trashed duplicate.', [
                'sku'     => $sku,
                'kept_id' => (int)$existing_id,
                'trashed' => (int)$post_id
            ]);
            return;
        }

        // Normal path: updated or created the correct post.
        self::sync_stock_status_from_locations($post_id);

        $data = [
            'post_id'          => (int)$post_id,
            'import_id'        => (int)$import_id,
            'is_update'        => (bool)$is_update,
            'sku'              => $sku,
            'primary_stock'    => (int) get_post_meta($post_id, '_stock', true),
            'secondary_stock'  => (int) get_post_meta($post_id, '_stock2', true),
            'primary_prices'   => [
                'regular' => get_post_meta($post_id, '_regular_price', true),
                'sale'    => get_post_meta($post_id, '_sale_price', true),
            ],
            'secondary_prices' => [
                'price2'      => get_post_meta($post_id, '_price2', true),
                'sale_price2' => get_post_meta($post_id, '_sale_price2', true),
            ],
            'locations'        => [
                'primary'   => apply_filters('gn_asl_primary_location_name', 'Primary'),
                'secondary' => apply_filters('gn_asl_secondary_location_name', 'Secondary'),
            ],
        ];
        self::log($is_update ? 'Updated existing by SKU.' : 'Created new item.', $data);
    }

    /** pmxi_article_data — force WPAI to update (not create) when SKU exists. */
    public static function force_update_when_sku_exists($article_data, $import_id) {
        // Try to read SKU in different WPAI shapes.
        $sku = '';
        if (isset($article_data['meta_input']['_sku'])) {
            $sku = (string) $article_data['meta_input']['_sku'];
        } elseif (isset($article_data['post_meta']) && is_array($article_data['post_meta'])) {
            foreach ($article_data['post_meta'] as $row) {
                if (!empty($row['key']) && $row['key'] === '_sku') {
                    $sku = (string) ($row['value'] ?? '');
                    break;
                }
            }
        }

        if ($sku !== '' && function_exists('wc_get_product_id_by_sku')) {
            $existing_id = wc_get_product_id_by_sku($sku);
            if ($existing_id) {
                $article_data['ID'] = (int)$existing_id; // tell WPAI to update this post
                self::log('pmxi_article_data forced update by SKU.', [
                    'sku'       => $sku,
                    'existing'  => (int)$existing_id,
                    'import_id' => (int)$import_id
                ]);
            }
        }
        return $article_data;
    }

    /** Copy GN ASL stock/location meta. */
    private static function copy_location_meta(int $from_id, int $to_id) : void {
        foreach (['_stock','_stock2','_location2_name'] as $key) {
            $val = get_post_meta($from_id, $key, true);
            if ($val !== '') update_post_meta($to_id, $key, $val);
        }
    }

    /** Copy pricing (both locations). */
    private static function copy_price_meta(int $from_id, int $to_id) : void {
        foreach (['_regular_price','_sale_price','_price','_price2','_sale_price2'] as $key) {
            $val = get_post_meta($from_id, $key, true);
            if ($val !== '') update_post_meta($to_id, $key, $val);
        }
    }

    /**
     * Combined stock status:
     * - Manage stock = yes
     * - instock if (_stock + _stock2) > 0, else outofstock
     * - Ensure secondary location name defaults to "Golden Sneakers"
     */
    private static function sync_stock_status_from_locations(int $post_id) : void {
        $primary   = (int) get_post_meta($post_id, '_stock', true);
        $secondary = (int) get_post_meta($post_id, '_stock2', true);
        $sum       = $primary + $secondary;
        $status    = ($sum > 0) ? 'instock' : 'outofstock';

        update_post_meta($post_id, '_manage_stock', 'yes');

        if ( function_exists('wc_update_product_stock_status') ) {
            wc_update_product_stock_status($post_id, $status);
        } else {
            update_post_meta($post_id, '_stock_status', $status);
        }

        if ( ! get_post_meta($post_id, '_location2_name', true) ) {
            update_post_meta($post_id, '_location2_name', apply_filters('gn_asl_secondary_location_name', 'Golden Sneakers'));
        }

        self::log('Synced combined stock status.', [
            'post_id'   => $post_id,
            'primary'   => $primary,
            'secondary' => $secondary,
            'sum'       => $sum,
            'status'    => $status
        ]);
    }

    /** Admin top-level menu for the plugin with Import Log submenu. */
    public static function admin_menu() : void {
        $capability  = current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
        $parent_slug = 'gn-asl';

        add_menu_page(
            'GN ASL',
            'GN ASL',
            $capability,
            $parent_slug,
            [__CLASS__, 'render_main_page'],
            'dashicons-media-text',
            56
        );

        add_submenu_page(
            $parent_slug,
            'Import Log',
            'Import Log',
            $capability,
            'gn-asl-import-log',
            [__CLASS__, 'render_log_page']
        );

        remove_submenu_page($parent_slug, $parent_slug);
    }

    /** Render main plugin page. */
    public static function render_main_page() : void {
        echo '<div class="wrap"><h1>GN Additional Stock Location</h1></div>';
    }

    /** Render log page with clear + tail. */
    public static function render_log_page() : void {
        if ( isset($_POST['gnasl_clear_log']) && check_admin_referer('gnasl_clear_log') ) {
            @unlink(self::log_path());
            echo '<div class="updated"><p>Log cleared.</p></div>';
        }

        echo '<div class="wrap"><h1>ASL Import Log</h1>';
        echo '<form method="post" style="margin-bottom:12px;">';
        wp_nonce_field('gnasl_clear_log');
        submit_button('Clear Log', 'delete', 'gnasl_clear_log', false);
        echo '</form>';

        $path = esc_html(self::log_path());
        echo '<p><em>Showing the last ~800 lines. Log file: <code>' . $path . '</code></em></p>';

        echo '<textarea style="width:100%;height:60vh;font-family:monospace;" readonly>';
        echo esc_textarea( self::tail_file(800) );
        echo '</textarea></div>';
    }

    /** Tail last N lines from log. */
    private static function tail_file(int $lines = 800) : string {
        $file = self::log_path();
        if ( ! file_exists($file) ) return '';
        $f = fopen($file, 'rb');
        if (!$f) return '';
        $buffer = '';
        $pos = -1;
        $line_count = 0;
        fseek($f, 0, SEEK_END);
        $filesize = ftell($f);
        while ($line_count < $lines && -$pos < $filesize) {
            fseek($f, $pos, SEEK_END);
            $char = fgetc($f);
            $buffer = $char . $buffer;
            if ($char === "\n") $line_count++;
            $pos--;
        }
        fclose($f);
        return $buffer;
    }
}

