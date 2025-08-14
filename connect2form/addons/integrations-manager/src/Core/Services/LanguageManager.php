<?php
/**
 * Shared Language Manager
 * 
 * Handles multilingual support, RTL languages, and localization for all integrations.
 * 
 * @package Connect2Form\Integrations\Core\Services
 * @since 1.0.0
 */

namespace Connect2Form\Integrations\Core\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Language Manager
 *
 * @since    1.0.0
 * @access   public
 */
class LanguageManager {
    /**
     * Supported languages.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $supported_languages    Supported languages.
     */
    private $supported_languages = array();

    /**
     * Current language.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $current_language    Current language.
     */
    private $current_language = '';

    /**
     * RTL languages.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $rtl_languages    RTL languages.
     */
    private $rtl_languages = array( 'ar', 'he', 'fa', 'ur', 'ps', 'sd' );

    /**
     * Text domain.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $text_domain    Text domain.
     */
    private $text_domain = 'connect2form';

    /**
     * Language cache.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $language_cache    Language cache.
     */
    private $language_cache = array();

    /**
     * Constructor
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->init_supported_languages();
        $this->detect_current_language();
        add_action( 'init', array( $this, 'init_hooks' ), 5 );
    }

    /**
     * Initialize hooks.
     *
     * @since    1.0.0
     */
    public function init_hooks() {
        add_action( 'init', array( $this, 'load_text_domain' ), 10 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_language_assets' ) );
    }

    /**
     * Initialize supported languages.
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_supported_languages() {
        $this->supported_languages = array(
            'en_US' => array( 'name' => 'English (US)', 'native_name' => 'English (US)', 'code' => 'en', 'locale' => 'en_US', 'rtl' => false, 'flag' => '🇺🇸' ),
            'en_GB' => array( 'name' => 'English (UK)', 'native_name' => 'English (UK)', 'code' => 'en', 'locale' => 'en_GB', 'rtl' => false, 'flag' => '🇬🇧' ),
            'es_ES' => array( 'name' => 'Spanish (Spain)', 'native_name' => 'Español (España)', 'code' => 'es', 'locale' => 'es_ES', 'rtl' => false, 'flag' => '🇪🇸' ),
            'fr_FR' => array( 'name' => 'French (France)', 'native_name' => 'Français (France)', 'code' => 'fr', 'locale' => 'fr_FR', 'rtl' => false, 'flag' => '🇫🇷' ),
            'de_DE' => array( 'name' => 'German', 'native_name' => 'Deutsch', 'code' => 'de', 'locale' => 'de_DE', 'rtl' => false, 'flag' => '🇩🇪' ),
            'it_IT' => array( 'name' => 'Italian', 'native_name' => 'Italiano', 'code' => 'it', 'locale' => 'it_IT', 'rtl' => false, 'flag' => '🇮🇹' ),
            'pt_BR' => array( 'name' => 'Portuguese (Brazil)', 'native_name' => 'Português (Brasil)', 'code' => 'pt', 'locale' => 'pt_BR', 'rtl' => false, 'flag' => '🇧🇷' ),
            'ru_RU' => array( 'name' => 'Russian', 'native_name' => 'Русский', 'code' => 'ru', 'locale' => 'ru_RU', 'rtl' => false, 'flag' => '🇷🇺' ),
            'ar'    => array( 'name' => 'Arabic', 'native_name' => 'العربية', 'code' => 'ar', 'locale' => 'ar', 'rtl' => true, 'flag' => '🇸🇦' ),
            'he_IL' => array( 'name' => 'Hebrew', 'native_name' => 'עברית', 'code' => 'he', 'locale' => 'he_IL', 'rtl' => true, 'flag' => '🇮🇱' ),
            'fa_IR' => array( 'name' => 'Persian', 'native_name' => 'فارسی', 'code' => 'fa', 'locale' => 'fa_IR', 'rtl' => true, 'flag' => '🇮🇷' ),
            // ... add more as needed ...
        );
    }

    /**
     * Detect current language.
     *
     * @since    1.0.0
     * @access   private
     */
    private function detect_current_language() {
        $this->current_language = get_locale();
        if ( ! isset( $this->supported_languages[ $this->current_language ] ) ) {
            $this->current_language = 'en_US';
        }
    }

    /**
     * Enqueue language assets.
     *
     * @since    1.0.0
     * @param    string $hook Hook name.
     */
    public function enqueue_language_assets( $hook ) {
        // Placeholder for RTL CSS or other assets.
    }

    /**
     * Check if language is RTL.
     *
     * @since    1.0.0
     * @param    string $language Language code.
     * @return   bool
     */
    public function is_rtl( $language = null ) {
        $lang = $language ?: $this->current_language;
        return ! empty( $this->supported_languages[ $lang ]['rtl'] );
    }

    /**
     * Get current language.
     *
     * @since    1.0.0
     * @return   string
     */
    public function get_current_language() {
        return $this->current_language;
    }

    /**
     * Get language strings for JavaScript.
     *
     * @since    1.0.0
     * @param    string $language Language code.
     * @return   array
     */
    public function get_language_strings_for_js( $language = null ) {
        // Return a minimal set for now; expand as needed.
        return array(
            'save' => __( 'Save', 'connect2form' ),
            'cancel' => __( 'Cancel', 'connect2form' ),
            'edit' => __( 'Edit', 'connect2form' ),
            'delete' => __( 'Delete', 'connect2form' ),
            'confirm' => __( 'Confirm', 'connect2form' ),
            'yes' => __( 'Yes', 'connect2form' ),
            'no' => __( 'No', 'connect2form' ),
        );
    }

    /**
     * Translate text.
     *
     * @since    1.0.0
     * @param    string $text     Text to translate.
     * @param    string $context  Translation context.
     * @param    string $language Language code.
     * @return   string
     */
    public function translate( $text, $context = '', $language = null ) {
        // Context-aware translation.
        if ( ! empty( $context ) ) {
            // If $text is singular, we can safely use _x.
            if ( is_string( $text ) && strpos( $text, '%' ) === false ) { // Ensure it's a string without variables.
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralContext
                return _x( $text, $context, 'connect2form' );
            }
        }
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
        return __( $text, 'connect2form' );
    }

    /**
     * Translate plural text.
     *
     * @since    1.0.0
     * @param    string $single   Singular form.
     * @param    string $plural   Plural form.
     * @param    int    $number   Number.
     * @param    string $context  Translation context.
     * @param    string $language Language code.
     * @return   string
     */
    public function translate_plural( $single, $plural, $number, $context = '', $language = null ) {
        // Use sprintf to handle dynamic strings properly
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralSingle,WordPress.WP.I18n.NonSingularStringLiteralPlural
        return sprintf(_n($single, $plural, $number, 'connect2form'), $number);
    }

    // Add more methods as needed for date/number formatting, etc.
} 
