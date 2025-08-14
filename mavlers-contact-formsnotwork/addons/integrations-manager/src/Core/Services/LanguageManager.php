<?php
/**
 * Shared Language Manager
 * 
 * Handles multilingual support, RTL languages, and localization for all integrations
 * 
 * @package MavlersCF\Integrations\Core\Services
 * @since 1.0.0
 */

namespace MavlersCF\Integrations\Core\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Language Manager Class
 */
class LanguageManager {

	/**
	 * Supported languages
	 *
	 * @var array
	 */
	private $supported_languages = array();

	/**
	 * Current language
	 *
	 * @var string
	 */
	private $current_language = '';

	/**
	 * RTL languages
	 *
	 * @var array
	 */
	private $rtl_languages = array( 'ar', 'he', 'fa', 'ur', 'ps', 'sd' );

	/**
	 * Text domain
	 *
	 * @var string
	 */
	private $text_domain = 'mavlers-cf';

	/**
	 * Language cache
	 *
	 * @var array
	 */
	private $language_cache = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_supported_languages();
		$this->detect_current_language();
		add_action( 'init', array( $this, 'init_hooks' ), 5 );
	}

	/**
	 * Initialize hooks
	 */
	public function init_hooks() {
		add_action( 'init', array( $this, 'load_text_domain' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_language_assets' ) );
	}

	/**
	 * Initialize supported languages
	 */
	private function init_supported_languages() {
		$this->supported_languages = array(
			'en_US' => array(
				'name' => 'English (US)',
				'native_name' => 'English (US)',
				'code' => 'en',
				'locale' => 'en_US',
				'rtl' => false,
				'flag' => '🇺🇸',
			),
			'en_GB' => array(
				'name' => 'English (UK)',
				'native_name' => 'English (UK)',
				'code' => 'en',
				'locale' => 'en_GB',
				'rtl' => false,
				'flag' => '🇬🇧',
			),
			'es_ES' => array(
				'name' => 'Spanish (Spain)',
				'native_name' => 'Español (España)',
				'code' => 'es',
				'locale' => 'es_ES',
				'rtl' => false,
				'flag' => '🇪🇸',
			),
			'fr_FR' => array(
				'name' => 'French (France)',
				'native_name' => 'Français (France)',
				'code' => 'fr',
				'locale' => 'fr_FR',
				'rtl' => false,
				'flag' => '🇫🇷',
			),
			'de_DE' => array(
				'name' => 'German',
				'native_name' => 'Deutsch',
				'code' => 'de',
				'locale' => 'de_DE',
				'rtl' => false,
				'flag' => '🇩🇪',
			),
			'it_IT' => array(
				'name' => 'Italian',
				'native_name' => 'Italiano',
				'code' => 'it',
				'locale' => 'it_IT',
				'rtl' => false,
				'flag' => '🇮🇹',
			),
			'pt_BR' => array(
				'name' => 'Portuguese (Brazil)',
				'native_name' => 'Português (Brasil)',
				'code' => 'pt',
				'locale' => 'pt_BR',
				'rtl' => false,
				'flag' => '🇧🇷',
			),
			'ru_RU' => array(
				'name' => 'Russian',
				'native_name' => 'Русский',
				'code' => 'ru',
				'locale' => 'ru_RU',
				'rtl' => false,
				'flag' => '🇷🇺',
			),
			'ar' => array(
				'name' => 'Arabic',
				'native_name' => 'العربية',
				'code' => 'ar',
				'locale' => 'ar',
				'rtl' => true,
				'flag' => '🇸🇦',
			),
			'he_IL' => array(
				'name' => 'Hebrew',
				'native_name' => 'עברית',
				'code' => 'he',
				'locale' => 'he_IL',
				'rtl' => true,
				'flag' => '🇮🇱',
			),
			'fa_IR' => array(
				'name' => 'Persian',
				'native_name' => 'فارسی',
				'code' => 'fa',
				'locale' => 'fa_IR',
				'rtl' => true,
				'flag' => '🇮🇷',
			),
			// ... add more as needed ...
		);
	}

	/**
	 * Detect current language
	 */
	private function detect_current_language() {
		$this->current_language = get_locale();
		if ( ! isset( $this->supported_languages[ $this->current_language ] ) ) {
			$this->current_language = 'en_US';
		}
	}

	/**
	 * Load text domain
	 */
	public function load_text_domain() {
		load_plugin_textdomain( $this->text_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Enqueue language assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_language_assets( $hook ) {
		// Placeholder for RTL CSS or other assets
	}

	/**
	 * Check if language is RTL
	 *
	 * @param string|null $language Language code.
	 * @return bool
	 */
	public function is_rtl( $language = null ) {
		$lang = $language ?: $this->current_language;
		return ! empty( $this->supported_languages[ $lang ]['rtl'] );
	}

	/**
	 * Get current language
	 *
	 * @return string
	 */
	public function get_current_language() {
		return $this->current_language;
	}

	/**
	 * Get language strings for JavaScript
	 *
	 * @param string|null $language Language code.
	 * @return array
	 */
	public function get_language_strings_for_js( $language = null ) {
		// Return a minimal set for now; expand as needed
		return array(
			'save' => __( 'Save', $this->text_domain ),
			'cancel' => __( 'Cancel', $this->text_domain ),
			'edit' => __( 'Edit', $this->text_domain ),
			'delete' => __( 'Delete', $this->text_domain ),
			'confirm' => __( 'Confirm', $this->text_domain ),
			'yes' => __( 'Yes', $this->text_domain ),
			'no' => __( 'No', $this->text_domain ),
		);
	}

	/**
	 * Translate text
	 *
	 * @param string $text      Text to translate.
	 * @param string $context   Translation context.
	 * @param string|null $language Language code.
	 * @return string
	 */
	public function translate( $text, $context = '', $language = null ) {
		// Context-aware translation
		if ( ! empty( $context ) ) {
			return _x( $text, $context, $this->text_domain );
		}
		return __( $text, $this->text_domain );
	}

	/**
	 * Translate plural text
	 *
	 * @param string $single    Singular form.
	 * @param string $plural    Plural form.
	 * @param int    $number    Number.
	 * @param string $context   Translation context.
	 * @param string|null $language Language code.
	 * @return string
	 */
	public function translate_plural( $single, $plural, $number, $context = '', $language = null ) {
		return _n( $single, $plural, $number, $this->text_domain );
	}

	// Add more methods as needed for date/number formatting, etc.
} 