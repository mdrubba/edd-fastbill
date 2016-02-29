<?php


/**
 * drubba_fastbill_purchase_history_header
 *
 * Appends to the table header (<thead>) on the Purchase History page for the
 * invoice column to be displayed
 *
 * @since 1.0
 */
function drubba_fastbill_purchase_history_header() {
	global $edd_options;

	if ( ! isset( $edd_options['drubba_fb_fastbill_online_invoice'] ) )
		return;

	echo '<th class="edd-fb-invoice">' . __( 'Invoice', 'edd-fastbill' ) . '</th>';
}

add_action( 'edd_purchase_history_header_after', 'drubba_fastbill_purchase_history_header' );

/**
 * drubba_fastbill_purchase_history_link
 *
 * Adds the invoice link to the [purchase_history] shortcode underneath the
 * previously created Invoice header
 *
 * @since 1.0
 *
 * @param int   $post_id       Payment post ID
 * @param array $purchase_data All the purchase data
 */
function drubba_fastbill_purchase_history_link( $post_id, $purchase_data ) {
	global $edd_options;

	if ( ! isset( $edd_options['drubba_fb_fastbill_online_invoice'] ) )
		return;

	$fb_document_url = get_post_meta( $post_id, '_fastbill_document_url', true );

	if ( empty( $fb_document_url ) ) {
		echo '<td>-</td>';

		return;
	}

	echo '<td class="edd_invoice"><a class="edd_invoice_link" title="' . __( 'Download Invoice', 'edd-fastbill' ) . '" href="' . esc_url( $fb_document_url ) . '">' . __( 'Download Invoice', 'edd-fastbill' ) . '</td>';
}

add_action( 'edd_purchase_history_row_end', 'drubba_fastbill_purchase_history_link', 10, 2 );

/**
 * drubba_fastbill_receipt_link
 *
 * Adds the invoice link to the [edd_receipt] shortcode
 *
 * @since 1.0.4
 *
 * @param object $payment All the payment data
 */
function drubba_fastbill_receipt_shortcode_link( $payment ) {
	global $edd_options;

	if ( ! isset( $edd_options['drubba_fb_fastbill_online_invoice'] ) )
		return;

	$fb_document_url = get_post_meta( $payment->ID, '_fastbill_document_url', true );

	if ( empty( $fb_document_url ) )
		return;

	?>
	<tr>
		<td><strong><?php _e( 'Invoice', 'edd-fastbill' ); ?>:</strong></td>
		<td>
			<a class="edd_invoice_link" title="<?php _e( 'Download Invoice', 'edd-fastbill' ); ?>" href="<?php echo esc_url( $fb_document_url ); ?>"><?php _e( 'Download Invoice', 'edd-fastbill' ); ?></a>
		</td>
	</tr>
	<?php
}

add_action( 'edd_payment_receipt_after', 'drubba_fastbill_receipt_shortcode_link', 10 );

