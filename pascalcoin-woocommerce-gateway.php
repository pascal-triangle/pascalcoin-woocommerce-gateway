<?php
/*
Plugin Name: PascalCoin Woocommerce Gateway
Plugin URI: https://pascalcoin.org/
Description: Extends WooCommerce by adding a PascalCoin Gateway
Version: 1.0.0
Tested up to: 4.9.8
Author: mosu-forge
Author URI: https://pascalcoin.org/
*/
// This code isn't for Dark Net Markets, please report them to Authority!

defined( 'ABSPATH' ) || exit;

// Constants, you can edit these if you fork this repo
define('PASCALCOIN_GATEWAY_EXPLORER_URL', 'http://explorer.pascalcoin.org');
define('PASCALCOIN_GATEWAY_ATOMIC_UNITS', 4);
define('PASCALCOIN_GATEWAY_ATOMIC_UNIT_THRESHOLD', 10); // Amount payment can be under in atomic units and still be valid
define('PASCALCOIN_GATEWAY_DIFFICULTY_TARGET', 300);

// Do not edit these constants
define('PASCALCOIN_GATEWAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PASCALCOIN_GATEWAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PASCALCOIN_GATEWAY_ATOMIC_UNITS_POW', pow(10, PASCALCOIN_GATEWAY_ATOMIC_UNITS));
define('PASCALCOIN_GATEWAY_ATOMIC_UNITS_SPRINTF', '%.'.PASCALCOIN_GATEWAY_ATOMIC_UNITS.'f');

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action('plugins_loaded', 'pascalcoin_init', 1);
function pascalcoin_init() {

    // If the class doesn't exist (== WooCommerce isn't installed), return NULL
    if (!class_exists('WC_Payment_Gateway')) return;

    // If we made it this far, then include our Gateway Class
    require_once('include/class-pascalcoin-gateway.php');

    // Create a new instance of the gateway so we have static variables set up
    new Pascalcoin_Gateway($add_action=false);

    // Include our Admin interface class
    require_once('include/admin/class-pascalcoin-admin-interface.php');

    add_filter('woocommerce_payment_gateways', 'pascalcoin_gateway');
    function pascalcoin_gateway($methods) {
        $methods[] = 'Pascalcoin_Gateway';
        return $methods;
    }

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pascalcoin_payment');
    function pascalcoin_payment($links) {
        $plugin_links = array(
            '<a href="'.admin_url('admin.php?page=pascalcoin_gateway_settings').'">'.__('Settings', 'pascalcoin_gateway').'</a>'
        );
        return array_merge($plugin_links, $links);
    }

    add_filter('cron_schedules', 'pascalcoin_cron_add_one_minute');
    function pascalcoin_cron_add_one_minute($schedules) {
        $schedules['one_minute'] = array(
            'interval' => 60,
            'display' => __('Once every minute', 'pascalcoin_gateway')
        );
        return $schedules;
    }

    add_action('wp', 'pascalcoin_activate_cron');
    function pascalcoin_activate_cron() {
        if(!wp_next_scheduled('pascalcoin_update_event')) {
            wp_schedule_event(time(), 'one_minute', 'pascalcoin_update_event');
        }
    }

    add_action('pascalcoin_update_event', 'pascalcoin_update_event');
    function pascalcoin_update_event() {
        Pascalcoin_Gateway::do_update_event();
    }

    add_action('woocommerce_thankyou_'.Pascalcoin_Gateway::get_id(), 'pascalcoin_order_confirm_page');
    add_action('woocommerce_order_details_after_order_table', 'pascalcoin_order_page');
    add_action('woocommerce_email_after_order_table', 'pascalcoin_order_email');

    function pascalcoin_order_confirm_page($order_id) {
        Pascalcoin_Gateway::customer_order_page($order_id);
    }
    function pascalcoin_order_page($order) {
        if(!is_wc_endpoint_url('order-received'))
            Pascalcoin_Gateway::customer_order_page($order);
    }
    function pascalcoin_order_email($order) {
        Pascalcoin_Gateway::customer_order_email($order);
    }

    add_action('wc_ajax_pascalcoin_gateway_payment_details', 'pascalcoin_get_payment_details_ajax');
    function pascalcoin_get_payment_details_ajax() {
        Pascalcoin_Gateway::get_payment_details_ajax();
    }

    add_filter('woocommerce_currencies', 'pascalcoin_add_currency');
    function pascalcoin_add_currency($currencies) {
        $currencies['Pascalcoin'] = __('Pascalcoin', 'pascalcoin_gateway');
        return $currencies;
    }

    add_filter('woocommerce_currency_symbol', 'pascalcoin_add_currency_symbol', 10, 2);
    function pascalcoin_add_currency_symbol($currency_symbol, $currency) {
        switch ($currency) {
        case 'Pascalcoin':
            $currency_symbol = 'Pascalcoin';
            break;
        }
        return $currency_symbol;
    }

    if(Pascalcoin_Gateway::use_pascalcoin_price()) {

        // This filter will replace all prices with amount in Pascalcoin (live rates)
        add_filter('wc_price', 'pascalcoin_live_price_format', 10, 3);
        function pascalcoin_live_price_format($price_html, $price_float, $args) {
            if(!isset($args['currency']) || !$args['currency']) {
                global $woocommerce;
                $currency = strtoupper(get_woocommerce_currency());
            } else {
                $currency = strtoupper($args['currency']);
            }
            return Pascalcoin_Gateway::convert_wc_price($price_float, $currency);
        }

        // These filters will replace the live rate with the exchange rate locked in for the order
        // We must be careful to hit all the hooks for price displays associated with an order,
        // else the exchange rate can change dynamically (which it should not for an order)
        add_filter('woocommerce_order_formatted_line_subtotal', 'pascalcoin_order_item_price_format', 10, 3);
        function pascalcoin_order_item_price_format($price_html, $item, $order) {
            return Pascalcoin_Gateway::convert_wc_price_order($price_html, $order);
        }

        add_filter('woocommerce_get_formatted_order_total', 'pascalcoin_order_total_price_format', 10, 2);
        function pascalcoin_order_total_price_format($price_html, $order) {
            return Pascalcoin_Gateway::convert_wc_price_order($price_html, $order);
        }

        add_filter('woocommerce_get_order_item_totals', 'pascalcoin_order_totals_price_format', 10, 3);
        function pascalcoin_order_totals_price_format($total_rows, $order, $tax_display) {
            foreach($total_rows as &$row) {
                $price_html = $row['value'];
                $row['value'] = Pascalcoin_Gateway::convert_wc_price_order($price_html, $order);
            }
            return $total_rows;
        }

    }

    add_action('wp_enqueue_scripts', 'pascalcoin_enqueue_scripts');
    function pascalcoin_enqueue_scripts() {
        if(Pascalcoin_Gateway::use_pascalcoin_price())
            wp_dequeue_script('wc-cart-fragments');
        if(Pascalcoin_Gateway::use_qr_code())
            wp_enqueue_script('pascalcoin-qr-code', PASCALCOIN_GATEWAY_PLUGIN_URL.'assets/js/qrcode.min.js');

        wp_enqueue_script('pascalcoin-clipboard-js', PASCALCOIN_GATEWAY_PLUGIN_URL.'assets/js/clipboard.min.js');
        wp_enqueue_script('pascalcoin-gateway', PASCALCOIN_GATEWAY_PLUGIN_URL.'assets/js/pascalcoin-gateway-order-page.js');
        wp_enqueue_style('pascalcoin-gateway', PASCALCOIN_GATEWAY_PLUGIN_URL.'assets/css/pascalcoin-gateway-order-page.css');
    }

    // [pascalcoin-price currency="USD"]
    // currency: BTC, GBP, etc
    // if no none, then default store currency
    function pascalcoin_price_func( $atts ) {
        global  $woocommerce;
        $a = shortcode_atts( array(
            'currency' => get_woocommerce_currency()
        ), $atts );

        $currency = strtoupper($a['currency']);
        $rate = Pascalcoin_Gateway::get_live_rate($currency);
        if($currency == 'BTC')
            $rate_formatted = sprintf('%.8f', $rate / 1e8);
        else
            $rate_formatted = sprintf('%.5f', $rate / 1e8);

        return "<span class=\"pascalcoin-price\">1 PASC = $rate_formatted $currency</span>";
    }
    add_shortcode('pascalcoin-price', 'pascalcoin_price_func');


    // [pascalcoin-accepted-here]
    function pascalcoin_accepted_func() {
        return '<img src="'.PASCALCOIN_GATEWAY_PLUGIN_URL.'assets/images/pascalcoin-accepted-here.png" />';
    }
    add_shortcode('pascalcoin-accepted-here', 'pascalcoin_accepted_func');

}

register_deactivation_hook(__FILE__, 'pascalcoin_deactivate');
function pascalcoin_deactivate() {
    $timestamp = wp_next_scheduled('pascalcoin_update_event');
    wp_unschedule_event($timestamp, 'pascalcoin_update_event');
}

register_activation_hook(__FILE__, 'pascalcoin_install');
function pascalcoin_install() {
    global $wpdb;
    require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . "pascalcoin_gateway_quotes";
    if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
               order_id BIGINT(20) UNSIGNED NOT NULL,
               payment_id VARCHAR(16) DEFAULT '' NOT NULL,
               currency VARCHAR(6) DEFAULT '' NOT NULL,
               rate BIGINT UNSIGNED DEFAULT 0 NOT NULL,
               amount BIGINT UNSIGNED DEFAULT 0 NOT NULL,
               paid TINYINT NOT NULL DEFAULT 0,
               confirmed TINYINT NOT NULL DEFAULT 0,
               pending TINYINT NOT NULL DEFAULT 1,
               created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
               PRIMARY KEY (order_id)
               ) $charset_collate;";
        dbDelta($sql);
    }

    $table_name = $wpdb->prefix . "pascalcoin_gateway_quotes_txids";
    if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
               id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
               payment_id VARCHAR(16) DEFAULT '' NOT NULL,
               txid VARCHAR(64) DEFAULT '' NOT NULL,
               amount BIGINT UNSIGNED DEFAULT 0 NOT NULL,
               height MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
               PRIMARY KEY (id),
               UNIQUE KEY (payment_id, txid, amount)
               ) $charset_collate;";
        dbDelta($sql);
    }

    $table_name = $wpdb->prefix . "pascalcoin_gateway_live_rates";
    if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
               currency VARCHAR(6) DEFAULT '' NOT NULL,
               rate BIGINT UNSIGNED DEFAULT 0 NOT NULL,
               updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
               PRIMARY KEY (currency)
               ) $charset_collate;";
        dbDelta($sql);
    }
}
