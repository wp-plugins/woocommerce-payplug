<?php
/**
 * Plugin Name: WooCommerce PayPlug, mode TEST seulement !
 * Plugin URI: http://wba.fr/woopayplug/
 * Description: WooCommerce PayPlug is a PayPlug payment test gateway for WooCommerce
 * Author: Boris Colombier
 * Author URI: https://wba.fr
 * Version: 2.0.0
 * License: GPLv2 or later
 * Text Domain: woocommerce-payplug
 * Domain Path: /languages/
 * WC requires at least: 2.0
 * WC tested up to: 2.3.7
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    
    /**
     * WooCommerce fallback notice.
     */
    function wcpayplug_woocommerce_fallback_notice() {
        $html = '<div class="error">';
            $html .= '<p>' . __( 'The WooCommerce PayPlug Gateway requires the latest version of <a href="http://wordpress.org/extend/plugins/woocommerce/" target="_blank">WooCommerce</a> to work!', 'woocommerce-payplug' ) . '</p>';
        $html .= '</div>';
        echo $html;
    }

    /**
     * define getallheaders function if not available.
     */
    if(!function_exists('getallheaders')){
        function getallheaders(){
            $headers = array();
            foreach ($_SERVER as $name => $value){
                if(substr($name, 0, 5) == 'HTTP_'){
                    $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[$name] = $value;
                } else if($name == "CONTENT_TYPE") {
                    $headers["Content-Type"] = $value;
                } else if($name == "CONTENT_LENGTH") {
                    $headers["Content-Length"] = $value;
                } else{
                    $headers[$name]=$value;
                }
           }
           return $headers;
        }
    }

    /**
     * Load functions.
     */
    add_action( 'plugins_loaded', 'wcpayplug_gateway_load', 0 );

    function wcpayplug_gateway_load() {
        /**
         * Load textdomain.
         */
        load_plugin_textdomain( 'woocommerce-payplug', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

        if(!class_exists('WC_Payment_Gateway')){
            add_action('admin_notices', 'wcpayplug_woocommerce_fallback_notice');
            return;
        }

        /**
         * Add the gateway to WooCommerce.
         */
        add_filter( 'woocommerce_payment_gateways', 'wcpayplug_add_gateway' );

        function wcpayplug_add_gateway( $methods ) {
            $methods[] = 'WC_Gateway_Payplug';
            return $methods;
        }

        /**
         * PayPlug Payment Gateway
         *
         * Provides a PayPlug Payment Gateway.
         *
         * @class       WC_Gateway_Payplug
         * @extends     WC_Payment_Gateway
         */
        class WC_Gateway_Payplug extends WC_Payment_Gateway {

            /**
             * Constructor for the gateway.
             */
            public function __construct() {
                global $woocommerce;
                
                $this->id                  = 'payplug';
                $this->icon                = plugins_url( 'assets/images/payplug.png', __FILE__ );
                $this->has_fields          = false;
                $this->method_title        = __( 'PayPlug', 'woocommerce-payplug' );

                $this->notify_url   = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_Payplug', home_url( '/' ) ) );

                // Load the form fields.
                $this->init_form_fields();

                // Load the settings.
                $this->init_settings();

                // Define user setting variables.
                $this->title                = $this->settings['title'];
                $this->description          = $this->settings['description'];
                $this->payplug_login        = $this->settings['payplug_login'];
                $this->payplug_password     = $this->settings['payplug_password'];
                $this->parameters           = json_decode(rawurldecode($this->settings['payplug_parameters']), true);
                $this->payplug_privatekey   = $this->parameters['yourPrivateKey'];
                $this->payplug_url          = $this->parameters['url'];

                $this->payplug_publickey    = $this->parameters['payplugPublicKey'];
                
                // Actions.
                add_action( 'woocommerce_api_wc_gateway_payplug', array($this, 'check_ipn_response'));
                add_action( 'valid_payplug_ipn_request', array( &$this, 'successful_request' ) );
                add_action( 'woocommerce_receipt_payplug', array( &$this, 'receipt_page' ) );

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')){
                    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
                }else{
                    add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
                }
            }

            /**
             * Checking if this gateway is enabled.
             */
            public function is_valid_for_use() {
                if (!in_array(get_woocommerce_currency(), array('EUR'))){
                    return false;
                }
                return true;
            }

            /**
             * Add specific infos on admin page.
             */
            public function admin_options() {
                wp_enqueue_style( 'wcpayplugStylesheet', plugins_url('assets/css/payplug.css', __FILE__) );
                ?>
                <div id="wc_get_started" class="payplug">
                    <?php _e( "<span>Add online payment by bank card to your website in a matter of clicks.</span><br/><b>No fixed monthly fees.</b> No customer account required to make a payment.<br>" , 'woocommerce-payplug' ); ?>
                    <div class="bt_hld">
                        <p>
                            <a href="http://url.wba.fr/payplug" target="_blank" class="button"><?php _e( "Create a free account" , 'woocommerce-payplug' ); ?></a>
                            <a href="https://www.payplug.fr/" target="_blank" class="button"><?php _e( "Find out more about PayPlug" , 'woocommerce-payplug' ); ?></a>
                        </p>
                    </div>
                    <h3>Cette version permet d'utiliser PayPlug en mode TEST seulement</h3>
                    <p style="margin-top:0"><a href="http://support.payplug.fr/customer/portal/articles/1701656" target="_blank">Plus d'information sur le mode TEST</a></p>
                    <a class="button button-primary" href="http://wba.fr/woopayplug/" target="_blank">Obtenir la version complète et effectuer des paiements réels</a>
                </div>
                <table class="form-table parameters">
                    <?php $this->generate_settings_html(); ?>
                </table>
                <?php
                // load required javascript with parameters
                wp_enqueue_script('payplug-script', plugins_url('assets/js/payplug.js', __FILE__) );
                $js_params = array(
                  'checking' => __( 'Checking PayPlug settings...', 'woocommerce-payplug' ),
                  'error_connecting' => __( 'An error occurred while connecting to PayPlug.', 'woocommerce-payplug' ),
                  'error_login' => __( 'Please check your PayPlug login and password.', 'woocommerce-payplug' ),
                  'error_unknown' => __( 'An error has occurred. Please contact us at http://wba.fr', 'woocommerce-payplug' ),
                  'url' => site_url().'/?payplug=parameters'
                );
                wp_localize_script( 'payplug-script', 'PayPlugJSParams', $js_params );
            }

            /**
             * Start Gateway Settings Form Fields.
             */
            public function init_form_fields() {

                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __( 'Enable/Disable', 'woocommerce-payplug' ),
                        'type' => 'checkbox',
                        'label' => __( 'Enable PayPlug', 'woocommerce-payplug' ),
                        'description' => __( 'NB: This payment gateway can only be enabled if the currency used by the store is the euro.', 'woocommerce-payplug' ),
                        'default' => 'yes'
                    ),
                    'title' => array(
                        'title' => __( 'Title', 'woocommerce-payplug' ),
                        'type' => 'text',
                        'description' => __( 'This field defines the title seen by the user upon payment.', 'woocommerce-payplug' ),
                        'default' => __( 'PayPlug', 'woocommerce-payplug' )
                    ),
                    'description' => array(
                        'title' => __( 'Description', 'woocommerce-payplug' ),
                        'type' => 'textarea',
                        'description' => __( 'This field defines the description seen by the user upon payment.', 'woocommerce-payplug' ),
                        'default' => __( 'Make secure payments using your bank card with PayPlug.', 'woocommerce-payplug' )
                    ),
                    'payplug_login' => array(
                        'title' => __( 'PayPlug login', 'woocommerce-payplug' ),
                        'type' => 'text',
                        'description' => __( 'The email address used to log on to PayPlug.', 'woocommerce-payplug' ),
                        'default' => ''
                    ),
                    'payplug_password' => array(
                        'title' => __( 'PayPlug password', 'woocommerce-payplug' ),
                        'type' => 'password',
                        'description' => __( 'The password used to log on to PayPlug. This information is not saved.', 'woocommerce-payplug' ),
                        'default' => ''
                    ),
                    'payplug_parameters' => array(
                        'title' => '',
                        'type' => 'hidden',
                        'default' => '',
                        'description' => ''
                    )
                );
            }
            /**
             * Get PayPlugArgs
             */
            function get_payplug_url( $order_id ) {
                global $woocommerce;
                $order = new WC_Order( $order_id );
                $order_id = $order->id;

                $params = array(
                        'amount'        => number_format($order->order_total, 2, '.', '')*100,
                        'currency'      => get_woocommerce_currency(),
                        'ipn_url'       => $this->notify_url,
                        'return_url'    => $this->get_return_url($order),
                        'email'         => $order->billing_email,
                        'firstname'     => $order->billing_first_name,
                        'lastname'      => $order->billing_last_name,
                        'order'         => $order->id
                    );
                $url_params = http_build_query($params);
                
                $data = urlencode(base64_encode($url_params));
                $privatekey = openssl_pkey_get_private($this->payplug_privatekey);
                openssl_sign($url_params, $signature, $privatekey, OPENSSL_ALGO_SHA1);
                $signature = urlencode(base64_encode($signature));
                $payplug_url = $this->payplug_url ."?data=".$data."&sign=".$signature;
                $payplug_url = apply_filters( 'woocommerce_payplug_args', $payplug_url );
                return $payplug_url;
            }

            /**
             * Process the payment and return the result.
             */
            public function process_payment( $order_id ) {
                global $woocommerce;
                $payplug_adr = $this->get_payplug_url($order_id);
                return array(
                    'result'    => 'success',
                    'redirect'  => $payplug_adr
                );
            }

            /**
             * Output for the order received page.
             */
            public function receipt_page( $order ) {
                echo $this->generate_payplug_form( $order );
            }

            
            /**
             * Check ipn validation.
             */
            public function check_ipn_request_is_valid($headers, $body) {

                $signature = base64_decode($headers['PAYPLUG-SIGNATURE']);
                
                $publicKey = openssl_pkey_get_public($this->payplug_publickey);
                $isValid = openssl_verify($body , $signature, $publicKey, OPENSSL_ALGO_SHA1);
                if($isValid){
                    return True;
                }
                return False;
            }

            /**
             * Check API Response.
             */
            public function check_ipn_response() {
                $headers = getallheaders();
                $headers = array_change_key_case($headers, CASE_UPPER);
                $body = file_get_contents('php://input');
                $data = json_decode($body, true);
                
                @ob_clean();

                if (!empty($headers) && !empty($body) && $this->check_ipn_request_is_valid($headers, $body)){
                    header( 'HTTP/1.1 200 OK' );
                    do_action( "valid_payplug_ipn_request", $data);
                } else {
                    wp_die( "PayPlug IPN Request Failure" );
                }
            }


            /**
             * Successful Payment.
             */
            public function successful_request( $posted ) {
                global $woocommerce;

                $order_id = $posted['order'];
                $posted_status = $posted['state'];

                $order = new WC_Order($order_id);


                if($posted_status == 'paid'){

                    // Check order not already completed
                    if ($order->status == 'completed'){
                        exit;
                    }
                    // Payment completed
                    // Reduce stock levels
                    $order->reduce_order_stock();
                    $order->add_order_note(__('Payment successfully completed', 'woocommerce'));
                    $order->payment_complete();
                    exit;
                }

                if($posted_status == 'refunded'){
                    $order->update_status( 'refunded', __( 'Payment refunded via IPN by PayPlug', 'woocommerce-payplug' ) );
                    $mailer = $woocommerce->mailer();
                    $message = $mailer->wrap_message(
                        __( 'Order refunded', 'woocommerce' ),
                        sprintf( __( 'The order %s has been refunded via IPN by PayPlug', 'woocommerce-payplug' ), $order->get_order_number())
                    );
                    $mailer->send( get_option( 'admin_email' ), sprintf( __( 'Payment for order %s has been refunded', 'woocommerce' ), $order->get_order_number() ), $message );
                    exit;
                }
                
            }
        }
    }


    /*
    *
    * Get PayPlug parameters from login and password
    *
    */
    function payplug_parse_request($wp) {
        if (array_key_exists('payplug', $wp->query_vars) && $wp->query_vars['payplug'] == 'parameters') {
            $url = 'https://www.payplug.fr/portal/test/ecommerce/autoconfig';
            $process = curl_init($url);
            curl_setopt($process, CURLOPT_USERPWD, $_POST["login"].':'.stripslashes($_POST["password"]));
            curl_setopt($process, CURLOPT_RETURNTRANSFER, true);
            @curl_setopt($process, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
            $answer = curl_exec($process);
            $errorCurl = curl_errno($process);
            curl_close($process);
            if($errorCurl == 0){
                $jsonAnswer = json_decode($answer);
                $authorizationSuccess = false;
                if($jsonAnswer->status == 200){
                    die($answer);
                }else{
                    die("errorlogin");
                }
            }else{
                die("errorconnexion");
            }
        }
    }
    add_action('parse_request', 'payplug_parse_request');

    function payplug_query_vars($vars) {
        $vars[] = 'payplug';
        return $vars;
    }
    add_filter('query_vars', 'payplug_query_vars');
}
