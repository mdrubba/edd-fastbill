<?php

namespace drumba\EDD\FastBill\Helper;

class Logger {
	private $option_name;

	/**
	 * Logger constructor.
	 *
	 * @param string $option_name
	 */
	public function __construct( $option_name ) {
		$this->option_name = $option_name;
	}

	/**
	 * Add data to a log
	 *
	 * @param  $log_string - The string to be appended to the log file.
	 *
	 * @access public
	 * @return void
	 *
	 **/
	public function add( $log_string ) {
		global $edd_options;

		if ( isset( $edd_options['drubba_fb_fastbill_debug_log'] ) && 1 == $edd_options['drubba_fb_fastbill_debug_log'] ) {

			$current_log = get_option( $this->option_name, '' );
			$log_string  = "Log Date: " . date( "r" ) . "\n" . trim( $log_string ) . "\n\n";

			$final_log = $current_log . $log_string;

			update_option( $this->option_name, $final_log );

		}
	}
}
