<?php
/**
 * FastBill Functions
 *
 * @package     FastBill Integration for Easy Digital Downloads
 * @subpackage  Register Settings
 * @copyright   Copyright (c) 2013, Markus Drubba (dev@markusdrubba.de)
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.0
 */

/**
 * drubba_fastbill__create_invoice()
 *
 * Create an invoice record in FastBill for the given order.
 *
 * @param  $payment_id
 *
 * @access public
 * @return void
 *
 **/
function drubba_fastbill_create_invoice( $payment_id ) {

	global $edd_options;

	$payment      = get_post( $payment_id );
	$payment_meta = get_post_meta( $payment->ID, '_edd_payment_meta', true );
	$user_info    = maybe_unserialize( $payment_meta['user_info'] );

	drubba_fastbill_addlog( 'START - Creating invoice for order #' . $payment_id );

	// Check if client exists with customer's email address

	drubba_fastbill_addlog( 'Checking for client with email: ' . $user_info['email'] );

	$client_id = drubba_fastbill_lookup_customer( $user_info['email'] );

	if ( $client_id == 0 ) {
		try {
			// client doesn't exist, so create a client record in FastBill
			$client_id = drubba_fastbill_create_customer( $payment_id );
		} catch ( Exception $e ) {
			drubba_fastbill_addlog( $e->getMessage() );
			drubba_fastbill_addlog( 'END - Creating invoice for order #' . $payment_id );
			return;
		}
	}

	$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
	$xml .= "<FBAPI>";
	$xml .= "<SERVICE>invoice.create</SERVICE>";
	$xml .= "<DATA>";
	$xml .= "<CUSTOMER_ID>" . $client_id . "</CUSTOMER_ID>";
	$xml .= "<INVOICE_DATE>" . $payment->post_date . "</INVOICE_DATE>";
	$currency = isset( $edd_options['currency'] ) ? $edd_options['currency'] : 'EUR';
	$xml .= "<CURRENCY_CODE>" . $currency . "</CURRENCY_CODE>";

	// add order discount as note to invoice items
	if ( $user_info['discount'] != 'none' ) {
		$discount = __( 'Discount used: ', 'edd-fastbill' ) . $user_info['discount'];
	}

	$xml .= "<ITEMS>";

	$cart_items = isset( $payment_meta['cart_details'] ) ? maybe_unserialize( $payment_meta['cart_details'] ) : false;
	if ( empty( $cart_items ) || ! $cart_items ) {
		$cart_items = maybe_unserialize( $payment_meta['downloads'] );
	}

	if ( $cart_items ) {

		foreach ( $cart_items as $key => $cart_item ) {
			// retrieve the ID of the download
			$id = isset( $payment_meta['cart_details'] ) ? $cart_item['id'] : $cart_item;

			// if download has variable prices, override the default price
			$price_override = isset( $payment_meta['cart_details'] ) ? $cart_item['item_price'] : null;

			// get the user information
			$user_info = edd_get_payment_meta_user_info( $payment->ID );

			// calculate the final item price
			if ( isset( $user_info['discount'] ) && $user_info['discount'] != 'none' ) {
				$price = edd_get_discounted_amount( $user_info['discount'], $cart_item['item_price'] );
			} else {
				$price = edd_get_download_final_price( $id, $user_info, $price_override );
			}

			$xml .= "<ITEM>";
			$xml .= "<DESCRIPTION>" . get_the_title( $id );
			if ( isset( $discount ) ) $xml .= ' ' . $discount;
			$xml .= "</DESCRIPTION>";
			$xml .= "<UNIT_PRICE>" . $price . "</UNIT_PRICE>";
			$xml .= "<QUANTITY>" . $cart_item['quantity'] . "</QUANTITY>";
			if ( edd_use_taxes() ) {
				$tax_rate = edd_get_tax_rate() * 100;
				$xml .= "<VAT_PERCENT>" . $tax_rate . "</VAT_PERCENT>";
			}
			$xml .= "</ITEM>";
		}
	}

	$xml .= "</ITEMS>";
	$xml .= "</DATA>";
	$xml .= "</FBAPI>";


//	$invoice_status = ( $edd_options['drubba_fb_fastbill_invoice_status'] == '' ) ? 'sent' : $edd_options['drubba_fb_fastbill_invoice_status'];

	try {

		$result = drubba_fastbill_apicall( $xml );

	} catch ( Exception $e ) {

		drubba_fastbill_addlog( $e->getMessage() );
		return;

	}

	$response = new SimpleXMLElement( $result );
	$is_error = isset( $response->RESPONSE->ERRORS ) ? true : false;

	if ( ! $is_error ) {
		// Invoice Created
		$fb_invoice_id = (string) $response->RESPONSE->INVOICE_ID;
		update_post_meta( $payment_id, '_fastbill_invoice_id', $fb_invoice_id );
		edd_insert_payment_note( $payment_id, 'FastBill Invoice ID: ' . $fb_invoice_id );
		drubba_fastbill_addlog( 'END - Creating invoice for order #' . $payment_id );

		if ( $edd_options['drubba_fb_fastbill_invoice_status'] == 'complete' )
			drubba_fastbill_invoice_complete( $payment_id, $response->RESPONSE->INVOICE_ID );
	} else {
		// An error occured
		$error_string = __( 'There was an error adding the invocie to FastBill:', 'edd-fastbill' ) . "\n" .
			__( 'Error: ', 'edd-fastbill' ) . $response->ERRORS->ERROR;

		drubba_fastbill_addlog( $error_string );
		drubba_fastbill_addlog( 'END - Creating invoice for order #' . $payment_id );
	}

}

/**
 * drubba_fastbill_invoice_complete()
 *
 * Set invoice to payed in FastBill for the given order.
 *
 * @param  $payment_id
 *
 * @access public
 * @return void
 *
 **/
function drubba_fastbill_invoice_complete( $payment_id, $invoice_id ) {

	if ( $invoice_id > 0 ) {
		// there is an invoice ID, so complete invoice

		drubba_fastbill_addlog( 'START - Complete invoice in FastBill for invoice ID: ' . $invoice_id );

		$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
		$xml .= "<FBAPI>";
		$xml .= "<SERVICE>invoice.complete</SERVICE>";
		$xml .= "<DATA>";
		$xml .= "<INVOICE_ID>" . $invoice_id . "</INVOICE_ID>";
		$xml .= "</DATA>";
		$xml .= "</FBAPI>";

		try {

			$result = drubba_fastbill_apicall( $xml );

		} catch ( Exception $e ) {

			drubba_fastbill_addlog( $e->getMessage() );
			return;

		}
		$response = new SimpleXMLElement( $result );
		$is_error = isset( $response->RESPONSE->ERRORS ) ? true : false;

		if ( ! $is_error ) {
			drubba_fastbill_addlog( 'END - Complete invoice for order #' . $payment_id );
			$fb_invoice_no = (string) $response->RESPONSE->INVOICE_NUMBER;
			edd_insert_payment_note( $payment_id, 'FastBill Invoice Number: ' . $fb_invoice_no );
		} else {
			// An error occured
			$error_string = __( 'There was an error completing invoice in FastBill:', 'edd-fastbill' ) . "\n" .
				__( 'Error: ', 'edd-fastbill' ) . $response->RESPONSE->ERRORS->ERROR;
			drubba_fastbill_addlog( $error_string );
		}
	} else {
		// no invoice id so exit.
		return;
	}
}

/**
 * drubba_fastbill_create_payment()
 *
 * Set invoice to payed in FastBill for the given order.
 *
 * @param  $payment_id
 *
 * @access public
 * @return void
 *
 **/
function drubba_fastbill_create_payment( $payment_id ) {

	$fb_invoice_id = (int) get_post_meta( $payment_id, '_fastbill_invoice_id', true );

	if ( $fb_invoice_id > 0 ) {
		// there is an invoice ID, so create payment

		drubba_fastbill_addlog( 'START - Creating payment in FastBill for invoice ID: ' . $fb_invoice_id );

		$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
		$xml .= "<FBAPI>";
		$xml .= "<SERVICE>invoice.setpaid</SERVICE>";
		$xml .= "<DATA>";
		$xml .= "<INVOICE_ID>" . $fb_invoice_id . "</INVOICE_ID>";
		$xml .= "</DATA>";
		$xml .= "</FBAPI>";

		try {

			$result = drubba_fastbill_apicall( $xml );

		} catch ( Exception $e ) {

			drubba_fastbill_addlog( $e->getMessage() );
			return;

		}
		$response = new SimpleXMLElement( $result );
		$is_error = isset( $response->RESPONSE->ERRORS ) ? true : false;

		if ( ! $is_error ) {
			drubba_fastbill_addlog( 'END - Creating payment for order #' . $payment_id );
		} else {
			// An error occured
			$error_string = __( 'There was an error creating a payment in FastBill:', 'edd-fastbill' ) . "\n" .
				__( 'Error: ', 'edd-fastbill' ) . $response->RESPONSE->ERRORS->ERROR;
			drubba_fastbill_addlog( $error_string );
		}
	} else {
		// no invoice id so exit.				
		return;
	}
}

/**
 * drubba_fastbill_create_customer()
 *
 * Create a client record in FastBill for the given order.
 *
 * @param  $payment_id
 *
 * @access public
 * @return $customer_id
 * @throws Excption on error
 *
 **/
function drubba_fastbill_create_customer( $payment_id ) {

	global $edd_options;

	$payment      = get_post( $payment_id );
	$payment_meta = get_post_meta( $payment->ID, '_edd_payment_meta', true );
	$user_info    = maybe_unserialize( $payment_meta['user_info'] );
	$first_name   = ! empty( $user_info['first_name'] ) ? $user_info['first_name'] : 'unknown';
	$last_name    = ! empty( $user_info['last_name'] ) ? $user_info['last_name'] : 'unknown';

	drubba_fastbill_addlog( 'Creating customer record in FastBill for email: ' . $user_info['email'] );

	$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
	$xml .= "<FBAPI>";
	$xml .= "<SERVICE>customer.create</SERVICE>";
	$xml .= "<DATA>";

	$xml .= "<FIRST_NAME>" . $first_name . "</FIRST_NAME>";
	$xml .= "<LAST_NAME>" . $last_name . "</LAST_NAME>";
	$xml .= "<EMAIL>" . $user_info['email'] . "</EMAIL>";

	if ( isset( $user_info['address']['line1'] ) && trim( $user_info['address']['line1'] ) != '' ) {
		$xml .= "<ADDRESS>" . $user_info['address']['line1'] . "</ADDRESS>";
	}

	if ( isset( $user_info['address']['line2'] ) && trim( $user_info['address']['line2'] ) != '' ) {
		$xml .= "<ADDRESS_2>" . $user_info['address']['line2'] . "</ADDRESS_2>";
	}

	if ( isset( $user_info['address']['city'] ) && trim( $user_info['address']['city'] ) != '' ) {
		$xml .= "<CITY>" . $user_info['address']['city'] . "</CITY>";
	}

	if ( isset( $user_info['address']['country'] ) && trim( $user_info['address']['country'] ) != '' ) {
		$xml .= "<COUNTRY_CODE>" . $user_info['address']['country'] . "</COUNTRY_CODE>";
	}

	if ( isset( $user_info['address']['zip'] ) && trim( $user_info['address']['zip'] ) != '' ) {
		$xml .= "<ZIPCODE>" . $user_info['address']['zip'] . "</ZIPCODE>";
	}

	if ( ! drubba_fb_cfm_active() ) {

		$xml .= "<CUSTOMER_TYPE>consumer</CUSTOMER_TYPE>";

	} else {

		$orga = get_post_meta( $payment_id, $edd_options['drubba_fb_fastbill_ORGANIZATION'], true );
		if ( isset( $orga ) && $orga != '' && ! is_array( $orga ) ) {
			$xml .= "<CUSTOMER_TYPE>business</CUSTOMER_TYPE>";
		} else {
			$xml .= "<CUSTOMER_TYPE>consumer</CUSTOMER_TYPE>";
		}

		$customer_fields = drubba_fb_get_customer_fields();
		foreach ( $customer_fields as $key => $value ) {

			$fb_option   = $edd_options['drubba_fb_fastbill_' . $key];
			$field_value = get_post_meta( $payment_id, $fb_option, true );

			if ( ! is_array( $field_value ) ) {
				if ( $key == 'SALUTATION' ) {
					if ( in_array( $field_value, array( 'Herr', 'Hr.', 'Hr', 'Mister', 'Mr', 'Mr.' ) ) ) {
						$field_value = 'mr';
					} elseif ( in_array( $field_value, array( 'Frau', 'Fr.', 'Fr', 'Misses', 'Miss', 'Mrs.' ) ) ) {
						$field_value = 'mrs';
					} else {
						$field_value = '';
					}
				}

				$xml .= "<" . $key . ">" . $field_value . "</" . $key . ">";
			}

		}

	}

	$xml .= "</DATA>";
	$xml .= "</FBAPI>";

	try {

		$result = drubba_fastbill_apicall( $xml );

	} catch ( Exception $e ) {

		drubba_fastbill_addlog( $e->getMessage() );
		return;

	}
	$response = new SimpleXMLElement( $result );
	$is_error = isset( $response->RESPONSE->ERRORS ) ? true : false;

	if ( ! $is_error ) {
		// get the first client
		if ( isset( $response->RESPONSE->CUSTOMER_ID ) ) {
			return $response->RESPONSE->CUSTOMER_ID;
		} else {
			drubba_fastbill_addlog( 'Unable to create client' . $response );
			throw new Exception( 'Unable to create client' . $response );
		}

	} else {
		// An error occured
		$error_string = __( 'There was an error creating this customer in FastBill:', 'edd-fastbill' ) . "\n" .
			__( 'Error: ', 'edd-fastbill' ) . $response->ERRORS->ERROR;

		drubba_fastbill_addlog( $error_string );
		throw new Exception( 'Unable to create client' . $response );
	}

}

/**
 * drubba_fastbill_lookup_customer()
 *
 * Check FastBill for a client record corresponding to the supplied email address.
 *
 * @param  $customer_email
 *
 * @access public
 * @return $customer_id or 0 if customer does not exist in FastBill
 *
 **/
function drubba_fastbill_lookup_customer( $customer_email ) {

	$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
	$xml .= "<FBAPI>";
	$xml .= "<SERVICE>customer.get</SERVICE>";
	$xml .= "<FILTER>";
	$xml .= "<TERM>" . $customer_email . "</TERM>";
	$xml .= "</FILTER>";
	$xml .= "</FBAPI>";

	try {

		$result = drubba_fastbill_apicall( $xml );

	} catch ( Exception $e ) {

		drubba_fastbill_addlog( $e->getMessage() );
		return;

	}
	$response = new SimpleXMLElement( $result );
	$is_error = isset( $response->RESPONSE->ERRORS ) ? true : false;

	if ( ! $is_error ) {
		// get the first client
		if ( isset( $response->RESPONSE->CUSTOMERS->CUSTOMER->CUSTOMER_ID ) ) {
			return $response->RESPONSE->CUSTOMERS->CUSTOMER->CUSTOMER_ID;
		} else {
			return 0;
		}

	} else {
		// An error occured
		$error_string = __( 'There was an error looking up this customer in FastBill:', 'edd-fastbill' ) . "\n" .
			__( 'Error: ', 'edd-fastbill' ) . $response->RESPONSE->ERRORS->ERROR;

		drubba_fastbill_addlog( $error_string );
		return 0;
	}

}


/**
 * drubba_fastbill_apicall()
 *
 * Send an XML request to the FastBill API
 *
 * @param  $xml
 *
 * @access public
 * @return $response
 *
 **/
function drubba_fastbill_apicall( $xml ) {

	global $edd_options;

	drubba_fastbill_addlog( "SENDING XML:\n" . $xml );

	$url = 'https://my.fastbill.com/api/1.0/api.php';

	$response = wp_remote_post( $url, array(
			'method'      => 'POST',
			'timeout'     => 40,
			'redirection' => 3,
			'httpversion' => '1.0',
			'cookies'     => array(),
			'headers'     => array(
				'Content-Type'  => 'application/xml',
				'Authorization' => 'Basic ' . base64_encode( $edd_options['drubba_fb_fastbill_email'] . ':' . $edd_options['drubba_fb_fastbill_api_key'] )
			),
			'body'        => $xml
		)
	);

	if ( is_wp_error( $response ) ) {
		drubba_fastbill_addlog( 'Something went wrong: ' . $response->get_error_message() );
		throw new Exception( 'An error occured: ' . wp_remote_retrieve_response_message( $response ) );
	}

	$result = wp_remote_retrieve_body( $response );

	drubba_fastbill_addlog( "RESPONSE XML: " . $result );

	return $result;
}

/**
 * drubba_fastbill_addlog()
 *
 * Output data to a log
 *
 * @param  $log_string - The string to be appended to the log file.
 *
 * @access public
 * @return void
 *
 **/
function drubba_fastbill_addlog( $log_string ) {

	global $edd_options;

	if ( $edd_options['drubba_fb_fastbill_debug_on'] == 1 ) {
		$path       = DRUBBAFASTBILL_DIR . "log/fastbill_debug.log";
		$log_string = "Log Date: " . date( "r" ) . "\n" . $log_string . "\n";
		if ( file_exists( $path ) ) {
			if ( $log = fopen( $path, "a" ) ) {
				fwrite( $log, $log_string, strlen( $log_string ) );
				fclose( $log );
			}
		} else {
			if ( $log = fopen( $path, "c" ) ) {
				fwrite( $log, $log_string, strlen( $log_string ) );
				fclose( $log );
			}
		}
	}
}