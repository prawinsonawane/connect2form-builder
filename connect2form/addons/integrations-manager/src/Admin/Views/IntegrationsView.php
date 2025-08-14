<?php

namespace Connect2Form\Integrations\Admin\Views;

/**
 * Integrations View
 *
 * Handles template rendering with clean separation of concerns
 *
 * @package Connect2Form
 * @since    2.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Integrations View Class
 *
 * Handles template rendering with clean separation of concerns
 *
 * @since    2.0.0
 */
class IntegrationsView {

	/**
	 * Template path.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $template_path    Template path.
	 */
	private $template_path;

	/**
	 * Constructor
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		$this->template_path = CONNECT2FORM_INTEGRATIONS_DIR . 'templates/admin/';
	}

	/**
	 * Render a template with data
	 *
	 * @since    2.0.0
	 * @param    string $template Template name.
	 * @param    array  $data     Template data.
	 */
	public function render( string $template, array $data = array() ): void {
		$template = preg_replace( '/[^A-Za-z0-9_\-]/', '', $template );
		$template_file = $this->template_path . $template . '.php';

		if ( ! file_exists( $template_file ) ) {
			$this->render_error( "Template not found: {$template}" );
			return;
		}

		// Extract data to variables for template.
		extract( $data, EXTR_SKIP );

		// Make view object available to template.
		$view = $this;

		// Start output buffering.
		ob_start();

		// Include template.
		include $template_file;

		// Output the rendered template (admin template, allow scripts/styles).
		// Output admin template markup as-is to preserve inline scripts/styles from first-party templates.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted admin templates may intentionally include inline scripts/styles
		echo (string) ob_get_clean();
	}

	/**
	 * Render partial template
	 *
	 * @since    2.0.0
	 * @param    string $partial Partial template name.
	 * @param    array  $data    Template data.
	 * @return   string
	 */
	public function partial( string $partial, array $data = array() ): string {
		$partial = preg_replace( '/[^A-Za-z0-9_\-]/', '', $partial );
		$partial_file = $this->template_path . 'partials/' . $partial . '.php';

		if ( ! file_exists( $partial_file ) ) {
			return "<!-- Partial not found: {$partial} -->";
		}

		// Extract data to variables.
		extract( $data, EXTR_SKIP );

		// Capture output.
		ob_start();
		include $partial_file;
		return ob_get_clean();
	}

	/**
	 * Render error message
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    string $message Error message.
	 */
	private function render_error( string $message ): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Integrations', 'connect2form' ) . '</h1>';
		echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		echo '</div>';
	}

	/**
	 * Escape and output text
	 *
	 * @since    2.0.0
	 * @param    string $text Text to escape and output.
	 */
	public function e( string $text ): void {
		echo esc_html( $text );
	}

	/**
	 * Escape and return text
	 *
	 * @since    2.0.0
	 * @param    string $text Text to escape.
	 * @return   string
	 */
	public function esc( string $text ): string {
		return esc_html( $text );
	}

	/**
	 * Output attribute value
	 *
	 * @since    2.0.0
	 * @param    string $attr Attribute value.
	 */
	public function attr( string $attr ): void {
		echo esc_attr( $attr );
	}

	/**
	 * Output URL
	 *
	 * @since    2.0.0
	 * @param    string $url URL to output.
	 */
	public function url( string $url ): void {
		echo esc_url( $url );
	}

	/**
	 * Check if integration is configured
	 *
	 * @since    2.0.0
	 * @param    object $integration Integration object.
	 * @return   bool
	 */
	public function is_configured( $integration ): bool {
		return $integration && $integration->isConfigured();
	}

	/**
	 * Get integration icon HTML
	 *
	 * @since    2.0.0
	 * @param    object $integration Integration object.
	 * @return   string
	 */
	public function integration_icon( $integration ): string {
		$icon = $integration->getIcon();

		if ( strpos( $icon, 'dashicons-' ) === 0 ) {
			return '<span class="dashicons ' . esc_attr( $icon ) . '"></span>';
		} elseif ( filter_var( $icon, FILTER_VALIDATE_URL ) ) {
			return '<img src="' . esc_url( $icon ) . '" alt="' . esc_attr( $integration->getName() ) . '" class="integration-icon">';
		}

		return '<span class="dashicons dashicons-admin-plugins"></span>';
	}

	/**
	 * Get status badge HTML
	 *
	 * @since    2.0.0
	 * @param    string $status Status value.
	 * @return   string
	 */
	public function status_badge( string $status ): string {
		$classes = array(
			'success' => 'badge-success',
			'error'   => 'badge-error',
			'warning' => 'badge-warning',
			'info'    => 'badge-info',
		);

		$class = $classes[ $status ] ?? 'badge-default';

		return '<span class="status-badge ' . esc_attr( $class ) . '">' . esc_html( ucfirst( $status ) ) . '</span>';
	}

	/**
	 * Format date for display
	 *
	 * @since    2.0.0
	 * @param    string $date Date string.
	 * @return   string
	 */
	public function format_date( string $date ): string {
		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $date ) );
	}

	/**
	 * Get admin URL for integration
	 *
	 * @since    2.0.0
	 * @param    string $integration_id Integration ID.
	 * @param    string $tab           Tab name.
	 * @return   string
	 */
	public function integration_url( string $integration_id, string $tab = 'settings' ): string {
		return esc_url_raw( admin_url( "admin.php?page=connect2form-integrations&tab={$tab}&integration={$integration_id}" ) );
	}
} 
