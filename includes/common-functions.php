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

    // EDD placeholders
    $payment_meta = get_post_meta( $payment_id, '_edd_payment_meta', true );

    if ( isset( $payment_meta['user_info']['first_name'] ) )
        $string = str_replace('{name}', $payment_meta['user_info']['first_name'], $string);

    if ( isset( $payment_meta['user_info']['first_name'] ) && isset( $payment_meta['user_info']['last_name'] ) )
        $string = str_replace('{fullname}', $payment_meta['user_info']['first_name'] . ' ' . $payment_meta['user_info']['last_name'], $string);

    if ( isset( $payment_meta['user_info']['email'] ) )
        $string = str_replace('{user_email}', $payment_meta['user_info']['email'], $string);

    if ( isset( $payment_meta['user_info']['address'] ) && sizeof($payment_meta['user_info']['address']) != 0 ) {
        $address_array = $payment_meta['user_info']['address'];
        $address = '';

        if ( isset( $address_array['line1'] ) && $address_array['line1'] != '')
            $address .= $address_array['line1'];

        if ( isset( $address_array['line2'] ) && $address_array['line2'] != '')
            $address .= ', ' . $address_array['line2'];

        if ( isset( $address_array['zip'] ) && $address_array['zip'] != '')
            $address .= ', ' . $address_array['zip'];

        if ( isset( $address_array['city'] ) && $address_array['city'] != '')
            $address .= ', ' . $address_array['city'];

        $string = str_replace('{billing_address}', $address, $string);
    }

    //if ( isset( $payment_meta['user_info']['email'] ) )
        //$string = str_replace('{price}', $payment_meta['email'], $string);

    $string = str_replace('{payment_id}', $payment_id, $string);

    // Fastbill placeholders
    $fb_invoice_id = (int) get_post_meta( $payment_id, '_fastbill_invoice_id', true );

    if ( $fb_invoice_id > 0 )
        $string = str_replace('{fastbill_invoice_id}', $fb_invoice_id, $string);

    return $string;
}