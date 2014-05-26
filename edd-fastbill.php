<?php
/*
Plugin Name: Easy Digital Downloads - FastBill Integration
Plugin URI: http://markusdrubba.de
Description: Integrates <a href="https://easydigitaldownloads.com/" target="_blank">Easy Digital Downloads</a> with the <a href="http://www.fastbill.com" target="_blank">FastBill - fast money</a> accounting software. 
Author: Markus Drubba
Author URI: http://markusdrubba.de
Version: 1.0.0

Easy Digital Downloads is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or 
any later version.

Easy Digital Downloads is distributed in the hope that it will be useful, 
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Easy Digital Downloads. If not, see <http://www.gnu.org/licenses/>.
*/


/*
|--------------------------------------------------------------------------
| UPDATER
|--------------------------------------------------------------------------
*/

define( 'DRUBBAFASTBILL_STORE_API_URL', 'https://easydigitaldownloads.com' );
define( 'DRUBBAFASTBILL_PRODUCT_NAME', 'FastBill Integration' );

/**
 * Licensing / Updates
 *
 * @access        private
 * @since         1.0.0
 * @return        void
 */
function drubba_fastbill_updater() {
	if ( ! class_exists( 'EDD_SL_Plugin_Updater' ) )
		include( DRUBBAFASTBILL_DIR . 'includes/EDD_SL_Plugin_Updater.php' );

	// get license key
	$license_key = isset( $edd_options['drubba_fb_license_key'] ) ? trim( $edd_options['drubba_fb_license_key'] ) : '';

	// setup the updater
	$edd_updater = new EDD_SL_Plugin_Updater( DRUBBAFASTBILL_STORE_API_URL, __FILE__, array(
			'version'   => '1.0.0',
			'license' 	=> $license_key,
			'item_name' => DRUBBAFASTBILL_PRODUCT_NAME,
			'author'    => 'Markus Drubba'
		)
	);
}

add_action( 'admin_init', 'drubba_fastbill_updater' );

/*
|--------------------------------------------------------------------------
| LICENCE ACTIVATION
|--------------------------------------------------------------------------
*/

/**
 * activate the license key for automatic upgrades
 *
 * @access        private
 * @since         1.0.0
 * @return        void
 */
function drubba_fastbill_activate_license() {
	global $edd_options;
	if ( ! isset( $_POST['edd_settings_misc'] ) )
		return;
	if ( ! isset( $_POST['edd_settings_misc']['drubba_fb_license_key'] ) )
		return;

	if ( get_option( 'drubba_fb_license_active' ) == 'valid' )
		return;

	$license = sanitize_text_field( $_POST['edd_settings_misc']['drubba_fb_license_key'] );

	// data for API request
	$api_params = array(
		'edd_action' => 'activate_license',
		'license'    => $license,
		'item_name'  => urlencode( DRUBBAFASTBILL_PRODUCT_NAME )
	);

	// Call API
	$response = wp_remote_get( add_query_arg( $api_params, EDD_MAILCHIMP_STORE_API_URL ) );

	// make sure the response came back without errors
	if ( is_wp_error( $response ) )
		return false;

	// decode the license data
	$license_data = json_decode( wp_remote_retrieve_body( $response ) );

	update_option( 'drubba_fb_license_active', $license_data->license );
}

add_action( 'admin_init', 'drubba_fastbill_activate_license' );

/*
|--------------------------------------------------------------------------
| CONSTANTS
|--------------------------------------------------------------------------
*/

// plugin folder url
if ( ! defined( 'DRUBBAFASTBILL_URL' ) ) {
	define( 'DRUBBAFASTBILL_URL', plugin_dir_url( __FILE__ ) );
}
// plugin folder path
if ( ! defined( 'DRUBBAFASTBILL_DIR' ) ) {
	define( 'DRUBBAFASTBILL_DIR', plugin_dir_path( __FILE__ ) );
}
// plugin root file
if ( ! defined( 'DRUBBAFASTBILL_FILE' ) ) {
	define( 'DRUBBAFASTBILL_FILE', __FILE__ );
}

/*
|--------------------------------------------------------------------------
| INCLUDES
|--------------------------------------------------------------------------
*/

include_once( DRUBBAFASTBILL_DIR . 'includes/register-settings.php' );
include_once( DRUBBAFASTBILL_DIR . 'includes/fastbill-functions.php' );
include_once( DRUBBAFASTBILL_DIR . 'includes/payment-actions.php' );