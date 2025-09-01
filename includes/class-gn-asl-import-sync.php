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
    const IMPORT_ID = 3; // Only process this WP All Import ID

    /**
     * Cache of the latest fields received from WP All Import keyed by SKU.
     *
     * @var array<string,array<string,mixed>>
     */
    private static $import_cache = [];

    /**
     * Tracks the last SKU/post ID logged so we can separate blocks.
     *
     * @var string|int|null
     */
    private static $last_block = null;

    public static function boot() {
        // Only run if WooCommerce is present.
        if ( ! function_exists('wc_get_product_id_by_sku') ) return;

        // Soft dependency on WP All Import: hooks won’t fire if WPAI isn’t active.
        // Use a later priority so WooCommerce and other add-ons finish updating
        // meta before we read it (fixes empty values in our log/stock sync).
        add_action('pmxi_saved_post', [__CLASS__, 'on_saved_post'], 9999, 4);
        add_action('pmxi_after_post_import', [__CLASS__, 'after_post_import'], 10, 1);
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

    /** Write to log with human-readable blocks per product. */
    private static function log(string $msg, array $ctx = []) : void {
        self::rotate_if_needed();

        // Determine block key based on SKU or post ID.
        $key  = $ctx['sku'] ?? ($ctx['post_id'] ?? null);
        $line = '';

        if ($key !== null && $key !== self::$last_block) {
            $line .= PHP_EOL . '=== ' . $key . ' ===' . PHP_EOL;
            self::$last_block = $key;
        }

        $line .= '[' . gmdate('Y-m-d H:i:s') . 'Z] ' . $msg . PHP_EOL;

        if ($ctx) {
            $json = wp_json_encode($ctx, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            foreach (explode("\n", $json) as $j) {
                $line .= '  ' . $j . PHP_EOL;
            }
        }

        $line .= PHP_EOL;
        @file_put_contents(self::log_path(), $line, FILE_APPEND);
    }

    /**
     * Get prices, stock quantity and stock status via WooCommerce getters with
     * a post meta fallback. Using the CRUD layer ensures we see the values that
     * WC/WPAI have just written even if the meta cache hasn't been updated yet.
     */
    private static function get_wc_prices_and_stock(int $post_id) : array {
        $p = function_exists('wc_get_product') ? wc_get_product($post_id) : null;
        return [
            'regular_price' => $p ? $p->get_regular_price()  : get_post_meta($post_id, '_regular_price', true),
            'sale_price'    => $p ? $p->get_sale_price()     : get_post_meta($post_id, '_sale_price', true),
            'stock'         => $p ? $p->get_stock_quantity() : get_post_meta($post_id, '_stock', true),
            'status'        => $p ? $p->get_stock_status()   : get_post_meta($post_id, '_stock_status', true),
        ];
    }

    /** Log a snapshot of key meta values for a post. */
    private static function log_meta_snapshot(int $post_id, string $msg, array $extra = []) : void {
        $wc = self::get_wc_prices_and_stock($post_id);
        $data = [
            'post_id' => (int) $post_id,
            'sku'     => get_post_meta($post_id, '_sku', true),
            'values'  => [
                '_stock'         => $wc['stock'],
                '_stock2'        => get_post_meta($post_id, '_stock2', true),
                '_regular_price' => $wc['regular_price'],
                '_sale_price'    => $wc['sale_price'],
                '_price2'        => get_post_meta($post_id, '_price2', true),
                '_sale_price2'   => get_post_meta($post_id, '_sale_price2', true),
            ],
        ] + $extra;
        self::log($msg, $data);
    }

    /**
     * Compare saved meta values with the data received from WP All Import and
     * log any mismatches so we can tell if the import was applied correctly.
     */
    private static function verify_import_values(int $post_id) : void {
        $sku = get_post_meta($post_id, '_sku', true);
        if ($sku === '' || !isset(self::$import_cache[$sku])) return;

        $expected = self::$import_cache[$sku];
        $wc       = self::get_wc_prices_and_stock($post_id);
        $actual   = [
            '_stock'         => (string) ($wc['stock'] ?? ''),
            '_stock2'        => (string) get_post_meta($post_id, '_stock2', true),
            '_regular_price' => (string) ($wc['regular_price'] ?? ''),
            '_sale_price'    => (string) ($wc['sale_price'] ?? ''),
            '_price2'        => (string) get_post_meta($post_id, '_price2', true),
            '_sale_price2'   => (string) get_post_meta($post_id, '_sale_price2', true),
        ];

        foreach ($expected as $key => $exp) {
            $act = $actual[$key] ?? '';
            if ((string) $exp !== (string) $act) {
                self::log('Imported field mismatch.', [
                    'post_id' => (int) $post_id,
                    'sku'     => $sku,
                    'field'   => $key,
                    'feed'    => $exp,
                    'saved'   => $act,
                ]);
            }
        }

        unset(self::$import_cache[$sku]);
    }

    /** Update post meta only when the imported value is a non-empty scalar. */
    private static function safe_update_meta(int $to, string $key, $value) : bool {
        if ($value === '' || $value === null || (is_array($value) && $value === [])) {
            self::log('Meta copy skipped (empty).', ['to' => $to, 'key' => $key, 'value' => $value]);
            return false;
        }
        update_post_meta($to, $key, $value);
        self::log('Meta copy.', ['to' => $to, 'key' => $key, 'value' => $value, 'updated' => true]);
        return true;
    }

    /** pmxi_after_post_import — placeholder for any after-import logic. */
    public static function after_post_import($import_id) : void {
        if ((int) $import_id !== self::IMPORT_ID) return;
        // Hook available for a second pass after imports if needed.
    }

    /**
     * pmxi_saved_post — after WPAI creates/updates a record.
     *
     * WP All Import 4.8+ passes only three arguments to this action. The fourth
     * parameter $import_id is therefore optional for backwards compatibility
     * with older versions that still provided it.
     */
    public static function on_saved_post($post_id, $xml, $is_update, $import_id = 0) : void {
        if ((int) $import_id !== self::IMPORT_ID) return;
        $sku = get_post_meta($post_id, '_sku', true);
        if (empty($sku)) {
            self::log('Saved post has no SKU; skipping.', compact('post_id','import_id','is_update'));
            return;
        }

        // Snapshot of values right after import.
        self::log_meta_snapshot($post_id, 'Import values received.', [
            'import_id' => (int) $import_id,
            'is_update' => (bool) $is_update,
        ]);

        $existing_id = wc_get_product_id_by_sku($sku);

        if ($existing_id && (int)$existing_id !== (int)$post_id) {
            // A duplicate was created—merge then trash the newcomer.
            $existing_type   = get_post_type($existing_id);
            $existing_parent = ($existing_type === 'product_variation') ? (int) wp_get_post_parent_id($existing_id) : 0;
            $existing_title  = get_the_title($existing_id);
            $created_title   = get_the_title($post_id);

            $msg = sprintf(
                'Duplicate detected – found same SKU "%s" on existing %s "%s" (ID %d) when saving "%s" (ID %d); merging into existing and trashing new post.',
                $sku,
                ($existing_type === 'product_variation') ? 'variation' : 'product',
                $existing_title,
                $existing_id,
                $created_title,
                $post_id
            );

            $ctx = [
                'sku'             => $sku,
                'existing'        => (int) $existing_id,
                'existing_title'  => $existing_title,
                'created'         => (int) $post_id,
                'created_title'   => $created_title,
                'import_id'       => (int) $import_id,
                'match_type'      => $existing_type,
            ];
            if ($existing_parent) {
                $ctx['existing_parent']       = $existing_parent;
                $ctx['existing_parent_title'] = get_the_title($existing_parent);
            }

            self::log($msg, $ctx);

            foreach (['_stock','_stock2','_regular_price','_sale_price','_price2','_sale_price2','_location2_name'] as $key) {
                $val = get_post_meta($post_id, $key, true);
                self::safe_update_meta($existing_id, $key, $val);
            }
            self::sync_stock_status_from_locations($existing_id);

            self::log_meta_snapshot($existing_id, 'Existing product after merge.', ['import_id' => (int) $import_id]);
            self::verify_import_values($existing_id);

            wp_trash_post($post_id);

            $ctx['kept_id']   = (int) $existing_id;
            $ctx['trashed']   = (int) $post_id;
            self::log('Merged and trashed duplicate.', $ctx);
            return;
        }

        // Normal path: updated or created the correct post.
        self::sync_stock_status_from_locations($post_id);
        self::log_meta_snapshot(
            $post_id,
            $is_update ? 'Updated existing product.' : 'Created new product.',
            [
                'import_id' => (int) $import_id,
                'is_update' => (bool) $is_update,
            ]
        );
        self::verify_import_values($post_id);
    }

    /** pmxi_article_data — force WPAI to update (not create) when SKU exists. */
    public static function force_update_when_sku_exists($article_data, $import_id) {
        if ((int) $import_id !== self::IMPORT_ID) return $article_data;
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

        if ($sku !== '') {
            $fields = [];
            foreach (['_stock','_stock2','_regular_price','_sale_price','_price2','_sale_price2'] as $key) {
                if (isset($article_data['meta_input'][$key])) {
                    $fields[$key] = $article_data['meta_input'][$key];
                } elseif (isset($article_data['post_meta']) && is_array($article_data['post_meta'])) {
                    foreach ($article_data['post_meta'] as $row) {
                        if (!empty($row['key']) && $row['key'] === $key) {
                            $fields[$key] = $row['value'] ?? '';
                            break;
                        }
                    }
                }
            }
            self::log('Import data received.', [
                'sku'       => $sku,
                'import_id' => (int) $import_id,
                'fields'    => $fields,
            ]);

            self::$import_cache[$sku] = $fields;
        }

        if ($sku !== '' && function_exists('wc_get_product_id_by_sku')) {
            $existing_id = wc_get_product_id_by_sku($sku);
            if ($existing_id) {
                $existing_type   = get_post_type($existing_id);
                $existing_parent = ($existing_type === 'product_variation') ? (int) wp_get_post_parent_id($existing_id) : 0;
                $existing_title  = get_the_title($existing_id);

                $article_data['ID'] = (int) $existing_id; // tell WPAI to update this post

                $msg = sprintf(
                    'pmxi_article_data forced update by SKU – found existing %s "%s" (ID %d) with SKU "%s".',
                    ($existing_type === 'product_variation') ? 'variation' : 'product',
                    $existing_title,
                    $existing_id,
                    $sku
                );
                $ctx = [
                    'sku'            => $sku,
                    'existing'       => (int) $existing_id,
                    'existing_title' => $existing_title,
                    'import_id'      => (int) $import_id,
                    'match_type'     => $existing_type,
                ];
                if ($existing_parent) {
                    $ctx['existing_parent']       = $existing_parent;
                    $ctx['existing_parent_title'] = get_the_title($existing_parent);
                }

                self::log($msg, $ctx);
            }
        }
        return $article_data;
    }

    /**
     * Combined stock status:
     * - Manage stock = yes
     * - instock if (_stock + _stock2) > 0, else outofstock
     * - Ensure secondary location name defaults to "Golden Sneakers"
     */
    private static function sync_stock_status_from_locations(int $post_id) : void {
        $primary   = (int) (self::get_wc_prices_and_stock($post_id)['stock'] ?? 0);
        $secondary = (int) get_post_meta($post_id, '_stock2', true);
        $sum       = $primary + $secondary;
        $status    = ($sum > 0) ? 'instock' : 'outofstock';
        $sku       = get_post_meta($post_id, '_sku', true);

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
            'post_id'   => (int) $post_id,
            'sku'       => $sku,
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

