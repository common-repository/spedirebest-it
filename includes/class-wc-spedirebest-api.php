<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * WC_spedirebest_API Class
 */
class WC_spedirebest_API
{
    /* API for check delivery data timing for one or multiple orders in transit */
    private $url_send = "https://www.spedirebest.it/api/spedizione?service=check_deliveryData";
    /* API for check when order is can be managed and send */
    private $url_retirement = "https://www.spedirebest.it/api/spedizione?service=get_available_dates";
    /* API for check status for one or multiple order id */
    private $url_status = "https://www.spedirebest.it/api/spedizione?service=get_last_tracking_by_id";
    /* API for delete shipping information (if order is not already in transit) */
    private $url_delete = "https://www.spedirebest.it/api/spedizione?service=delete";

    private $spedirebest = null;

    public function __construct(){
        $this->spedirebest = new WC_spedirebest_Integration();
    }

    public function isAutomaticCreation(){
        if($this->spedirebest->getAutomaticOrderCreation() == 'yes'){
            return true;
        }else{
            return false;
        }
    }

    private function calculateWeight($order){
        $total_weight = 0;
        foreach( $order->get_items() as $item_id => $product_item ){
            $quantity = $product_item->get_quantity();
            $product = $product_item->get_product();
            $product_weight = $product->get_weight();
            $total_weight += floatval( $product_weight * $quantity );
        }
        //error_log("Total Weigth: ".$total_weight);
        return $total_weight;
    }

    private function calculateLength($order){
        $total_length = 0;
        $max_length = 0;
        foreach( $order->get_items() as $item_id => $product_item ){
            $quantity = $product_item->get_quantity();
            $product = $product_item->get_product();
            $product_length = $product->get_length();
            $total_length += floatval( $product_length * $quantity );
            $total_length += floatval($quantity * 0.04);

            if($product_length > $max_length){
                $max_length = $product_length;
            }
        }
        //error_log("Total Length: ".$total_length);
        //return $total_length;
        return $max_length;
    }

    private function calculateWidth($order){
        $total_width = 0;
        $max_width = 0;
        foreach( $order->get_items() as $item_id => $product_item ){
            $quantity = $product_item->get_quantity();
            $product = $product_item->get_product();
            $product_width = $product->get_width();
            $total_width += floatval( $product_width * $quantity );
            $total_width += floatval($quantity * 0.04);

            if($product_width > $max_width){
                $max_width = $product_width;
            }
        }
        //error_log("Total Width: ".$total_width);
        //return $total_width;
        return $max_width;
    }

    private function calculateHeight($order){
        $total_height = 0;
        foreach( $order->get_items() as $item_id => $product_item ){
            $quantity = $product_item->get_quantity();
            $product = $product_item->get_product();
            $product_height = $product->get_height();
            $total_height += floatval( $product_height * $quantity );
            //$total_height += floatval($quantity * 0.04);
        }
        //error_log("Total Heigth: ".$total_height);
        return $total_height;
    }

    public function getFirstRetirement(){
        $response = wp_remote_post( $this->url_retirement, array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type'  => 'application/json',
                'api-key'       => $this->spedirebest->getApiKey(),
            ),
        ) );
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            //error_log($error_message);
            return false;
        } else {
            $dateRetirement = json_decode($response['body'], true);
            return $dateRetirement['value'][0];
        }
    }

    public function deleteOrder($order_id){
        $response = wp_remote_post($this->url_delete, array(
            'method' => 'POST',
            'body' => array(
                'service' => 'delete',
                'id' => $order_id,
            ),
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'api-key' => $this->spedirebest->getApiKey(),
            ),
        ));
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log($error_message);
            return false;
        } else {
            $status = json_decode($response['body'], true);
            //error_log(print_r($status, TRUE));
            if ($status['success'] == 'true' || $status['success'] == true || $status['success'] == 1) {
                return true;
            } else {
                return false;
            }
        }

    }

    public function updateStatus($order){
        $spedirebest_id = get_post_meta($order->get_id(),"spedirebest_id", true);
        $response2 = wp_remote_post( $this->url_status, array(
            'method' => 'POST',
            'body'   => array(
                'service'       => 'get_last_tracking_by_id',
                'id'            => $spedirebest_id
            ),
            'headers' => array(
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'api-key'       => $this->spedirebest->getApiKey(),
            ),
        ) );
        if ( is_wp_error( $response2 ) ) {
            $error_message = $response2->get_error_message();
            //error_log($error_message);
            return false;
        } else {
            $statusOrder = json_decode($response2['body'], true);

            if(!$statusOrder['data']['spedizione_esito_stato_id'])
                return;

            $actual_state = $statusOrder['data']['spedizione_esito_stato_id'];
            $latest_state = get_post_meta($order->get_id(),"spedirebest_latest_status", true);

            //lo setto la prima volta
            if(empty($latest_state) && !empty($actual_state)){
                add_post_meta($order->get_id(),"spedirebest_latest_status",$actual_state, true);
            }

            if(!empty($actual_state) && $actual_state != $latest_state){
                //devo aggiornare lo stato
                if($actual_state == 10){
                    $order->update_status('wc-spedizione-preparata', $statusOrder['data']['spedizione_esito_stato_nome'].' '.$statusOrder['data']['spedizione_esito_nome']);
                    update_post_meta($order->get_id(),"spedirebest_latest_status",$actual_state);
                    wp_update_post(array(
                        'ID'            =>  $order->get_id(),
                        'post_status'   =>  'wc-spedizione-preparata'
                    ));
                }else if($actual_state == 50){
                    $order->update_status('wc-attesa-corriere', $statusOrder['data']['spedizione_esito_stato_nome'].' '.$statusOrder['data']['spedizione_esito_nome']);
                    update_post_meta($order->get_id(),"spedirebest_latest_status",$actual_state);
                    wp_update_post(array(
                        'ID'            =>  $order->get_id(),
                        'post_status'   =>  'wc-attesa-corriere'
                    ));
                }else if($actual_state == 100){
                    $order->update_status('wc-affidata-corriere', $statusOrder['data']['spedizione_esito_stato_nome'].' '.$statusOrder['data']['spedizione_esito_nome']);
                    update_post_meta($order->get_id(),"spedirebest_latest_status",$actual_state);
                    wp_update_post(array(
                        'ID'            =>  $order->get_id(),
                        'post_status'   =>  'wc-affidata-corriere'
                    ));
                }else if($actual_state == 200){
                    $order->update_status('wc-completed', $statusOrder['data']['spedizione_esito_stato_nome'].' '.$statusOrder['data']['spedizione_esito_nome']);
                    update_post_meta($order->get_id(),"spedirebest_latest_status",$actual_state);
                    wp_update_post(array(
                        'ID'            =>  $order->get_id(),
                        'post_status'   =>  'wc-completed'
                    ));
                }else if($actual_state == 201){
                    $order->update_status('wc-cancelled', $statusOrder['data']['spedizione_esito_stato_nome'].' '.$statusOrder['data']['spedizione_esito_nome']);
                    update_post_meta($order->get_id(),"spedirebest_latest_status",$actual_state);
                    wp_update_post(array(
                        'ID'            =>  $order->get_id(),
                        'post_status'   =>  'wc-cancelled'
                    ));
                }
                $order->save();
            }
        }
    }

    public function updateStatusMultiple($check_ids, $orders_id){
        $response2 = wp_remote_post( $this->url_status, array(
            'method' => 'POST',
            'body'   => array(
                'service'       => 'get_last_tracking_by_id',
                'id'            => $check_ids
            ),
            'headers' => array(
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'api-key'       => $this->spedirebest->getApiKey(),
            ),
        ) );
        //error_log(print_r($response2, TRUE));
        if ( is_wp_error( $response2 ) ) {
            $error_message = $response2->get_error_message();
            //error_log($error_message);
            return false;
        } else {
            $statusOrder = json_decode($response2['body'], true);

            $data_response = $statusOrder['data'];

            foreach ($data_response as $item_order){
                //error_log($item_order['spedizione_id']);
                $key = array_search($item_order['spedizione_id'], $check_ids);

                $actual_state = $item_order['spedizione_esito_stato_id'];
                $order = new WC_Order($orders_id[$key]);
                $latest_state = get_post_meta($order->get_id(),"spedirebest_latest_status", true);

                if(empty($latest_state) && !empty($actual_state)){
                    add_post_meta($order->get_id(),"spedirebest_latest_status",$actual_state, true);
                }

                if(!empty($actual_state) && $actual_state != $latest_state){
                    //devo aggiornare lo stato
                    if($actual_state == 10){
                        $order->update_status('wc-spedizione-preparata', $item_order['spedizione_esito_stato_nome'].' '.$item_order['spedizione_esito_nome']);
                        update_post_meta($order->get_id(),"spedirebest_latest_status",$actual_state);
                        wp_update_post(array(
                            'ID'            =>  $order->get_id(),
                            'post_status'   =>  'wc-spedizione-preparata'
                        ));
                    }else if($actual_state == 50){
                        $order->update_status('wc-attesa-corriere', $item_order['spedizione_esito_stato_nome'].' '.$item_order['spedizione_esito_nome']);
                        update_post_meta($order->get_id(),"spedirebest_latest_status",$actual_state);
                        wp_update_post(array(
                            'ID'            =>  $order->get_id(),
                            'post_status'   =>  'wc-attesa-corriere'
                        ));
                    }else if($actual_state == 100){
                        $order->update_status('wc-affidata-corriere', $item_order['spedizione_esito_stato_nome'].' '.$item_order['spedizione_esito_nome']);
                        update_post_meta($order->get_id(),"spedirebest_latest_status",$actual_state);
                        wp_update_post(array(
                            'ID'            =>  $order->get_id(),
                            'post_status'   =>  'wc-affidata-corriere'
                        ));
                    }else if($actual_state == 200){
                        $order->update_status('wc-completed', $item_order['spedizione_esito_stato_nome'].' '.$item_order['spedizione_esito_nome']);
                        update_post_meta($order->get_id(),"spedirebest_latest_status",$actual_state);
                        wp_update_post(array(
                            'ID'            =>  $order->get_id(),
                            'post_status'   =>  'wc-completed'
                        ));
                    }else if($actual_state == 201){
                        $order->update_status('wc-cancelled', $item_order['spedizione_esito_stato_nome'].' '.$item_order['spedizione_esito_nome']);
                        update_post_meta($order->get_id(),"spedirebest_latest_status",$actual_state);
                        wp_update_post(array(
                            'ID'            =>  $order->get_id(),
                            'post_status'   =>  'wc-cancelled'
                        ));
                    }
                    $order->save();
                }
            }
        }
    }

    private function calculateColli($order){
        if($this->spedirebest->getTipologiaCollo() == 'divided' ){
            $spedizioni = array();
            foreach( $order->get_items() as $item_id => $product_item ){
                for($i=0; $i<$product_item->get_quantity(); $i++){
                    $product = $product_item->get_product();
                    $temp = array(
                        'colli'         => '1',
                        'peso'          => $product->get_weight(),
                        'lunghezza'     => $product->get_length(),
                        'larghezza'     => $product->get_width(),
                        'altezza'       => $product->get_height(),
                        'descrizione'   => ''
                    );
                    array_push($spedizioni, $temp);
                }
            }
        }else{
            $spedizioni = array(
                array(
                    'colli'         => '1',
                    'peso'          => $this->calculateWeight($order),
                    'lunghezza'     => $this->calculateLength($order),
                    'larghezza'     => $this->calculateWidth($order),
                    'altezza'       => $this->calculateHeight($order),
                    'descrizione'   => ''
                )
            );
        }
        return $spedizioni;
    }

    private function has_refunds( $order ) {
        if(method_exists($order, 'get_refunds')){
            $refund_situation = sizeof( $order->get_refunds() ) > 0 ? true : false;
            return $refund_situation;
        }else{
            return false;
        }
    }

    private function getNote($order){
        $note = '';
        $first = true;
        foreach( $order->get_items() as $item_id => $product_item ){
            if(!$first)
                $note .= ' | ';
            $note .= $product_item->get_quantity().' x '.$product_item->get_name();
            $first = false;
        }
        return $note;
    }

    private function callSpedireBest($order, $production, $retirement = null){
        if( ! $this->has_refunds( $order ) && method_exists($order, 'get_shipping_first_name') && $order->get_shipping_country() == 'IT' ) {
            $response = wp_remote_post($this->url_send, array(
                'method' => 'POST',
                'body' => array(
                    'service' => 'check_deliveryData',
                    'spedizione_tipo_id' => '1',
                    'spedizione_data_ritiro' => $retirement,
                    'spedizione_fascia_ritiro' => 'P',
                    'spedizione_note_ritiro' => $this->getNote($order),
                    'spedizione_note' => 'Ordine: '.$order->get_id(),
                    'spedizione_valore' => '',
                    'fascia_limite' => '',
                    'spedizione_dettagli' => $this->calculateColli($order),
                    'mittente' => array(
                        'nome' => $this->spedirebest->getNome(),
                        'localita' => $this->spedirebest->getLocalita(),
                        'cap' => $this->spedirebest->getCap(),
                        'indirizzo' => $this->spedirebest->getIndirizzo(),
                        'email' => $this->spedirebest->getEmail(),
                        'telefono' => $this->spedirebest->getTelefono(),
                    ),
                    'destinatario' => array(
                        'nome' => $order->get_shipping_first_name() . " " . $order->get_shipping_last_name(),
                        'localita' => $order->get_shipping_city() . " (" . $order->get_shipping_state() . ")",
                        'cap' => $order->get_shipping_postcode(),
                        'indirizzo' => $order->get_shipping_address_1() . " ". $order->get_shipping_address_2(),
                        'email' => $order->get_billing_email(),
                        'telefono' => $order->get_billing_phone(),
                        'aggiuntive' => $order->get_customer_note(),
                    ),
                    'richiedi_ritiro' => 'true',
                    'servizi_accessori' => array(
                        '201'   =>  array(
                            'enabled'   =>  $this->spedirebest->getTracking() == 'yes' && !empty($order->get_billing_email()) ? 'true' : 'false',
                        ),
                        '202'   =>  array(
                            'enabled'   =>  $this->spedirebest->getEmailTracking() == 'yes' && !empty($order->get_billing_email()) ? 'true' : 'false',
                        ),
                    ),
                    'spedizione_triangolazione' => 'false',
                    'create_delivery' => $production == 1 ? 'true' : 'false'
                ),
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'api-key' => $this->spedirebest->getApiKey(),
                ),
            ));

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                //error_log($error_message);
                return false;
            } else {
                $resultSend = json_decode($response['body'], true);

                if (!empty($resultSend['validated_errors'])) {
                    $note = __("ATTENZIONE SpedireBest: " . implode(",", $resultSend['validated_errors']));
                    $order->update_status('shipping-error', $note);
                    wp_update_post(array(
                        'ID'            =>  $order->get_id(),
                        'post_status'   =>  'wc-shipping-error'
                    ));
                } else {
                    if ($production == 1) {
                        add_post_meta($order->get_id(), 'spedirebest_id', $resultSend['spedizione_id'], true);
                        if($order->get_status() == 'crea-spedizione' || $order->get_status() == 'wc-crea-spedizione'){
                            $order->update_status('wc-processing', "ID Spedizione: ".$resultSend['spedizione_id']);
                        }else{
                            $order->add_order_note("ID Spedizione: " . $resultSend['spedizione_id']);
                        }
                    }
                }
            }
        }else{
            return null;
        }
    }

    public function send($order, $production, $retirement = null){
        $this->callSpedireBest($order, $production, $retirement);
        //error_log($response);
    }

}
