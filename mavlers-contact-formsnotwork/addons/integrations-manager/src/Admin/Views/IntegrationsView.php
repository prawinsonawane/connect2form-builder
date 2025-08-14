<?php

namespace MavlersCF\Integrations\Admin\Views;

/**
 * Integrations View
 * 
 * Handles template rendering with clean separation of concerns
 */
class IntegrationsView {

	/**
	 * Template path
	 *
	 * @var string
	 */
	private $template_path;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->template_path = MAVLERS_CF_INTEGRATIONS_DIR . 'templates/admin/';
	}

	/**
	 * Render a template with data
	 *
	 * @param string $template Template name.
	 * @param array  $data     Template data.
	 */
	public function render( string $template, array $data = array() ): void {
		$template_file = $this->template_path . $template . '.php';

		if ( ! file_exists( $template_file ) ) {
			$this->render_error( "Template not found: {$template}" );
			return;
		}

		// Extract data to variables for template
		extract( $data, EXTR_SKIP );
		
		// Make view object available to template
		$view = $this;

		// Start output buffering
		ob_start();

		// Include template
		include $template_file;

		// Output the rendered template
		echo ob_get_clean();
	}

	/**
	 * Render partial template
	 *
	 * @param string $partial Partial template name.
	 * @param array  $data    Template data.
	 * @return string
	 */
	public function partial( string $partial, array $data = array() ): string {
		$partial_file = $this->template_path . 'partials/' . $partial . '.php';

		if ( ! file_exists( $partial_file ) ) {
			return "<!-- Partial not found: {$partial} -->";
		}

		// Extract data to variables
		extract( $data, EXTR_SKIP );

		// Capture output
		ob_start();
		include $partial_file;
		return ob_get_clean();
	}

	/**
	 * Render error message
	 *
	 * @param string $message Error message.
	 */
	private function render_error( string $message ): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Integrations', 'mavlers-contact-forms' ) . '</h1>';
		echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		echo '</div>';
	}

	/**
	 * Escape and output text
	 *
	 * @param string $text Text to escape and output.
	 */
	public function e( string $text ): void {
		echo esc_html( $text );
	}

	/**
	 * Escape and return text
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	public function esc( string $text ): string {
		return esc_html( $text );
	}

	/**
	 * Output attribute value
	 *
	 * @param string $attr Attribute value.
	 */
	public function attr( string $attr ): void {
		echo esc_attr( $attr );
	}

	/**
	 * Output URL
	 *
	 * @param string $url URL to output.
	 */
	public function url( string $url ): void {
		echo esc_url( $url );
	}

	/**
	 * Check if integration is configured
	 *
	 * @param mixed $integration Integration object.
	 * @return bool
	 */
	public function is_configured( $integration ): bool {
		return $integration && $integration->isConfigured();
	}

	/**
	 * Get integration icon HTML
	 *
	 * @param mixed $integration Integration object.
	 * @return string
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
	 * @param string $status Status value.
	 * @return string
	 */
	public function status_badge( string $status ): string {
		$classes = array(
			'success' => 'badge-success',
			'error' => 'badge-error',
			'warning' => 'badge-warning',
			'info' => 'badge-info',
		);

		$class = $classes[ $status ] ?? 'badge-default';
		
		return '<span class="status-badge ' . esc_attr( $class ) . '">' . esc_html( ucfirst( $status ) ) . '</span>';
	}

	/**
	 * Format date for display
	 *
	 * @param string $date Date string.
	 * @return string
	 */
	public function format_date( string $date ): string {
		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $date ) );
	}

	/**
	 * Get admin URL for integration
	 *
	 * @param string $integration_id Integration ID.
	 * @param string $tab           Tab name.
	 * @return string
	 */
	public function integration_url( string $integration_id, string $tab = 'settings' ): string {
		return admin_url( "admin.php?page=mavlers-cf-integrations&tab={$tab}&integration={$integration_id}" );
	}
} 