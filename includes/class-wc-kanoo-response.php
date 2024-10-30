<?php
/**
 * Class-wc-kanoo-response file.
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adding Custom Endpoints
 *
 * url: http://abc.com/wp-json/kanoopays/v1/callback
 */
add_action( 'rest_api_init', 'kanoo_add_receive_callback' );
function kanoo_add_receive_callback () {
    register_rest_route(
        'kanoopays/v1',
        '/callback/',
        array(array(
            'methods' => 'POST',
            'callback' => 'callback_kanoo',
            'permission_callback' => '__return_true',
        )) );
}

/**
 * Handles Responses.
 */
function callback_kanoo() {

    if($_SERVER['REQUEST_METHOD'] === 'POST') {

        if(is_array($_POST))
        {
            $kanoo = new Kanoo_Payment_Gateway();
            if (!$kanoo->validate_post_data($_POST)) {
                return;
            }

            $params = $kanoo->handle_request($_POST);

            if ($params['status'] !== 'SUCCESS') {
                return;
            }

            $auth_key = $kanoo->authentication_key;
            $merchant_id = $params['merchantId'];
            $transaction_id = $params['transactionId'];

            $url_get_transaction = $kanoo->url_check_payment."/id?authenticationKey=$auth_key&merchantId=$merchant_id&transactionId=$transaction_id";

            $header = array(
                'X-TENANT' => 'kanoo'
            );
            
            $args = array(
                'headers' => $header,
            );

            $response = wp_remote_get($url_get_transaction, $args);
            $response_body = wp_remote_retrieve_body( $response );
            $data = json_decode( $response_body );

            if (!$kanoo->validate_check_payment_response($data)) {
                return;
            }

            $params_response = $kanoo->handle_check_payment_response($data);

            // Check statusCode is SUCCESS
            if ($params_response['statusCode'] !== 'SUCCESS') {
                return;
            }

            // Check order payment success
            if ($params_response['result']['orderId'] === $params['orderId']
                && $params_response['result']['processingStatus'] === 'SUCCESS') {
                $order = wc_get_order($params['orderId']);
                $order->payment_complete();
                $order->add_order_note( 'Order paid via Kanoo. Transaction ID: ' . $params['transactionId'], true);
            }
        }
    }
}