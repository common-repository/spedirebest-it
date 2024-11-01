<?php

$fields = array(
    'apikey' => array(
        'title' => __('API Key', 'spedirebest'),
        'type' => 'text',
        'default' => ''
    ),
    'nome' => array(
        'title' => __('Nome Mittente (*)', 'spedirebest'),
        'type' => 'text',
        'default' => ''
    ),
    'localita' => array(
        'title' => __('Città mittente e provincia (es: Roma (RM)) (*)', 'spedirebest'),
        'type' => 'text',
        'default' => ''
    ),
    'cap' => array(
        'title' => __('CAP Mittente (*)', 'spedirebest'),
        'type' => 'text',
        'default' => ''
    ),
    'indirizzo' => array(
        'title' => __('Indirizzo Mittente (*)', 'spedirebest'),
        'type' => 'text',
        'default' => ''
    ),
    'email' => array(
        'title' => __('EMail Mittente', 'spedirebest'),
        'type' => 'text',
        'default' => ''
    ),
    'telefono' => array(
        'title' => __('Telefono Mittente', 'spedirebest'),
        'type' => 'text',
        'default' => ''
    ),
    'tipologia_collo' => array(
        'title' => __('Modalità di preparazione spedizione', 'spedirebest'),
        'type' => 'select',
        'options' => array(
            'unique'        => __( 'Unico collo per tutti i prodotti', 'spedirebest' ),
            'divided'      => __( 'Ogni prodotto in un collo diverso', 'spedirebest' ),
        ),
        'desc_tip' =>  true,
    ),
    'tracking' => array(
        'title' => __('Invia link di tracking alla mail del destinatario', 'spedirebest'),
        'type' => 'checkbox',
        'default' => 'yes',
    ),
    'email_tracking' => array(
        'title' => __('Invia email di avviso consegna al destinatario', 'spedirebest'),
        'type' => 'checkbox',
        'default' => 'yes',
    ),
    'automatic_order_create' => array(
        'title' => __('Creare ordine su SpedireBest automaticamente', 'spedirebest'),
        'type' => 'checkbox',
        'default' => 'yes',
    ),
);


return $fields;


