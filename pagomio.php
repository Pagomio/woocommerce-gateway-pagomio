<?php
/*
Plugin Name: WooCommerce Pagomío - Gateway
Plugin URI: https://www.pagomio.com/
Description: Incrementa el valor de tus negocios recibiendo dinero de todo el mundo.
Version: 1.0
Author: Pagomío
Author URI: https://www.pagomio.com/
*/
add_action('plugins_loaded', 'woocommerce_pagomio_gateway', 0);
function woocommerce_pagomio_gateway() {
	if(!class_exists('WC_Payment_Gateway')) return;

	class WC_Pagomio extends WC_Payment_Gateway {

		/**
		 * Constructor de la pasarela de pago
		 *
		 * @access public
		 * @return void
		 */
		public function __construct(){
			$this->id					= 'pagomio';
			$this->icon					= plugins_url('/img/franquicias-pagomio-logo.png', __FILE__);
			$this->has_fields			= false;
			$this->method_title			= 'Pagomío';
			$this->method_description	= 'Incrementa el valor de tus negocios recibiendo dinero de todo el mundo.';

			$this->init_form_fields();
			$this->init_settings();

			$this->title = $this->settings['title'];
			$this->cliente_id = $this->settings['client_id'];
			$this->secret_id = $this->settings['secret_id'];
			$this->sandbox = $this->settings['sandbox'];

			if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' )) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
			add_action('woocommerce_receipt_pagomio', array(&$this, 'receipt_page'));
		}

		/**
		 * Funcion que define los campos que iran en el formulario en la configuracion
		 * de la pasarela de Pagomío
		 *
		 * @access public
		 * @return void
		 */
		function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
                    'title' => 'Habilitar/Deshabilitar',
                    'type' => 'checkbox',
                    'label' => 'Habilitar Pagomío Gateway',
                    'default' => 'no'),
                'title' => array(
                    'title' => 'Título',
                    'type'=> 'text',
                    'description' =>'Título que el usuario verá durante checkout.',
                    'default' => 'Pagomío'),
                'client_id' => array(
                    'title' => 'Client ID',
                    'type' => 'text',
                    'description' => 'Client ID otorgado por Pagomío'),
                'secret_id' => array(
                    'title' => 'Secret ID',
                    'type' => 'text',
                    'description' => 'Secret ID otorgado por Pagomío'),
                'sandbox' => array(
                            'title' => 'Sandbox',
                            'type' => 'checkbox',
                            'label' => 'Sandbox',
                            'default' => 'no')
                    );
		}

		/**
         * Muestra el fomrulario en el admin con los campos de configuración
		 *
		 * @access public
         * @return void
         */
        public function admin_options() {
			echo '<h3>Pagomío Gateway</h3>';
			echo '<table class="form-table">';
			$this -> generate_settings_html();
			echo '</table>';
		}

		/**
		 * Atiende el evento de checkout y genera la pagina con el formularion de pago.
		 * Solo para la versiones anteriores a la 2.1.0 de WC
         *
         * @access public
         * @return void
		 */
		function receipt_page($order){
			$url = $this->get_params_post($order);
			if($url){
				wp_redirect($url);
				exit;
			}else{
				echo '<h4>Error generando su pago, por favor inténtelo nuevamente.</h4>';
			}
		}

		/**
		 * @return \Pagomio\Pagomio
		 */
		public function getPagomioObject(){
			include_once "lib/pagomio/pagomio-sdk-php/pagomio.php";
            if(!class_exists('Requests')){
			    include_once "lib/rmccue/requests/library/Requests.php";
            }
			Requests::register_autoloader();
			$sandbox = false;
			if($this->sandbox == "yes"){
				$sandbox = true;
			}
			return new \Pagomio\Pagomio($this->cliente_id,$this->secret_id,$sandbox);
		}

		/**
		 * Genera el link de redireccionamiento a la pasarela de Pagomío
         *
         * @access public
         * @return mixed
		 */
		public function get_params_post($order_id){
			global $woocommerce;

			$pagomio = $this->getPagomioObject();

			$order = new WC_Order( $order_id );
			$currency = get_woocommerce_currency();
			$amount = number_format(($order -> get_total()),2,'.','');
			$description = "";
			$products = $order->get_items();
			foreach($products as $product) {
				$description .= $product['name'] . ',';
			}
			$tax = number_format(($order -> get_total_tax()),2,'.','');
			$taxReturnBase = number_format(($amount - $tax),2,'.','');
			if ($tax == 0) $taxReturnBase = 0;

			//Customer information - Not required
			$userData = new Pagomio\UserData();
			$userData->names = $order->billing_first_name;
			$userData->lastNames = $order->billing_last_name;
			$userData->identificationType = 'CC'; # Allow: CC, TI, PT, NIT
			$userData->identification = '0';
			$userData->email = $order->billing_email;

			// Payment information - Is required
			$paymentData = new Pagomio\PaymentData();
			$paymentData->currency = in_array($currency,['COP','USD']) ? $currency : 'COP';
			$paymentData->reference = strval($order->id);
			$paymentData->totalAmount = $amount;
			$paymentData->taxAmount = $tax;
			$paymentData->devolutionBaseAmount = $taxReturnBase;
			$paymentData->description = $description;

			// Url return to after payment
			$enterpriseData = new Pagomio\EnterpriseData();
			$enterpriseData->url_redirect = site_url() . '/wp-content/plugins/woocommerce-gateway-pagomio/response.php';
			$enterpriseData->url_notify = site_url() . '/wp-content/plugins/woocommerce-gateway-pagomio/confirmation.php';

			// Create the object
			$aut = new Pagomio\AuthorizePayment();
			$aut->enterpriseData = $enterpriseData;
			$aut->paymentData = $paymentData;
			$aut->userData = $userData;

			try{
				// Generate the token
				$response = $pagomio->getToken($aut);
			}catch (Exception $e){
				return false;
			}

			// Redirect to Pagomio.com
			if($response->success) {
				return $response->url;
			}
			return false;
		}

		/**
		 * Procesa el pago
         *
         * @access public
         * @return mixed
		 */
		function process_payment($order_id) {
			global $woocommerce;
			$order = new WC_Order($order_id);
			$woocommerce->cart->empty_cart();
			if (version_compare(WOOCOMMERCE_VERSION, '2.0.19', '<=' )) {
				return array('result' => 'success', 'redirect' => add_query_arg('order',
					$order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
				);
			} else {
				return array(
					'result' => 'success',
					'redirect' =>  $order->get_checkout_payment_url(true)
				);
			}
		}

		/**
		 * get_icon function.
		 *
		 * @return string
		 */
		public function get_icon() {

			$icon = $this->icon ? '<br/><img src="' . WC_HTTPS::force_https_url( $this->icon ) . '" alt="' . esc_attr( $this->get_title() ) . '" width="200" />' : '';

			return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
		}
	}

	/**
	 * Ambas funciones son utilizadas para notifcar a WC la existencia de Pagomío
	 */
	function add_pagomio($methods) {
		$methods[] = 'WC_Pagomio';
		return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'add_pagomio' );
}