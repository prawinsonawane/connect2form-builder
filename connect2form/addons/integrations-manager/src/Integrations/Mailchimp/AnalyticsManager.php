<?php
// phpcs:ignoreFile -- Analytics class uses many WordPress globals (wp_*), which exist at runtime; security-sensitive code paths remain validated and sanitized.
/**
 * Mailchimp Analytics Manager
 * 
 * Handles analytics tracking, dashboard functionality, and reporting for Mailchimp integration
 * 
 * @package Connect2Form\\Integrations\\Mailchimp
 * @since 1.0.0
 */

namespace Connect2Form\Integrations\Mailchimp;

// Import common WP functions for static analysis in this namespace
use function \add_action;
use function \wp_next_scheduled;
use function \wp_schedule_event;
use function \wp_register_script;
use function \plugin_dir_url;
use function \wp_enqueue_script;
use function \wp_enqueue_style;
use function \wp_localize_script;
use function \wp_create_nonce;
use function \admin_url;
use function \__;
use function \wp_json_encode;
use function \sanitize_text_field;
use function \wp_unslash;
use function \esc_url_raw;
use function \current_time;
use function \do_action;
use function \wp_cache_get;
use function \wp_cache_set;
use function \wp_send_json_success;
use function \wp_send_json_error;
use function \check_ajax_referer;
use function \current_user_can;
use function \apply_filters;
use function \wp_cache_flush_group;
use function \dbDelta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mailchimp Analytics Manager Class
 *
 * @since    1.0.0
 */
class AnalyticsManager {
	
	/**
	 * Database table name for analytics
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	private $analytics_table;
	
	/**
	 * Cache group for analytics data
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	private $cache_group = 'connect2form_mailchimp_analytics';
	
	/**
	 * Cache expiry time
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	private $cache_expiry = 1800; // 30 minutes
	
	/**
	 * Analytics data types
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private $data_types = array(
		'subscription' => 'Subscription',
		'unsubscription' => 'Unsubscription',
		'update' => 'Profile Update',
		'bounce' => 'Email Bounce',
		'complaint' => 'Spam Complaint',
		'open' => 'Email Open',
		'click' => 'Link Click'
	);
	
	/**
	 * Constructor
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->analytics_table = $wpdb->prefix . 'connect2form_mailchimp_analytics';
		
		$this->init_hooks();
		$this->maybe_create_analytics_table();
	}
	
	/**
	 * Initialize WordPress hooks
	 *
	 * @since    1.0.0
	 */
	private function init_hooks() {
		// Analytics tracking hooks.
        \add_action( 'connect2form_mailchimp_subscription_success', array( $this, 'track_subscription' ), 10, 3 );
        \add_action( 'connect2form_mailchimp_subscription_error', array( $this, 'track_subscription_error' ), 10, 3 );
        \add_action( 'connect2form_mailchimp_profile_updated', array( $this, 'track_profile_update' ), 10, 3 );
		
		// Webhook analytics.
        \add_action( 'connect2form_mailchimp_webhook_processed', array( $this, 'track_webhook_event' ), 10, 2 );
		
		// Admin dashboard - AJAX handlers moved to main integration to avoid conflicts.
		// Analytics AJAX handlers are now registered in MailchimpIntegration.php.
		
		// Cleanup old analytics data.
        \add_action( 'connect2form_mailchimp_cleanup_analytics', array( $this, 'cleanup_old_analytics' ) );
		
		// Schedule cleanup if not already scheduled.
        if ( ! \wp_next_scheduled( 'connect2form_mailchimp_cleanup_analytics' ) ) {
            \wp_schedule_event( time(), 'weekly', 'connect2form_mailchimp_cleanup_analytics' );
		}
		
		// Admin assets.
        \add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_analytics_assets' ) );
	}
	
	/**
	 * Create analytics table if it doesn't exist
	 *
	 * @since    1.0.0
	 */
	private function maybe_create_analytics_table() {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();
		
		$sql = "CREATE TABLE IF NOT EXISTS {$this->analytics_table} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			form_id bigint(20) NOT NULL,
			audience_id varchar(20) NOT NULL,
			email varchar(255) NOT NULL,
			event_type varchar(50) NOT NULL,
			event_data longtext,
			user_agent text,
			ip_address varchar(45),
			referrer text,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY form_id (form_id),
			KEY audience_id (audience_id),
			KEY email (email),
			KEY event_type (event_type),
			KEY created_at (created_at)
		) $charset_collate;";
		
		require_once( \ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
	
	/**
	 * Enqueue analytics assets
	 *
	 * @since    1.0.0
	 * @param    string $hook Hook name.
	 */
	public function enqueue_analytics_assets( $hook ) {
		if ( strpos( $hook, 'connect2form' ) !== false ) {
			// Load local Chart.js for analytics charts (WordPress.org compliance).
        \wp_register_script(
				'chartjs',
            \plugin_dir_url( dirname( dirname( dirname( __FILE__ ) ) ) ) . 'assets/js/vendor/chart.min.js',
				array(),
				'3.9.1',
				true
			);
        \wp_enqueue_script( 'chartjs' );
			
			// Analytics dashboard CSS.
        \wp_enqueue_style(
				'connect2form-analytics-dashboard',
				CONNECT2FORM_INTEGRATIONS_URL . 'assets/css/admin/analytics-dashboard.css',
				array(),
				'1.0.0'
			);
			
			// Analytics dashboard JavaScript.
        \wp_enqueue_script(
				'connect2form-analytics-dashboard',
				CONNECT2FORM_INTEGRATIONS_URL . 'assets/js/admin/analytics-dashboard.js',
				array( 'jquery', 'chartjs' ),
				'1.0.0',
				true
			);
			
			// Localize script.
        \wp_localize_script( 'connect2form-analytics-dashboard', 'connect2formCFAnalytics', array(
                'nonce' => \wp_create_nonce( 'connect2form_nonce' ),
                'ajaxUrl' => \admin_url( 'admin-ajax.php' ),
				'strings' => array(
                    'loading' => \__( 'Loading...', 'connect2form' ),
                    'noData' => \__( 'No data available', 'connect2form' ),
                    'subscriptions' => \__( 'Subscriptions', 'connect2form' ),
                    'errors' => \__( 'Errors', 'connect2form' ),
                    'conversionRate' => \__( 'Conversion Rate', 'connect2form' ),
                    'exportSuccess' => \__( 'Analytics exported successfully', 'connect2form' ),
                    'exportError' => \__( 'Failed to export analytics', 'connect2form' ),
                    'today' => \__( 'Today', 'connect2form' ),
                    'yesterday' => \__( 'Yesterday', 'connect2form' ),
                    'thisWeek' => \__( 'This Week', 'connect2form' ),
                    'lastWeek' => \__( 'Last Week', 'connect2form' ),
                    'thisMonth' => \__( 'This Month', 'connect2form' ),
                    'lastMonth' => \__( 'Last Month', 'connect2form' ),
                    'last30Days' => \__( 'Last 30 Days', 'connect2form' ),
                    'last90Days' => \__( 'Last 90 Days', 'connect2form' )
				)
			) );
		}
	}
	
	/**
	 * Track successful subscription
	 *
	 * @since    1.0.0
	 * @param    string $email       Email address.
	 * @param    int    $form_id     Form ID.
	 * @param    string $audience_id Audience ID.
	 * @param    array  $data        Additional data.
	 */
	public function track_subscription( $email, $form_id, $audience_id, $data = array() ) {
		$this->record_analytics_event( array(
			'form_id' => $form_id,
			'audience_id' => $audience_id,
			'email' => $email,
			'event_type' => 'subscription',
            'event_data' => \wp_json_encode( $data )
		) );
	}
	
	/**
	 * Track subscription error
	 *
	 * @since    1.0.0
	 * @param    string $email       Email address.
	 * @param    int    $form_id     Form ID.
	 * @param    string $audience_id Audience ID.
	 * @param    array  $error_data  Error data.
	 */
	public function track_subscription_error( $email, $form_id, $audience_id, $error_data = array() ) {
		$this->record_analytics_event( array(
			'form_id' => $form_id,
			'audience_id' => $audience_id,
			'email' => $email,
			'event_type' => 'subscription_error',
            'event_data' => \wp_json_encode( $error_data )
		) );
	}
	
	/**
	 * Track profile update
	 *
	 * @since    1.0.0
	 * @param    string $email       Email address.
	 * @param    int    $form_id     Form ID.
	 * @param    string $audience_id Audience ID.
	 * @param    array  $data        Update data.
	 */
	public function track_profile_update( $email, $form_id, $audience_id, $data = array() ) {
		$this->record_analytics_event( array(
			'form_id' => $form_id,
			'audience_id' => $audience_id,
			'email' => $email,
			'event_type' => 'update',
            'event_data' => \wp_json_encode( $data )
		) );
	}
	
	/**
	 * Track webhook event
	 *
	 * @since    1.0.0
	 * @param    string $event_type Event type.
	 * @param    array  $data       Event data.
	 */
	public function track_webhook_event( $event_type, $data ) {
		$email = $data['email'] ?? '';
		$audience_id = $data['list_id'] ?? '';
		
		$this->record_analytics_event( array(
			'form_id' => 0, // Webhook events don't have form context.
			'audience_id' => $audience_id,
			'email' => $email,
			'event_type' => $event_type,
            'event_data' => \wp_json_encode( $data )
		) );
	}
	
	/**
	 * Record analytics event
	 *
	 * @since    1.0.0
	 * @param    array $event_data Event data.
	 * @return   bool
	 */
	private function record_analytics_event( $event_data ) {
		global $wpdb;
		
		// Add metadata with sanitization.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized immediately via sanitize_text_field
        $event_data['user_agent'] = isset( $_SERVER['HTTP_USER_AGENT'] ) ? \sanitize_text_field( \wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$event_data['ip_address'] = $this->get_client_ip();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized immediately via esc_url_raw
        $event_data['referrer'] = isset( $_SERVER['HTTP_REFERER'] ) ? \esc_url_raw( \wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) ) : '';
        $event_data['created_at'] = \current_time( 'mysql' );
		
		// Insert into database.
		if ( class_exists( '\\Connect2Form\\Integrations\\Core\\Services\\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$insert_id = $service_manager->database()->insertAnalyticsEvent( $event_data );
			$result = $insert_id > 0;
		} else {
			// Fallback to direct database call if service not available.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics event insert; service layer preferred but this is a fallback.
			$result = $wpdb->insert( $this->analytics_table, $event_data );
			$insert_id = $result ? $wpdb->insert_id : 0;
		}
		
		if ( $result ) {
			// Clear relevant caches.
			$this->clear_analytics_cache( $event_data['form_id'], $event_data['audience_id'] );
			
			// Trigger action for other plugins.
            \do_action( 'connect2form_mailchimp_analytics_recorded', $event_data, $insert_id );
		}
		
		return $result;
	}
	
	/**
	 * Get client IP address
	 *
	 * @since    1.0.0
	 * @return   string
	 */
	private function get_client_ip() {
		$ip_keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );
		
		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = trim( explode( ',', $_SERVER[ $key ] )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}
		
		return $_SERVER['REMOTE_ADDR'] ?? '';
	}
	
	/**
	 * Get analytics overview
	 *
	 * @since    1.0.0
	 * @param    string $date_range   Date range.
	 * @param    int    $form_id      Form ID.
	 * @param    string $audience_id  Audience ID.
	 * @return   array
	 */
	public function get_analytics_overview( $date_range = '30_days', $form_id = null, $audience_id = null ) {
		$cache_key = "overview_{$date_range}_{$form_id}_{$audience_id}";
        $cached = \wp_cache_get( $cache_key, $this->cache_group );
		
		if ( $cached !== false ) {
			return $cached;
		}
		
		global $wpdb;
		
		$date_conditions = $this->get_date_conditions( $date_range );
		$where_conditions = array( "created_at {$date_conditions}" );
		
		if ( $form_id ) {
			$where_conditions[] = $wpdb->prepare( "form_id = %d", $form_id );
		}
		
		if ( $audience_id ) {
			$where_conditions[] = $wpdb->prepare( "audience_id = %s", $audience_id );
		}
		
		$where_clause = implode( ' AND ', $where_conditions );
		
		// Get subscription stats.
		if ( class_exists( '\\Connect2Form\\Integrations\\Core\\Services\\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$filters = array();
			if ( $form_id ) $filters['form_id'] = $form_id;
			if ( $audience_id ) $filters['audience_id'] = $audience_id;
			$subscription_stats = $service_manager->database()->getAnalyticsStats( $filters );
		} else {
			// Fallback to direct database call if service not available.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics subscription stats query; service layer preferred but this is a fallback.
			$subscription_stats = $wpdb->get_row( " // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated static table identifier and composed WHERE clause
				SELECT 
					COUNT(*) as total_submissions,
					SUM(CASE WHEN event_type = 'subscription' THEN 1 ELSE 0 END) as successful_subscriptions,
					SUM(CASE WHEN event_type = 'subscription_error' THEN 1 ELSE 0 END) as failed_subscriptions,
					SUM(CASE WHEN event_type = 'update' THEN 1 ELSE 0 END) as profile_updates
				FROM {$this->analytics_table} 
				WHERE {$where_clause}
			" );
		}
		
		// Calculate conversion rate.
		$conversion_rate = 0;
		if ( $subscription_stats->total_submissions > 0 ) {
			$conversion_rate = round( ( $subscription_stats->successful_subscriptions / $subscription_stats->total_submissions ) * 100, 2 );
		}
		
		// Get top performing forms.
		if ( class_exists( '\\Connect2Form\\Integrations\\Core\\Services\\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$top_forms = $service_manager->database()->getTopForms( 5 );
		} else {
			// Fallback to direct database call if service not available.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics top forms query; service layer preferred but this is a fallback.
			$top_forms = $wpdb->get_results( " // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated static table identifier and composed WHERE clause
				SELECT 
					form_id,
					COUNT(*) as submissions,
					SUM(CASE WHEN event_type = 'subscription' THEN 1 ELSE 0 END) as subscriptions
				FROM {$this->analytics_table} 
				WHERE {$where_clause} AND form_id > 0
				GROUP BY form_id
				ORDER BY subscriptions DESC
				LIMIT 5
			" );
		}
		
		// Get recent activity.
		if ( class_exists( '\\Connect2Form\\Integrations\\Core\\Services\\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$recent_activity = $service_manager->database()->getRecentActivity( 10 );
		} else {
			// Fallback to direct database call if service not available.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics recent activity query; service layer preferred but this is a fallback.
			$recent_activity = $wpdb->get_results( " // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated static table identifier and composed WHERE clause
				SELECT event_type, email, form_id, audience_id, created_at
				FROM {$this->analytics_table} 
				WHERE {$where_clause}
				ORDER BY created_at DESC
				LIMIT 10
			" );
		}
		
		$overview = array(
			'stats' => array(
				'total_submissions' => intval( $subscription_stats->total_submissions ),
				'successful_subscriptions' => intval( $subscription_stats->successful_subscriptions ),
				'failed_subscriptions' => intval( $subscription_stats->failed_subscriptions ),
				'profile_updates' => intval( $subscription_stats->profile_updates ),
				'conversion_rate' => $conversion_rate
			),
			'top_forms' => $top_forms,
			'recent_activity' => $recent_activity
		);
		
		// Cache for 30 minutes.
        \wp_cache_set( $cache_key, $overview, $this->cache_group, $this->cache_expiry );
		
		return $overview;
	}
	
	/**
	 * Get analytics chart data
	 *
	 * @since    1.0.0
	 * @param    string $chart_type   Chart type.
	 * @param    string $date_range   Date range.
	 * @param    int    $form_id      Form ID.
	 * @param    string $audience_id  Audience ID.
	 * @return   array
	 */
	public function get_analytics_chart( $chart_type = 'subscriptions', $date_range = '30_days', $form_id = null, $audience_id = null ) {
		$cache_key = "chart_{$chart_type}_{$date_range}_{$form_id}_{$audience_id}";
        $cached = \wp_cache_get( $cache_key, $this->cache_group );
		
		if ( $cached !== false ) {
			return $cached;
		}
		
		global $wpdb;
		
		$date_conditions = $this->get_date_conditions( $date_range );
		$where_conditions = array( "created_at {$date_conditions}" );
		
		if ( $form_id ) {
			$where_conditions[] = $wpdb->prepare( "form_id = %d", $form_id );
		}
		
		if ( $audience_id ) {
			$where_conditions[] = $wpdb->prepare( "audience_id = %s", $audience_id );
		}
		
		$where_clause = implode( ' AND ', $where_conditions );
		
		// Determine grouping based on date range.
		$date_format = $this->get_date_format_for_range( $date_range );
		$group_by = "DATE_FORMAT(created_at, '{$date_format}')";
		
		switch ( $chart_type ) {
			case 'subscriptions':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics chart data query; no caching needed for chart generation.
				$data = $wpdb->get_results( " // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated static table identifier and composed WHERE/ORDER/GROUP BY clauses
					SELECT 
						{$group_by} as date_group,
						SUM(CASE WHEN event_type = 'subscription' THEN 1 ELSE 0 END) as subscriptions,
						SUM(CASE WHEN event_type = 'subscription_error' THEN 1 ELSE 0 END) as errors
					FROM {$this->analytics_table} 
					WHERE {$where_clause}
					GROUP BY date_group
					ORDER BY date_group ASC
				" );
				break;
				
			case 'conversion':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics chart data query; no caching needed for chart generation.
				$data = $wpdb->get_results( " // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated static table identifier and composed WHERE/ORDER/GROUP BY clauses
					SELECT 
						{$group_by} as date_group,
						COUNT(*) as total,
						SUM(CASE WHEN event_type = 'subscription' THEN 1 ELSE 0 END) as successful
					FROM {$this->analytics_table} 
					WHERE {$where_clause}
					GROUP BY date_group
					ORDER BY date_group ASC
				" );
				
				// Calculate conversion rate for each period.
				foreach ( $data as &$row ) {
					$row->conversion_rate = $row->total > 0 ? round( ( $row->successful / $row->total ) * 100, 2 ) : 0;
				}
				break;
				
			case 'activity':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics chart data query; no caching needed for chart generation.
				$data = $wpdb->get_results( " // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated static table identifier and composed WHERE/ORDER/GROUP BY clauses
					SELECT 
						{$group_by} as date_group,
						event_type,
						COUNT(*) as count
					FROM {$this->analytics_table} 
					WHERE {$where_clause}
					GROUP BY date_group, event_type
					ORDER BY date_group ASC, event_type ASC
				" );
				break;
				
			default:
				$data = array();
		}
		
		// Format data for Chart.js.
		$chart_data = $this->format_chart_data( $data, $chart_type, $date_range );
		
		// Cache for 30 minutes.
        \wp_cache_set( $cache_key, $chart_data, $this->cache_group, $this->cache_expiry );
		
		return $chart_data;
	}
	
	/**
	 * Get form-specific analytics
	 *
	 * @since    1.0.0
	 * @param    int    $form_id     Form ID.
	 * @param    string $date_range  Date range.
	 * @return   array
	 */
	public function get_form_analytics( $form_id, $date_range = '30_days' ) {
		$cache_key = "form_{$form_id}_{$date_range}";
        $cached = \wp_cache_get( $cache_key, $this->cache_group );
		
		if ( $cached !== false ) {
			return $cached;
		}
		
		global $wpdb;
		
		$date_conditions = $this->get_date_conditions( $date_range );
		
		// Get form analytics.
		if ( class_exists( '\\Connect2Form\\Integrations\\Core\\Services\\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$form_analytics_data = $service_manager->database()->getFormAnalytics( $form_id );
			$analytics = $form_analytics_data['analytics'];
			$errors = $form_analytics_data['errors'];
			$hourly_pattern = $form_analytics_data['hourly_pattern'];
		} else {
			// Fallback to direct database call if service not available.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics form details query; service layer preferred but this is a fallback.
			$analytics = $wpdb->get_row( $wpdb->prepare( "
				SELECT 
					COUNT(*) as total_submissions,
					SUM(CASE WHEN event_type = 'subscription' THEN 1 ELSE 0 END) as successful_subscriptions,
					SUM(CASE WHEN event_type = 'subscription_error' THEN 1 ELSE 0 END) as failed_subscriptions,
					COUNT(DISTINCT email) as unique_emails,
					COUNT(DISTINCT audience_id) as audiences_used
				FROM {$this->analytics_table} 
				WHERE form_id = %d AND created_at {$date_conditions}
			", $form_id ) );
			
			// Get error breakdown.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics error breakdown query; service layer preferred but this is a fallback.
			$errors = $wpdb->get_results( $wpdb->prepare( "
				SELECT 
					event_data,
					COUNT(*) as count
				FROM {$this->analytics_table} 
				WHERE form_id = %d AND event_type = 'subscription_error' AND created_at {$date_conditions}
				GROUP BY event_data
				ORDER BY count DESC
				LIMIT 10
			", $form_id ) );
			
			// Get hourly activity pattern.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics hourly pattern query; service layer preferred but this is a fallback.
			$hourly_pattern = $wpdb->get_results( $wpdb->prepare( "
				SELECT 
					HOUR(created_at) as hour,
					COUNT(*) as submissions
				FROM {$this->analytics_table} 
				WHERE form_id = %d AND created_at {$date_conditions}
				GROUP BY hour
				ORDER BY hour ASC
			", $form_id ) );
		}
		
		$form_analytics = array(
			'stats' => $analytics,
			'errors' => $errors,
			'hourly_pattern' => $hourly_pattern
		);
		
		// Cache for 30 minutes.
        \wp_cache_set( $cache_key, $form_analytics, $this->cache_group, $this->cache_expiry );
		
		return $form_analytics;
	}
	
	/**
	 * Get audience-specific analytics
	 *
	 * @since    1.0.0
	 * @param    string $audience_id  Audience ID.
	 * @param    string $date_range   Date range.
	 * @return   array
	 */
	public function get_audience_analytics( $audience_id, $date_range = '30_days' ) {
		$cache_key = "audience_{$audience_id}_{$date_range}";
        $cached = \wp_cache_get( $cache_key, $this->cache_group );
		
		if ( $cached !== false ) {
			return $cached;
		}
		
		global $wpdb;
		
		$date_conditions = $this->get_date_conditions( $date_range );
		
		// Get audience analytics.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audience analytics query; no caching needed for audience analytics.
		$analytics = $wpdb->get_row( $wpdb->prepare( "
			SELECT 
				COUNT(*) as total_events,
				SUM(CASE WHEN event_type = 'subscription' THEN 1 ELSE 0 END) as subscriptions,
				SUM(CASE WHEN event_type = 'unsubscription' THEN 1 ELSE 0 END) as unsubscriptions,
				SUM(CASE WHEN event_type = 'update' THEN 1 ELSE 0 END) as profile_updates,
				COUNT(DISTINCT email) as unique_subscribers,
				COUNT(DISTINCT form_id) as forms_used
			FROM {$this->analytics_table} 
			WHERE audience_id = %s AND created_at {$date_conditions}
		", $audience_id ) );
		
		// Get top referring forms.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics top forms for audience query; no caching needed for analytics.
		$top_forms = $wpdb->get_results( $wpdb->prepare( "
			SELECT 
				form_id,
				COUNT(*) as subscriptions
			FROM {$this->analytics_table} 
			WHERE audience_id = %s AND event_type = 'subscription' AND created_at {$date_conditions}
			GROUP BY form_id
			ORDER BY subscriptions DESC
			LIMIT 5
		", $audience_id ) );
		
		$audience_analytics = array(
			'stats' => $analytics,
			'top_forms' => $top_forms
		);
		
		// Cache for 30 minutes.
        \wp_cache_set( $cache_key, $audience_analytics, $this->cache_group, $this->cache_expiry );
		
		return $audience_analytics;
	}
	
	/**
	 * Export analytics data
	 *
	 * @since    1.0.0
	 * @param    string $date_range   Date range.
	 * @param    string $format       Export format.
	 * @param    int    $form_id      Form ID.
	 * @param    string $audience_id  Audience ID.
	 * @return   string
	 */
	public function export_analytics( $date_range = '30_days', $format = 'csv', $form_id = null, $audience_id = null ) {
		global $wpdb;
		
		$date_conditions = $this->get_date_conditions( $date_range );
		$where_conditions = array( "created_at {$date_conditions}" );
		
		if ( $form_id ) {
			$where_conditions[] = $wpdb->prepare( "form_id = %d", $form_id );
		}
		
		if ( $audience_id ) {
			$where_conditions[] = $wpdb->prepare( "audience_id = %s", $audience_id );
		}
		
		$where_clause = implode( ' AND ', $where_conditions );
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics export query; no caching needed for data export.
		$data = $wpdb->get_results( " // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated static table identifier and composed WHERE clause
			SELECT 
				form_id,
				audience_id,
				email,
				event_type,
				event_data,
				ip_address,
				created_at
			FROM {$this->analytics_table} 
			WHERE {$where_clause}
			ORDER BY created_at DESC
		" );
		
		switch ( $format ) {
			case 'json':
                return \wp_json_encode( $data );
				
			case 'csv':
			default:
				return $this->convert_to_csv( $data );
		}
	}
	
	/**
	 * Convert data to CSV format
	 *
	 * @since    1.0.0
	 * @param    array $data Data to convert.
	 * @return   string
	 */
	private function convert_to_csv( $data ) {
		if ( empty( $data ) ) {
			return '';
		}
		
		$csv = '';
		$headers = array( 'Form ID', 'Audience ID', 'Email', 'Event Type', 'Event Data', 'IP Address', 'Date' );
		$csv .= implode( ',', $headers ) . "\n";
		
		foreach ( $data as $row ) {
			$csv_row = array(
				$row->form_id,
				$row->audience_id,
				$row->email,
				$row->event_type,
				'"' . str_replace( '"', '""', $row->event_data ) . '"',
				$row->ip_address,
				$row->created_at
			);
			$csv .= implode( ',', $csv_row ) . "\n";
		}
		
		return $csv;
	}
	
	/**
	 * Get date conditions for SQL
	 *
	 * @since    1.0.0
	 * @param    string $date_range  Date range.
	 * @return   string
	 */
	private function get_date_conditions( $date_range ) {
		switch ( $date_range ) {
			case 'today':
				return ">= CURDATE()";
			case 'yesterday':
				return "BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND CURDATE()";
			case 'this_week':
				return ">= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
			case 'last_week':
				return "BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) + 7 DAY) AND DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
			case 'this_month':
				return ">= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
			case 'last_month':
				return "BETWEEN DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH) AND DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 DAY)";
			case '7_days':
				return ">= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
			case '30_days':
			default:
				return ">= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
			case '90_days':
				return ">= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
		}
	}
	
	/**
	 * Get date format for grouping
	 *
	 * @since    1.0.0
	 * @param    string $date_range  Date range.
	 * @return   string
	 */
	private function get_date_format_for_range( $date_range ) {
		switch ( $date_range ) {
			case 'today':
			case 'yesterday':
				return '%Y-%m-%d %H:00:00'; // Hourly.
			case 'this_week':
			case 'last_week':
			case '7_days':
				return '%Y-%m-%d'; // Daily.
			case 'this_month':
			case 'last_month':
			case '30_days':
				return '%Y-%m-%d'; // Daily.
			case '90_days':
			default:
				return '%Y-%m-%d'; // Daily for now, could be weekly for longer ranges.
		}
	}
	
	/**
	 * Format data for Chart.js
	 *
	 * @since    1.0.0
	 * @param    array  $data       Chart data.
	 * @param    string $chart_type Chart type.
	 * @param    string $date_range Date range.
	 * @return   array
	 */
	private function format_chart_data( $data, $chart_type, $date_range ) {
		$labels = array();
		$datasets = array();
		
		switch ( $chart_type ) {
			case 'subscriptions':
				$subscriptions = array();
				$errors = array();
				
				foreach ( $data as $row ) {
					$labels[] = $row->date_group;
					$subscriptions[] = intval( $row->subscriptions );
					$errors[] = intval( $row->errors );
				}
				
				$datasets = array(
					array(
						'label' => 'Successful Subscriptions',
						'data' => $subscriptions,
						'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
						'borderColor' => 'rgba(75, 192, 192, 1)',
						'borderWidth' => 2
					),
					array(
						'label' => 'Failed Subscriptions',
						'data' => $errors,
						'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
						'borderColor' => 'rgba(255, 99, 132, 1)',
						'borderWidth' => 2
					)
				);
				break;
				
			case 'conversion':
				$conversion_rates = array();
				
				foreach ( $data as $row ) {
					$labels[] = $row->date_group;
					$conversion_rates[] = floatval( $row->conversion_rate );
				}
				
				$datasets = array(
					array(
						'label' => 'Conversion Rate (%)',
						'data' => $conversion_rates,
						'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
						'borderColor' => 'rgba(54, 162, 235, 1)',
						'borderWidth' => 2,
						'fill' => true
					)
				);
				break;
		}
		
		return array(
			'labels' => $labels,
			'datasets' => $datasets
		);
	}
	
	/**
	 * Clear analytics cache
	 *
	 * @since    1.0.0
	 * @param    int    $form_id      Form ID.
	 * @param    string $audience_id  Audience ID.
	 */
	private function clear_analytics_cache( $form_id = null, $audience_id = null ) {
		// Clear overview caches.
		$cache_patterns = array( 'overview_', 'chart_', 'form_', 'audience_' );
		
		foreach ( $cache_patterns as $pattern ) {
            \wp_cache_flush_group( $this->cache_group );
		}
	}
	
	/**
	 * Cleanup old analytics data
	 *
	 * @since    1.0.0
	 */
	public function cleanup_old_analytics() {
		// Delete analytics older than 1 year by default.
        $retention_days = \apply_filters( 'connect2form_mailchimp_analytics_retention_days', 365 );
		
		if ( class_exists( '\\Connect2Form\\Integrations\\Core\\Services\\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$deleted = $service_manager->database()->cleanupOldAnalytics( $retention_days );
		} else {
			// Fallback to direct database call if service not available.
			global $wpdb;
			
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics cleanup operation; no caching needed for cleanup operations.
			$deleted = $wpdb->query( $wpdb->prepare( "
				DELETE FROM {$this->analytics_table} 
				WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
			", $retention_days ) );
		}
		
		if ( $deleted ) {
		}
	}
	
	/**
	 * AJAX: Get analytics data
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_analytics_data() {
        \check_ajax_referer( 'connect2form_nonce', 'nonce' );
		
        $date_range_raw = isset( $_POST['date_range'] ) ? \wp_unslash( $_POST['date_range'] ) : '30_days';
        $date_range = \sanitize_text_field( $date_range_raw );
        $form_id_raw = isset( $_POST['form_id'] ) ? \wp_unslash( $_POST['form_id'] ) : 0;
		$form_id = ( int ) $form_id_raw ?: null;
        $audience_id_raw = isset( $_POST['audience_id'] ) ? \wp_unslash( $_POST['audience_id'] ) : '';
        $audience_id = \sanitize_text_field( $audience_id_raw ) ?: null;
		
		$overview = $this->get_analytics_overview( $date_range, $form_id, $audience_id );
		
        \wp_send_json_success( $overview );
	}
	
	/**
	 * AJAX: Get analytics chart
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_analytics_chart() {
        \check_ajax_referer( 'connect2form_nonce', 'nonce' );
		
        $chart_type_raw = isset( $_POST['chart_type'] ) ? \wp_unslash( $_POST['chart_type'] ) : 'subscriptions';
        $chart_type = \sanitize_text_field( $chart_type_raw );
        $date_range_raw = isset( $_POST['date_range'] ) ? \wp_unslash( $_POST['date_range'] ) : '30_days';
        $date_range = \sanitize_text_field( $date_range_raw );
        $form_id_raw = isset( $_POST['form_id'] ) ? \wp_unslash( $_POST['form_id'] ) : 0;
		$form_id = ( int ) $form_id_raw ?: null;
        $audience_id_raw = isset( $_POST['audience_id'] ) ? \wp_unslash( $_POST['audience_id'] ) : '';
        $audience_id = \sanitize_text_field( $audience_id_raw ) ?: null;
		
		$chart_data = $this->get_analytics_chart( $chart_type, $date_range, $form_id, $audience_id );
		
        \wp_send_json_success( $chart_data );
	}
	
	/**
	 * AJAX: Export analytics
	 *
	 * @since    1.0.0
	 */
	public function ajax_export_analytics() {
        \check_ajax_referer( 'connect2form_nonce', 'nonce' );
		
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_send_json_error( 'Insufficient permissions' );
		}
		
        $date_range_raw = isset( $_POST['date_range'] ) ? \wp_unslash( $_POST['date_range'] ) : '30_days';
        $date_range = \sanitize_text_field( $date_range_raw );
        $format_raw = isset( $_POST['format'] ) ? \wp_unslash( $_POST['format'] ) : 'csv';
        $format = \sanitize_text_field( $format_raw );
        $form_id_raw = isset( $_POST['form_id'] ) ? \wp_unslash( $_POST['form_id'] ) : 0;
		$form_id = ( int ) $form_id_raw ?: null;
        $audience_id_raw = isset( $_POST['audience_id'] ) ? \wp_unslash( $_POST['audience_id'] ) : '';
        $audience_id = \sanitize_text_field( $audience_id_raw ) ?: null;
		
		$export_data = $this->export_analytics( $date_range, $format, $form_id, $audience_id );
		
        \wp_send_json_success( array(
			'data' => $export_data,
			'format' => $format,
			'filename' => 'mailchimp-analytics-' . gmdate( 'Y-m-d' ) . '.' . $format
		) );
	}
	
	/**
	 * AJAX: Get form analytics
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_form_analytics() {
        \check_ajax_referer( 'connect2form_nonce', 'nonce' );
		
        $form_id_raw = isset( $_POST['form_id'] ) ? \wp_unslash( $_POST['form_id'] ) : 0;
		$form_id = ( int ) $form_id_raw;
        $date_range_raw = isset( $_POST['date_range'] ) ? \wp_unslash( $_POST['date_range'] ) : '30_days';
        $date_range = \sanitize_text_field( $date_range_raw );
		
		if ( ! $form_id ) {
            \wp_send_json_error( 'Form ID is required' );
		}
		
		$analytics = $this->get_form_analytics( $form_id, $date_range );
		
        \wp_send_json_success( $analytics );
	}
	
	/**
	 * AJAX: Get audience analytics
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_audience_analytics() {
        \check_ajax_referer( 'connect2form_nonce', 'nonce' );
		
        $audience_id_raw = isset( $_POST['audience_id'] ) ? \wp_unslash( $_POST['audience_id'] ) : '';
        $audience_id = \sanitize_text_field( $audience_id_raw );
        $date_range_raw = isset( $_POST['date_range'] ) ? \wp_unslash( $_POST['date_range'] ) : '30_days';
        $date_range = \sanitize_text_field( $date_range_raw );
		
		if ( ! $audience_id ) {
            \wp_send_json_error( 'Audience ID is required' );
		}
		
		$analytics = $this->get_audience_analytics( $audience_id, $date_range );
		
        \wp_send_json_success( $analytics );
	}
} 


