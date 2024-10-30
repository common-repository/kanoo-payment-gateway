<?php
/**
 * Eway Support for Cart and Checkout blocks.
 *
 * @package WooCommerce Eway Payment Gateway
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Eway payment method integration
 *
 * @since 3.2.0
 */
final class WC_Kanoo_Blocks_Support extends AbstractPaymentMethodType {
    /**
     * Name of the payment method.
     *
     * @var string
     */
    protected $name = 'kanoo';

    private function _get_gateway_class(): Kanoo_Payment_Gateway {
        $registered_gateways = WC()->payment_gateways()->payment_gateways();
        return $registered_gateways['kanoo'];
    }

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option( 'woocommerce_kanoo_settings', array() );
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        return $this->_get_gateway_class()->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        $script_file_path  = '/assets/js/kanoo-payment-blocks.js';
        $asset_data = wc_kanoo_get_asset_data( $script_file_path );
        wp_enqueue_style(
             'wc-stripe-blocks-checkout-style',
             WOOCOMMERCE_GATEWAY_KANOO_URL . '/assets/css/style.css',
             [],
             $asset_data['version']
        );

        wp_register_script(
        // wp_enqueue_script(
            'wc-kanoo-blocks-integration',
            WOOCOMMERCE_GATEWAY_KANOO_URL . $script_file_path,
            $asset_data['dependencies'],
            $asset_data['version'],
            true
        );

        return array( 'wc-kanoo-blocks-integration' );
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        $payment_method_data = array(
            'title'                  => $this->get_setting( 'title' ),
            'description'            => $this->get_setting( 'description' ),
            'supports'               => $this->get_supported_features(),
            'icon'                   => $this->_get_gateway_class()->icon,
        );

        return $payment_method_data;
    }

    /**
     * Returns an array of supported features.
     *
     * @return string[]
     */
    public function get_supported_features() {
        return $this->_get_gateway_class()->supports;
    }
}

/**
 * This function should return assets data.
 *
 * @since x.x.x
 *
 * @param string $asset_path Path to asset file.
 */
function wc_kanoo_get_asset_data( string $asset_path ): array {
    $asset_path   = trailingslashit( WOOCOMMERCE_GATEWAY_KANOO_PATH ) . str_replace( '.js', '.asset.php', $asset_path );
    $version      = WOOCOMMERCE_GATEWAY_KANOO_VERSION;
    $dependencies = array();
    $data         = array(
        'version'      => $version,
        'dependencies' => $dependencies,
    );

    if ( file_exists( $asset_path ) ) {
        $asset                = require $asset_path;
        $data['version']      = is_array( $asset ) && isset( $asset['version'] )
            ? $asset['version']
            : $version;
        $data['dependencies'] = is_array( $asset ) && isset( $asset['dependencies'] )
            ? $asset['dependencies']
            : $dependencies;
    }

    return $data;
}