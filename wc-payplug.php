<?php
/**
 * Plugin Name: WooCommerce PayPlug
 * Plugin URI: https://www.payplug.fr/
 * Description: WooCommerce PayPlug is a PayPlug payment gateway for WooCommerce
 * Author: Boris Colombier
 * Author URI: http://wba.fr
 * Version: 1.4.2
 * License: GPLv2 or later
 * Text Domain: wcpayplug
 * Domain Path: /languages/
 */

/**
 * WooCommerce fallback notice.
 */
function wcpayplug_woocommerce_fallback_notice() {
    $html = '<div class="error">';
        $html .= '<p>' . __( 'WooCommerce PayPlug Gateway depends on the last version of <a href="http://wordpress.org/extend/plugins/woocommerce/" target="_blank">WooCommerce</a> to work!', 'wcpayplug' ) . '</p>';
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
    /**
     * Load textdomain.
     */
    load_plugin_textdomain( 'wcpayplug', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

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
            $this->set_completed        = $this->settings['set_completed'];
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

        public function admin_options() {
            ?>
            <style>
            #wc_get_started.payplug{
                padding: 10px;
                padding-left: 230px;
                background-image: url(<?php echo plugins_url( 'images/payplug-logo-large.png' , __FILE__ )?>);
                background-position: 20px 40%;
                background-repeat: no-repeat;
                background-color: white;
                margin-top: 10px;
                border-radius: 5px;
                margin-bottom: 15px;
            }
            .curl_missing{
                border: solid 2px #f00;
                color: #f00;
                padding: 5px 15px;
                border-radius: 5px;
            }
            .wp-core-ui .button{
                width: 210px;
                text-align: center;
                margin-right: 4px;
            }
            .bt_hld{
                display: inline-block;
                vertical-align: top;
            }
            .rmt_infos, .rmt_infos a{
                display: inline-block;
                vertical-align: top;
                margin: 0;
                color: #a46497;
                text-decoration: none;
            }
            .rmt_infos a:hover{
                text-decoration: underline;
            }
            </style>
            <div id="wc_get_started" class="payplug">
                <span>Intégrez le paiement en ligne par carte bancaire sur votre site en 1 minute.</span>
                <br/><b>Sans frais fixes ni mensuels.</b> Votre client n'a pas besoin de compte pour payer.
                <br>
                <div class="bt_hld">
                    <p>
                        <a href="http://url.wba.fr/payplug" target="_blank" class="button button-primary">Créer un compte gratuitement</a>
                        <a href="https://www.payplug.fr/" target="_blank" class="button">En savoir plus sur PayPlug</a>
                    </p>
                    <p>
                        <a href="http://wba.fr/woocommerce-payplug/#referencement" target="_blank" class="button">Référencer mon site</a>
                        <a href="https://wba.fr/woocommerce-payplug/support/" target="_blank" class="button">Obtenir de l'aide</a>
                    </p>
                </div>
                <br>
                <div class="rmt_infos">
                    <a href="https://www.woosuperemails.com/fr/?utm_source=wc_p_plugin&utm_medium=wp&utm_campaign=wc_p" target="_blank">
                        Vous souhaitez améliorer vos ventes facilement avec WooCommerce ?
                    </a>
                </div>
            </div>
            <?php
            if(!function_exists('curl_version')){
            ?>
            <div class="curl_missing">
            Votre serveur n'intègre pas CURL pour PHP.<br>
            Pour configurer le plugin, vous devez vous connecter sur cette <a href="https://www.payplug.fr/portal/ecommerce/autoconfig" target="_blank">page Payplug</a> en utilisant vos identifiant et mot de passe.<br>
            Copiez tout le code s'affichant sur la page et copiez le dans le dernier champ 'Configuration PayPlug' en bas du formulaire en laissant les champs identifiant et mot de passe vides puis validez.<br>
            Faites ensuite un test en passant une commande pour vous assurez que le plugin est bien fonctionnel.
            </div>
            <?php 
            }
            ?>
            <table class="form-table parameters">
                <?php $this->generate_settings_html(); ?>
            </table>
            <?php
            $js_code = '
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
                if(jQuery("input[name=\'woocommerce_payplug_payplug_login\']").val()=="" && jQuery("input[name=\'woocommerce_payplug_payplug_password\']").val()=="" && jQuery("textarea[name=\'woocommerce_payplug_payplug_parameters\']").val()!=""){
                    alert("Si vous n\'utilisez pas vos identifiant et mot de passe Payplug, vous devez  passer une commande pour tester le bon fonctionnement de Payplug.");
                    try{
                        jQuery.parseJSON(jQuery("textarea[name=\'woocommerce_payplug_payplug_parameters\']").val());
                        jQuery("textarea[name=\'woocommerce_payplug_payplug_parameters\']").val(encodeURI(jQuery("textarea[name=\'woocommerce_payplug_payplug_parameters\']").val()));
                    }
                    catch(err){
                    }
                    jQuery("#mainform").submit();
                }else{
                    console.log(jQuery("input[name=\'woocommerce_payplug_test_mode\']").is(\':checked\'));
                    console.log(typeof jQuery("input[name=\'woocommerce_payplug_test_mode\']").is(\':checked\'));
                    jQuery.post( "'.site_url().'/?payplug=parameters", { login: jQuery("input[name=\'woocommerce_payplug_payplug_login\']").val(), password: jQuery("input[name=\'woocommerce_payplug_payplug_password\']").val(), test_mode: jQuery("input[name=\'woocommerce_payplug_test_mode\']").is(\':checked\')})
                    .done(function(data) {
                        if(data == "errorconnexion"){
                            jQuery(window).unblock();
                            alert("'.__( 'An error occurred during connection to PayPlug.', 'wcpayplug' ).'");
                        }else if(data == "errorlogin"){
                            jQuery(window).unblock();
                            alert("'.__( 'Please check your PayPlug login and password.', 'wcpayplug' ).'");
                        }else{
                            try{
                                jQuery.parseJSON(data);
                                jQuery("textarea[name=\'woocommerce_payplug_payplug_parameters\']").val(encodeURI(data));
                                jQuery("input[name=\'woocommerce_payplug_payplug_password\']").val(\'\');
                                jQuery("#mainform").submit();
                            }
                            catch(err){
                                jQuery(window).unblock();
                                alert("'.__( 'An error occurred during connection to PayPlug.', 'wcpayplug' ).'");
                            }
                        }
                    })
                    .fail(function() {
                        jQuery(window).unblock();
                        alert("Une erreur est survenue. Merci de nous contacter sur http://wba.fr");
                    });
                }
            })
            ';
            if(version_compare(WOOCOMMERCE_VERSION, '2.1', '>=')){
                wc_enqueue_js($js_code);
            }else{
                global $woocommerce;
                $woocommerce->add_inline_js($js_code);
            }
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
                    'description' => __( 'The password used to create your account on payplug. This value is not stored.', 'wcpayplug' ),
                    'default' => ''
                ),
                'set_completed' => array(
                    'title' => __( 'Set order completed', 'wcpayplug' ),
                    'type' => 'checkbox',
                    'label' => __("Set order as 'completed' after PayPlug payment instead of 'processing'", 'wcpayplug'),
                    'default' => 'no',
                ),
                'test_mode' => array(
                    'title' => __( 'Mode test', 'wcpayplug' ),
                    'type' => 'checkbox',
                    'label' => __( 'Utiliser PayPlug en TEST (Sandbox) Mode', 'wcpayplug' ),
                    'default' => 'no',
                    'description' => __('Utilisez le mode test pour tester les transactions sans faire de paiement réel', 'wcpayplug'),
                ),
                'payplug_parameters' => array(
                    'title' => __('PayPlug configuration', 'wcpayplug'),
                    'type' => 'textarea',
                    'default' => '',
                    'description' => __("Ce champ ne doit pas être utilisé sauf indication contraire en haut du formulaire. Il sera rempli automatiquement si vous n'avez pas d'erreur à l'enregistrement après avoir indiqué vos identifiant et mot de passe Payplug.<br>En cas de problème, utilisez le bouton 'Obtenir de l'aide' en haut de la page.", 'wcpayplug'),
                    'class' => 'bipbip',
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
         * Successful Payment!
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

                if($this->set_completed == 'yes'){
                    $order->update_status('completed');
                }
                // Payment completed
                // Reduce stock levels
                $order->reduce_order_stock();
                $order->add_order_note(__('Payment is successfully completed.', 'woocommerce'));
                $order->payment_complete();
                exit;
            }

            if($posted_status == 'refunded'){
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
    }
}


/*
*
* Get PayPlug parameters from login and password
*
*/
function payplug_parse_request($wp) {
    // only process requests with "my-plugin=ajax-handler"
    if (array_key_exists('payplug', $wp->query_vars) && $wp->query_vars['payplug'] == 'parameters') {
        if ( $_POST["test_mode"] == 'true' ) {
            $url = 'https://www.payplug.fr/portal/test/ecommerce/autoconfig';
        } else {
            $url = 'https://www.payplug.fr/portal/ecommerce/autoconfig';
        }
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
