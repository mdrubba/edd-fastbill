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
	$link_available = true;
	if ( ! $link_available ) {
		echo '<td>-</td>';

		return;
	}

	echo '<td class="edd_invoice"><a class="edd_invoice_link" title="' . __( 'Download Invoice', 'edd-fastbill' ) . '" href="' . esc_url( '#' ) . '">' . __( 'Download Invoice', 'edd-fastbill' ) . '</td>';
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
	// TODO implement check if lin is available
	$link_available = true;
	if ( ! $link_available ) {
		return;
	}

	$purchase_data = edd_get_payment_meta( $payment->ID );
	?>
	<tr>
		<td><strong><?php _e( 'Invoice', 'edd-fastbill' ); ?>:</strong></td>
		<td>
			<a class="edd_invoice_link" title="<?php _e( 'Download Invoice', 'edd-fastbill' ); ?>" href="<?php echo esc_url( '#' ); ?>"><?php _e( 'Download Invoice', 'edd-fastbill' ); ?></a>
		</td>
	</tr>
	<?php
}

add_action( 'edd_payment_receipt_after', 'drubba_fastbill_receipt_shortcode_link', 10 );