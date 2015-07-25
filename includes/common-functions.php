<?php
/**
 * drubba_fastbill_replace_placeholder_values()
 *
 * Search for fastbill and/or EDD placeholders and replace values
 *
 * @param  $payment_id
 *
 * @access public
 * @return string
 *
 **/
function drubba_fastbill_replace_placeholder_values( $string, $payment_id ) {

    // Fastbill placeholders
    $fb_invoice_id = (int) get_post_meta( $payment_id, '_fastbill_invoice_id', true );

    if ( $fb_invoice_id > 0 ) {
        $string = str_replace('{fastbill_invoice_id}', $fb_invoice_id, $string);
    }

    return $string;
}