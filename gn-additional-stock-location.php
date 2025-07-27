<?php
/**
 * Plugin Name: GN Additional Stock Location
 * Description: Adds a second stock location field to WooCommerce products and manages stock during checkout.
 * Version: 1.0.0
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

add_action( 'woocommerce_product_options_stock', 'gn_asl_additional_stock_location' );
 
function gn_asl_additional_stock_location() {
   global $product_object;
   echo '<div class="show_if_simple show_if_variable">';
   woocommerce_wp_text_input(
      array(
         'id' => '_stock2',
         'value' => get_post_meta( $product_object->get_id(), '_stock2', true ),
         'label' => '2nd Stock Location',
         'data_type' => 'stock',
      )
   );
   echo '</div>';
}
 
add_action( 'save_post_product', 'gn_asl_save_additional_stock' );
   
function gn_asl_save_additional_stock( $product_id ) {
    global $typenow;
    if ( 'product' === $typenow ) {
      if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
      if ( isset( $_POST['_stock2'] ) ) {
         update_post_meta( $product_id, '_stock2', $_POST['_stock2'] );
      }
   }
}
 
add_filter( 'woocommerce_product_get_stock_quantity' , 'gn_asl_get_overall_stock_quantity', 9999, 2 );
 
function gn_asl_get_overall_stock_quantity( $value, $product ) {
   $value = (int) $value + (int) get_post_meta( $product->get_id(), '_stock2', true );
    return $value;
}
 
add_filter( 'woocommerce_product_get_stock_status' , 'gn_asl_get_overall_stock_status', 9999, 2 );
 
function gn_asl_get_overall_stock_status( $status, $product ) {
   if ( ! $product->managing_stock() ) return $status;
   $stock = (int) $product->get_stock_quantity() + (int) get_post_meta( $product->get_id(), '_stock2', true );
   $status = $stock && ( $stock > 0 ) ? 'instock' : 'outofstock';
    return $status;
}
 
add_filter( 'woocommerce_payment_complete_reduce_order_stock', 'gn_asl_maybe_reduce_second_stock', 9999, 2 );
 
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
require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$gn_asl_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/example/gn-additional-stock-location/',
    __FILE__,
    'gn-additional-stock-location'
);
$gn_asl_update_checker->setBranch('main');
