<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * HELPER COMMENT START
 * 
 * This class contains all of the plugin related settings.
 * Everything that is relevant data and used multiple times throughout 
 * the plugin.
 * 
 * To define the actual values, we recommend adding them as shown above
 * within the __construct() function as a class-wide variable. 
 * This variable is then used by the callable functions down below. 
 * These callable functions can be called everywhere within the plugin 
 * as followed using the get_plugin_name() as an example: 
 * 
 * FCFPAY->settings->get_plugin_name();
 * 
 * HELPER COMMENT END
 */

/**
 * Class Fcf_Pay_Settings
 *
 * This class contains all of the plugin settings.
 * Here you can configure the whole plugin data.
 *
 * @package		FCFPAY
 * @subpackage	Classes/Fcf_Pay_Settings
 * @author		 The FCF Inc
 * @since		1.0.0
 */
class Fcf_Pay_Endpoints{

	/**
	 * Namespace
	 *
	 * @var		string
	 * @since   1.0.0
	 */
    private $namespace = 'woocommerce-fcf-pay/v1';

    /**
     * Amount percent
     *
     * @var		integer
     * @since   1.0.0
     */
    private $amount_percent;

    /**
     * Max amount
     *
     * @var		integer
     * @since   1.0.0
     */
    private $max_amount;

	/**
	 * Our Fcf_Pay_Settings constructor 
	 * to run the plugin logic.
	 *
	 * @since 1.0.0
	 */
    /**
     * WC_FCF_PAY_Api_Endpoints constructor.
     */
    public function __construct() {
        // Settings
        $settings = get_option( 'woocommerce_fcf_pay_settings' );
        $this->amount_percent = ! empty( $settings['amount_percent'] ) ? (int) $settings['amount_percent'] : '';
        $this->max_amount = ! empty( $settings['max_amount'] ) ? (int) $settings['max_amount'] : '';

        // Routes
        $this->register_api_routes();
    }

    /**
     * Register routes for API endpoints
     */
    public function register_api_routes(){
        add_action( 'rest_api_init', function () {

            register_rest_route( $this->namespace, '/check-order', array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this,'check_order'),
            ) );

            register_rest_route( $this->namespace, '/order-status', array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this,'order_status'),
            ) );

        } );
    }

    /**
     * Check if order exists
     *
     * @param $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function check_order($request){
        $order = wc_get_order( $request['order_id'] );
        if (!$order) {
            return new WP_Error( 'not_found', 'Order not found', array('status' => 404) );
        }

        $response = new WP_REST_Response(
            array(
                'status' => true,
            )
        );
        $response->set_status(200);

        return $response;
    }

    /**
     * Change order status
     *
     * @param $request
     *
     * @return WP_Error|WP_REST_Response
     * @throws Exception
     */
    public function order_status($request){
        $amount = $request['data']['crypto_amount'];
        $order_id = $request['data']["order_id"];
        $deposited = $request['data']["deposited"];
        $state = $request['data']["processing_state"];
        $currency = $request['data']["currency"];
        $usd = $request['data']["usd_amount"];
        $order = wc_get_order($order_id);

        if (!$order) {
            $response = new WP_REST_Response(
                array(
                    'status' => false,
                    'message' => 'Order not found',
                )
            );
            $response->set_status(404);

            return $response;
        }

        if($deposited || $state === 2){
            $total = (float) $order->get_total();
            $percent = 100 - ( ( $usd / $total ) * 100 );

            if(is_null($usd)){
                $status = 'on-hold';
            }elseif($this->amount_percent === '' && $this->max_amount === '' && $usd >= $total){
                $status = 'completed';
            }else{
                if( ($this->amount_percent >= $percent && $this->max_amount > 0 && ($total - $usd) <= $this->max_amount) || ($this->amount_percent >= $percent && $this->max_amount <= 0) || ($total - $usd) <= $this->max_amount){
                    $status = 'completed';
                }else{
                    $status = 'processing';
                }
            }
        }else{
            $status = 'processing';
        }

        $order->update_status($status);
        wc_update_order_item_meta($order_id, 'fcf_pay_deposited_amount', $amount);
        wc_update_order_item_meta($order_id, 'fcf_pay_deposited_currency', $currency);
        wc_update_order_item_meta($order_id, 'fcf_pay_deposited_amount_in_usd', $usd);
        $response = new WP_REST_Response(
            array(
                'status' => true,
            )
        );
        $response->set_status(200);

        return $response;
    }


}
