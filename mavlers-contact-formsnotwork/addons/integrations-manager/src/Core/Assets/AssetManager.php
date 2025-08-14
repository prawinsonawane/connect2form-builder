<?php

namespace MavlersCF\Integrations\Core\Assets;

/**
 * Asset Manager
 * 
 * Handles enqueuing of CSS and JS files with proper separation of concerns
 */
class AssetManager {

	/**
	 * Registered assets
	 *
	 * @var array
	 */
	private $assets = array();

	/**
	 * Localized data for scripts
	 *
	 * @var array
	 */
	private $localized_data = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Register a CSS file
	 *
	 * @param string $handle  Asset handle.
	 * @param string $file    Asset file path.
	 * @param array  $deps    Dependencies.
	 * @param string $context Context (admin/frontend).
	 */
	public function registerStyle( string $handle, string $file, array $deps = array(), string $context = 'admin' ): void {
		$this->assets['styles'][ $context ][ $handle ] = array(
			'file' => $file,
			'deps' => $deps,
			'version' => MAVLERS_CF_INTEGRATIONS_VERSION,
		);
	}

	/**
	 * Register a JS file
	 *
	 * @param string $handle Asset handle.
	 * @param string $file   Asset file path.
	 * @param array  $deps   Dependencies.
	 * @param string $context Context (admin/frontend).
	 */
	public function registerScript( string $handle, string $file, array $deps = array( 'jquery' ), string $context = 'admin' ): void {
		$this->assets['scripts'][ $context ][ $handle ] = array(
			'file' => $file,
			'deps' => $deps,
			'version' => MAVLERS_CF_INTEGRATIONS_VERSION,
			'in_footer' => true,
		);
	}

	/**
	 * Add localized data for a script
	 *
	 * @param string $handle      Asset handle.
	 * @param string $object_name Object name for localization.
	 * @param array  $data        Localized data.
	 */
	public function localizeScript( string $handle, string $object_name, array $data ): void {
		$this->localized_data[ $handle ] = array(
			'object_name' => $object_name,
			'data' => $data,
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ): void {
		// Only load on our pages
		if ( ! $this->should_load_admin_assets( $hook ) ) {
			return;
		}

		$this->enqueue_assets( 'admin' );
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets(): void {
		$this->enqueue_assets( 'frontend' );
	}

	/**
	 * Check if we should load admin assets
	 *
	 * @param string $hook Current admin page hook.
	 * @return bool
	 */
	private function should_load_admin_assets( string $hook ): bool {
		$valid_pages = array(
			'mavlers-cf',
			'mavlers-contact-forms',
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
	 * @param string $context Asset context.
	 */
	private function enqueue_assets( string $context ): void {
		// Enqueue styles
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

		// Enqueue scripts
		if ( ! empty( $this->assets['scripts'][ $context ] ) ) {
			foreach ( $this->assets['scripts'][ $context ] as $handle => $asset ) {
				wp_enqueue_script(
					$handle,
					$this->get_asset_url( $asset['file'] ),
					$asset['deps'],
					$asset['version'],
					$asset['in_footer']
				);

				// Add localized data if exists
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
	 * @param string $file Asset file path.
	 * @return string
	 */
	private function get_asset_url( string $file ): string {
		return MAVLERS_CF_INTEGRATIONS_URL . 'assets/' . ltrim( $file, '/' );
	}

	/**
	 * Initialize default assets
	 */
	public function init_default_assets(): void {
		// Core admin styles
		$this->registerStyle(
			'mavlers-cf-integrations-admin',
			'css/admin/integrations-admin.css'
		);

		// Core admin scripts  
		$this->registerScript(
			'mavlers-cf-integrations-admin',
			'js/admin/integrations-admin.js'
		);

		// Add common localized data
		$this->localizeScript( 'mavlers-cf-integrations-admin', 'mavlersCFIntegrations', array(
			'nonce' => wp_create_nonce( 'mavlers_cf_nonce' ),
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'pluginUrl' => MAVLERS_CF_INTEGRATIONS_URL,
			'strings' => array(
				'confirm_delete' => __( 'Are you sure you want to delete this item?', 'mavlers-contact-forms' ),
				'saving' => __( 'Saving...', 'mavlers-contact-forms' ),
				'saved' => __( 'Saved!', 'mavlers-contact-forms' ),
				'error' => __( 'An error occurred. Please try again.', 'mavlers-contact-forms' ),
				'testing_connection' => __( 'Testing connection...', 'mavlers-contact-forms' ),
				'connection_successful' => __( 'Connection successful!', 'mavlers-contact-forms' ),
				'connection_failed' => __( 'Connection failed. Please check your settings.', 'mavlers-contact-forms' ),
			),
		) );

		// Mailchimp form styles
		$this->registerStyle(
			'mavlers-cf-mailchimp-form',
			'css/admin/mailchimp-form.css'
		);

		// Mailchimp form scripts (comprehensive field mapping included)
		$this->registerScript(
			'mavlers-cf-mailchimp-form',
			'js/admin/mailchimp-form.js',
			array( 'mavlers-cf-integrations-admin' )
		);
	}
} 