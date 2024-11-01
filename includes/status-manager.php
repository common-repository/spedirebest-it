<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function spedirebest_order_change_custom($post_id){
    global $post;
    if ( 'shop_order' == $post->post_type ) {
        $spedirebest_api = new WC_spedirebest_API();

        $order = wc_get_order($post_id);
        $spedirebest_id = get_post_meta($order->get_id(), "spedirebest_id", true);
        $status_order = $order->get_status();
        if ($status_order == 'processing' || $status_order == 'wc-processing' || $status_order == 'shipping-error' ) {
            if (!empty($spedirebest_id)) {
                //spedizione già con ID devo cancellarlo e ricrearlo
                $delete_status = $spedirebest_api->deleteOrder($spedirebest_id);
                if ($delete_status == true) {
                    delete_post_meta($order->get_id(), "spedirebest_id");
                    $retirement = $spedirebest_api->getFirstRetirement();
                    if ($retirement != false) {
                        $spedirebest_api->send($order, 1, $retirement);
                    }
                } else {
                    $order->add_order_note("Impossibile creare nuova spedizione. Contattare assistenza clienti SpedireBest.it");
                }
            }
        }
    }
}
add_action('save_post', 'spedirebest_order_change_custom', 10, 1);


function spedirebest_order_status_change_custom($order_id,$old_status,$new_status) {
    $spedirebest_api = new WC_spedirebest_API();

    if( ( $new_status == "processing" && $spedirebest_api->isAutomaticCreation() && $old_status != "crea-spedizione" ) || $new_status == "crea-spedizione"){
        $order = wc_get_order( $order_id );
        $spedirebest_id = get_post_meta($order->get_id(),"spedirebest_id", true);
        if( empty($spedirebest_id) ){
            $retirement = $spedirebest_api->getFirstRetirement();
            if($retirement != false){
                //posso continuare
                $spedirebest_api->send($order, 1, $retirement);
            }
        }else{
            //spedizione già con ID devo cancellarlo e ricrearlo
            $delete_status = $spedirebest_api->deleteOrder($spedirebest_id);
            if($delete_status == true){
                delete_post_meta($order->get_id(),"spedirebest_id");
                $retirement = $spedirebest_api->getFirstRetirement();
                if($retirement != false){
                    $spedirebest_api->send($order, 1, $retirement);
                }
            }else{
                $order->add_order_note("Impossibile creare nuova spedizione. Contattare assistenza clienti SpedireBest.it");
            }
        }
    }else if( $new_status == "cancelled"){
        $order = wc_get_order( $order_id );
        $spedirebest_id = get_post_meta($order->get_id(),"spedirebest_id", true);
        if( !empty($spedirebest_id) ){
            $delete_status = $spedirebest_api->deleteOrder($spedirebest_id);
            if ($delete_status == true) {
                delete_post_meta($order->get_id(), "spedirebest_id");
            }else{
                $order->add_order_note("Impossibile creare nuova spedizione. Contattare assistenza clienti SpedireBest.it");
            }
        }
    }
}
add_action('woocommerce_order_status_changed','spedirebest_order_status_change_custom', 10, 3);

function spedirebest_register_crea_spedizione_order_status() {
    register_post_status( 'wc-crea-spedizione', array(
        'label'                     => 'Crea Spedizione SpedireBest.it',
        'public'                    => true,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Crea Spedizione SpedireBest.it (%s)', 'Spedizione Preparata (%s)' )
    ) );
}
add_action( 'init','spedirebest_register_crea_spedizione_order_status', 10, 1 );


function spedirebest_register_crea_spedizione_order_status2( $order_statuses ){
    $order_statuses['wc-crea-spedizione'] = array(
        'label'                     => 'Crea Spedizione SpedireBest.it',
        'public'                    => true,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Crea Spedizione SpedireBest.it (%s)', 'Spedizione Preparata (%s)' )
    );
    return $order_statuses;
}
add_filter( 'woocommerce_register_shop_order_post_statuses', 'spedirebest_register_crea_spedizione_order_status2' );

function spedirebest_add_crea_spedizione_to_order_statuses($order_statuses){
    //$order_statuses['crea-spedizione'] = _x( 'Crea Spedizione SpedireBest.it', 'Order Status', '' );
    //return $order_statuses;
    foreach ( $order_statuses as $key => $status ) {
        $new_order_statuses[ $key ] = $status;
        if ( 'wc-processing' === $key ) {
            $new_order_statuses['wc-crea-spedizione'] = 'Crea Spedizione SpedireBest.it';
        }
    }
    return $new_order_statuses;
}
add_filter( 'wc_order_statuses', 'spedirebest_add_crea_spedizione_to_order_statuses', 10, 1);
function spedirebest_hook_statuses_icons_css_crea_spedizione() {
    $output   = '<style>';

    $content = 'e011';
    $color   = '#999999';

    $output .= 'mark.crea-spedizione' . '::after { content: "\\' . $content . '"; color: ' . $color . '; }';
    $output .= 'mark.crea-spedizione' . ':after {font-family:WooCommerce;speak:none;font-weight:400;font-variant:normal;text-transform:none;line-height:1;-webkit-font-smoothing:antialiased;margin:0;text-indent:0;position:absolute;top:0;left:0;width:100%;height:100%;text-align:center}';
    $output .= '</style>';
    echo wp_kses( $output, array( 'style' => array() ) );
}
add_action( 'admin_head', 'spedirebest_hook_statuses_icons_css_crea_spedizione', 11 );

function spedirebest_register_spedizione_preparata_order_status() {
    register_post_status( 'wc-spedizione-preparata', array(
        'label'                     => 'Spedizione Preparata',
        'public'                    => true,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Spedizione Preparata (%s)', 'Spedizione Preparata (%s)' )
    ) );
}
add_action( 'init','spedirebest_register_spedizione_preparata_order_status', 10, 1 );

function spedirebest_register_spedizione_preparata_order_status2( $order_statuses ){
    $order_statuses['wc-spedizione-preparata'] = array(
        'label'                     => 'Spedizione Preparata',
        'public'                    => true,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Spedizione Preparata (%s)', 'Spedizione Preparata (%s)' )
    );
    return $order_statuses;
}
add_filter( 'woocommerce_register_shop_order_post_statuses', 'spedirebest_register_spedizione_preparata_order_status2' );

function spedirebest_add_spedizione_preparata_to_order_statuses($order_statuses){
    foreach ( $order_statuses as $key => $status ) {
        $new_order_statuses[ $key ] = $status;
        if ( 'wc-processing' === $key ) {
            $new_order_statuses['wc-spedizione-preparata'] = 'Spedizione Preparata';
        }
    }
    return $new_order_statuses;
}
add_filter( 'wc_order_statuses', 'spedirebest_add_spedizione_preparata_to_order_statuses', 10, 1);
function spedirebest_hook_statuses_icons_css_spedizione_preparata() {
    $output   = '<style>';

    $content = 'e011';
    $color   = '#999999';

    $output .= 'mark.wc-spedizione-preparata' . '::after { content: "\\' . $content . '"; color: ' . $color . '; }';
    $output .= 'mark.wc-spedizione-preparata' . ':after {font-family:WooCommerce;speak:none;font-weight:400;font-variant:normal;text-transform:none;line-height:1;-webkit-font-smoothing:antialiased;margin:0;text-indent:0;position:absolute;top:0;left:0;width:100%;height:100%;text-align:center}';
    $output .= '</style>';
    echo wp_kses( $output, array( 'style' => array() ) );
}
add_action( 'admin_head', 'spedirebest_hook_statuses_icons_css_spedizione_preparata', 11 );

/* Now Order */
function spedirebest_register_attesa_ritiro_corriere_order_status() {
    register_post_status( 'wc-attesa-corriere', array(
        'label'                     => 'In attesa ritiro corriere',
        'public'                    => true,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'In attesa ritiro corriere (%s)', 'In attesa ritiro corriere (%s)' )
    ) );
}
add_action( 'init', 'spedirebest_register_attesa_ritiro_corriere_order_status' );

function spedirebest_add_attesa_ritiro_corriere_to_order_statuses($order_statuses){
    foreach ( $order_statuses as $key => $status ) {
        $new_order_statuses[ $key ] = $status;
        if ( 'wc-processing' === $key ) {
            $new_order_statuses['wc-attesa-corriere'] = 'In attesa ritiro corriere';
        }
    }
    return $new_order_statuses;
}

add_filter( 'wc_order_statuses', 'spedirebest_add_attesa_ritiro_corriere_to_order_statuses', 10, 1);
function spedirebest_hook_statuses_icons_css_attesa_ritiro_corriere() {
    $output   = '<style>';

    $content = 'e011';
    $color   = '#999999';

    $output .= 'mark.wc-attesa-corriere' . '::after { content: "\\' . $content . '"; color: ' . $color . '; }';
    $output .= 'mark.wc-attesa-corriere' . ':after {font-family:WooCommerce;speak:none;font-weight:400;font-variant:normal;text-transform:none;line-height:1;-webkit-font-smoothing:antialiased;margin:0;text-indent:0;position:absolute;top:0;left:0;width:100%;height:100%;text-align:center}';
    $output .= '</style>';
    echo wp_kses( $output, array( 'style' => array() ) );
}
add_action( 'admin_head', 'spedirebest_hook_statuses_icons_css_attesa_ritiro_corriere', 11 );

/* Error Order */
function spedirebest_register_shipment_error_order_status() {
    register_post_status( 'wc-shipping-error', array(
        'label'                     => 'Errore creazione spedizione',
        'public'                    => true,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Errore creazione spedizione (%s)', 'Errore creazione spedizione (%s)' )
    ) );
}
add_action( 'init', 'spedirebest_register_shipment_error_order_status' );

function spedirebest_add_shipping_error_to_order_statuses($order_statuses){
    foreach ( $order_statuses as $key => $status ) {
        $new_order_statuses[ $key ] = $status;
        if ( 'wc-processing' === $key ) {
            $new_order_statuses['wc-shipping-error'] = 'Errore creazione spedizione';
        }
    }
    return $new_order_statuses;
}

add_filter( 'wc_order_statuses', 'spedirebest_add_shipping_error_to_order_statuses', 10, 1);
function spedirebest_hook_statuses_icons_css_attesa_errore_creazione_spedizione() {
    $output   = '<style>';

    $content = 'e011';
    $color   = '#999999';

    $output .= 'mark.shipping-error' . '::after { content: "\\' . $content . '"; color: ' . $color . '; }';
    $output .= 'mark.shipping-error' . ':after {font-family:WooCommerce;speak:none;font-weight:400;font-variant:normal;text-transform:none;line-height:1;-webkit-font-smoothing:antialiased;margin:0;text-indent:0;position:absolute;top:0;left:0;width:100%;height:100%;text-align:center}';
    $output .= '</style>';
    echo wp_kses( $output, array( 'style' => array() ) );
}
add_action( 'admin_head', 'spedirebest_hook_statuses_icons_css_attesa_errore_creazione_spedizione', 11 );

/* Complete Order */
function spedirebest_register_affidata_corriere_order_status() {
    register_post_status( 'wc-affidata-corriere', array(
        'label'                     => 'Affidata al corriere',
        'public'                    => true,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Affidata al corriere (%s)', 'Affidata al corriere (%s)' )
    ) );
}
add_action( 'init', 'spedirebest_register_affidata_corriere_order_status' );
function spedirebest_add_affidata_corriere_to_order_statuses($order_statuses){
    foreach ( $order_statuses as $key => $status ) {
        $new_order_statuses[ $key ] = $status;
        if ( 'wc-processing' === $key ) {
            $new_order_statuses['wc-affidata-corriere'] = 'Affidata al corriere';
        }
    }
    return $new_order_statuses;
}

add_filter( 'wc_order_statuses', 'spedirebest_add_affidata_corriere_to_order_statuses', 10, 1);
function spedirebest_hook_statuses_icons_css_attesa_affidata_corriere() {
    $output   = '<style>';

    $content = 'e011';
    $color   = '#999999';

    $output .= 'mark.wc-affidata-corriere' . '::after { content: "\\' . $content . '"; color: ' . $color . '; }';
    $output .= 'mark.wc-affidata-corriere' . ':after {font-family:WooCommerce;speak:none;font-weight:400;font-variant:normal;text-transform:none;line-height:1;-webkit-font-smoothing:antialiased;margin:0;text-indent:0;position:absolute;top:0;left:0;width:100%;height:100%;text-align:center}';
    $output .= '</style>';
    echo wp_kses( $output, array( 'style' => array() ) );
}
add_action( 'admin_head', 'spedirebest_hook_statuses_icons_css_attesa_affidata_corriere', 11 );