<?php 
if ( !defined( 'ABSPATH' ) ) exit;

function custom_localisation_address_formats($formats) {
    $formats['KR'] = "{name}\n{postcode}\n{city}\n{address_1} {address_2}";
    return $formats;
}
add_filter('woocommerce_localisation_address_formats', 'custom_localisation_address_formats');