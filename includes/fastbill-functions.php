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

namespace drumba\EDD\FastBill;

use drumba\EDD\FastBill\Helper\Logger;
use SimpleXMLElement;

class FastBill {

	private $api_key;
	private $api_email;

	/**
	 * Create a new instance
	 *
	 * @throws \Exception
	 */
	public function __construct() {
		global $edd_options;

		$this->api_key   = $edd_options['drubba_fb_fastbill_api_key'];
		$this->api_email = $edd_options['drubba_fb_fastbill_email'];
		$this->logger    = new Logger( 'edd_fastbill_error_log' );

		if ( empty( $this->api_key ) || empty( $this->api_email ) ) {
			throw new \Exception( __( 'Invalid FastBill credentials supplied', 'edd-fastbill' ) );
		}
	}

	/**
	 * Create a client record in FastBill for the given order.
	 *
	 * @param $payment_id
	 *
	 * @return mixed
	 * @throws Exception
	 *
	 **/
	public function customer_create( $payment_id, $client_id = false ) {

		global $edd_options;

		$payment         = get_post( $payment_id );
		$payment_meta    = get_post_meta( $payment->ID, '_edd_payment_meta', true );
		$user_info       = maybe_unserialize( $payment_meta['user_info'] );
		$first_name      = ! empty( $user_info['first_name'] ) ? $user_info['first_name'] : 'unknown';
		$last_name       = ! empty( $user_info['last_name'] ) ? $user_info['last_name'] : 'unknown';
		$customer_action = $client_id > 0 ? 'customer.update' : 'customer.create';

		$this->logger->add( 'Creating customer record in FastBill for email: ' . $user_info['email'] );

		$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
		$xml .= "<FBAPI>";
		$xml .= "<SERVICE>$customer_action</SERVICE>";
		$xml .= "<DATA>";

		if ( $client_id > 0 ) {
			$xml .= "<CUSTOMER_ID>$client_id</CUSTOMER_ID>";
		}

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

		if ( isset( $user_info['vat_number'] ) && trim( $user_info['vat_number'] ) != '' ) {
			$xml .= "<VAT_ID>" . $user_info['vat_number'] . "</VAT_ID>";
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

				$fb_option   = $edd_options[ 'drubba_fb_fastbill_' . $key ];
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

			$result = $this->apicall( $xml );

		} catch ( Exception $e ) {

			$this->logger->add( $e->getMessage() );

			return;

		}
		$response = new SimpleXMLElement( $result );
		$is_error = isset( $response->RESPONSE->ERRORS ) ? true : false;

		if ( ! $is_error ) {
			// get the first client
			if ( isset( $response->RESPONSE->CUSTOMER_ID ) ) {
				return $response->RESPONSE->CUSTOMER_ID;
			} else {
				$this->logger->add( 'Unable to create client' . $response );
				throw new Exception( 'Unable to create client' . $response );
			}

		} else {
			// An error occured
			$error_string = __( 'There was an error creating this customer in FastBill:', 'edd-fastbill' ) . "\n" .
			                __( 'Error: ', 'edd-fastbill' ) . $response->ERRORS->ERROR;

			$this->logger->add( $error_string );
			throw new Exception( 'Unable to create client' . $response );
		}

	}

	/**
	 * Check FastBill for a client record corresponding to the supplied email address.
	 *
	 * @param  $customer_email
	 *
	 * @access public
	 * @return int|void customer_id or 0 if customer does not exist in FastBill
	 *
	 **/
	public function customer_lookup( $customer_email ) {

		$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
		$xml .= "<FBAPI>";
		$xml .= "<SERVICE>customer.get</SERVICE>";
		$xml .= "<FILTER>";
		$xml .= "<TERM>" . $customer_email . "</TERM>";
		$xml .= "</FILTER>";
		$xml .= "</FBAPI>";

		try {

			$result = $this->apicall( $xml );

		} catch ( Exception $e ) {

			$this->logger->add( $e->getMessage() );

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

			$this->logger->add( $error_string );

			return 0;
		}

	}

	/**
	 * Create an invoice record in FastBill for the given order.
	 *
	 * @param  $payment_id
	 *
	 * @access public
	 * @return void
	 *
	 **/
	public function invoice_create( $payment_id, $payment_type = 'direct' ) {

		global $edd_options;

		$payment_meta = edd_get_payment_meta( $payment_id );
		$user_info    = $payment_meta['user_info'];
		$cart_items   = isset( $payment_meta['cart_details'] ) ? maybe_unserialize( $payment_meta['cart_details'] ) : false;
		$user_country = isset( $user_info['address']['country'] ) ? maybe_unserialize( $user_info['address']['country'] ) : false;

		$this->logger->add( 'START - Creating invoice for order #' . $payment_id );

		// Check if client exists with customer's email address

		$this->logger->add( 'Checking for client with email: ' . $user_info['email'] );

		$client_id = $this->customer_lookup( $user_info['email'] );

		try {
			// client doesn't exist, so create a client record in FastBill
			$client_id = $this->customer_create( $payment_id, $client_id );
		} catch ( Exception $e ) {
			$this->logger->add( $e->getMessage() );
			$this->logger->add( 'END - Creating invoice for order #' . $payment_id );

			return;
		}

		$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
		$xml .= "<FBAPI>";
		$xml .= "<SERVICE>invoice.create</SERVICE>";
		$xml .= "<DATA>";
		$xml .= "<CUSTOMER_ID>" . $client_id . "</CUSTOMER_ID>";
		$xml .= "<INVOICE_DATE>" . $payment_meta['date'] . "</INVOICE_DATE>";
		$currency = isset( $edd_options['currency'] ) ? $edd_options['currency'] : 'EUR';
		$xml .= "<CURRENCY_CODE>" . $currency . "</CURRENCY_CODE>";

		// check if a specific template is selected
		$template_id = $this->_get_template_id( $payment_type );

		if ( $template_id ) {
			$xml .= "<TEMPLATE_ID>" . $template_id . "</TEMPLATE_ID>";
		}

		// add order discount as note to invoice items
		if ( $user_info['discount'] != 'none' ) {
			$discount = __( 'Discount used: ', 'edd-fastbill' ) . $user_info['discount'];
		}

		$xml .= "<ITEMS>";


		if ( empty( $cart_items ) || ! $cart_items ) {
			$cart_items = maybe_unserialize( $payment_meta['downloads'] );
		}

		if ( $cart_items ) {
			$added_items = 0;
			foreach ( $cart_items as $key => $cart_item ) {
				// retrieve the ID of the download
				$id = isset( $payment_meta['cart_details'] ) ? $cart_item['id'] : $cart_item;

				// calculate price for item and pay attention to discounts
				$price = $cart_item['subtotal'] - $cart_item['discount'];

				// if item is for free, don't process the item
				if ( $price <= 0 ) {
					continue;
				}
				$added_items ++;

				// retrieve price option name if available
				if ( isset( $cart_item['item_number']['options']['price_id'] ) ) {
					$option_name = edd_get_price_option_name( $id, $cart_item['item_number']['options']['price_id'] );
				}

				// check for renewal and append renewal string if renewals are discounted
				$renewal_discount = edd_get_option( 'edd_sl_renewal_discount', false );
				if ( isset( $cart_item['item_number']['options']['is_renewal'] ) && $renewal_discount ) {
					$renewal = __( 'renewal', 'edd-fastbill' );
					if ( isset( $option_name ) ) {
						$option_name += ', ' + $renewal;
					} else {
						$option_name += $renewal;
					}
				}

				// Build XML
				$xml .= "<ITEM>";
				$xml .= "<DESCRIPTION>" . get_the_title( $id );
				if ( ! empty( $option_name ) ) {
					$xml .= ' (' . $option_name . ')';
				}
				if ( ! empty( $discount ) ) {
					$xml .= ' ' . $discount;
				}
				$xml .= "</DESCRIPTION>";
				$xml .= "<UNIT_PRICE>" . $price . "</UNIT_PRICE>";
				$xml .= "<QUANTITY>" . $cart_item['quantity'] . "</QUANTITY>";
				if ( edd_use_taxes() ) {
					$tax_rate = edd_get_tax_rate( $user_country ) * 100;
					$xml .= "<VAT_PERCENT>" . $tax_rate . "</VAT_PERCENT>";
				}
				$xml .= "</ITEM>";
			}
		}

		if ( ! $added_items ) {
			$this->logger->add( 'END - Invoice for order #' . $payment_id . ' was not created. Only free items included.' );

			return;

		}

		$xml .= "</ITEMS>";
		$xml .= "</DATA>";
		$xml .= "</FBAPI>";


//	$invoice_status = ( $edd_options['drubba_fb_fastbill_invoice_status'] == '' ) ? 'sent' : $edd_options['drubba_fb_fastbill_invoice_status'];

		try {

			$result = $this->apicall( $xml );

		} catch ( Exception $e ) {

			$this->logger->add( print_r( $e, true ) );

			return;

		}

		$response = new SimpleXMLElement( $result );
		$is_error = isset( $response->RESPONSE->ERRORS ) ? true : false;

		if ( ! $is_error ) {

			// Invoice Created
			$fb_invoice_id = (string) $response->RESPONSE->INVOICE_ID;
			update_post_meta( $payment_id, '_fastbill_invoice_id', $fb_invoice_id );
			edd_insert_payment_note( $payment_id, 'FastBill Invoice ID: ' . $fb_invoice_id );

			$this->_add_invoice_url( $payment_id, $fb_invoice_id );

			$this->logger->add( 'END - Creating invoice for order #' . $payment_id );

			if ( $edd_options['drubba_fb_fastbill_invoice_status'] == 'complete' ) {
				$this->invoice_complete( $payment_id, $response->RESPONSE->INVOICE_ID );
			}
		} else {
			// An error occured
			$error_string = __( 'There was an error adding the invocie to FastBill:', 'edd-fastbill' ) . "\n" .
			                __( 'Error: ', 'edd-fastbill' ) . $response->ERRORS->ERROR;

			$this->logger->add( $error_string );
			$this->logger->add( 'END - Creating invoice for order #' . $payment_id );
		}

	}

	/**
	 * Get an invoice record in FastBill for the invoice id.
	 *
	 * @param  $invoice_id
	 *
	 * @access public
	 * @return object
	 *
	 **/
	public function invoice_get( $invoice_id ) {

		if ( $invoice_id > 0 ) {
			// there is an invoice ID, so retrieve the invoice

			$this->logger->add( 'START - Get invoice in FastBill for invoice ID: ' . $invoice_id );

			$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
			$xml .= "<FBAPI>";
			$xml .= "<SERVICE>invoice.get</SERVICE>";
			$xml .= "<FILTER>";
			$xml .= "<INVOICE_ID>" . $invoice_id . "</INVOICE_ID>";
			$xml .= "</FILTER>";
			$xml .= "</FBAPI>";

			try {

				$result = $this->apicall( $xml );

			} catch ( Exception $e ) {

				$this->logger->add( $e->getMessage() );

				return;

			}
			$response = new SimpleXMLElement( $result );
			$is_error = isset( $response->RESPONSE->ERRORS ) ? true : false;

			if ( ! $is_error ) {
				$this->logger->add( 'END - Complete get invoice ID: ' . $invoice_id );

				return ( ! empty( $response->RESPONSE->INVOICES->INVOICE ) ) ? $response->RESPONSE->INVOICES->INVOICE : null;
			} else {
				// An error occured
				$error_string = __( 'There was an error completing invoice in FastBill:', 'edd-fastbill' ) . "\n" .
				                __( 'Error: ', 'edd-fastbill' ) . $response->RESPONSE->ERRORS->ERROR;
				$this->logger->add( $error_string );

				return null;
			}
		} else {
			// no invoice id so exit.
			return null;
		}
	}

	/**
	 * Set invoice to payed in FastBill for the given order.
	 *
	 * @param  $payment_id
	 *
	 * @access public
	 * @return void
	 *
	 **/
	public function invoice_complete( $payment_id, $invoice_id ) {

		if ( $invoice_id > 0 ) {
			// there is an invoice ID, so complete invoice

			$this->logger->add( 'START - Complete invoice in FastBill for invoice ID: ' . $invoice_id );

			$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
			$xml .= "<FBAPI>";
			$xml .= "<SERVICE>invoice.complete</SERVICE>";
			$xml .= "<DATA>";
			$xml .= "<INVOICE_ID>" . $invoice_id . "</INVOICE_ID>";
			$xml .= "</DATA>";
			$xml .= "</FBAPI>";

			try {

				$result = $this->apicall( $xml );

			} catch ( Exception $e ) {

				$this->logger->add( $e->getMessage() );

				return;

			}
			$response = new SimpleXMLElement( $result );
			$is_error = isset( $response->RESPONSE->ERRORS ) ? true : false;

			if ( ! $is_error ) {
				$this->logger->add( 'END - Complete invoice for order #' . $payment_id );
				$fb_invoice_no = (string) $response->RESPONSE->INVOICE_NUMBER;
				edd_insert_payment_note( $payment_id, 'FastBill Invoice Number: ' . $fb_invoice_no );
			} else {
				// An error occured
				$error_string = __( 'There was an error completing invoice in FastBill:', 'edd-fastbill' ) . "\n" .
				                __( 'Error: ', 'edd-fastbill' ) . $response->RESPONSE->ERRORS->ERROR;
				$this->logger->add( $error_string );
			}
		} else {
			// no invoice id so exit.
			return;
		}
	}

	/**
	 * Cancel invoice in FastBill for the given order.
	 *
	 * @param  $payment_id
	 *
	 * @access public
	 * @return void
	 *
	 **/
	public function invoice_cancel( $payment_id ) {

		$fb_invoice_id = (int) get_post_meta( $payment_id, '_fastbill_invoice_id', true );

		if ( $fb_invoice_id > 0 ) {
			// there is an invoice ID, so cancel invoice

			$this->logger->add( 'START - Canceling invoice in FastBill for invoice ID: ' . $fb_invoice_id );

			$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
			$xml .= "<FBAPI>";
			$xml .= "<SERVICE>invoice.cancel</SERVICE>";
			$xml .= "<DATA>";
			$xml .= "<INVOICE_ID>" . $fb_invoice_id . "</INVOICE_ID>";
			$xml .= "</DATA>";
			$xml .= "</FBAPI>";

			try {

				$result = $this->apicall( $xml );

			} catch ( Exception $e ) {

				$this->logger->add( $e->getMessage() );

				return;

			}
			$response = new SimpleXMLElement( $result );
			$is_error = isset( $response->RESPONSE->ERRORS ) ? true : false;

			if ( ! $is_error ) {
				$this->logger->add( 'END - Canceling invoice for order #' . $payment_id );
				edd_insert_payment_note( $payment_id, 'FastBill Invoice ID: ' . $fb_invoice_id . ' canceled due to refunding purchase.' );
			} else {
				// An error occured
				$error_string = __( 'There was an error canceling an invoice in FastBill:', 'edd-fastbill' ) . "\n" .
				                __( 'Error: ', 'edd-fastbill' ) . $response->RESPONSE->ERRORS->ERROR;
				$this->logger->add( $error_string );
			}
		} else {
			// no invoice id so exit.
			return;
		}
	}

	/**
	 * Cancel invoice in FastBill for the given order.
	 *
	 * @param  $payment_id
	 *
	 * @access public
	 * @return void
	 *
	 **/
	public function invoice_delete( $payment_id ) {

		$fb_invoice_id = (int) get_post_meta( $payment_id, '_fastbill_invoice_id', true );

		if ( $fb_invoice_id > 0 ) {
			// there is an invoice ID, so cancel invoice

			$this->logger->add( 'START - Delete invoice in FastBill for invoice ID: ' . $fb_invoice_id );

			$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
			$xml .= "<FBAPI>";
			$xml .= "<SERVICE>invoice.delete</SERVICE>";
			$xml .= "<DATA>";
			$xml .= "<INVOICE_ID>" . $fb_invoice_id . "</INVOICE_ID>";
			$xml .= "</DATA>";
			$xml .= "</FBAPI>";

			try {

				$result = $this->apicall( $xml );

			} catch ( Exception $e ) {

				$this->logger->add( $e->getMessage() );

				return;

			}
			$response = new SimpleXMLElement( $result );
			$is_error = isset( $response->RESPONSE->ERRORS ) ? true : false;

			if ( ! $is_error ) {
				$this->logger->add( 'END - Delete invoice for order #' . $payment_id );
				edd_insert_payment_note( $payment_id, 'FastBill Invoice ID: ' . $fb_invoice_id . ' deleted due to abandonded purchase.' );
			} else {
				// An error occured
				$error_string = __( 'There was an error deleting an invoice in FastBill:', 'edd-fastbill' ) . "\n" .
				                __( 'Error: ', 'edd-fastbill' ) . $response->RESPONSE->ERRORS->ERROR;
				$this->logger->add( $error_string );
			}
		} else {
			// no invoice id so exit.
			return;
		}
	}


	/**
	 * Send created and completed invoice by mail to the customer.
	 *
	 * @param  $payment_id
	 *
	 * @access public
	 * @return void
	 *
	 **/
	public function invoice_sendbyemail( $payment_id ) {
		$fb_invoice_id = (int) get_post_meta( $payment_id, '_fastbill_invoice_id', true );

		// no invoice id so exit.
		if ( $fb_invoice_id <= 0 ) {
			return false;
		}

		// there is an invoice ID, send invoice to customer
		$this->logger->add( 'START - Sending invoice ID: ' . $fb_invoice_id . ' to customer by email.' );

		// Prepare email
		$payment_meta   = get_post_meta( $payment_id, '_edd_payment_meta', true );
		$customer_email = ( isset( $payment_meta['user_info']['email'] ) ) ? $payment_meta['user_info']['email'] : null;

		// customer email not found
		if ( ! $customer_email ) {
			$this->logger->add( __( 'Error: ', 'edd-fastbill' ) . 'Customer email address was not found.' );

			return false;
		}

		// customer email not valid
		if ( ! is_email( $customer_email ) ) {
			$this->logger->add( __( 'Error: ', 'edd-fastbill' ) . 'Customer email address is not valid.' );

			return false;
		}

		// Build request
		$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
		$xml .= "<FBAPI>";
		$xml .= "<SERVICE>invoice.sendbyemail</SERVICE>";
		$xml .= "<DATA>";
		$xml .= "<INVOICE_ID>" . $fb_invoice_id . "</INVOICE_ID>";
		$xml .= "<RECIPIENT>";
		$xml .= "<TO>" . $customer_email . "</TO>";
		$xml .= "</RECIPIENT>";
		$xml .= "<RECEIPT_CONFIRMATION>1</RECEIPT_CONFIRMATION>";
		$xml .= "</DATA>";
		$xml .= "</FBAPI>";

		try {

			$result = $this->apicall( $xml );

		} catch ( Exception $e ) {

			$this->logger->add( $e->getMessage() );

			return false;

		}
		$response = new SimpleXMLElement( $result );
		$success  = isset( $response->RESPONSE->ERRORS ) ? false : true;

		if ( $success ) {
			$this->logger->add( 'END - Invoice for order #' . $payment_id . ' sent to customer.' );
		} else {
			// An error occured
			$error_string = __( 'There was an error sending an invoice via FastBill:', 'edd-fastbill' ) . "\n" .
			                __( 'Error: ', 'edd-fastbill' ) . $response->RESPONSE->ERRORS->ERROR;
			$this->logger->add( $error_string );
		}

		return $success;
	}

	/**
	 * Set invoice to payed in FastBill for the given order.
	 *
	 * @param  $payment_id
	 *
	 * @access public
	 * @return void
	 *
	 **/
	public function payment_create( $payment_id ) {

		$fb_invoice_id = (int) get_post_meta( $payment_id, '_fastbill_invoice_id', true );

		if ( $fb_invoice_id > 0 ) {
			// there is an invoice ID, so create payment

			$this->logger->add( 'START - Creating payment in FastBill for invoice ID: ' . $fb_invoice_id );

			$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
			$xml .= "<FBAPI>";
			$xml .= "<SERVICE>invoice.setpaid</SERVICE>";
			$xml .= "<DATA>";
			$xml .= "<INVOICE_ID>" . $fb_invoice_id . "</INVOICE_ID>";
			$xml .= "</DATA>";
			$xml .= "</FBAPI>";

			try {

				$result = $this->apicall( $xml );

			} catch ( Exception $e ) {

				$this->logger->add( $e->getMessage() );

				return;

			}
			$response = new SimpleXMLElement( $result );
			$is_error = isset( $response->RESPONSE->ERRORS ) ? true : false;

			if ( ! $is_error ) {
				$this->logger->add( 'END - Creating payment for order #' . $payment_id );
			} else {
				// An error occured
				$error_string = __( 'There was an error creating a payment in FastBill:', 'edd-fastbill' ) . "\n" .
				                __( 'Error: ', 'edd-fastbill' ) . $response->RESPONSE->ERRORS->ERROR;
				$this->logger->add( $error_string );
			}
		} else {
			// no invoice id so exit.
			return;
		}
	}

	/**
	 * Receive all available templates from Fastbill
	 *
	 * @access public
	 * @return array
	 *
	 **/
	public function templates_get() {

		$cache_key        = 'edd_fastbill_templates';
		$cache_time       = 60 * 60;
		$cached_templates = get_transient( $cache_key );

		if ( $cached_templates ) {
			return $cached_templates;
		}

		$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
		$xml .= "<FBAPI>";
		$xml .= "<SERVICE>template.get</SERVICE>";
		$xml .= "<FILTER/>";
		$xml .= "</FBAPI>";

		try {

			$result = $this->apicall( $xml );

		} catch ( Exception $e ) {

			$this->logger->add( $e->getMessage() );

			return false;
		}
		$response = new SimpleXMLElement( $result );

		if ( ! isset( $response->RESPONSE->ERRORS ) ) {
			if ( isset( $response->RESPONSE->TEMPLATES ) ) {
				$templates = json_decode( json_encode( $response->RESPONSE->TEMPLATES ), true );
				set_transient( $cache_key, $templates, $cache_time );

				return $templates;
			} else {
				return 0;
			}

		} else {
			// An error occured
			$error_string = __( 'There was an error when receiving templates from FastBill:', 'edd-fastbill' ) . "\n" .
			                __( 'Error: ', 'edd-fastbill' ) . $response->RESPONSE->ERRORS->ERROR;

			$this->logger->add( $error_string );

			return 0;
		}
	}

	/**
	 * save url to invoice
	 *
	 * @param $payment_id
	 * @param $fb_invoice_id
	 *
	 * @return bool|string
	 */
	private function _add_invoice_url( $payment_id, $fb_invoice_id ) {
		global $edd_options;

		if ( ! isset( $edd_options['drubba_fb_fastbill_online_invoice'] ) ) {
			return false;
		}

		$invoice = $this->invoice_get( $fb_invoice_id );
		if ( empty( $invoice->DOCUMENT_URL ) ) {
			return false;
		}

		$fb_document_url = (string) $invoice->DOCUMENT_URL;
		update_post_meta( $payment_id, '_fastbill_document_url', $fb_document_url );
		edd_insert_payment_note( $payment_id, 'FastBill Document URL: ' . $fb_document_url );

		return $fb_document_url;
	}

	/**
	 * Get selected template id by looping the available templates
	 *
	 * @access private
	 *
	 * @param string $payment_type
	 *
	 * @return string/bol
	 *
	 **/
	private function _get_template_id( $payment_type ) {
		global $edd_options;

		$selected_template = '';

		if ( $payment_type == 'direct' ) {
			$selected_template = isset( $edd_options['drubba_fb_fastbill_invoice_template'] ) ? $edd_options['drubba_fb_fastbill_invoice_template'] : '';
		}

		if ( $payment_type == 'advance' ) {
			$selected_template = isset( $edd_options['drubba_fb_fastbill_invoice_template_advance_payment'] ) ? $edd_options['drubba_fb_fastbill_invoice_template_advance_payment'] : '';
		}

		if ( empty( $selected_template ) ) {
			return false;
		}

		return $selected_template;
	}

	/**
	 * Send an XML request to the FastBill API
	 *
	 * @param $xml
	 *
	 * @return string
	 * @throws Exception
	 *
	 **/
	public function apicall( $xml ) {

		global $edd_options;

		if ( ! isset( $edd_options['drubba_fb_fastbill_email'] ) || ! isset( $edd_options['drubba_fb_fastbill_api_key'] ) ) {
			throw new Exception( 'An error occured: Credentials are not set' );
		}

		$this->logger->add( "SENDING XML:\n" . $xml );

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
			$this->logger->add( 'Something went wrong: ' . $response->get_error_message() );
			throw new Exception( 'An error occured: ' . wp_remote_retrieve_response_message( $response ) );
		}

		$result = wp_remote_retrieve_body( $response );

		$this->logger->add( "RESPONSE XML: " . $result );

		return $result;
	}
}
