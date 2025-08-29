<?php
/**
 * Connect2Form Accessibility Manager
 *
 * Ensures WCAG 2.1 AA compliance for WordPress standards
 *
 * @package Connect2Form
 * @since 2.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Connect2Form Accessibility Manager Class
 *
 * Ensures WCAG 2.1 AA compliance for WordPress standards
 *
 * @since    2.0.0
 */
class Connect2Form_Accessibility {

	/**
	 * Initialize accessibility features
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		// Form accessibility enhancements.
		add_filter( 'connect2form_form_html', array( $this, 'enhance_form_accessibility' ), 10, 2 );

		// Admin interface accessibility.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_accessibility_assets' ) );

		// ARIA labels and descriptions.
		add_filter( 'connect2form_field_attributes', array( $this, 'add_aria_attributes' ), 10, 3 );

		// Keyboard navigation.
		add_action( 'wp_footer', array( $this, 'add_keyboard_navigation_script' ) );
		add_action( 'admin_footer', array( $this, 'add_admin_keyboard_navigation' ) );

		// Screen reader support.
		add_filter( 'connect2form_screen_reader_text', array( $this, 'add_screen_reader_support' ), 10, 2 );

		// Color contrast compliance.
		add_action( 'wp_head', array( $this, 'add_accessibility_css' ) );
		add_action( 'admin_head', array( $this, 'add_admin_accessibility_css' ) );

		// Focus management.
		add_filter( 'connect2form_focus_management', array( $this, 'manage_focus' ), 10, 2 );
	}

	/**
	 * Enhance form accessibility
	 *
	 * @since    2.0.0
	 * @param    string $html Form HTML.
	 * @param    object $form Form object.
	 * @return   string
	 */
	public function enhance_form_accessibility( $html, $form ) {
		// Add form role and aria-label.
		        $form_title = esc_attr( $form->form_title ?? __( 'Contact Form', 'connect2form-builder') );
		$html       = str_replace(
			'<form',
			'<form role="form" aria-label="' . $form_title . '"',
			$html
		);

		// Add fieldset for grouped fields.
		if ( strpos( $html, 'radio' ) !== false || strpos( $html, 'checkbox' ) !== false ) {
			$html = $this->add_fieldsets( $html );
		}

		// Ensure all inputs have labels.
		$html = $this->ensure_input_labels( $html );

		// Add error announcement area.
		$html = $this->add_error_announcement_area( $html );

		return $html;
	}

	/**
	 * Add fieldsets for grouped form controls
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    string $html Form HTML.
	 * @return   string
	 */
	private function add_fieldsets( $html ) {
		// Group radio buttons and checkboxes in fieldsets.
		$html = preg_replace_callback(
			'/<div class="connect2form-field[^"]*radio[^"]*"[^>]*>(.*?)<\/div>/s',
			array( $this, 'wrap_in_fieldset' ),
			$html
		);

		$html = preg_replace_callback(
			'/<div class="connect2form-field[^"]*checkbox[^"]*"[^>]*>(.*?)<\/div>/s',
			array( $this, 'wrap_in_fieldset' ),
			$html
		);

		return $html;
	}

	/**
	 * Wrap field group in fieldset
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    array $matches Regex matches.
	 * @return   string
	 */
	private function wrap_in_fieldset( $matches ) {
		$content = $matches[1];

		// Extract legend from first label.
		preg_match( '/<label[^>]*>(.*?)<\/label>/', $content, $label_matches );
		$legend = $label_matches[1] ?? __( 'Form Field Group', 'connect2form-builder');

		return '<fieldset class="connect2form-fieldset">' .
			   '<legend class="connect2form-legend">' . $legend . '</legend>' .
			   $content .
			   '</fieldset>';
	}

	/**
	 * Ensure all inputs have proper labels
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    string $html Form HTML.
	 * @return   string
	 */
	private function ensure_input_labels( $html ) {
		// Find inputs without labels and add them.
		$html = preg_replace_callback(
			'/<input([^>]*?)(?:id="([^"]*)")?([^>]*?)>/i',
			array( $this, 'ensure_input_has_label' ),
			$html
		);

		return $html;
	}

	/**
	 * Ensure input has label callback
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    array $matches Regex matches.
	 * @return   string
	 */
	private function ensure_input_has_label( $matches ) {
		$input = $matches[0];
		$id    = $matches[2] ?? '';

		// Skip if input is hidden or already has aria-label.
		if ( strpos( $input, 'type="hidden"' ) !== false ||
			strpos( $input, 'aria-label' ) !== false ) {
			return $input;
		}

		// Check if label exists for this input.
		if ( ! empty( $id ) ) {
			// Input has ID, check if label exists (this should be handled elsewhere).
			return $input;
		}

		// Add generic aria-label if none exists.
		if ( strpos( $input, 'aria-label' ) === false ) {
			$input = str_replace( '>', ' aria-label="' . __( 'Form Input Field', 'connect2form-builder') . '">', $input );
		}

		return $input;
	}

	/**
	 * Add error announcement area
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    string $html Form HTML.
	 * @return   string
	 */
	private function add_error_announcement_area( $html ) {
		$error_area = '<div id="connect2form-announcements" class="sr-only" aria-live="polite" aria-atomic="true"></div>';

		// Add at the beginning of the form.
		$html = str_replace( '<form', $error_area . '<form', $html );

		return $html;
	}

	/**
	 * Add ARIA attributes to form fields
	 *
	 * @since    2.0.0
	 * @param    array  $attributes Field attributes.
	 * @param    array  $field      Field data.
	 * @param    object $form       Form object.
	 * @return   array
	 */
	public function add_aria_attributes( $attributes, $field, $form ) {
		// Add required aria attributes.
		if ( ! empty( $field['required'] ) ) {
			$attributes['aria-required'] = 'true';
		}

		// Add describedby for help text.
		if ( ! empty( $field['description'] ) ) {
			$description_id = 'desc-' . ( $field['id'] ?? uniqid() );
			$attributes['aria-describedby'] = $description_id;
		}

		// Add invalid state for errors.
		if ( ! empty( $field['error'] ) ) {
			$attributes['aria-invalid'] = 'true';
			$error_id = 'error-' . ( $field['id'] ?? uniqid() );
			$attributes['aria-describedby'] = ( $attributes['aria-describedby'] ?? '' ) . ' ' . $error_id;
		}

		// Add expanded state for select fields.
		if ( $field['type'] === 'select' ) {
			$attributes['aria-expanded'] = 'false';
		}

		return $attributes;
	}

	/**
	 * Add keyboard navigation support
	 *
	 * @since    2.0.0
	 */
	public function add_keyboard_navigation_script() {
		if ( ! $this->should_load_accessibility_assets() ) {
			return;
		}
		?>
		<script>
		(function() {
			'use strict';

			// Enhance keyboard navigation for Connect2Form.
			document.addEventListener('DOMContentLoaded', function() {
				var forms = document.querySelectorAll('.connect2form-form-wrapper');

				forms.forEach(function(form) {
					// Handle Enter key on buttons.
					form.addEventListener('keydown', function(e) {
						if (e.key === 'Enter' && e.target.type === 'button') {
							e.preventDefault();
							e.target.click();
						}
					});

					// Manage focus for dynamic content.
					form.addEventListener('connect2form:error', function(e) {
						var firstError = form.querySelector('.connect2form-error');
						if (firstError) {
							firstError.focus();
							firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
						}
					});

					// Announce form submission status.
					form.addEventListener('connect2form:success', function(e) {
						var announcement = document.getElementById('connect2form-announcements');
						if (announcement) {
							announcement.textContent = '<?php echo esc_js( __( 'Form submitted successfully', 'connect2form-builder') ); ?>';
						}
					});
				});

				// Enhance select dropdowns.
				var selects = document.querySelectorAll('.connect2form-form-wrapper select');
				selects.forEach(function(select) {
					select.addEventListener('focus', function() {
						this.setAttribute('aria-expanded', 'true');
					});

					select.addEventListener('blur', function() {
						this.setAttribute('aria-expanded', 'false');
					});
				});
			});
		})();
		</script>
		<?php
	}

	/**
	 * Add admin keyboard navigation
	 *
	 * @since    2.0.0
	 */
	public function add_admin_keyboard_navigation() {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'connect2form' ) === false ) {
			return;
		}
		?>
		<script>
		(function() {
			'use strict';

			document.addEventListener('DOMContentLoaded', function() {
				// Enhanced keyboard navigation for admin interface.
				var formBuilder = document.getElementById('form-builder');
				if (formBuilder) {
					// Allow keyboard interaction with drag-drop interface.
					var draggableItems = formBuilder.querySelectorAll('.draggable');
					draggableItems.forEach(function(item) {
						if (!item.hasAttribute('tabindex')) {
							item.setAttribute('tabindex', '0');
						}

						item.addEventListener('keydown', function(e) {
							if (e.key === 'Enter' || e.key === ' ') {
								e.preventDefault();
								item.click();
							}
						});
					});
				}

				// Skip links for admin interface.
				var skipLink = document.createElement('a');
				skipLink.href = '#main-content';
				skipLink.className = 'screen-reader-shortcut';
				skipLink.textContent = '<?php echo esc_js( __( 'Skip to main content', 'connect2form-builder') ); ?>';
				document.body.insertBefore(skipLink, document.body.firstChild);
			});
		})();
		</script>
		<?php
	}

	/**
	 * Add screen reader support
	 *
	 * @since    2.0.0
	 * @param    string $text    Screen reader text.
	 * @param    string $context Context.
	 * @return   string
	 */
	public function add_screen_reader_support( $text, $context ) {
		$screen_reader_texts = array(
			'required_field' => __( 'Required field', 'connect2form-builder'),
			'error_field'    => __( 'Error in field', 'connect2form-builder'),
			'loading'        => __( 'Loading, please wait', 'connect2form-builder'),
			'form_submitted' => __( 'Form submitted successfully', 'connect2form-builder'),
			'form_error'     => __( 'Form submission failed, please check for errors', 'connect2form-builder'),
			'file_selected'  => __( 'File selected', 'connect2form-builder'),
			'file_removed'   => __( 'File removed', 'connect2form-builder'),
		);

		return $screen_reader_texts[ $context ] ?? $text;
	}

	/**
	 * Add accessibility CSS
	 *
	 * @since    2.0.0
	 */
	public function add_accessibility_css() {
		if ( ! $this->should_load_accessibility_assets() ) {
			return;
		}
		?>
		<style>
		/* Connect2Form Accessibility Styles */
		.connect2form-form-wrapper {
			/* High contrast mode support */
			--primary-color: #0073aa;
			--error-color: #d63638;
			--success-color: #00a32a;
			--focus-color: #005a87;
		}

		/* Screen reader only text */
		.connect2form-sr-only,
		.sr-only {
			position: absolute !important;
			width: 1px !important;
			height: 1px !important;
			padding: 0 !important;
			margin: -1px !important;
			overflow: hidden !important;
			clip: rect(0, 0, 0, 0) !important;
			white-space: nowrap !important;
			border: 0 !important;
		}

		/* Skip links */
		.screen-reader-shortcut {
			position: absolute !important;
			top: -40px;
			left: 6px;
			z-index: 999999;
			color: var(--focus-color);
			background: #fff;
			text-decoration: none;
			padding: 8px 16px;
			border: 2px solid var(--focus-color);
		}

		.screen-reader-shortcut:focus {
			top: 6px;
		}

		/* Focus indicators */
		.connect2form-form-wrapper input:focus,
		.connect2form-form-wrapper select:focus,
		.connect2form-form-wrapper textarea:focus,
		.connect2form-form-wrapper button:focus {
			outline: 2px solid var(--focus-color);
			outline-offset: 2px;
		}

		/* Error states */
		.connect2form-field[aria-invalid="true"] input,
		.connect2form-field[aria-invalid="true"] select,
		.connect2form-field[aria-invalid="true"] textarea {
			border-color: var(--error-color);
			border-width: 2px;
		}

		/* Required field indicators */
		.connect2form-required::after {
			content: " *";
			color: var(--error-color);
			font-weight: bold;
		}

		/* High contrast mode support */
		@media (prefers-contrast: high) {
			.connect2form-form-wrapper {
				--primary-color: #000;
				--error-color: #ff0000;
				--success-color: #008000;
				--focus-color: #0000ff;
			}
		}

		/* Reduced motion support */
		@media (prefers-reduced-motion: reduce) {
			.connect2form-form-wrapper * {
				animation-duration: 0.01ms !important;
				animation-iteration-count: 1 !important;
				transition-duration: 0.01ms !important;
			}
		}

		/* Font size respect */
		.connect2form-form-wrapper {
			font-size: 1rem; /* Respects user's base font size */
		}

		/* Minimum touch target size */
		.connect2form-form-wrapper button,
		.connect2form-form-wrapper input[type="checkbox"],
		.connect2form-form-wrapper input[type="radio"] {
			min-height: 44px;
			min-width: 44px;
		}
		</style>
		<?php
	}

	/**
	 * Add admin accessibility CSS
	 *
	 * @since    2.0.0
	 */
	public function add_admin_accessibility_css() {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'connect2form' ) === false ) {
			return;
		}
		?>
		<style>
		/* Connect2Form Admin Accessibility */
		.connect2form-admin-wrapper {
			/* Ensure proper color contrast */
			--admin-primary: #0073aa;
			--admin-focus: #005a87;
			--admin-error: #d63638;
		}

		/* Focus management for admin interface */
		.connect2form-admin-wrapper button:focus,
		.connect2form-admin-wrapper .button:focus,
		.connect2form-admin-wrapper input:focus,
		.connect2form-admin-wrapper select:focus {
			box-shadow: 0 0 0 2px var(--admin-focus);
			outline: none;
		}

		/* Improved contrast for form builder */
		.form-builder-field {
			border: 2px solid #ddd;
		}

		.form-builder-field.selected {
			border-color: var(--admin-primary);
			background: rgba(0, 115, 170, 0.05);
		}

		/* Screen reader text for admin */
		.connect2form-admin-sr-only {
			position: absolute !important;
			width: 1px !important;
			height: 1px !important;
			overflow: hidden !important;
			clip: rect(1px, 1px, 1px, 1px) !important;
		}
		</style>
		<?php
	}

	/**
	 * Manage focus for dynamic content
	 *
	 * @since    2.0.0
	 * @param    string $element Element selector.
	 * @param    string $context Context.
	 * @return   string
	 */
	public function manage_focus( $element, $context ) {
		switch ( $context ) {
			case 'error':
				return 'setTimeout(function() { document.querySelector("' . $element . '").focus(); }, 100);';
			case 'success':
				return 'setTimeout(function() { document.querySelector("#connect2form-announcements").focus(); }, 100);';
			default:
				return '';
		}
	}

	/**
	 * Should load accessibility assets
	 *
	 * @since    2.0.0
	 * @access   private
	 * @return   bool
	 */
	private function should_load_accessibility_assets() {
		global $post;

		if ( is_admin() ) {
			return strpos( get_current_screen()->id ?? '', 'connect2form' ) !== false;
		}

		if ( ! $post ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'connect2form' ) ||
			   has_shortcode( $post->post_content, 'connect2form' );
	}

	/**
	 * Enqueue accessibility assets
	 *
	 * @since    2.0.0
	 */
	public function enqueue_accessibility_assets() {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'connect2form' ) === false ) {
			return;
		}

		// Enqueue screen reader utilities if needed.
		wp_enqueue_script(
			'connect2form-accessibility',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/accessibility.js',
			array( 'jquery' ),
			CONNECT2FORM_VERSION,
			true
		);

		wp_localize_script( 'connect2form-accessibility', 'connect2formA11y', array(
			'strings' => array(
							'required' => __( 'Required field', 'connect2form-builder'),
			'error'    => __( 'Error in field', 'connect2form-builder'),
			'loading'  => __( 'Loading', 'connect2form-builder'),
			'success'  => __( 'Success', 'connect2form-builder'),
			),
		) );
	}
}

// Initialize accessibility manager.
new Connect2Form_Accessibility();

