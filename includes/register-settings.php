<?php
/**
 * Register Settings
 *
 * @package     FastBill Integration for Easy Digital Downloads
 * @subpackage  Register Settings
 * @copyright   Copyright (c) 2013, Markus Drubba (dev@markusdrubba.de)
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.0
 */

/**
 * Register Settings
 *
 * Registers the required settings for the plugin and adds them to the 'Misc' tab.
 *
 * @access      private
 * @since       1.0.0
 * @return      void
 */
function drubba_fb_register_settings( $settings ) {

	$settings[] = array(
		'id'   => 'drubba_fastbill',
		'name' => '<strong>' . __( 'FastBill Settings', 'edd' ) . '</strong>',
		'desc' => '',
		'type' => 'header',
	);
	$settings[] = array(
		'id'   => 'drubba_fb_license_key',
		'name' => __( 'License Key', 'edd' ),
		'desc' => __( 'Enter your license for EDD FastBill Integration to receive automatic upgrades', 'edd' ),
		'type' => 'text',
	);
	$settings[] = array(
		'id'   => 'drubba_fb_fastbill_email',
		'name' => __( 'FastBill Email', 'edd' ),
		'desc' => __( 'The Email you use to login to your FastBill Account', 'edd' ),
		'type' => 'text',
	);
	$settings[] = array(
		'id'   => 'drubba_fb_fastbill_api_key',
		'name' => __( 'FastBill API Key', 'edd' ),
		'desc' => __( 'Get this from your FastBill settings.  Account > Settings', 'edd' ),
		'type' => 'text',
	);
	$settings[] = array(
		'id'   => 'drubba_fb_fastbill_invoice',
		'name' => __( 'Auto create invoice', 'edd' ),
		'desc' => __( 'Check this box to create a invoice when the order is placed', 'edd' ),
		'type' => 'checkbox',
	);
	$settings[] = array(
		'id'   => 'drubba_fb_fastbill_payments',
		'name' => __( 'Auto create payment', 'edd' ),
		'desc' => __( 'Check the box to create a payment when order is placed. Requires Invoice Status COMPLETE.', 'edd' ),
		'type' => 'checkbox',
	);
	$settings[] = array(
		'id'      => 'drubba_fb_fastbill_invoice_status',
		'name'    => __( 'Invoice Status', 'edd' ),
		'desc'    => __( 'Status for the invoices being created in FastBill.', 'edd' ),
		'std'     => 'draft',
		'type'    => 'select',
		'options' => array(
			'draft'    => __( 'Draft', 'edd' ),
			'complete' => __( 'Complete', 'edd' )
		)
	);
	$settings[] = array(
		'id'      => 'drubba_fb_fastbill_country_code',
		'name'    => __( 'Country Code', 'edd' ),
		'desc'    => __( 'The default country of your customers.', 'edd' ),
		'std'     => 'de',
		'type'    => 'select',
		'options' => edd_get_country_list()
	);
	$settings[] = array(
		'id'   => 'drubba_fb_fastbill_debug_on',
		'name' => __( 'Enable Debug', 'edd' ),
		'desc' => __( 'Check the box to create a log file for debug purposes.', 'edd' ),
		'type' => 'checkbox',
	);
	return $settings;

}

add_filter( 'edd_settings_misc', 'drubba_fb_register_settings', 10, 1 );