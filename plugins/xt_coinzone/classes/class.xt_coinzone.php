<?php
defined( '_VALID_CALL' ) or die( 'Direct Access is not allowed.' );

include_once( "plugins/xt_coinzone/lib/class.coinzone.php" );

class xt_coinzone {

	var $version = '1.0';
	var $data = array();
	var $external = true;
	var $apiKey;
	var $clientCode;

	function __construct() {
		$this->setApiKey( XT_COINZONE_API_KEY );
		$this->setClientCode( XT_COINZONE_CLIENT_CODE );
	}

	/**
	 * @return mixed
	 */
	public function getApiKey() {
		return $this->apiKey;
	}

	/**
	 * @param mixed $apiKey
	 */
	public function setApiKey( $apiKey ) {
		$this->apiKey = $apiKey;
	}

	/**
	 * @return mixed
	 */
	public function getClientCode() {
		return $this->clientCode;
	}

	/**
	 * @param mixed $clientCode
	 */
	public function setClientCode( $clientCode ) {
		$this->clientCode = $clientCode;
	}

	function build_payment_info( $data ) {

	}

	function pspRedirect( $data ) {
		global $xtLink, $order, $logHandler;

		$amount   = $order->order_total['total']['plain'];
		$currency = $order->order_data['currency_code'];

		$payload = array(
			'amount'            => $amount,
			'currency'          => $currency,
			'merchantReference' => $order->oID,
			'email'             => $order->order_customer['customers_email_address'],
			'redirectUrl'       => html_entity_decode($xtLink->_link( array( 'page' => 'checkout', 'paction' => 'payment_process' ) )),
			'notificationUrl'   => html_entity_decode($xtLink->_link( array( 'page' => 'callback', 'paction' => 'xt_coinzone' ) ))
		);

		$coinzone = new Coinzone( $this->getApiKey(), $this->getClientCode() );
		$response = $coinzone->callApi( 'transaction', $payload );


		if ( $response->status->code !== 201 ) {

			$log_data         = array();
			$log_data['data'] = 'Failed to create transaction - ' . json_encode( $response );
			$log_data['time'] = time();
			$logHandler->_addLog( 'coinzone-redirect', 'xt_coinzone', $order->oID, $log_data );


			die( 'Internal Error' );
		}

		unset( $_SESSION['cart'] );

		return $response->response->url;

	}

}