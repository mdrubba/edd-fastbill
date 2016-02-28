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


/**
 * Complete a purchase
 *
 * Exports invoice and payment to FastBill when an order is complete
 * Triggered by the edd_update_payment_status() function.
 *
 * @param        int    $payment_id the ID number of the payment
 * @param        string $new_status the status of the payment, probably "publish"
 * @param        string $old_status the status of the payment prior to being marked as "complete", probably "pending"
 *
 * @access      private
 * @since       1.0.0
 * @return      void
 */

function drubba_fastbill_complete_purchase( $payment_id, $new_status, $old_status ) {

	global $edd_options;

	if ( $new_status == 'publish' ) {
		drubba_fastbill_create_invoice( $payment_id );
	}

	$create_payment = isset( $edd_options['drubba_fb_fastbill_payments'] ) ? $edd_options['drubba_fb_fastbill_payments'] : 0;
	$invoice_status = isset( $edd_options['drubba_fb_fastbill_invoice_status'] ) ? $edd_options['drubba_fb_fastbill_invoice_status'] : 'draft';
	if ( $create_payment == 1 && $invoice_status == 'complete' && $new_status == 'publish' ) {
		drubba_fastbill_create_payment( $payment_id );
	}

	$send_invoice = isset( $edd_options['drubba_fb_fastbill_sendbyemail'] ) ? $edd_options['drubba_fb_fastbill_sendbyemail'] : 0;
	if ( $send_invoice == 1 && $invoice_status == 'complete' && $new_status == 'publish' ) {
		drubba_fastbill_invoice_sendbyemail( $payment_id );
	}
}

add_action( 'edd_update_payment_status', 'drubba_fastbill_complete_purchase', 10, 3 );