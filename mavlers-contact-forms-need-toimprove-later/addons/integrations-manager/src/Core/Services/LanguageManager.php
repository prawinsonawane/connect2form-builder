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

if (!defined('ABSPATH')) {
    exit;
}

class LanguageManager {
    private $supported_languages = array();
    private $current_language = '';
    private $rtl_languages = array('ar', 'he', 'fa', 'ur', 'ps', 'sd');
    private $language_cache = array();

    public function __construct() {
        $this->init_supported_languages();
        $this->detect_current_language();
        add_action('init', array($this, 'init_hooks'), 5);
    }

    public function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_text_domain'), 5);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_language_assets'));
    }

    private function init_supported_languages() {
        $this->supported_languages = array(
            'en_US' => array('name' => 'English (US)', 'native_name' => 'English (US)', 'code' => 'en', 'locale' => 'en_US', 'rtl' => false, 'flag' => '🇺🇸'),
            'en_GB' => array('name' => 'English (UK)', 'native_name' => 'English (UK)', 'code' => 'en', 'locale' => 'en_GB', 'rtl' => false, 'flag' => '🇬🇧'),
            'es_ES' => array('name' => 'Spanish (Spain)', 'native_name' => 'Español (España)', 'code' => 'es', 'locale' => 'es_ES', 'rtl' => false, 'flag' => '🇪🇸'),
            'fr_FR' => array('name' => 'French (France)', 'native_name' => 'Français (France)', 'code' => 'fr', 'locale' => 'fr_FR', 'rtl' => false, 'flag' => '🇫🇷'),
            'de_DE' => array('name' => 'German', 'native_name' => 'Deutsch', 'code' => 'de', 'locale' => 'de_DE', 'rtl' => false, 'flag' => '🇩🇪'),
            'it_IT' => array('name' => 'Italian', 'native_name' => 'Italiano', 'code' => 'it', 'locale' => 'it_IT', 'rtl' => false, 'flag' => '🇮🇹'),
            'pt_BR' => array('name' => 'Portuguese (Brazil)', 'native_name' => 'Português (Brasil)', 'code' => 'pt', 'locale' => 'pt_BR', 'rtl' => false, 'flag' => '🇧🇷'),
            'ru_RU' => array('name' => 'Russian', 'native_name' => 'Русский', 'code' => 'ru', 'locale' => 'ru_RU', 'rtl' => false, 'flag' => '🇷🇺'),
            'ar'    => array('name' => 'Arabic', 'native_name' => 'العربية', 'code' => 'ar', 'locale' => 'ar', 'rtl' => true, 'flag' => '🇸🇦'),
            'he_IL' => array('name' => 'Hebrew', 'native_name' => 'עברית', 'code' => 'he', 'locale' => 'he_IL', 'rtl' => true, 'flag' => '🇮🇱'),
            'fa_IR' => array('name' => 'Persian', 'native_name' => 'فارسی', 'code' => 'fa', 'locale' => 'fa_IR', 'rtl' => true, 'flag' => '🇮🇷'),
            // ... add more as needed ...
        );
    }

    private function detect_current_language() {
        $this->current_language = get_locale();
        if (!isset($this->supported_languages[$this->current_language])) {
            $this->current_language = 'en_US';
        }
    }

    public function load_text_domain() {
        load_plugin_textdomain('mavlers-contact-forms', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    public function enqueue_language_assets($hook) {
        // Placeholder for RTL CSS or other assets
    }

    public function is_rtl($language = null) {
        $lang = $language ?: $this->current_language;
        return !empty($this->supported_languages[$lang]['rtl']);
    }

    public function get_current_language() {
        return $this->current_language;
    }

    public function get_language_strings_for_js($language = null) {
        // Return a minimal set for now; expand as needed
        return array(
            'save' => __('Save', 'mavlers-contact-forms'),
            'cancel' => __('Cancel', 'mavlers-contact-forms'),
            'edit' => __('Edit', 'mavlers-contact-forms'),
            'delete' => __('Delete', 'mavlers-contact-forms'),
            'confirm' => __('Confirm', 'mavlers-contact-forms'),
            'yes' => __('Yes', 'mavlers-contact-forms'),
            'no' => __('No', 'mavlers-contact-forms'),
        );
    }

    public function translate($text, $context = '', $language = null) {
        // Context-aware translation
        if (!empty($context)) {
            return _x($text, $context, 'mavlers-contact-forms');
        }
        return __($text, 'mavlers-contact-forms');
    }

    public function translate_plural($single, $plural, $number, $context = '', $language = null) {
        return _n($single, $plural, $number, 'mavlers-contact-forms');
    }

    // Add more methods as needed for date/number formatting, etc.
} 