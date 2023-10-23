<?php
/*
Plugin Name: AllAround Mini Store
Plugin URI: https://allaround.co.il/
Description: AllAround User Management and Mini Store.
Version: 1.0
Text Domain: mini-store
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


class AlrndCustomerManagement {

    /**
     * Plugin version.
     *
     * @var string
     */
    const version = '0.1';

    /**
	 * Call this method to get the singleton
	 *
	 * @return AlrndCustomerManagement|null
	 */
	public static function instance() {

		static $instance = null;
		if ( is_null( $instance ) ) {
			$instance = new AlrndCustomerManagement();
		}

		return $instance;
	}

	public function __construct() {

        $this->define_constanst();

		register_activation_hook( __FILE__, array( $this, 'activation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );

		//run on uninstall
		register_uninstall_hook( __FILE__, array( 'AlrndCustomerManagement', 'uninstall' ) );

        add_action( 'plugins_loaded', array( $this, 'init' ) );

		load_plugin_textdomain( 'allaroundminilng', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}

    /**
	 * Init
	 */
	public function init() {
		require_once(AlRNDCM_PATH . '/includes/functions.php');
		require_once(AlRNDCM_PATH . '/includes/template-generator.php');
		require_once(AlRNDCM_PATH . '/includes/public-functions.php');
		require_once(AlRNDCM_PATH . '/includes/class-add-new-customer.php');
		require_once(AlRNDCM_PATH . '/includes/class-all-customers.php');
		require_once(AlRNDCM_PATH . '/includes/class-handle-submissions.php');
		require_once(AlRNDCM_PATH . '/includes/ajax-handler.php');
		require_once(AlRNDCM_PATH . '/includes/enqueue-scripts.php');
		require_once(AlRNDCM_PATH . '/includes/mockup-generator/mockup.php');
		require_once(AlRNDCM_PATH . '/admin/class-acm-admin.php');
	}

	/**
	 *  Runs on plugin uninstall.
	 *  a static class method or function can be used in an uninstall hook
	 *
	 * @since 0.1
	 */
	public static function uninstall() {

	}


	/**
	 * plugin activation
	 *
	 * @return void
	 */
	public function activation() {
		// Specify the full path to the folder
		$folder_path = AlRNDCM_UPLOAD_DIR;

		// Check if the folder doesn't exist yet
		if ( ! is_dir($folder_path) ) {
			// Create the folder
			mkdir($folder_path, 0755);

			// Optionally, you can create an index.php file to protect the folder
			$index_file = $folder_path . '/index.php';
			if (!file_exists($index_file)) {
				file_put_contents($index_file, '<?php // Silence is golden.');
			}
		}
	}

	public function upload_dir() {
		$upload_dir = wp_upload_dir();

		// Define the folder name you want to create
		$folder_name = AlRNDCM_UPLOAD_FOLDER;

		// Specify the full path to the folder
		$folder_path = $upload_dir['basedir'] . '/' . $folder_name;

		return $folder_path;
	}

	/**
	 * plugin activation
	 *
	 * @return void
	 */
	public function deactivation() {

	}

    /**
     * Define require constansts
     * 
     * @return void
     */
    public function define_constanst(){
		define( 'AlRNDCM_VERSION', self::version );
		define( 'AlRNDCM_UPLOAD_FOLDER', 'alaround-mockup' );
		define( 'AlRNDCM_UPLOAD_DIR', self::upload_dir() );
		define( "AlRNDCM_URL", plugins_url( "/" , __FILE__ ) );
		define( "AlRNDCM_PATH", plugin_dir_path( __FILE__ ) );
    }

	
}

( new AlrndCustomerManagement() );




