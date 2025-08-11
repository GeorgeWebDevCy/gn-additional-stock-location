<?php
/**
 * Plugin Name: GN Additional Stock Location
 * Description: Adds a second stock location field to WooCommerce products and manages stock during checkout.
 * Version: 1.9.2
 * Author: George Nicolaou
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/GeorgeWebDevCy/gn-additional-stock-location',
    __FILE__,
    'gn-additional-stock-location'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');

define( 'GN_ASL_PRIMARY_LOCATION_NAME', apply_filters( 'gn_asl_primary_location_name', 'Sneakfreaks' ) );
define( 'GN_ASL_SECONDARY_LOCATION_NAME', apply_filters( 'gn_asl_secondary_location_name', 'Golden Sneakers' ) );

add_action( 'woocommerce_product_options_stock', 'gn_asl_additional_stock_location' );
add_action( 'woocommerce_variation_options_inventory', 'gn_asl_additional_stock_location_variation', 10, 3 );
add_action( 'woocommerce_product_options_pricing', 'gn_asl_additional_price_location' );
add_action( 'woocommerce_variation_options_pricing', 'gn_asl_additional_price_location_variation', 10, 3 );
add_action( 'woocommerce_product_options_pricing', 'gn_asl_additional_sale_price_location' );
add_action( 'woocommerce_variation_options_pricing', 'gn_asl_additional_sale_price_location_variation', 10, 3 );
add_action( 'woocommerce_product_options_stock', 'gn_asl_location_name_field' );
add_action( 'woocommerce_variation_options_inventory', 'gn_asl_location_name_field_variation', 10, 3 );
 
/**
 * Display an input for the second stock location on the product edit screen.
 *
 * The value is stored in the custom meta field `_stock2` and applies to both
 * simple and variable products.
 *
 * @return void
 */
function gn_asl_additional_stock_location() {
   global $product_object;
   echo '<div class="show_if_simple show_if_variable">';
   woocommerce_wp_text_input(
      array(
         'id'           => '_stock2',
         'value'        => get_post_meta( $product_object->get_id(), '_stock2', true ),
         'label'        => GN_ASL_SECONDARY_LOCATION_NAME . ' Stock',
         'data_type'    => 'stock',
         'wrapper_class'=> 'form-row form-row-first',
      )
   );
   echo '</div>';
}

/**
 * Add the second stock location field to each product variation.
 *
 * @param int   $loop            Current variation index.
 * @param array $variation_data  Data for the variation being edited.
 * @param WP_Post $variation     The variation post object.
 * @return void
 */
function gn_asl_additional_stock_location_variation( $loop, $variation_data, $variation ) {
   woocommerce_wp_text_input(
      array(
         'id'            => "variable_stock2{$loop}",
         'name'          => "variable_stock2[{$loop}]",
         'value'         => get_post_meta( $variation->ID, '_stock2', true ),
         'label'         => GN_ASL_SECONDARY_LOCATION_NAME . ' Stock',
         'data_type'     => 'stock',
         'wrapper_class' => 'form-row form-row-first',
      )
   );
}

/**
 * Display a price input that is used when stock from location two is sold.
 *
 * This field appears in the pricing section for simple and variable products
 * and is saved to the meta key `_price2`.
 *
 * @return void
 */
function gn_asl_additional_price_location() {
   global $product_object;
   echo '<div class="show_if_simple show_if_variable">';
   woocommerce_wp_text_input(
      array(
         'id' => '_price2',
         'value' => get_post_meta( $product_object->get_id(), '_price2', true ),
         'label' => GN_ASL_SECONDARY_LOCATION_NAME . ' Price',
         'data_type' => 'price',
         'wrapper_class' => 'form-row form-row-first',
      )
   );
   echo '</div>';
}

/**
 * Display a sale price input for the second location.
 *
 * Stored in the meta key `_sale_price2` and used when the primary
 * location is empty and a sale price is provided.
 *
 * @return void
 */
function gn_asl_additional_sale_price_location() {
   global $product_object;
   echo '<div class="show_if_simple show_if_variable">';
   woocommerce_wp_text_input(
      array(
         'id'    => '_sale_price2',
         'value' => get_post_meta( $product_object->get_id(), '_sale_price2', true ),
         'label' => GN_ASL_SECONDARY_LOCATION_NAME . ' Sale Price',
         'data_type' => 'price',
         'wrapper_class' => 'form-row form-row-last',
      )
   );
   echo '</div>';
}

/**
 * Add the second location price field to each product variation.
 *
 * @param int   $loop            Current variation index.
 * @param array $variation_data  Data for the variation being edited.
 * @param WP_Post $variation     The variation post object.
 * @return void
 */
function gn_asl_additional_price_location_variation( $loop, $variation_data, $variation ) {
   woocommerce_wp_text_input(
      array(
         'id'            => "variable_price2{$loop}",
         'name'          => "variable_price2[{$loop}]",
         'value'         => get_post_meta( $variation->ID, '_price2', true ),
         'label'         => GN_ASL_SECONDARY_LOCATION_NAME . ' Price',
         'data_type'     => 'price',
         'wrapper_class' => 'form-row form-row-first',
      )
   );
}

/**
 * Add the second location sale price field to each product variation.
 *
 * @param int   $loop            Current variation index.
 * @param array $variation_data  Data for the variation being edited.
 * @param WP_Post $variation     The variation post object.
 * @return void
 */
function gn_asl_additional_sale_price_location_variation( $loop, $variation_data, $variation ) {
   woocommerce_wp_text_input(
      array(
         'id'            => "variable_sale_price2{$loop}",
         'name'          => "variable_sale_price2[{$loop}]",
         'value'         => get_post_meta( $variation->ID, '_sale_price2', true ),
         'label'         => GN_ASL_SECONDARY_LOCATION_NAME . ' Sale Price',
         'data_type'     => 'price',
         'wrapper_class' => 'form-row form-row-last',
      )
   );
}
 
add_action( 'save_post_product', 'gn_asl_save_additional_stock' );
add_action( 'woocommerce_save_product_variation', 'gn_asl_save_additional_stock_variation', 10, 2 );
add_action( 'save_post_product', 'gn_asl_save_additional_price' );
add_action( 'woocommerce_save_product_variation', 'gn_asl_save_additional_price_variation', 10, 2 );
add_action( 'save_post_product', 'gn_asl_save_additional_sale_price' );
add_action( 'woocommerce_save_product_variation', 'gn_asl_save_additional_sale_price_variation', 10, 2 );
add_action( 'save_post_product', 'gn_asl_save_location_name' );
add_action( 'woocommerce_save_product_variation', 'gn_asl_save_location_name_variation', 10, 2 );
   
/**
 * Save the value of the second stock location for simple products.
 *
 * @param int $product_id ID of the product being saved.
 * @return void
 */
function gn_asl_save_additional_stock( $product_id ) {
    global $typenow;
    if ( 'product' === $typenow ) {
      if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
      if ( isset( $_POST['_stock2'] ) ) {
         update_post_meta( $product_id, '_stock2', $_POST['_stock2'] );
      }
   }
}

/**
 * Save second location stock for a product variation.
 *
 * @param int $variation_id ID of the variation being saved.
 * @param int $i            Index of the variation in the form.
 * @return void
 */
function gn_asl_save_additional_stock_variation( $variation_id, $i ) {
   if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
   if ( isset( $_POST['variable_stock2'][ $i ] ) ) {
      update_post_meta( $variation_id, '_stock2', wc_stock_amount( wp_unslash( $_POST['variable_stock2'][ $i ] ) ) );
   }
}

/**
 * Save the second location price for simple products.
 *
 * @param int $product_id ID of the product being saved.
 * @return void
 */
function gn_asl_save_additional_price( $product_id ) {
    global $typenow;
    if ( 'product' === $typenow ) {
      if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
      if ( isset( $_POST['_price2'] ) ) {
         update_post_meta( $product_id, '_price2', wc_clean( wp_unslash( $_POST['_price2'] ) ) );
      }
   }
}

/**
 * Save second location price for a product variation.
 *
 * @param int $variation_id ID of the variation being saved.
 * @param int $i            Index of the variation in the form.
 * @return void
 */
function gn_asl_save_additional_price_variation( $variation_id, $i ) {
   if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
   if ( isset( $_POST['variable_price2'][ $i ] ) ) {
      update_post_meta( $variation_id, '_price2', wc_clean( wp_unslash( $_POST['variable_price2'][ $i ] ) ) );
   }
}

/**
 * Save the second location sale price for simple products.
 *
 * @param int $product_id ID of the product being saved.
 * @return void
 */
function gn_asl_save_additional_sale_price( $product_id ) {
    global $typenow;
    if ( 'product' === $typenow ) {
      if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
      if ( isset( $_POST['_sale_price2'] ) ) {
         update_post_meta( $product_id, '_sale_price2', wc_clean( wp_unslash( $_POST['_sale_price2'] ) ) );
      }
   }
}

/**
 * Save second location sale price for a product variation.
 *
 * @param int $variation_id ID of the variation being saved.
 * @param int $i            Index of the variation in the form.
 * @return void
 */
function gn_asl_save_additional_sale_price_variation( $variation_id, $i ) {
   if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
   if ( isset( $_POST['variable_sale_price2'][ $i ] ) ) {
      update_post_meta( $variation_id, '_sale_price2', wc_clean( wp_unslash( $_POST['variable_sale_price2'][ $i ] ) ) );
   }
}

/**
 * Display read-only field with the second location name on the product edit screen.
 */
function gn_asl_location_name_field() {
   global $product_object;
   echo '<div class="show_if_simple show_if_variable">';
   woocommerce_wp_select(
      array(
         'id'           => '_location2_name',
         'value'        => get_post_meta( $product_object->get_id(), '_location2_name', true ) ?: GN_ASL_PRIMARY_LOCATION_NAME,
         'label'        => 'Location Name',
         'options'      => array(
            GN_ASL_PRIMARY_LOCATION_NAME   => GN_ASL_PRIMARY_LOCATION_NAME,
            GN_ASL_SECONDARY_LOCATION_NAME => GN_ASL_SECONDARY_LOCATION_NAME,
         ),
         'wrapper_class'=> 'form-row form-row-last',
      )
   );
   echo '</div>';
}

/**
 * Display read-only field with the second location name for variations.
 *
 * @param int   $loop   Current variation index.
 * @param array $data   Data for the variation being edited.
 * @param WP_Post $variation Variation post object.
 */
function gn_asl_location_name_field_variation( $loop, $data, $variation ) {
   woocommerce_wp_select(
      array(
         'id'            => "variable_location2_name{$loop}",
         'name'          => "variable_location2_name[{$loop}]",
         'value'         => get_post_meta( $variation->ID, '_location2_name', true ) ?: GN_ASL_PRIMARY_LOCATION_NAME,
         'label'         => 'Location Name',
         'options'       => array(
            GN_ASL_PRIMARY_LOCATION_NAME   => GN_ASL_PRIMARY_LOCATION_NAME,
            GN_ASL_SECONDARY_LOCATION_NAME => GN_ASL_SECONDARY_LOCATION_NAME,
         ),
         'wrapper_class' => 'form-row form-row-last',
      )
   );
}

/**
 * Save the location name for simple products.
 */
function gn_asl_save_location_name( $product_id ) {
    global $typenow;
    if ( 'product' === $typenow ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( isset( $_POST['_location2_name'] ) ) {
            update_post_meta( $product_id, '_location2_name', sanitize_text_field( wp_unslash( $_POST['_location2_name'] ) ) );
        }
    }
}

/**
 * Save the location name for product variations.
 */
function gn_asl_save_location_name_variation( $variation_id, $i ) {
   if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
   if ( isset( $_POST['variable_location2_name'][ $i ] ) ) {
      update_post_meta( $variation_id, '_location2_name', sanitize_text_field( wp_unslash( $_POST['variable_location2_name'][ $i ] ) ) );
   }
}

add_action( 'woocommerce_product_meta_end', 'gn_asl_output_location_name_on_product' );

/**
 * Display location name on the single product page.
 */
function gn_asl_output_location_name_on_product() {
   global $product;
   $name = get_post_meta( $product->get_id(), '_location2_name', true ) ?: GN_ASL_PRIMARY_LOCATION_NAME;
   echo '<p class="gn-location-name">' . esc_html__( 'Location:', 'gn-additional-stock-location' ) . ' ' . esc_html( $name ) . '</p>';
}
 
/**
 * Get total stock across primary and secondary locations.
 *
 * @param int $product_id Product ID.
 * @return int Total stock.
 */
function gn_asl_get_total_stock( $product_id ) {
   $primary   = (int) get_post_meta( $product_id, '_stock', true );
   $secondary = (int) get_post_meta( $product_id, '_stock2', true );
   return $primary + $secondary;
}

add_filter( 'woocommerce_product_get_stock_quantity', 'gn_asl_get_overall_stock_quantity', 9999, 2 );

/**
 * Combine stock from both locations when displaying stock quantity.
 *
 * @param int       $value   Original stock quantity from WooCommerce.
 * @param WC_Product $product Product being queried.
 * @return int Total stock across both locations.
 */
function gn_asl_get_overall_stock_quantity( $value, $product ) {
   return gn_asl_get_total_stock( $product->get_id() );
}

add_filter( 'woocommerce_product_get_stock_status', 'gn_asl_get_overall_stock_status', 9999, 2 );

/**
 * Determine stock status based on the combined stock quantity.
 *
 * @param string     $status  Original stock status.
 * @param WC_Product $product Product being checked.
 * @return string "instock" or "outofstock" based on total stock.
 */
function gn_asl_get_overall_stock_status( $status, $product ) {
   if ( ! $product->managing_stock() ) return $status;
   $stock = gn_asl_get_total_stock( $product->get_id() );
   return $stock > 0 ? 'instock' : 'outofstock';
}

add_filter( 'woocommerce_product_get_price', 'gn_asl_maybe_use_second_price', 10, 2 );
add_filter( 'woocommerce_product_get_regular_price', 'gn_asl_maybe_use_second_price', 10, 2 );
add_filter( 'woocommerce_product_get_sale_price', 'gn_asl_maybe_use_second_price', 10, 2 );
add_filter( 'woocommerce_product_variation_get_price', 'gn_asl_maybe_use_second_price', 10, 2 );
add_filter( 'woocommerce_product_variation_get_regular_price', 'gn_asl_maybe_use_second_price', 10, 2 );
add_filter( 'woocommerce_product_variation_get_sale_price', 'gn_asl_maybe_use_second_price', 10, 2 );
add_filter( 'woocommerce_product_is_on_sale', 'gn_asl_second_price_is_on_sale', 10, 2 );
add_filter( 'woocommerce_product_variation_is_on_sale', 'gn_asl_second_price_is_on_sale', 10, 2 );

/**
 * Use the price from location two when the primary location is out of stock.
 *
 * @param string     $price   Current price determined by WooCommerce.
 * @param WC_Product $product Product being priced.
 * @return string New price if location one is empty, otherwise the original.
 */
function gn_asl_maybe_use_second_price( $price, $product ) {
   $primary_stock = (int) get_post_meta( $product->get_id(), '_stock', true );
   if ( $primary_stock <= 0 ) {
      $sale_price2  = get_post_meta( $product->get_id(), '_sale_price2', true );
      $regular2     = get_post_meta( $product->get_id(), '_price2', true );
      $filter       = current_filter();

      if ( in_array( $filter, array( 'woocommerce_product_get_sale_price', 'woocommerce_product_variation_get_sale_price' ), true ) ) {
         return '' !== $sale_price2 ? $sale_price2 : '';
      }

      if ( in_array( $filter, array( 'woocommerce_product_get_regular_price', 'woocommerce_product_variation_get_regular_price' ), true ) ) {
         return '' !== $regular2 ? $regular2 : $price;
      }

      if ( '' !== $sale_price2 ) {
         return $sale_price2;
      }
      if ( '' !== $regular2 ) {
         return $regular2;
      }
   }
   return $price;
}

/**
 * Determine sale status when using the second location price.
 *
 * @param bool       $on_sale Current on sale state.
 * @param WC_Product $product Product being checked.
 * @return bool True if the second location has a sale price when active.
 */
function gn_asl_second_price_is_on_sale( $on_sale, $product ) {
   $primary_stock = (int) get_post_meta( $product->get_id(), '_stock', true );
   if ( $primary_stock <= 0 ) {
      $sale_price2 = get_post_meta( $product->get_id(), '_sale_price2', true );
      return '' !== $sale_price2;
   }
   return $on_sale;
}
 
add_filter( 'woocommerce_payment_complete_reduce_order_stock', 'gn_asl_maybe_reduce_second_stock', 9999, 2 );

/**
 * Reduce stock levels in both locations when an order is completed.
 *
 * The default WooCommerce stock reduction only handles the primary location.
 * This function ensures that any remaining quantity is deducted from the
 * secondary stock location.
 *
 * @param bool $reduce   Whether WooCommerce should reduce stock automatically.
 * @param int  $order_id ID of the processed order.
 * @return bool False to prevent default reduction when custom logic runs.
 */
function gn_asl_maybe_reduce_second_stock( $reduce, $order_id ) {
   $order = wc_get_order( $order_id );
   $atleastastock2change = false;
   foreach ( $order->get_items() as $item ) {
      if ( ! $item->is_type( 'line_item' ) ) {
         continue;
      }
      $product = $item->get_product();
      $item_stock_reduced = $item->get_meta( '_reduced_stock', true );
      if ( $item_stock_reduced || ! $product || ! $product->managing_stock() ) {
         continue;
      }
      $qty = apply_filters( 'woocommerce_order_item_quantity', $item->get_quantity(), $order, $item );
      $stock1 = (int) get_post_meta( $product->get_id(), '_stock', true );
      if ( $qty <= $stock1 ) continue;
      $atleastastock2change = true;
   }
   if ( ! $atleastastock2change ) return $reduce;
   foreach ( $order->get_items() as $item ) {
      if ( ! $item->is_type( 'line_item' ) ) {
         continue;
      }
      $product = $item->get_product();
      $item_stock_reduced = $item->get_meta( '_reduced_stock', true );
      if ( $item_stock_reduced || ! $product || ! $product->managing_stock() ) {
         continue;
      }
      $item_name = $product->get_formatted_name();
      $qty = apply_filters( 'woocommerce_order_item_quantity', $item->get_quantity(), $order, $item );
      $stock1 = (int) get_post_meta( $product->get_id(), '_stock', true );
      $stock2 = (int) get_post_meta( $product->get_id(), '_stock2', true );
      if ( $qty <= $stock1 ) {
         wc_update_product_stock( $product, $qty, 'decrease' );
         $order->add_order_note( sprintf( 'Reduced stock for item "%s"; Stock 1: "%s" to "%s".', $item_name, $stock1, $stock1 - $qty ) );
      } else {
         $newstock2 = $stock2 - ( $qty - $stock1 );
         wc_update_product_stock( $product, $stock1, 'decrease' );
         update_post_meta( $product->get_id(), '_stock2', $newstock2 );
         $item->add_meta_data( '_reduced_stock', $qty, true );
         $item->save();
         $order->add_order_note( sprintf( 'Reduced stock for item "%s"; Stock 1: "%s" to "0" and Stock 2: "%s" to "%s".', $item_name, $stock1, $stock2, $newstock2 ) );
      }
   }
   $order->get_data_store()->set_stock_reduced( $order_id, true );
   return false;
}

/**
 * Load the WP All Import sync + logger module (only if WooCommerce is active).
 */
if ( class_exists( 'WooCommerce' ) ) {
   $module_file = __DIR__ . '/includes/class-gn-asl-import-sync.php';
   if ( file_exists( $module_file ) ) {
      require_once $module_file;

      // Boot the module so it registers hooks & the admin page.
      add_action( 'plugins_loaded', function () {
         if ( class_exists( '\GN_ASL\ImportSync\Module' ) ) {
            \GN_ASL\ImportSync\Module::boot();
         }
      }, 20 );
   }
}
