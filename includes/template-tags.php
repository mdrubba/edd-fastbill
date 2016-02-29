<?php
/**
 * Template Tags
 *
 * Creates and renders the additional template tags for the Fastbill document url.
 *
 * @package     FastBill Integration for Easy Digital Downloads
 * @subpackage  Template Tags
 * @copyright   Copyright (c) 2013, Markus Drubba (dev@markusdrubba.de)
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.0
 */

/**
 * Register email tag
 *
 * @access      private
 * @since       1.0
 * @return      void
 */
function drubba_fastbill_register_email_tag() {
    global $edd_options;

    if ( ! isset( $edd_options['drubba_fb_fastbill_online_invoice'] ) )
        return;

    edd_add_email_tag( 'fastbill_invoice', __( 'Creates a link to the downloadable Fastbill invoice', 'edd-fastbill' ), 'drubba_fastbill_email_template_tags' );
}
add_action( 'edd_add_email_tags', 'drubba_fastbill_register_email_tag' );

/**
 * Email Template Tags
 *
 * Additional template tags for the Purchase Receipt.
 *
 * @since       1.0
 * @return      string Fastbill document link.
 */

function drubba_fastbill_email_template_tags( $payment_id ) {

    $fb_document_url = get_post_meta( $payment_id, '_fastbill_document_url', true );

    if ( empty( $fb_document_url ) )
        return null;

    return '<a title="' . __( 'Download Invoice', 'edd-fastbill' ) . '" href="' . esc_url( $fb_document_url ) . '">' . __( 'Download Invoice', 'edd-fastbill' ) . '</a>';
}