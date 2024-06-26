<?php

class MLBackgroundInit {

	/**
	 * Init
	 */
	public function __construct() {
		if( ! class_exists( 'WP_Async_Request') ) {
			require_once plugin_dir_path( __FILE__ ) . 'libs/wp-async-request.php';
		}
		if( ! class_exists( 'WP_Background_Process') ) {
			require_once plugin_dir_path( __FILE__ ) . 'libs/wp-background-process.php';
		}
		
		require_once plugin_dir_path( __FILE__ ) . 'classes/class-data.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/class-product-process.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/class-user-process.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/class-bulk-process.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-generation-options.php';
	}

}

new MLBackgroundInit();