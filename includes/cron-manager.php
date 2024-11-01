<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_filter("cron_schedules", function($schedules){
    //add a new key to the $schedules. key name is same as custom interval name
    //value is a array with interval  recurrance seconds and textual description
    $schedules["spedirebest_every_two_hours"] = array("interval" => 7200, "display" => "Every 2 Hours");
    return $schedules;
});

/* Funzione che si occupa di reinserire le spedizioni che inizialmente hanno avuto errori */
function spedirebest_insert_shipping_errored_funct() {
    error_log("dentro: spedirebest_insert_shipping_errored_funct");
    $args = array(
        'status' => 'wc-processing',
        'limit' => 10000,
        'orderby' => 'date',
        'order' => 'DESC',
    );
    $orders = wc_get_orders( $args );
    $spedirebest_api = new WC_spedirebest_API();

    foreach ($orders as $order){
        if($order->get_status() == 'processing' && $spedirebest_api->isAutomaticCreation()){
            $data_creazione = $order->get_date_created();
            $now = new DateTime('now');
            $interval = $now->diff($data_creazione);
            $minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
            if($minutes > 10){
                $spedirebest_id = get_post_meta($order->get_id(),"spedirebest_id", true);
                if(empty($spedirebest_id)){
                    //error_log("Reinserisco - ".$order->get_id());
                    $retirement = $spedirebest_api->getFirstRetirement();
                    if($retirement != false){
                        //posso continuare
                        $spedirebest_api->send($order, 1, $retirement);
                    }else{
                        //error_log("errore su retirement, fermarsi");
                    }
                }
            }
        }
    }
}

function spedirebest_update_shipping_funct() {
    $spedirebest_api = new WC_spedirebest_API();
    $args = array(
        'status' => ['wc-processing','spedizione-preparata','attesa-corriere','affidata-corriere'],
        'limit' => 10000,
        'orderby' => 'date',
        'order' => 'DESC',
    );
    $orders = wc_get_orders( $args );

    $check_id = array();
    $order_id_array = array();
    $i=0;
    foreach ($orders as $order) {
        $status_order = $order->get_status();
        if ($status_order == 'processing' || $status_order == 'spedizione-preparata' || $status_order == 'attesa-corriere' || $status_order == 'affidata-corriere') {
            $spedirebest_id = get_post_meta($order->get_id(), "spedirebest_id", true);
            if (!empty($spedirebest_id)) {
                array_push($check_id, $spedirebest_id);
                array_push($order_id_array, $order->get_id());
                $i++;
            }else{
                //va creata la spedizione
                $retirement = $spedirebest_api->getFirstRetirement();
                if($retirement != false){
                    $spedirebest_api->send($order, 1, $retirement);
                }else{
                    //error_log("errore su retirement, fermarsi");
                }
            }
        }
        /* DA MODIFICARE */
        if($i == 200){
            $spedirebest_api->updateStatusMultiple($check_id,$order_id_array);
            $check_id = array();
            $order_id_array = array();
            $i=0;
        }
    }
    $spedirebest_api->updateStatusMultiple($check_id,$order_id_array);
    echo "<h2>Sincronizzazione Ordini SpedireBest completata</h2>";
}

add_action('spedirebest_insert_shipping_errored_hook','spedirebest_insert_shipping_errored_funct');
function spedirebest_setup_schedule2(){
    if ( ! wp_next_scheduled( 'spedirebest_insert_shipping_errored_hook' ) ) {
        wp_schedule_event( time(), 'spedirebest_every_two_hours', 'spedirebest_insert_shipping_errored_hook' );
    }
}
add_action('wp', 'spedirebest_setup_schedule2');

add_action('spedirebest_update_shipping_hook','spedirebest_update_shipping_funct');
function spedirebest_setup_schedule(){
    if (!wp_next_scheduled('spedirebest_update_shipping_hook')) {
        wp_schedule_event(time(), 'spedirebest_every_two_hours', 'spedirebest_update_shipping_hook');
    }
}
add_action('wp', 'spedirebest_setup_schedule');