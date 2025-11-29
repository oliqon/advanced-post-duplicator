<?php
/**
 * Plugin Name: Advanced Post Duplicator
 * Plugin URI: https://wordpress.org/plugins/advanced-post-duplicator
 * Description: Duplicate posts, pages, and custom post types with full Elementor and WooCommerce support. Includes bulk duplication and WordPress Multisite cross-site duplication.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: advanced-post-duplicator
 * Domain Path: /languages
 * Network: true
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'APD_VERSION', '1.0.0' );
define( 'APD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'APD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'APD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class Advanced_Post_Duplicator {

	/**
	 * Instance of this class
	 *
	 * @var Advanced_Post_Duplicator
	 */
	private static $instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return Advanced_Post_Duplicator
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files
	 */
	private function includes() {
		require_once APD_PLUGIN_DIR . 'includes/class-duplicator.php';
		require_once APD_PLUGIN_DIR . 'admin/class-settings.php';
		require_once APD_PLUGIN_DIR . 'admin/class-list-table.php';
		require_once APD_PLUGIN_DIR . 'admin/class-editor-integration.php';

		// Multisite support files
		if ( is_multisite() ) {
			require_once APD_PLUGIN_DIR . 'includes/class-multisite.php';
			require_once APD_PLUGIN_DIR . 'includes/class-cross-site-duplicator.php';
			require_once APD_PLUGIN_DIR . 'includes/class-slug-resolver.php';
			require_once APD_PLUGIN_DIR . 'includes/class-media-handler.php';
			require_once APD_PLUGIN_DIR . 'includes/class-taxonomy-migrator.php';
			require_once APD_PLUGIN_DIR . 'includes/class-meta-migrator.php';
			require_once APD_PLUGIN_DIR . 'includes/class-error-handler.php';
			require_once APD_PLUGIN_DIR . 'admin/class-multisite-ui.php';
			require_once APD_PLUGIN_DIR . 'admin/class-ajax-handlers.php';
		}
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );
		
		// Activation and deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Load plugin textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'advanced-post-duplicator',
			false,
			dirname( APD_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize plugin components
	 */
	public function init() {
		// Initialize admin components
		if ( is_admin() ) {
			APD_Settings::get_instance();
			APD_List_Table::get_instance();
			APD_Editor_Integration::get_instance();

			// Initialize multisite components
			if ( is_multisite() && APD_Multisite::user_can_cross_site_duplicate() ) {
				APD_Multisite_UI::get_instance();
				APD_Ajax_Handlers::get_instance();
			}
		}
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Set default options
		$default_options = array(
			'post_status'  => 'same',
			'post_date'    => 'duplicate',
			'offset_date'  => 0,
			'offset_days'  => 1,
			'offset_hours' => 1,
			'offset_minutes' => 1,
			'offset_seconds' => 0,
			'offset_direction' => 'older',
		);

		if ( ! get_option( 'apd_settings' ) ) {
			add_option( 'apd_settings', $default_options );
		}
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Clean up if needed
	}
}

/**
 * Initialize the plugin
 */
function apd_init() {
	return Advanced_Post_Duplicator::get_instance();
}

// Start the plugin
apd_init();

