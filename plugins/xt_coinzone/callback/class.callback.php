<?php
defined( '_VALID_CALL' ) or die( 'Direct Access is not allowed.' );

include_once( "plugins/xt_coinzone/lib/class.coinzone.php" );

class callback_xt_coinzone extends callback {

	var $apiKey;
	var $clientcode;

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
	public function getClientcode() {
		return $this->clientcode;
	}

	/**
	 * @param mixed $clientcode
	 */
	public function setClientcode( $clientcode ) {
		$this->clientcode = $clientcode;
	}


	function process() {
		global $db, $logHandler;

		$this->setApiKey( XT_COINZONE_API_KEY );
		$this->setClientCode( XT_COINZONE_CLIENT_CODE );

		$response = file_get_contents( 'php://input' );

		$coinzone = new Coinzone( $this->getApiKey(), $this->getClientCode() );
		$coinzone->checkRequest( $response );

		if ( ! empty( $response ) ) {
			$response        = preg_replace( '/\s+/', '', $response );
			$decodedResponse = json_decode( $response, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				parse_str( $response, $decodedResponse );
			}

			if (
				! is_array( $decodedResponse ) ||
				! isset( $decodedResponse['merchantReference'] ) ||
				! isset( $decodedResponse['refNo'] ) ||
				! isset( $decodedResponse['amount'] ) ||
				! isset( $decodedResponse['currency'] ) ||
				! isset( $decodedResponse['convertedAmount'] ) ||
				! isset( $decodedResponse['convertedCurrency'] ) ||
				! isset( $decodedResponse['status'] )
			) {
				http_response_code( 400 );
				die(
				json_encode(
					array(
						'error'   => true,
						'message' => 'Invalid Notification format.'
					)
				)
				);
			}

			if ( $decodedResponse['merchantReference'] == '' ) {
				$error = 'Missing order ID.';
			}
			$rs = $db->Execute( "SELECT orders_id FROM " . TABLE_ORDERS . " WHERE orders_id='" . $decodedResponse['merchantReference'] . "' " );
			if ( $rs->RecordCount() == 0 ) {
				http_response_code( 400 );
				$error = 'Order ID not found.';
			} else {
				if (in_array($decodedResponse['status'], array('PAID', 'COMPLETE'))) {
					$this->orders_id = $decodedResponse['merchantReference'];
					$this->_updateOrderStatus( XT_COINZONE_ORDER_STATUS_SUCCESS, 'true', $decodedResponse['refNo'] );
				}
			}

			if ( $error ) {
				http_response_code( 400 );
				$log_data         = array();
				$log_data['data'] = 'Failed callback - ' . $error;
				$log_data['time'] = time();
				$logHandler->_addLog( 'callback-process', 'xt_coinzone', $decodedResponse['merchantReference'],
					$log_data );

			}
		}

	}

}

?>