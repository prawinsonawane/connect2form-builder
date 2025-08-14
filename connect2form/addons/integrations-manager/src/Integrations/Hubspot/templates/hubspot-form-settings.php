<?php
/**
 * HubSpot Form Settings Template - User-Friendly Version
 *
 * Clean, modern HubSpot integration settings with proper loading and success messages
 *
 * @package Connect2Form
 * @since    2.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get form ID.
$form_id = 0;

// Check nonce if it’s part of form processing (Example)
if ( isset( $_POST['connect2form_nonce'] ) && ! check_admin_referer( 'connect2form_nonce_action', 'connect2form_nonce' ) ) {
    // If nonce verification fails, prevent further execution
    die( 'Nonce verification failed' );
}

// Get form ID from GET or other sources with sanitization
if ( isset( $_GET['id'] ) && $_GET['id'] ) {
    // Unsplash and sanitize the form ID
    $form_id = absint( wp_unslash( $_GET['id'] ) );  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
} elseif ( isset( $hubspot_form_id ) && $hubspot_form_id ) {
    $form_id = absint( $hubspot_form_id );
} elseif ( isset( $GLOBALS['connect2form_current_form_id'] ) ) {
    $form_id = absint( $GLOBALS['connect2form_current_form_id'] );
}

// Now $form_id is sanitized and ready for use


// Get HubSpot integration instance.
$hubspot_integration = null;
$is_configured       = false;
$global_settings     = array();

try {
	if ( class_exists( 'Connect2Form\Integrations\Core\Plugin' ) ) {
		$plugin = Connect2Form\Integrations\Core\Plugin::getInstance();
		if ( $plugin && method_exists( $plugin, 'getRegistry' ) ) {
			$registry = $plugin->getRegistry();
			if ( $registry && method_exists( $registry, 'get' ) ) {
				$hubspot_integration = $registry->get( 'hubspot' );
			}
		}
	}

	if ( $hubspot_integration ) {
		$is_configured = method_exists( $hubspot_integration, 'is_globally_connected' )
			? $hubspot_integration->is_globally_connected()
			: ( method_exists( $hubspot_integration, 'isConfigured' ) ? $hubspot_integration->isConfigured() : false );

		$global_settings = method_exists( $hubspot_integration, 'get_global_settings' )
			? $hubspot_integration->get_global_settings()
			: ( get_option( 'connect2form_integrations_global', array() )['hubspot'] ?? array() );
	}
} catch ( Exception $e ) {
	$is_configured = false;
}

// Load form settings from custom meta table.
$form_settings = array();
if ( $form_id ) {
	// Use service class instead of direct database call.
	if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
		$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
		$meta_value      = $service_manager->database()->getFormMeta( $form_id, '_connect2form_integrations' );

		if ( $meta_value ) {
			$integration_settings = json_decode( $meta_value, true );
			if ( is_array( $integration_settings ) && isset( $integration_settings['hubspot'] ) ) {
				$form_settings = $integration_settings['hubspot'];
			}
		}
	} else {
		// Fallback to direct database call if service not available.
		global $wpdb;
		$meta_table = $wpdb->prefix . 'connect2form_form_meta';

		// Check if meta table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence check for form meta; no caching needed for INFORMATION_SCHEMA queries
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s", $meta_table ) ) == $meta_table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- form meta query for template display; no caching needed for template rendering
			$meta_value = $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_value FROM $meta_table WHERE form_id = %d AND meta_key = %s",
				$form_id,
				'_connect2form_integrations'
			) );

			if ( $meta_value ) {
				$integration_settings = json_decode( $meta_value, true );
				if ( is_array( $integration_settings ) && isset( $integration_settings['hubspot'] ) ) {
					$form_settings = $integration_settings['hubspot'];
				}
			}
		}
	}

	// Fallback: Try post meta (for backward compatibility).
	if ( empty( $form_settings ) ) {
		$post_meta = get_post_meta( $form_id, '_connect2form_integrations', true );
		if ( $post_meta && isset( $post_meta['hubspot'] ) ) {
			$form_settings = $post_meta['hubspot'];
		}
	}

	// Fallback: Try options table.
	if ( empty( $form_settings ) ) {
		$option_key   = "connect2form_hubspot_form_{$form_id}";
		$form_settings = get_option( $option_key, array() );
	}
}

// Default settings.
$default_settings = array(
	'enabled'           => false,
	'object_type'       => 'contacts',
	'custom_object_name' => '',
	'hubspot_form_id'   => '',
	'action_type'       => 'create_or_update',
	'workflow_enabled'  => false,
	'field_mapping'     => array(),
);

$form_settings = array_merge( $default_settings, $form_settings );

// Auto-enable if settings exist.
if ( ! isset( $form_settings['enabled'] ) && ! empty( $form_settings ) ) {
	$form_settings['enabled'] = true;
}
?>

<div class="hubspot-form-settings" data-form-id="<?php echo esc_attr( $form_id ); ?>">
	<?php if ( ! $is_configured ) : ?>
		<!-- Not Connected State -->
		<div class="integration-not-connected">
			<div class="not-connected-icon">
				<span class="dashicons dashicons-businessman"></span>
			</div>
			<div class="not-connected-content">
				<h4><?php echo esc_html__( 'HubSpot Not Connected', 'connect2form' ); ?></h4>
				<p><?php echo esc_html__( 'Please configure your HubSpot access token in the global settings first.', 'connect2form' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=connect2form-integrations&tab=settings&integration=hubspot' ) ); ?>" class="button button-primary">
					<?php echo esc_html__( 'Configure HubSpot', 'connect2form' ); ?>
				</a>
			</div>
		</div>
	<?php else : ?>
		<!-- Connected State -->
		<div class="integration-form-settings">
			<!-- Header -->
			<div class="integration-header">
				<div class="integration-info">
					<div class="integration-title">
						<span class="dashicons dashicons-businessman"></span>
						<span><?php echo esc_html__( 'HubSpot Integration', 'connect2form' ); ?></span>
					</div>
					<div class="integration-status connected">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php echo esc_html__( 'Connected', 'connect2form' ); ?>
					</div>
				</div>

				<div class="integration-toggle">
					<label class="switch">
						<input type="checkbox" id="hubspot_enabled" name="enabled" value="1" <?php checked( $form_settings['enabled'] ); ?>>
						<span class="slider round"></span>
					</label>
					<span class="toggle-label">
						<?php echo esc_html__( 'Enable HubSpot Integration', 'connect2form' ); ?>
					</span>
				</div>
			</div>

			<!-- Settings Form -->
			<div class="integration-settings" id="hubspot_settings" style="<?php echo esc_attr( $form_settings['enabled'] ? 'display: block;' : 'display: none;' ); ?>">
				<form id="hubspot-form-settings">
					<?php wp_nonce_field( 'connect2form_nonce', 'hubspot_form_nonce' ); ?>
					<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>" />

					<!-- Object Type Selection -->
					<div class="setting-group">
						<h4><?php echo esc_html__( 'HubSpot Object Type', 'connect2form' ); ?></h4>
						<div class="setting-row">
							<label for="hubspot_object_type" class="setting-label">
								<?php echo esc_html__( 'Select Object Type', 'connect2form' ); ?>
								<span class="required">*</span>
							</label>
							<div class="setting-control">
								<select id="hubspot_object_type" name="object_type" class="setting-select" required>
									<option value=""><?php echo esc_html__( 'Choose an object type...', 'connect2form' ); ?></option>
									<option value="contacts" <?php selected( $form_settings['object_type'], 'contacts' ); ?>><?php echo esc_html__( 'Contacts', 'connect2form' ); ?></option>
									<option value="deals" <?php selected( $form_settings['object_type'], 'deals' ); ?>><?php echo esc_html__( 'Deals', 'connect2form' ); ?></option>
									<option value="companies" <?php selected( $form_settings['object_type'], 'companies' ); ?>><?php echo esc_html__( 'Companies', 'connect2form' ); ?></option>
									<option value="custom" <?php selected( $form_settings['object_type'], 'custom' ); ?>><?php echo esc_html__( 'Custom Objects', 'connect2form' ); ?></option>
									<option value="forms" <?php selected( $form_settings['object_type'], 'forms' ); ?>><?php echo esc_html__( 'Forms', 'connect2form' ); ?></option>
								</select>
							</div>
							<p class="setting-help">
								<?php echo esc_html__( 'Choose which HubSpot object type to save form data to.', 'connect2form' ); ?>
							</p>
						</div>

						<!-- Custom Object Selection -->
						<div class="setting-row" id="hubspot-custom-object-row" style="display: <?php echo esc_attr( $form_settings['object_type'] === 'custom' ? 'block' : 'none' ); ?>;">
							<label for="hubspot_custom_object" class="setting-label">
								<?php echo esc_html__( 'Custom Object', 'connect2form' ); ?>
								<span class="required">*</span>
							</label>
							<div class="setting-control">
								<select id="hubspot_custom_object" name="custom_object_name" class="setting-select">
									<option value=""><?php echo esc_html__( 'Loading custom objects...', 'connect2form' ); ?></option>
								</select>
							</div>
							<p class="setting-help">
								<?php echo esc_html__( 'Choose which custom object to save data to.', 'connect2form' ); ?>
							</p>
						</div>

						<!-- HubSpot Form Selection -->
						<div class="setting-row" id="hubspot-form-selection-row" style="display: <?php echo esc_attr( $form_settings['object_type'] === 'forms' ? 'block' : 'none' ); ?>;">
							<label for="hubspot_form_select" class="setting-label">
								<?php echo esc_html__( 'HubSpot Form', 'connect2form' ); ?>
								<span class="required">*</span>
							</label>
							<div class="setting-control">
								<select id="hubspot_form_select" name="hubspot_form_id" class="setting-select">
									<option value=""><?php echo esc_html__( 'Loading HubSpot forms...', 'connect2form' ); ?></option>
								</select>
							</div>
							<p class="setting-help">
								<?php echo esc_html__( 'Choose which HubSpot form to submit data to. Data will be submitted as if filled directly on your HubSpot form.', 'connect2form' ); ?>
							</p>
						</div>
					</div>

					<!-- Action Type -->
					<div class="setting-group">
						<h4><?php echo esc_html__( 'Data Action', 'connect2form' ); ?></h4>
						<div class="setting-row">
							<label for="hubspot_action_type" class="setting-label">
								<?php echo esc_html__( 'Action Type', 'connect2form' ); ?>
							</label>
							<div class="setting-control">
								<select id="hubspot_action_type" name="action_type" class="setting-select">
									<option value="create" <?php selected( $form_settings['action_type'], 'create' ); ?>><?php echo esc_html__( 'Create New', 'connect2form' ); ?></option>
									<option value="update" <?php selected( $form_settings['action_type'], 'update' ); ?>><?php echo esc_html__( 'Update Existing', 'connect2form' ); ?></option>
									<option value="create_or_update" <?php selected( $form_settings['action_type'], 'create_or_update' ); ?>><?php echo esc_html__( 'Create or Update', 'connect2form' ); ?></option>
								</select>
							</div>
							<p class="setting-help">
								<?php echo esc_html__( 'Choose whether to create new records, update existing ones, or both.', 'connect2form' ); ?>
							</p>
						</div>
					</div>

					<!-- Additional Options -->
					<div class="setting-group">
						<h4><?php echo esc_html__( 'Additional Options', 'connect2form' ); ?></h4>
						<div class="setting-row">
							<label class="setting-label">
								<input type="checkbox" id="hubspot_workflow_enabled" name="workflow_enabled" value="1" <?php checked( $form_settings['workflow_enabled'] ); ?>>
								<?php echo esc_html__( 'Enroll in Workflow', 'connect2form' ); ?>
							</label>
							<p class="setting-help">
								<?php echo esc_html__( 'Enroll contact in HubSpot workflow after form submission.', 'connect2form' ); ?>
							</p>
						</div>
					</div>

					<!-- Field Mapping Section -->
					<div class="setting-group" id="hubspot-field-mapping-section" style="display: <?php echo esc_attr( in_array( $form_settings['object_type'], array( 'contacts', 'deals', 'companies', 'forms' ), true ) ? 'block' : 'none' ); ?>;">
						<h4><?php echo esc_html__( 'Field Mapping', 'connect2form' ); ?></h4>
						<div class="setting-row">
							<div class="field-mapping-container">
								<div class="field-mapping-header">
									<div class="mapping-instructions">
										<p><?php echo esc_html__( 'Map your form fields to HubSpot properties. Email mapping is recommended for contacts.', 'connect2form' ); ?></p>
									</div>
									<div class="mapping-actions">
										<button type="button" id="hubspot-auto-map-fields" class="button button-secondary">
											<span class="dashicons dashicons-randomize"></span>
											<?php echo esc_html__( 'Auto-Map', 'connect2form' ); ?>
										</button>
										<button type="button" id="hubspot-clear-mappings" class="button button-secondary">
											<span class="dashicons dashicons-dismiss"></span>
											<?php echo esc_html__( 'Clear All', 'connect2form' ); ?>
										</button>
										<button type="button" id="hubspot-clean-mappings" class="button button-secondary" title="Remove mappings for deleted/duplicate fields">
											<span class="dashicons dashicons-admin-tools"></span>
											<?php echo esc_html__( 'Clean Up', 'connect2form' ); ?>
										</button>
									</div>
								</div>

								<div class="field-mapping-table-container">
									<table class="field-mapping-table">
										<thead>
											<tr>
												<th><?php echo esc_html__( 'Form Fields', 'connect2form' ); ?></th>
												<th class="mapping-arrow">→</th>
												<th><?php echo esc_html__( 'HubSpot Properties', 'connect2form' ); ?></th>
												<th><?php echo esc_html__( 'Status', 'connect2form' ); ?></th>
											</tr>
										</thead>
										<tbody id="hubspot-field-mapping-tbody">
											<tr class="no-fields-row">
												<td colspan="4" class="text-center">
													<?php echo esc_html__( 'Select an object type to see field mapping options.', 'connect2form' ); ?>
												</td>
											</tr>
										</tbody>
									</table>
								</div>

								<div class="mapping-status">
									<span id="hubspot-mapping-count">0 fields mapped</span>
								</div>
							</div>
						</div>
					</div>

					<!-- Save Button -->
					<div class="setting-actions">
						<button type="submit" class="button button-primary" id="hubspot-save-settings">
							<span class="button-text"><?php echo esc_html__( 'Save HubSpot Settings', 'connect2form' ); ?></span>
							<span class="button-loading" style="display: none;">
								<span class="spinner is-active"></span>
								<?php echo esc_html__( 'Saving...', 'connect2form' ); ?>
							</span>
						</button>
						<div id="hubspot-save-status" class="save-status"></div>
					</div>
				</form>
			</div>
		</div>
	<?php endif; ?>
</div>

<style>
/* HubSpot Integration Styles */
.hubspot-form-settings {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 8px;
	margin: 20px 0;
}

.integration-not-connected {
	padding: 30px;
	text-align: center;
	background: #f8f9fa;
	border-radius: 8px;
}

.not-connected-icon {
	font-size: 48px;
	color: #6c757d;
	margin-bottom: 20px;
}

.not-connected-content h4 {
	margin: 0 0 10px 0;
	color: #495057;
}

.integration-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 20px;
	border-bottom: 1px solid #eee;
	background: #f8f9fa;
	border-radius: 8px 8px 0 0;
}

.integration-info {
	display: flex;
	align-items: center;
	gap: 15px;
}

.integration-title {
	display: flex;
	align-items: center;
	gap: 8px;
	font-weight: 600;
	font-size: 16px;
}

.integration-status {
	display: flex;
	align-items: center;
	gap: 5px;
	padding: 4px 12px;
	border-radius: 20px;
	font-size: 12px;
	font-weight: 500;
}

.integration-status.connected {
	background: #d4edda;
	color: #155724;
}

.integration-toggle {
	display: flex;
	align-items: center;
	gap: 10px;
}

.switch {
	position: relative;
	display: inline-block;
	width: 50px;
	height: 24px;
}

.switch input {
	opacity: 0;
	width: 0;
	height: 0;
}

.slider {
	position: absolute;
	cursor: pointer;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background-color: #ccc;
	transition: .4s;
	border-radius: 24px;
}

.slider:before {
	position: absolute;
	content: "";
	height: 18px;
	width: 18px;
	left: 3px;
	bottom: 3px;
	background-color: white;
	transition: .4s;
	border-radius: 50%;
}

input:checked + .slider {
	background-color: #0073aa;
}

input:checked + .slider:before {
	transform: translateX(26px);
}

.integration-settings {
	padding: 20px;
}

.setting-group {
	margin-bottom: 30px;
	padding: 20px;
	background: #f8f9fa;
	border-radius: 6px;
}

.setting-group h4 {
	margin: 0 0 15px 0;
	color: #495057;
	font-size: 14px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.setting-row {
	margin-bottom: 20px;
}

.setting-label {
	display: block;
	margin-bottom: 8px;
	font-weight: 500;
	color: #495057;
}

.setting-control {
	margin-bottom: 8px;
}

.setting-select {
	width: 100%;
	max-width: 400px;
	padding: 8px 12px;
	border: 1px solid #ddd;
	border-radius: 4px;
	font-size: 14px;
}

.setting-help {
	margin: 5px 0 0 0;
	font-size: 12px;
	color: #6c757d;
	font-style: italic;
}

.field-mapping-container {
	width: 100%;
}

.field-mapping-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 15px;
	padding: 15px;
	background: #e9ecef;
	border-radius: 4px;
}

.mapping-instructions p {
	margin: 0;
	font-size: 13px;
	color: #495057;
}

.mapping-actions {
	display: flex;
	gap: 10px;
}

.field-mapping-table-container {
	margin-bottom: 15px;
	border: 1px solid #ddd;
	border-radius: 4px;
	overflow: hidden;
}

.field-mapping-table {
	width: 100%;
	border-collapse: collapse;
}

.field-mapping-table th,
.field-mapping-table td {
	padding: 12px;
	text-align: left;
	border-bottom: 1px solid #eee;
}

.field-mapping-table th {
	background: #f8f9fa;
	font-weight: 600;
	font-size: 12px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.mapping-arrow {
	text-align: center;
	font-weight: bold;
	color: #0073aa;
}

.text-center {
	text-align: center;
	color: #6c757d;
	font-style: italic;
}

.mapping-status {
	text-align: right;
	font-size: 12px;
	color: #6c757d;
}

.setting-actions {
	display: flex;
	align-items: center;
	gap: 15px;
	padding-top: 20px;
	border-top: 1px solid #eee;
}

.save-status {
	font-size: 13px;
	font-weight: 500;
}

.save-status.success {
	color: #155724;
}

.save-status.error {
	color: #721c24;
}

.button-loading {
	display: flex;
	align-items: center;
	gap: 5px;
}

.spinner.is-active {
	width: 12px;
	height: 12px;
	border: 2px solid #f3f3f3;
	border-top: 2px solid #0073aa;
	border-radius: 50%;
	animation: spin 1s linear infinite;
}

@keyframes spin {
	0% { transform: rotate(0deg); }
	100% { transform: rotate(360deg); }
}

.required {
	color: #dc3545;
}

/* Responsive */
@media (max-width: 768px) {
	.integration-header {
		flex-direction: column;
		gap: 15px;
		text-align: center;
	}

	.field-mapping-header {
		flex-direction: column;
		gap: 10px;
		text-align: center;
	}

	.mapping-actions {
		justify-content: center;
	}
}
</style>

<script>
// Initialize HubSpot settings.
window.hubspotFormSettings = <?php echo wp_json_encode( $form_settings ); ?>;
window.hubspotFormId = <?php echo (int) $form_id; ?>;
window.hubspotGlobalSettings = <?php echo wp_json_encode( $global_settings ); ?>;

// Create connect2formCFHubspot object.
window.connect2formCFHubspot = {
	nonce: '<?php echo esc_js( wp_create_nonce( "connect2form_nonce" ) ); ?>',
	ajaxUrl: '<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>',
	formId: <?php echo (int) $form_id; ?>,
	globalSettings: <?php echo wp_json_encode( $global_settings ); ?>,
	strings: {
		testing: '<?php echo esc_js( __( "Testing...", 'connect2form' ) ); ?>',
		connected: '<?php echo esc_js( __( "Connected", 'connect2form' ) ); ?>',
		disconnected: '<?php echo esc_js( __( "Disconnected", 'connect2form' ) ); ?>',
		testConnection: '<?php echo esc_js( __( "Test Connection", 'connect2form' ) ); ?>',
		savingSettings: '<?php echo esc_js( __( "Saving...", 'connect2form' ) ); ?>',
		settingsSaved: '<?php echo esc_js( __( "Settings saved successfully!", 'connect2form' ) ); ?>',
		connectionFailed: '<?php echo esc_js( __( "Connection failed", 'connect2form' ) ); ?>',
		selectContact: '<?php echo esc_js( __( "Select contact properties...", 'connect2form' ) ); ?>',
		loadingFields: '<?php echo esc_js( __( "Loading fields...", 'connect2form' ) ); ?>',
		fieldsLoaded: '<?php echo esc_js( __( "Fields loaded successfully", 'connect2form' ) ); ?>',
		noFieldsFound: '<?php echo esc_js( __( "No fields found", 'connect2form' ) ); ?>',
		networkError: '<?php echo esc_js( __( "Network error", 'connect2form' ) ); ?>',
		mappingSaved: '<?php echo esc_js( __( "Field mapping saved successfully", 'connect2form' ) ); ?>',
		mappingFailed: '<?php echo esc_js( __( "Failed to save field mapping", 'connect2form' ) ); ?>',
		autoMappingComplete: '<?php echo esc_js( __( "Auto-mapping completed", 'connect2form' ) ); ?>',
		clearMappingsConfirm: '<?php echo esc_js( __( "Are you sure you want to clear all mappings?", 'connect2form' ) ); ?>',
	},
};

</script> 


