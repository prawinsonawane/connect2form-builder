<?php

namespace Connect2Form\Integrations\Core;

use Connect2Form\Integrations\Admin\AdminManager;
use Connect2Form\Integrations\Core\Registry\IntegrationRegistry;
use Connect2Form\Integrations\Core\Assets\AssetManager;

/**
 * Main Plugin Class
 *
 * Clean singleton pattern for plugin initialization
 *
 * @package Connect2Form
 * @since    2.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Main Plugin Class
 *
 * Clean singleton pattern for plugin initialization
 *
 * @since    2.0.0
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      self|null    $instance    Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Admin manager instance.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      AdminManager    $admin_manager    Admin manager instance.
	 */
	private $admin_manager;

	/**
	 * Integration registry instance.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      IntegrationRegistry    $integration_registry    Integration registry instance.
	 */
	private $integration_registry;

	/**
	 * Asset manager instance.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      AssetManager    $asset_manager    Asset manager instance.
	 */
	private $asset_manager;

	/**
	 * Get singleton instance
	 *
	 * @since    2.0.0
	 * @return   self
	 */
	public static function getInstance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor - singleton pattern
	 *
	 * @since    2.0.0
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize plugin components
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function init() {
		// Initialize core components.
		$this->integration_registry = new IntegrationRegistry();
		$this->asset_manager        = new AssetManager();
		$this->asset_manager->init_default_assets();

		// Initialize admin if in admin area.
		if ( is_admin() ) {
			$this->admin_manager = new AdminManager( $this->integration_registry, $this->asset_manager );
		}

		// Register hooks.
		$this->register_hooks();

		// Load integrations after WordPress is fully loaded and text domain is loaded.
		add_action( 'init', array( $this, 'load_integrations' ), 15 );
	}

	/**
	 * Register WordPress hooks
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function register_hooks() {
		// Form submission processing.
		add_action( 'connect2form_after_submission', array( $this, 'process_form_submission' ), 10, 2 );

		// Integration discovery.
		add_action( 'init', array( $this, 'discover_integrations' ) );
	}

	/**
	 * Load available integrations
	 *
	 * @since    2.0.0
	 */
	public function load_integrations() {
		$integrations_dir = CONNECT2FORM_INTEGRATIONS_DIR . 'src/Integrations/';

		if ( ! is_dir( $integrations_dir ) ) {
			return;
		}

		$integration_folders = array_filter( scandir( $integrations_dir ), function( $item ) use ( $integrations_dir ) {
			return $item !== '.' && $item !== '..' && is_dir( $integrations_dir . $item );
		} );

		foreach ( $integration_folders as $folder ) {
			$class_name = "Connect2Form\\Integrations\\{$folder}\\{$folder}Integration";
			$class_file = $integrations_dir . $folder . '/' . $folder . 'Integration.php';

			// Make sure file exists.
			if ( ! file_exists( $class_file ) ) {
				continue;
			}

			// Try to load the class if it doesn't exist.
			if ( ! class_exists( $class_name ) ) {
				require_once $class_file;
			}

			if ( class_exists( $class_name ) ) {
				try {
					$integration = new $class_name();
					$this->integration_registry->register( $integration );

					// Test if AJAX handlers are registered.
					if ( method_exists( $integration, 'register_ajax_handlers' ) ) {
						$integration->register_ajax_handlers();
					}
				} catch ( \Exception $e ) {
					// Silent fail for integration loading.
				}
			}
		}
	}

	/**
	 * Discover integrations action hook
	 *
	 * @since    2.0.0
	 */
	public function discover_integrations() {
		do_action( 'connect2form_integrations_loaded', $this->integration_registry );
	}

	/**
	 * Process form submission through integrations
	 *
	 * @since    2.0.0
	 * @param    int   $submission_id Submission ID.
	 * @param    array $form_data     Form data.
	 */
	public function process_form_submission( $submission_id, $form_data ) {
		$enabled_integrations = $this->get_enabled_integrations_for_form( $form_data['form_id'] ?? 0 );

		foreach ( $enabled_integrations as $integration_id => $settings ) {
			$integration = $this->integration_registry->get( $integration_id );

			if ( $integration && $integration->isEnabled( $settings ) ) {
				try {
					$integration->processSubmission( $submission_id, $form_data, $settings );
				} catch ( \Exception $e ) {
					// Silent fail for integration processing.
				}
			}
		}
	}

	/**
	 * Get enabled integrations for a form
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    int $form_id Form ID.
	 * @return   array
	 */
	private function get_enabled_integrations_for_form( $form_id ) {
		// Get form settings and return enabled integrations.
		$form_settings = get_post_meta( $form_id, '_connect2form_integrations', true );
		return is_array( $form_settings ) ? $form_settings : array();
	}

	/**
	 * Get integration registry
	 *
	 * @since    2.0.0
	 * @return   IntegrationRegistry
	 */
	public function getRegistry() {
		return $this->integration_registry;
	}

	/**
	 * Get asset manager
	 *
	 * @since    2.0.0
	 * @return   AssetManager
	 */
	public function getAssetManager() {
		return $this->asset_manager;
	}

	/**
	 * Plugin activation
	 *
	 * @since    2.0.0
	 */
	public static function activate() {
		// Create database tables if needed.
		self::create_tables();

		// Set default options.
		self::set_default_options();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 *
	 * @since    2.0.0
	 */
	public static function deactivate() {
		// Clean up scheduled events.
		wp_clear_scheduled_hook( 'connect2form_integrations_batch_process' );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Create necessary database tables
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private static function create_tables() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'connect2form_integration_logs';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			form_id mediumint(9) NOT NULL,
			submission_id mediumint(9) DEFAULT NULL,
			integration_id varchar(50) NOT NULL,
			status varchar(20) NOT NULL,
			message text DEFAULT NULL,
			data longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY form_id (form_id),
			KEY integration_id (integration_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Set default plugin options
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private static function set_default_options() {
		add_option( 'connect2form_integrations_version', CONNECT2FORM_INTEGRATIONS_VERSION );
		add_option( 'connect2form_integrations_settings', array(
			'enable_logging'      => true,
			'log_retention_days'  => 30,
			'batch_processing'    => false,
		) );
	}
} 
