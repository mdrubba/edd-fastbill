=== EDD - FastBill Integration ===
Contributors: drumba
Donate link: https://www.paypal.me/markusdrubba
Tags: Digital Downloads, EDD, Fastbill, Accounting, Invoice
Requires at least: 4.5
Tested up to: 4.7
Stable tag: 1.4.3
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Automatically create invoices, clients, and payments in your FastBill account when purchases are made.

== Description ==

[FastBill](https://www.fastbill.com/) is a web based invoicing system for tracking clients, payments, projects, and more.
This extension connects an EDD store to a FastBill account. When a download is purchased an Invoice, Payment and Client are created in FastBill.
With this integration, you will save a significant amount of time on keeping your records up to date.

Store owners can set the status of the FastBill invoice to draft or completed, sending Email with the invoice, let users Download the invoice from there Users Account..

== Installation ==

* Unzip the files and upload the folder into your plugins folder (wp-content/plugins/)
* Activate the plugin in your WordPress admin area.

== Screenshots ==

1. FastBill Settings
2. Payment column

== Configuration ==

* Navigate to Downloads > Settings
* Click on the tab labeled "Extensions"
* Open the Section named "FastBill"
* Configure these settings and add your FastBill credentials
* You find the FastBill credentials in the main settings, of your FastBill account


== Changelog ==

= 2016-12-06 1.4.3 =

* New: Cancel Payments when customer get a refund
* Tweak: debug logs are written to the database

= 2016-07-04 1.4.2 =

* Fix: optimize usage of tax country when creating invoice

= 2016-07-04 1.4.1 =

* Update Translation implementation

= 2016-06-20 1.4.0 =

* Remove EDD Licencing, Update for WordPress.org Plugin directory

= 2016-04-06 1.3.1 =

* Fix: minor fix to settings

= 2016-03-01 1.3.0 =

* New: send invoice via email
* New: download invoice
* New: select invoice template
* Tweak: improved settings section

= 2015-08-07  1.2.0 =

* Added support for EDD EU VAT Extension
* Send VAT ID from EU VAT Extension to FastBill

= 2014-06-10  1.1.0 =

* Added support for EDD Checkout Fields Manager
* Added support for billing address
* Added EDD_License_Handler classes (after updating the plugin, you need to set your licence key again)
* Added german translation
* Fix Discount calculation
* Fix Tax calculation
* Move settings from sisc to extensions tab
* Updating EDD_SL_Plugin_Updater

= 2013-05-30  1.0.0 =

* Initial release