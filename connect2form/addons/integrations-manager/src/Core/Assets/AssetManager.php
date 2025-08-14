<?php

namespace Connect2Form\Integrations\Core\Assets;

/**
 * Asset Manager
 *
 * Handles enqueuing of CSS and JS files with proper separation of concerns
 *
 * @package Connect2Form
 * @since    2.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Asset Manager Class
 *
 * Handles enqueuing of CSS and JS files with proper separation of concerns
 *
 * @since    2.0.0
 */
class AssetManager {

	/**
	 * Registered assets.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      array    $assets    Registered assets.
	 */
	private $assets = array();

	/**
	 * Localized data for scripts.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      array    $localized_data    Localized data for scripts.
	 */
	private $localized_data = array();

	/**
	 * Constructor
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Register a CSS file
	 *
	 * @since    2.0.0
	 * @param    string $handle  Asset handle.
	 * @param    string $file    Asset file path.
	 * @param    array  $deps    Dependencies.
	 * @param    string $context Context (admin/frontend).
	 */
	public function registerStyle( string $handle, string $file, array $deps = array(), string $context = 'admin' ): void {
		$this->assets['styles'][ $context ][ $handle ] = array(
			'file'    => $file,
			'deps'    => $deps,
			'version' => CONNECT2FORM_INTEGRATIONS_VERSION,
		);
	}

	/**
	 * Register a JS file
	 *
	 * @since    2.0.0
	 * @param    string $handle  Asset handle.
	 * @param    string $file    Asset file path.
	 * @param    array  $deps    Dependencies.
	 * @param    string $context Context (admin/frontend).
	 */
	public function registerScript( string $handle, string $file, array $deps = array( 'jquery' ), string $context = 'admin' ): void {
		$this->assets['scripts'][ $context ][ $handle ] = array(
			'file'      => $file,
			'deps'      => $deps,
			'version'   => CONNECT2FORM_INTEGRATIONS_VERSION,
			'in_footer' => true,
		);
	}

	/**
	 * Add localized data for a script
	 *
	 * @since    2.0.0
	 * @param    string $handle      Asset handle.
	 * @param    string $object_name Object name for localization.
	 * @param    array  $data        Data to localize.
	 */
	public function localizeScript( string $handle, string $object_name, array $data ): void {
		$this->localized_data[ $handle ] = array(
			'object_name' => $object_name,
			'data'        => $data,
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @since    2.0.0
	 * @param    string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ): void {
		// Only load on our pages.
		if ( ! $this->should_load_admin_assets( $hook ) ) {
			return;
		}

		$this->enqueue_assets( 'admin' );
	}

	/**
	 * Enqueue frontend assets
	 *
	 * @since    2.0.0
	 */
	public function enqueue_frontend_assets(): void {
		$this->enqueue_assets( 'frontend' );
	}

	/**
	 * Check if we should load admin assets
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    string $hook Current admin page hook.
	 * @return   bool
	 */
	private function should_load_admin_assets( string $hook ): bool {
		$valid_pages = array(
			'connect2form',
			'connect2form-integrations',
			'connect2form-integration-settings',
			'connect2form-debug',
		);

		foreach ( $valid_pages as $page ) {
			if ( strpos( $hook, $page ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enqueue assets for given context
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    string $context Asset context.
	 */
	private function enqueue_assets( string $context ): void {
		// Enqueue styles.
		if ( ! empty( $this->assets['styles'][ $context ] ) ) {
			foreach ( $this->assets['styles'][ $context ] as $handle => $asset ) {
				wp_enqueue_style(
					$handle,
					$this->get_asset_url( $asset['file'] ),
					$asset['deps'],
					$asset['version']
				);
			}
		}

		// Enqueue scripts.
		if ( ! empty( $this->assets['scripts'][ $context ] ) ) {
			foreach ( $this->assets['scripts'][ $context ] as $handle => $asset ) {
				wp_enqueue_script(
					$handle,
					$this->get_asset_url( $asset['file'] ),
					$asset['deps'],
					$asset['version'],
					$asset['in_footer']
				);

				// Add localized data if exists.
				if ( isset( $this->localized_data[ $handle ] ) ) {
					$localize = $this->localized_data[ $handle ];
					wp_localize_script( $handle, $localize['object_name'], $localize['data'] );
				}
			}
		}
	}

	/**
	 * Get asset URL
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    string $file Asset file path.
	 * @return   string
	 */
	private function get_asset_url( string $file ): string {
		return CONNECT2FORM_INTEGRATIONS_URL . 'assets/' . ltrim( $file, '/' );
	}

	/**
	 * Initialize default assets
	 *
	 * @since    2.0.0
	 */
	public function init_default_assets(): void {
		// Core admin styles.
		$this->registerStyle(
			'connect2form-integrations-admin',
			'css/admin/integrations-admin.css'
		);

		// Core admin scripts.
		$this->registerScript(
			'connect2form-integrations-admin',
			'js/admin/integrations-admin.js',
			array( 'jquery', 'wp-util' )
		);

		// Mailchimp specific assets.
		$this->registerStyle(
			'connect2form-mailchimp',
			'css/admin/mailchimp.css'
		);

		$this->registerScript(
			'connect2form-mailchimp',
			'js/admin/mailchimp.js',
			array( 'jquery', 'wp-util' )
		);

		// Mailchimp form-specific assets.
		$this->registerScript(
			'connect2form-mailchimp-form',
			'js/admin/mailchimp-form.js',
			array( 'jquery', 'wp-util', 'connect2form-mailchimp' )
		);

		// HubSpot specific assets.
		$this->registerStyle(
			'connect2form-hubspot',
			'css/admin/hubspot.css'
		);

		$this->registerScript(
			'connect2form-hubspot',
			'js/admin/hubspot.js',
			array( 'jquery', 'wp-util' )
		);

		$this->registerScript(
			'connect2form-hubspot-form',
			'js/admin/hubspot-form.js',
			array( 'jquery', 'wp-util', 'connect2form-hubspot' )
		);
	}
} 
