<?php
/**
 *
 * drubba_fastbill_invoice_link
 *
 * Creates a invoice get / invoice donwload link on the payment history page in the backend
 *
 * @param $row_actions
 * @param $payment_meta
 *
 * @return array
 */
function drubba_fastbill_invoice_link( $row_actions, $payment_meta ) {
	global $edd_options;

	if ( ! isset( $edd_options['drubba_fb_fastbill_online_invoice'] ) ) {
		return $row_actions;
	}

	$fb_document_url = get_post_meta( $payment_meta->ID, '_fastbill_document_url', true );

	if ( empty( $fb_document_url ) ) {
		$nonce                      = wp_create_nonce( 'edd-fastbill-get-invoice' );
		$row_actions['invoice_get'] = '<a href="' . add_query_arg( array(
				'edd-action'  => 'set_fastbill_invoice',
				'purchase_id' => $payment_meta->ID,
				'_wpnonce'    => $nonce
			) ) . '">' . __( 'Set invoice link', 'edd-fastbill' ) . '</a>';

		return $row_actions;
	}

	$row_actions['invoice'] = '<a href="' . esc_url( $fb_document_url ) . '">' . __( 'Download Invoice', 'edd-fastbill' ) . '</a>';

	return $row_actions;
}

add_filter( 'edd_payment_row_actions', 'drubba_fastbill_invoice_link', 10, 2 );

/**
 * drubba_fastbill_invoice_email()
 *
 * Creates a resend invoice link on the payment history page in the backend
 *
 * @param $row_actions
 * @param $payment_meta
 *
 * @return mixed
 */
function drubba_fastbill_invoice_email( $row_actions, $payment_meta ) {
	global $edd_options;

	if ( ! isset( $edd_options['drubba_fb_fastbill_sendbyemail'] ) ) {
		return $row_actions;
	}

	$nonce                       = wp_create_nonce( 'edd-fastbill-mail-invoice' );
	$row_actions['mail_invoice'] = '<a href="' . add_query_arg( array(
			'edd-action'  => 'mail_fastbill_invoice',
			'purchase_id' => $payment_meta->ID,
			'_wpnonce'    => $nonce
		) ) . '">' . __( 'Resend invoice', 'edd-fastbill' ) . '</a>';

	return $row_actions;
}

add_filter( 'edd_payment_row_actions', 'drubba_fastbill_invoice_email', 10, 2 );

/**
 * Resend the Email Purchase Receipt. (This can be done from the Payment History page)
 *
 * @since 1.0
 *
 * @param array $data Payment Data
 *
 * @return void
 */
function drubba_fastbill_get_invoice_link( $data ) {

	$purchase_id = absint( $data['purchase_id'] );

	if ( empty( $purchase_id ) ) {
		return;
	}

	if ( ! is_admin() || ! isset( $data['_wpnonce'] ) || ! wp_verify_nonce( $data['_wpnonce'], 'edd-fastbill-get-invoice' ) ) {
		return;
	}

	$fb_invoice_id = get_post_meta( $purchase_id, '_fastbill_invoice_id', true );
	$invoice_url   = false;
	if ( $fb_invoice_id ) {
		$invoice_url = drubba_fastbill_add_invoice_url( $purchase_id, $fb_invoice_id );
	}

	$message = $invoice_url ? 'got_fastbill_invoice' : 'got_fastbill_invoice_error';

	wp_redirect( add_query_arg( array(
		'edd-message' => $message,
		'edd-action'  => false,
		'purchase_id' => false
	) ) );
	exit;
}

add_action( 'edd_set_fastbill_invoice', 'drubba_fastbill_get_invoice_link' );

/**
 * drubba_fastbill_mail_invoice()
 *
 * resend invoice via mail to customer
 *
 * @param $data
 */
function drubba_fastbill_mail_invoice( $data ) {

	$purchase_id = absint( $data['purchase_id'] );

	if ( empty( $purchase_id ) ) {
		return;
	}

	if ( ! is_admin() || ! isset( $data['_wpnonce'] ) || ! wp_verify_nonce( $data['_wpnonce'], 'edd-fastbill-mail-invoice' ) ) {
		return;
	}

	$success = drubba_fastbill_invoice_sendbyemail( $purchase_id );
	$message = $success ? 'mailed_fastbill_invoice' : 'mailed_fastbill_invoice_error';

	wp_redirect( add_query_arg( array(
		'edd-message' => $message,
		'edd-action'  => false,
		'purchase_id' => false
	) ) );
	exit;
}

add_action( 'edd_mail_fastbill_invoice', 'drubba_fastbill_mail_invoice' );

/**
 * drubba_fastbill_admin_messages()
 *
 * show admin messages for different actions
 */
function drubba_fastbill_admin_messages() {

	if ( isset( $_GET['edd-message'] ) && 'got_fastbill_invoice_error' == $_GET['edd-message'] && current_user_can( 'view_shop_reports' ) ) {
		add_settings_error( 'edd-notices', 'fastbill-invoice-retrieved-error', __( 'The invoice link could not be generated.', 'edd-fastbill' ), 'error' );
	}
	if ( isset( $_GET['edd-message'] ) && 'got_fastbill_invoice' == $_GET['edd-message'] && current_user_can( 'view_shop_reports' ) ) {
		add_settings_error( 'edd-notices', 'fastbill-invoice-retrieved', __( 'The invoice link was generated.', 'edd-fastbill' ), 'updated' );
	}

	if ( isset( $_GET['edd-message'] ) && 'mailed_fastbill_invoice_error' == $_GET['edd-message'] && current_user_can( 'view_shop_reports' ) ) {
		add_settings_error( 'edd-notices', 'fastbill-invoice-retrieved', __( 'The invoice could not be sent.', 'edd-fastbill' ), 'error' );
	}

	if ( isset( $_GET['edd-message'] ) && 'mailed_fastbill_invoice' == $_GET['edd-message'] && current_user_can( 'view_shop_reports' ) ) {
		add_settings_error( 'edd-notices', 'fastbill-invoice-retrieved', __( 'The invoice was sent.', 'edd-fastbill' ), 'updated' );
	}

	settings_errors( 'edd-notices' );
}

add_action( 'admin_notices', 'drubba_fastbill_admin_messages' );

/**
 * drubba_fastbill_get_bulk_actions()
 *
 * set bulk action action
 *
 * @param $actions
 *
 * @return mixed
 */
function drubba_fastbill_get_bulk_actions( $actions ) {
	$actions['set-invoice-link'] = __( 'Set invoice link', 'edd-fastbill' );

	return $actions;
}

add_filter( 'edd_payments_table_bulk_actions', 'drubba_fastbill_get_bulk_actions' );

/**
 * drubba_fastbill_process_bulk_action()
 *
 * process bulk actions, trigger via bulk editing dropdown
 *
 * @param $payment_id
 * @param $action
 */
function drubba_fastbill_process_bulk_action( $payment_id, $action ) {
	// Detect when a bulk action is being triggered...
	if ( 'set-invoice-link' === $action ) {
		$fb_invoice_id = get_post_meta( $payment_id, '_fastbill_invoice_id', true );
		if ( $fb_invoice_id ) {
			drubba_fastbill_add_invoice_url( $payment_id, $fb_invoice_id );
		}
	}
}

add_action( 'edd_payments_table_do_bulk_action', 'drubba_fastbill_process_bulk_action', 10, 2 );