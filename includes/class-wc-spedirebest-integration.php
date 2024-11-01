<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * WC_spedirebest_Integration Class
 */
class WC_spedirebest_Integration extends WC_Integration
{

    public static $url = null;
    public static $apikey = null;
    public static $export_statuses = array();
    public static $nome = null;
    public static $localita = null;
    public static $cap = null;
    public static $indirizzo = null;
    public static $email = null;
    public static $telefono = null;
    public static $tipologia_collo = null;
    public static $tracking = null;
    public static $email_tracking = null;
    public static $automatic_order_create = null;

    /**
     * Constructor
     */
    public function __construct()
    {

        $this->id = 'spedirebest';
        $this->method_title = __('SpedireBest.it', 'spedirebest');
        $this->method_description = __('', 'spedirebest');

        // Load admin form
        $this->init_form_fields();

        // Load settings
        $this->init_settings();

        self::$apikey = $this->get_option('apikey', false);
        self::$nome = $this->get_option('nome', false);
        self::$localita = $this->get_option('localita', false);
        self::$cap = $this->get_option('cap', false);
        self::$indirizzo = $this->get_option('indirizzo', false);
        self::$email = $this->get_option('email', false);
        self::$telefono = $this->get_option('telefono', false);
        self::$tipologia_collo = $this->get_option('tipologia_collo', false);
        self::$tracking = $this->get_option('tracking', false);
        self::$email_tracking = $this->get_option('email_tracking', false);
        self::$automatic_order_create = $this->get_option('automatic_order_create', false);

        self::$export_statuses = $this->get_option('export_statuses', array('wc-processing', 'wc-on-hold', 'wc-completed', 'wc-cancelled'));

        $this->settings['apikey'] = self::$apikey;
        $this->settings['nome'] = self::$nome;
        $this->settings['localita'] = self::$localita;
        $this->settings['cap'] = self::$cap;
        $this->settings['indirizzo'] = self::$indirizzo;
        $this->settings['email'] = self::$email;
        $this->settings['telefono'] = self::$telefono;
        $this->settings['tipologia_collo'] = self::$tipologia_collo;
        $this->settings['tracking'] = self::$tracking;
        $this->settings['email_tracking'] = self::$email_tracking;
        $this->settings['automatic_order_create'] = self::$automatic_order_create;

        // Hooks
        add_action('woocommerce_update_options_integration_spedirebest', array($this, 'process_admin_options'));
        add_filter('woocommerce_subscriptions_renewal_order_meta_query', array($this, 'subscriptions_renewal_order_meta_query'), 10, 4);
        add_filter('plugin_action_links_' . SPEDIREBEST_PLUGIN_BASENAME, array($this, 'emt_plugin_action_links'));

        if (!self::$apikey) {
            add_action('admin_notices', array($this, 'settings_notice'));
        }

    }
    /**
     *
     */
    public function process_admin_options()
    {
        parent::process_admin_options();
    }

    /**
     * Init integration form fields
     */
    public function init_form_fields()
    {
        $this->form_fields = include('data/data-settings.php');
    }

    /**
     * Prevents WooCommerce Subscriptions from copying across certain meta keys to renewal orders.
     * @param  array $order_meta_query
     * @param  int $original_order_id
     * @param  int $renewal_order_id
     * @param  string $new_order_role
     * @return array
     */
    public function subscriptions_renewal_order_meta_query($order_meta_query, $original_order_id, $renewal_order_id, $new_order_role)
    {
        if ('parent' == $new_order_role) {
            $order_meta_query .= " AND `meta_key` NOT IN ("
                . "'_tracking_provider', "
                . "'_tracking_number', "
                . "'_date_shipped', "
                . "'_order_custtrackurl', "
                . "'_order_custcompname', "
                . "'_order_trackno', "
                . "'_order_trackurl' )";
        }
        return $order_meta_query;
    }

    /**
     * Settings prompt
     */
    public function settings_notice()
    {
        if (!empty($_GET['tab']) && 'integration' === $_GET['tab']) {
            return;
        }
        ?>
        <div id="message" class="updated woocommerce-message">
            <p><?php _e('<strong>SpedireBest.it</strong> deve essere configurato.', 'spedirebest'); ?></p>
            <p class="submit"><a href="<?php echo admin_url('admin.php?page=wc-settings&tab=integration&section=spedirebest'); ?>"
                                 class="button-primary"><?php _e('Impostazioni', 'spedirebest'); ?></a></p>
        </div>
        <?php
    }

    /**
     * @param $links
     * @return array
     */
    function emt_plugin_action_links($links)
    {

        $action_links = array(
            'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=integration&section=spedirebest') . '" title="' . esc_attr(__('Impostazioni SpedireBest.it', 'spedirebest')) . '">' . __('Settings', 'spedirebest') . '</a>',
        );

        return array_merge($action_links, $links);
    }

    public function getNome(){
        return self::$nome;
    }
    public function getLocalita(){
        return self::$localita;
    }
    public function getCap(){
        return self::$cap;
    }
    public function getIndirizzo(){
        return self::$indirizzo;
    }
    public function getEmail(){
        return self::$email;
    }
    public function getTelefono(){
        return self::$telefono;
    }
    public function getApiKey(){
        return self::$apikey;
    }
    public function getTipologiaCollo(){
        return self::$tipologia_collo;
    }
    public function getTracking(){
        return self::$tracking;
    }
    public function getEmailTracking(){
        return self::$email_tracking;
    }
    public function getAutomaticOrderCreation(){
        return self::$automatic_order_create;
    }
}
