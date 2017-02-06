<?php
/**
 * Payment Actions
 *
 * @package     FastBill Integration for Easy Digital Downloads
 * @subpackage  Payment Actions
 * @copyright   Copyright (c) 2013, Markus Drubba (dev@markusdrubba.de)
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.0
 */

namespace drumba\EDD\FastBill;


class Payment_Actions {

	private $edd_options;
	private $fastbill;
	private $payment_id;
	private $new_status;
	private $old_status;

	/**
	 * Payment_Actions constructor.
	 *
	 * @param FastBill $fastbill
	 */
	public function __construct( $fastbill ) {
		global $edd_options;

		$this->edd_options    = $edd_options;
		$this->fastbill       = $fastbill;
		$this->create_payment = isset( $this->edd_options['drubba_fb_fastbill_payments'] ) ? $this->edd_options['drubba_fb_fastbill_payments'] : 0;
		$this->invoice_status = isset( $this->edd_options['drubba_fb_fastbill_invoice_status'] ) ? $this->edd_options['drubba_fb_fastbill_invoice_status'] : 'draft';
		$this->send_invoice   = isset( $this->edd_options['drubba_fb_fastbill_sendbyemail'] ) ? $this->edd_options['drubba_fb_fastbill_sendbyemail'] : 0;

	}

	public function load() {
		add_action( 'edd_update_payment_status', [ $this, 'on_update_payment_status' ], 10, 3 );
		add_action( 'edd_insert_payment', [ $this, 'on_insert_payment' ], 10, 2 );
	}

	public function on_insert_payment( $payment_id, $payment_data ) {
		$this->payment_id = $payment_id;

		$this->_on_payment_status_pending();
	}

	public function on_update_payment_status( $payment_id, $new_status, $old_status ) {
		$this->payment_id = $payment_id;
		$this->new_status = $new_status;
		$this->old_status = $old_status;

		if ( $new_status == 'publish' ) {
			$this->_on_payment_status_publish();
		}

		if ( $new_status == 'refunded' ) {
			$this->_on_payment_status_refunded();
		}

		if ( $new_status == 'abandoned' || $new_status == 'revoked' ) {
			$this->_on_payment_status_canceled();
		}
	}

	private function _on_payment_status_pending() {
		if ( $this->_checkout_with_advance_payment_gateway() ) {
			// create invoice
			$this->fastbill->invoice_create( $this->payment_id, 'advance' );

			// send invoice
			if ( $this->send_invoice == 1 ) {
				$this->fastbill->invoice_sendbyemail( $this->payment_id );
			}
		}
	}

	private function _on_payment_status_publish() {


		if ( ! $this->_checkout_with_advance_payment_gateway() ) {
			// create invoice for $payment_id
			$this->fastbill->invoice_create( $this->payment_id, 'direct' );
		}

		// create payment if activated
		if ( $this->create_payment == 1 && $this->invoice_status == 'complete' ) {
			$this->fastbill->payment_create( $this->payment_id );
		}

		if ( ! $this->_checkout_with_advance_payment_gateway() ) {
			// send invoice by email if activated
			if ( $this->send_invoice == 1 && $this->invoice_status == 'complete' ) {
				$this->fastbill->invoice_sendbyemail( $this->payment_id );
			}
		}
	}

	private function _on_payment_status_refunded() {
		$this->fastbill->invoice_cancel( $this->payment_id );
	}

	private function _on_payment_status_canceled() {
		$this->fastbill->invoice_delete( $this->payment_id );
	}

	/**
	 * Check if an advance payment gateway is used on checkout
	 *
	 * @return bool
	 */
	private function _checkout_with_advance_payment_gateway() {
		$gateway = edd_get_payment_gateway( $this->payment_id );

		return array_key_exists( $gateway, $this->edd_options['drubba_fb_fastbill_advance_payment_gateways'] );
	}

}

$payment_actions = new Payment_Actions( new FastBill() );
$payment_actions->load();
