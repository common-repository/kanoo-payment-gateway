<?php
/**
 * Plugin Name: Kanoo Payment Gateway
 * Plugin URI: https://wordpress.org/plugins/kanoo-payment-gateway/
 * Description: Accept digital payments on your store using Kanoo. Visa, MasterCard, American Express, Discover, Kanoo Cash Card and Sand Dollar. <a href="https://kanoopays.com">https://kanoopays.com</a>.
 * Author: CaribPay (Bahamas) Ltd.
 * Author URI: https://www.kanoopays.com/
 * Version: 1.0.2
 * Requires at least: 4.4
 * Tested up to: 6.6
 * WC requires at least: 3.0
 * WC tested up to: 6.6
 * Text Domain: kanoo-payment-gateway
 * Domain Path: /languages
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WOOCOMMERCE_GATEWAY_KANOO_VERSION', '1.1.0');
define( 'WOOCOMMERCE_GATEWAY_KANOO_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WOOCOMMERCE_GATEWAY_KANOO_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

add_filter('woocommerce_payment_gateways', 'kanoo_add_gateway_class');
add_action('plugins_loaded', 'kanoo_init_gateway_class');

function kanoo_add_gateway_class($gateways)
{
    $gateways[] = 'Kanoo_Payment_Gateway';
    return $gateways;
}

require_once dirname( __FILE__ ) . '/includes/class-wc-kanoo-response.php';

add_action( 'woocommerce_blocks_loaded', 'woocommerce_kanoo_blocks_support');

/**
 * Support Cart and Checkout blocks from WooCommerce Blocks.
 */
function woocommerce_kanoo_blocks_support() {
    if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        require_once dirname( __FILE__ ) . '/includes/class-wc-kanoo-blocks-support.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                $container = Automattic\WooCommerce\Blocks\Package::container();
                $container->register(
                    WC_Kanoo_Blocks_Support::class,
                    function() {
                        return new WC_Kanoo_Blocks_Support();
                        
                    }
                );
                $payment_method_registry->register( $container->get( WC_Kanoo_Blocks_Support::class ) );
                
            }
        );
    }
}

function kanoo_init_gateway_class()
{
    class Kanoo_Payment_Gateway extends WC_Payment_Gateway
    {
        private $environment;
        public $authentication_key;
        public $user_id;
        private $kanoo_api;
        private $url_payment;
        public $url_check_payment;

        public function __construct()
        {
            $this->id = 'kanoo';
            $this->icon = plugin_dir_url(__FILE__).'/assets/images/kanoo.png';
            $this->has_fields = false;
            $this->method_title = 'Kanoo';
            $this->method_description = 'Same as KanooPOS (Accept digital payments)';
            $this->supports = array(
                'products',
            );

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->environment = $this->get_option('environment');
            $this->authentication_key = $this->get_option('authentication_key');
            $this->user_id = $this->get_option('user_id');

            $this->kanoo_api = 'https://api.kanoopays.com/visipay/api/external/payment/request/token';
            $this->url_payment = 'https://external.kanoopays.com/pay/login/';
            $this->url_check_payment = 'https://api.kanoopays.com/visipay/api/external/payment/transactions';

            $this->init();
            $this->kanoo_init_form_fields();
            $this->init_settings();

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_response'));
        }

        /**
         *  Function initialization
         */
        public function init() {
            require_once dirname( __FILE__ ) . '/includes/class-wc-kanoo-exception.php';

            // add style
            wp_enqueue_style( 'style-kanoo', plugin_dir_url(__FILE__) . 'assets/css/style.css');

            if ($this->environment === 'sandbox') {
                $this->kanoo_api = 'https://demo.api-gateway.kardinc.com/visipay/api/external/payment/request/token';
                $this->url_payment = 'https://demo.kanoo.pay.kardinc.com/pay/login/';
                $this->url_check_payment = 'https://demo.api-gateway.kardinc.com/visipay/api/external/payment/transactions';
            }
        }

        /**
         * Displays the admin settings environment description.
         *
         * @return string
         */
        public function display_admin_settings_environment_description() {
            return sprintf( __( ' This setting specifies whether you will process Production or Sandbox transactions. These environments require a separate API Key and User Id. <br>
            Register and get your <strong style="background-color:#ddd;">&nbsp; Production credential &nbsp;</strong> <a href="http://arm.kanoopays.com/"> here </a> at Kanoo ARM Production.  
            <strong style="background-color:#ddd;">&nbsp; Sandbox credential &nbsp;</strong> <a href="https://demo.kanoo.arm.kardinc.com/"> here </a> at Kanoo ARM Sandbox. ' ));
        }

        /**
         * Function load field in admin setting payment
         */
        public function kanoo_init_form_fields()
        {
            $environment_desc = $this->display_admin_settings_environment_description();

            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Enable Kanoo Payment Gateway',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'Pay with Kanoo',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Accept digital payments with Kanoo. Accept Visa, MasterCard, American Express, Discover, Kanoo Cash Card and the Sand Dollar.',
                ),
                'environment' => array(
                    'title'       => __( 'Environment'),
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'description' => $environment_desc,
                    'default'     => 'production',
                    'options'     => array(
                        'production '    => __( 'Production '),
                        'sandbox' => __( 'Sandbox'),
                    ),
                ),
                'authentication_key' => array(
                    'title' => 'Authentication Key',
                    'type' => 'text',
                    'default' => ''
                ),
                'user_id' => array(
                    'title' => 'User Id',
                    'type' => 'text',
                    'default' => ''
                ),
            );
        }

        /**
         *  Process Payment
         *
         * @param int $order_id
         * @return array|string[]|void
         */
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            try {
                global $woocommerce;
                
                $redirect_url = wc_get_endpoint_url('view-order', $order->get_id(), wc_get_page_permalink('myaccount'));
                $callback_url = rest_url('/kanoopays/v1/callback');

                // array data
                $data = array(
                    'authenticationKey' =>  $this->authentication_key,
                    'userId'            =>  $this->user_id,
                    'orderId'           =>  $order->get_id(),
                    'currency'          =>  get_woocommerce_currency(),
                    'amount'            =>  $order->get_total(),
                    'redirectUrl'       =>  esc_url($redirect_url),
                    'callbackUrl'       =>  esc_url($callback_url)
                );

                $header = array(
                    'X-TENANT' => 'kanoo'
                );

                $args = array(
                    'body'        => $data,
                    'timeout'     => '5',
                    'redirection' => '5',
                    'httpversion' => '1.0',
                    'blocking'    => true,
                    'headers'     => $header,
                    'cookies'     => array(),
                );

                $response = wp_remote_retrieve_body(wp_remote_post($this->kanoo_api, $args));
                $response = json_decode($response, true);

                $message_err = 'Connection issue: Cannot create the connection to KanooPays. Please contact customer support.';

                if (!$this->validate_get_access_token($response)) {
                    wc_add_notice($message_err, 'error');
                    return;
                }
                $params = $this->handle_get_access_token($response);

                if ($params['statusCode'] === 'SUCCESS') {
                    $token = $params['result'];

                    $order->add_order_note('The order is being processed by Kanoopays.', true);
                    $woocommerce->cart->empty_cart();
                } else {
                    wc_add_notice($message_err, 'error');
                    return;
                }

                $url_redirect = esc_url($this->url_payment.$token);

                return array(
                    'result' => 'success',
                    'redirect' => $url_redirect
                );
            } catch (WC_Kanoo_Exception $e) {
                wc_add_notice( $e->getLocalizedMessage(), 'error' );

                do_action( 'wc_gateway_kanoo_process_payment_error', $e, $order );

                /* translators: error message */
                $order->update_status( 'failed' );

                return array(
                    'result'   => 'fail',
                    'redirect' => '',
                );
            }
        }

        /**
         * Validate data when get access token
         *
         * @param $post
         * @return bool
         */
        function validate_get_access_token($post) {
            $error_flg = true;

            if (empty($post['statusCode'])) {
                $error_flg = false;
            }

            if (empty($post['result'])) {
                $error_flg = false;
            }

            return $error_flg;
        }

        /**
         * Sanitize data when get access token
         *
         * @param $post
         * @return array
         */
        function handle_get_access_token($post) {
            $params = array();

            $params['statusCode'] = sanitize_text_field($post['statusCode']);
            $params['result'] = sanitize_text_field($post['result']);

            return $params;
        }

        /**
         *  Check params in response method POST
         *
         * @param $post
         * @return bool
         */
        function validate_post_data($post) {
            $error_flg = true;

            if (empty($post['orderId'])) {
                $error_flg = false;
            }

            if (empty($post['merchantId'])) {
                $error_flg = false;
            }

            if (empty($post['accessToken'])) {
                $error_flg = false;
            }

            if (empty($post['transactionId'])) {
                $error_flg = false;
            }

            if (empty($post['status'])) {
                $error_flg = false;
            }

            return $error_flg;
        }

        /**
         * Handle request
         *
         * @param $post
         * @return array
         */
        function handle_request($post) {
            $params = array();

            $params['orderId'] = sanitize_text_field($post['orderId']);
            $params['merchantId'] = sanitize_text_field($post['merchantId']);
            $params['accessToken'] = sanitize_text_field($post['accessToken']);
            $params['transactionId'] = sanitize_text_field($post['transactionId']);
            $params['status'] = sanitize_text_field($post['status']);
            $params['errorCode'] = sanitize_text_field($post['errorCode']);

            return $params;
        }

        /**
         * Validate check payment response
         *
         * @param $data
         * @return bool
         */
        function validate_check_payment_response($data) {
            $error_flg = true;

            if (empty($data->statusCode)) {
                $error_flg = false;
            }

            if (empty($data->result)) {
                $error_flg = false;
            } else {
                $result = $data->result;

                if (empty($result->orderId)) {
                    $error_flg = false;
                }

                if (empty($result->processingStatus)) {
                    $error_flg = false;
                }
            }

            return $error_flg;
        }

        /**
         * Handle check payment response
         *
         * @param $data
         * @return array
         */
        function handle_check_payment_response($data) {
            $params = array();

            $params['statusCode'] = sanitize_text_field($data->statusCode);

            $result = $data->result;

            $params['result']['orderId'] = sanitize_text_field($result->orderId);
            $params['result']['processingStatus'] = sanitize_text_field($result->processingStatus);

            return $params;
        }
    }
}