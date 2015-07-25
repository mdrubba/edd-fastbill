<?php
/*
Plugin Name: Easy Digital Downloads - FastBill Integration
Plugin URI: https://easydigitaldownloads.com/extensions/fastbill-integration/?ref=2325
Description: Integrates <a href="https://easydigitaldownloads.com/" target="_blank">Easy Digital Downloads</a> with the <a href="http://www.fastbill.com" target="_blank">FastBill - fast money</a> accounting software. 
Author: Markus Drubba
Author URI: http://markusdrubba.de
Version: 1.1.0

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
| CONSTANTS
|--------------------------------------------------------------------------
*/
define( 'DRUBBAFASTBILL_STORE_API_URL', 'https://easydigitaldownloads.com' );
define( 'DRUBBAFASTBILL_PRODUCT_NAME', 'FastBill Integration' );
define( 'DRUBBAFASTBILL_DIR', plugin_dir_path( __FILE__ ) );


/*
|--------------------------------------------------------------------------
| LICENCE / UPDATING
|--------------------------------------------------------------------------
*/
if ( class_exists( 'EDD_License' ) ) {
	$license = new EDD_License( __FILE__, DRUBBAFASTBILL_PRODUCT_NAME, '1.1.0', 'Markus Drubba' );
}


/*
|--------------------------------------------------------------------------
| LOCALIZATION
|--------------------------------------------------------------------------
*/
function drubba_fastbill_localization() {
	load_plugin_textdomain( 'edd-fastbill', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

add_action( 'plugins_loaded', 'drubba_fastbill_localization' );

/*
|--------------------------------------------------------------------------
| INCLUDES
|--------------------------------------------------------------------------
*/

include_once( DRUBBAFASTBILL_DIR . 'includes/register-settings.php' );
include_once( DRUBBAFASTBILL_DIR . 'includes/fastbill-functions.php' );
include_once( DRUBBAFASTBILL_DIR . 'includes/payment-actions.php' );
include_once( DRUBBAFASTBILL_DIR . 'includes/common-functions.php' );