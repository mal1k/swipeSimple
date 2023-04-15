<?php
/*
Plugin Name: WooCommerce SwipeSimple automation
Description: Parses products from swipeSimple to WooCommerce
Version: 1.1.3
Author: Alex-K
*/

include 'endpoint.php';

add_action( 'after_setup_theme', 'woocommerce_support' );
function woocommerce_support() {
   add_theme_support( 'woocommerce' );
}   
add_theme_support( 'wc-product-gallery-zoom' );
add_theme_support( 'wc-product-gallery-lightbox' );
add_theme_support( 'wc-product-gallery-slider' );