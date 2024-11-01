<?php

/**
 * Plugin Name: SpedireBest.it
 * Plugin URI: https://www.spedirebest.it/
 * Version: 0.0.1
 * Description: Sincronizza e gestisci le tue spedizioni sul portale SpedireBest.it per i tuoi corrieri di riferimento. Tracking sempre aggiornato per i tuoi clienti.
 * Author: SpedireBest.it
 * Author URI: http://www.spedirebest.it/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: spedirebest
 * Domain Path:       /languages
 *
 *  */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Required functions
 */
if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}


require_once('includes/status-manager.php');
require_once('includes/cron-manager.php');
require_once('includes/class-wc-spedirebest-api.php');

/**
 * Include spedirebest class
 */
function spedirebest_init()
{
    if (!defined('SPEDIREBEST_PLUGIN_BASENAME')) {
        define('SPEDIREBEST_PLUGIN_BASENAME', plugin_basename(__FILE__));
    }

    load_plugin_textdomain( 'spedirebest', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );

    include_once('includes/class-wc-spedirebest-integration.php');
}

add_action('plugins_loaded', 'spedirebest_init');

/**
 * Define integration
 * @param  array $integrations
 * @return array
 */
function spedirebest_load_integration($integrations)
{
    $integrations[] = 'WC_spedirebest_Integration';

    return $integrations;
}

add_filter('woocommerce_integrations', 'spedirebest_load_integration');

/**
 * Listen for API requests
 */
function spedirebest_api()
{
    include_once('includes/class-wc-spedirebest-api.php');
}

add_action('woocommerce_api_wc_spedirebest', 'spedirebest_api');


/* Menu for sync order state */
add_action( 'admin_action_wpse10500', 'spedirebest_admin_action' );
function spedirebest_admin_action()
{
    wp_redirect( esc_url_raw($_SERVER['HTTP_REFERER']) );
    exit();
}

add_action( 'admin_menu', 'spedirebest_admin_menu' );
function spedirebest_admin_menu()
{
    add_management_page( 'SpedireBest - Sync Spedizioni', 'SpedireBest - Sync Spedizioni', 'administrator', 'spedirebest-sync-spedizioni', 'spedirebest_update_shipping_funct' );
}

/**
 * Disable plugin
 */
function spedirebest_deactivation() {
    $spedirebest_api = new WC_spedirebest_API();
    $args = array(
        'status' => ['wc-processing','spedizione-preparata','attesa-corriere','affidata-corriere','shipping-error','crea-spedizione'],
        'limit' => 10000,
        'orderby' => 'date',
        'order' => 'DESC',
    );
    $orders = wc_get_orders( $args );
    foreach ($orders as $order) {
        $status_order = $order->get_status();
        if ($status_order == 'processing' || $status_order == 'spedizione-preparata' || $status_order == 'attesa-corriere' || $status_order == 'affidata-corriere' || $status_order == 'shipping-error' || $status_order == 'crea-spedizione') {
            delete_post_meta($order->get_id(), "spedirebest_latest_status");
            $order->update_status('wc-processing', 'Stato In Lavorazione per disabilitazione plugin SpedireBest.it');
            wp_update_post(array(
                'ID'            =>  $order->get_id(),
                'post_status'   =>  'wc-processing'
            ));
            $order->save();
        }
    }

    /* disattivo Cronjob */
    $timestamp = wp_next_scheduled( 'spedirebest_update_shipping_funct' );
    wp_unschedule_event( $timestamp, 'spedirebest_update_shipping_funct' );

    $timestamp = wp_next_scheduled( 'spedirebest_insert_shipping_errored_funct' );
    wp_unschedule_event( $timestamp, 'spedirebest_insert_shipping_errored_funct' );
}
register_deactivation_hook( __FILE__, 'spedirebest_deactivation' );
