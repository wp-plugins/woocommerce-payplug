<?php
/**
 * Plugin Name: WooCommerce PayPlug
 * Plugin URI: https://www.payplug.fr/
 * Description: WooCommerce PayPlug is a PayPlug payment gateway for WooCommerce
 * Author: Boris Colombier
 * Author URI: http://wba.fr
 * Version: 1.0.0
 * License: GPLv2 or later
 * Text Domain: wcpayplug
 * Domain Path: /languages/
 */

/**
 * WooCommerce fallback notice.
 */
function wcpayplug_woocommerce_fallback_notice() {
    $html = '<div class="error">';
        $html .= '<p>' . __( 'WooCommerce PayPlug Gateway depends on the last version of <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> to work!', 'wcpayplug' ) . '</p>';
    $html .= '</div>';

    echo $html;
}

//define getallheaders function if not available
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

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', 'wcpayplug_woocommerce_fallback_notice' );

        return;
    }

    /**
     * Load textdomain.
     */
    load_plugin_textdomain( 'wcpayplug', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    /**
     * Add the gateway to WooCommerce.
     */
    add_filter( 'woocommerce_payment_gateways', 'wcpayplug_add_gateway' );

    function wcpayplug_add_gateway( $methods ) {
        $methods[] = 'WC_Gateway_Payplug';
        return $methods;
    }

    /**
     * WC PayPlug Gateway Class.
     */
    class WC_Gateway_Payplug extends WC_Payment_Gateway {

        /**
         * Gateway's Constructor.
         */
        public function __construct() {
            global $woocommerce;

            $this->id                  = 'payplug';
            $this->icon                = plugins_url( 'images/payplug.png', __FILE__ );
            $this->has_fields          = false;
            $this->method_title        = __( 'PayPlug', 'wcpayplug' );

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
            $this->parameters           = json_decode($this->settings['payplug_parameters'], true);

            
            //$this->log->add( 'payplug', 'on est la');
            $this->payplug_privatekey   = $this->parameters['yourPrivateKey'];
            $this->payplug_url          = $this->parameters['url'];
            $this->payplug_publickey    = $this->parameters['payplugPublicKey'];
            
            $this->debug                = $this->settings['debug'];


            // Actions.
            add_action( 'woocommerce_api_wc_gateway_payplug', array($this, 'check_ipn_response'));
            add_action( 'valid_payplug_ipn_request', array( &$this, 'successful_request' ) );
            add_action( 'woocommerce_receipt_payplug', array( &$this, 'receipt_page' ) );

            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
            } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }

            // Valid for use.
            $this->enabled = ( 'yes' == $this->settings['enabled'] ) && ! empty( $this->parameters ) && $this->is_valid_for_use();

            // Checking if payplug_login and payplug_password is not empty.
            if ( empty( $this->payplug_login ) || empty( $this->payplug_password ) ) {
                add_action( 'admin_notices', array( &$this, 'parameters_missing_message' ) );
            }

            // Active logs.
            if ( 'yes' == $this->debug ) {
                $this->log = $woocommerce->logger();
            }
        }

        /**
         * Checking if this gateway is enabled.
         */
        public function is_valid_for_use() {

            if ( ! in_array( get_woocommerce_currency(), array('EUR') ) ) {
                return false;
            }
            return true;
        }


        /**
         * Admin Panel Options
         */
        public function admin_options() {

            ?>
            <?php if ( empty( $this->parameters ) ) : ?>
            <style>
            #wc_get_started.payplug{
                padding-left: 230px;
                background-image: url(<?php echo plugins_url( 'images/payplug-logo-large.png' , __FILE__ )?>);
                background-position: 20px 40%
            }
            </style>
            <div id="wc_get_started" class="payplug">
                <span class="main">Débuter avec PayPlug</span>
                <span>Intégrez le paiement en ligne sur votre site en 1 minute.</span>
                <p>
                    <b>2,5% par transaction</b>, sans frais fixes ou mensuels.<br/>Votre client n'a pas besoin de compte pour payer.
                </p>
                <p><a href="http://url.wba.fr/payplug" target="_blank" class="button button-primary">Créer un compte gratuitement</a>
                    <a href="https://www.payplug.fr/" target="_blank" class="button">En savoir plus sur PayPlug</a>
                </p>
            </div>
            <?php endif;?>
            <h3><?php _e( 'PayPlug', 'wcpayplug' ); ?></h3>
            <p><?php _e( 'PayPlug works by sending the user to PayPlug to enter their payment information.', 'wcpayplug' ); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table> <!-- /.form-table -->
            <?php
            global $woocommerce;
            $woocommerce->add_inline_js('
                jQuery(\'input[type="submit"]\').click(function(e){
                e.preventDefault();
                jQuery(window).block({
                    message: "' . esc_js( __( 'Checking PayPlug parameters...', 'wcpayplug' ) ) . '",
                    baseZ: 99999,
                    overlayCSS:
                    {
                        background: "#fff",
                        opacity: 0.6
                    },
                    css: {
                        padding:        "20px",
                        zindex:         "9999999",
                        textAlign:      "center",
                        color:          "#555",
                        border:         "3px solid #aaa",
                        backgroundColor:"#fff",
                        cursor:         "wait",
                        lineHeight:     "24px",
                        fontWeight:     "bold",
                        position:       "fixed"
                    }
                });
                $.post( "'.get_home_url().'/?payplug=parameters", { login: jQuery("input[name=\'woocommerce_payplug_payplug_login\']").val(), password: jQuery("input[name=\'woocommerce_payplug_payplug_password\']").val() })
                    .done(function( data ) {
                        if(data == "errorconnexion"){
                            jQuery(window).unblock();
                            alert("'.__( 'An error occurred during connection to PayPlug.', 'wcpayplug' ).'");
                        }else if(data == "errorlogin"){
                            jQuery(window).unblock();
                            alert("'.__( 'Please check your PayPlug login and password.', 'wcpayplug' ).'");
                        }else{
                            jQuery("input[name=\'woocommerce_payplug_payplug_parameters\']").val(data);
                            jQuery("#mainform").submit();
                        }
                });
            })
            ');
        }

        /**
         * Start Gateway Settings Form Fields.
         */
        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'wcpayplug' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable PayPlug', 'wcpayplug' ),
                    'description' => __( 'NB : This gateway is disabled if the currency is not in euros.', 'wcpayplug' ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __( 'Title', 'wcpayplug' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'wcpayplug' ),
                    'default' => __( 'PayPlug', 'wcpayplug' )
                ),
                'description' => array(
                    'title' => __( 'Description', 'wcpayplug' ),
                    'type' => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'wcpayplug' ),
                    'default' => __( 'Pay safely via PayPlug with your credit card', 'wcpayplug' )
                ),
                'payplug_login' => array(
                    'title' => __( 'PayPlug account login', 'wcpayplug' ),
                    'type' => 'text',
                    'description' => __( 'The email used to create your account on payplug.', 'wcpayplug' ),
                    'default' => ''
                ),
                'payplug_password' => array(
                    'title' => __( 'PayPlug account password', 'wcpayplug' ),
                    'type' => 'password',
                    'description' => __( 'The password used to create your account on payplug.', 'wcpayplug' ),
                    'default' => ''
                ),
                'payplug_parameters' => array(
                    'type' => 'hidden',
                    'default' => ''
                ),
                'testing' => array(
                    'title' => __( 'Gateway Testing', 'wcpayplug' ),
                    'type' => 'title',
                    'description' => '',
                ),
                'debug' => array(
                    'title' => __( 'Debug Log', 'wcpayplug' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable logging', 'wcpayplug' ),
                    'default' => 'no',
                    'description' => __( 'Log PayPlug events inside <code>woocommerce/logs/payplug.txt</code>', 'wcpayplug'  ),
                )
            );
        }
        /**
         * Get PayPlugArgs for passing
         */
        function get_payplug_url( $order_id ) {
            global $woocommerce;
            $order = new WC_Order( $order_id );
            $order_id = $order->id;

            if ( 'yes' == $this->debug )
                $this->log->add( 'payplug', 'Generating payment form for order ' . $order->get_order_number() . '. Notify URL: ' . $this->notify_url );

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
            if ( 'yes' == $this->debug )
                $this->log->add( 'payplug', 'process_payment function');
            global $woocommerce;
            
            $payplug_adr = $this->get_payplug_url( $order_id );
            return array(
                'result'    => 'success',
                'redirect'  => $payplug_adr
            );
        }

        /**
         * Output for the order received page.
         */
        public function receipt_page( $order ) {
            if ( 'yes' == $this->debug )
                $this->log->add( 'payplug', 'receipt_page function');
            echo $this->generate_payplug_form( $order );
        }

        
        /**
         * Check ipn validation.
         */
        public function check_ipn_request_is_valid($headers, $body) {
            if ( 'yes' == $this->debug )
                $this->log->add( 'payplug', 'check_ipn_request_is_valid function');

            $signature = base64_decode($headers['PAYPLUG-SIGNATURE']);
            
            $publicKey = openssl_pkey_get_public($this->payplug_publickey);
            $isValid = openssl_verify($body , $signature, $publicKey, OPENSSL_ALGO_SHA1);
            if($isValid){
                if ( 'yes' == $this->debug )
                    $this->log->add( 'payplug', 'check_ipn_request_is_valid YES isValid');
                return True;
            }
        }

        /**
         * Check API Response.
         */
        public function check_ipn_response() {
            $headers = getallheaders();
            $headers = array_change_key_case($headers, CASE_UPPER);
            $body = file_get_contents('php://input');
            $data = json_decode($body, true);
            if ( 'yes' == $this->debug ){
                $this->log->add( 'payplug', 'check_ipn_response : '.print_r($headers, true));
                $this->log->add( 'payplug', 'check_ipn_response body : '.print_r($body, true));
            }
            
            @ob_clean();

            if (!empty($headers) && !empty($body) && $this->check_ipn_request_is_valid($headers, $body)){
                if ( 'yes' == $this->debug )
                    $this->log->add( 'payplug', 'check_ipn_response YES check_ipn_request_is_valid');
                header( 'HTTP/1.1 200 OK' );

                do_action( "valid_payplug_ipn_request", $data);

            } else {

                wp_die( "PayPlug IPN Request Failure" );

            }
        }


        /**
         * Successful Payment!
         */
        public function successful_request( $posted ) {
            if ( 'yes' == $this->debug )
                $this->log->add( 'payplug', 'check_ipn_response : '.print_r($posted, true));
            
            global $woocommerce;

            $order_id = $posted['order'];
            $posted_status = $posted['status'];
            if ( 'yes' == $this->debug )
                $this->log->add( 'payplug', 'successful_request retour status : '.$posted_status);

            $order = new WC_Order($order_id);


            if($posted_status == '0'){
                if ( 'yes' == $this->debug )
                    $this->log->add( 'payplug', 'successful_request on passe en completed');
                // Check order not already completed
                if ($order->status == 'completed'){
                    exit;
                }

                // Payment completed
                // Reduce stock levels
                $order->reduce_order_stock();
                $order->add_order_note(__('Payment is successfully completed.', 'woocommerce'));
                $order->payment_complete();
                exit;
            }

            if($posted_status == '4'){
                if ( 'yes' == $this->debug )
                    $this->log->add( 'payplug', 'successful_request on passe en refund');
                // Payment refunded
                
                $order->update_status( 'refunded', sprintf( __( 'Payment %s refunded via IPN by PayPlug.', 'wcpayplug' ), strtolower( $posted['payment_status'] ) ) );
                $mailer = $woocommerce->mailer();
                $message = $mailer->wrap_message(
                    __( 'Order refunded/reversed', 'woocommerce' ),
                    sprintf( __( 'Order %s has been marked as refunded via IPN by PayPlug', 'wcpayplug' ), $order->get_order_number())
                );
                $mailer->send( get_option( 'admin_email' ), sprintf( __( 'Payment for order %s refunded/reversed', 'woocommerce' ), $order->get_order_number() ), $message );
                exit;
            }
            
        }

        /**
         * Adds error message when not configured the parameters.
         */
        public function parameters_missing_message() {
            $html = '<div class="error">';
                $html .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should inform your parameters in PayPlug. %sClick here to configure!%s', 'wcpayplug' ), '<a href="' . get_admin_url() . 'admin.php?page=woocommerce_settings&amp;tab=payment_gateways&amp;section=WC_Gateway_Payplug">', '</a>' ) . '</p>';
            $html .= '</div>';

            echo $html;
        }


    } // class WC_Gateway_Payplug.
} // function wcpayplug_gateway_load.


/*
*
* Get PayPlug parameters from login and password
*
*/
function payplug_parse_request($wp) {
    // only process requests with "my-plugin=ajax-handler"
    if (array_key_exists('payplug', $wp->query_vars) && $wp->query_vars['payplug'] == 'parameters') {
        //die(print_r($_POST));
        
        $process = curl_init('https://www.payplug.fr/portal/ecommerce/autoconfig');
        curl_setopt($process, CURLOPT_USERPWD, sanitize_text_field($_POST["login"]).':'.sanitize_text_field($_POST["password"]));
        curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($process, CURLOPT_SSLVERSION, 3);
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
