<?php
/**
 * Connect2Form Performance Optimizer
 *
 * Handles performance optimization for WordPress standards compliance
 *
 * @package Connect2Form
 * @since    2.0.0
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Connect2Form Performance Class
 *
 * Handles performance optimization for WordPress standards compliance
 *
 * @since    2.0.0
 */
class Connect2Form_Performance {

	/**
	 * Cache prefix for plugin-specific caching.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $cache_prefix    Cache prefix for plugin-specific caching.
	 */
	private $cache_prefix = 'connect2form_';

	/**
	 * Cache group for plugin-specific caching.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $cache_group    Cache group for plugin-specific caching.
	 */
	private $cache_group = 'connect2form';

	/**
	 * Initialize performance optimizations
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		// Database query optimization.
		add_filter( 'connect2form_query_cache', array( $this, 'cache_database_queries' ), 10, 3 );

		// Asset optimization.
		add_action( 'wp_enqueue_scripts', array( $this, 'optimize_frontend_assets' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'optimize_admin_assets' ), 5 );

		// Lazy loading for heavy operations.
		add_filter( 'connect2form_lazy_load', array( $this, 'implement_lazy_loading' ), 10, 2 );

		// Memory optimization.
		add_action( 'connect2form_cleanup_memory', array( $this, 'cleanup_memory' ) );

		// Database optimization.
		add_action( 'connect2form_optimize_database', array( $this, 'optimize_database_tables' ) );

		// Cron job for cleanup.
		if ( ! wp_next_scheduled( 'connect2form_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'connect2form_daily_cleanup' );
		}
		add_action( 'connect2form_daily_cleanup', array( $this, 'daily_cleanup' ) );
	}

	/**
	 * Cache database queries
	 *
	 * @since    2.0.0
	 * @param    mixed  $result     Query result.
	 * @param    string $query      Database query.
	 * @param    int    $cache_time Cache time in seconds.
	 * @return   mixed
	 */
	public function cache_database_queries( $result, $query, $cache_time = 300 ) {
		$cache_key = $this->cache_prefix . md5( $query );

		// Try to get from cache first.
		$cached = wp_cache_get( $cache_key, $this->cache_group );
		if ( $cached !== false ) {
			return $cached;
		}

		// Store in cache.
		wp_cache_set( $cache_key, $result, $this->cache_group, $cache_time );

		return $result;
	}

	/**
	 * Optimize frontend assets
	 *
	 * @since    2.0.0
	 */
	public function optimize_frontend_assets() {
		// Only load assets where needed.
		if ( ! $this->is_connect2form_page() && ! $this->has_connect2form_shortcode() ) {
			return;
		}

		// Minify and combine CSS/JS in production.
		if ( ! WP_DEBUG ) {
			add_filter( 'connect2form_minify_assets', '__return_true' );
		}

		// Defer non-critical JavaScript.
		add_filter( 'script_loader_tag', array( $this, 'defer_non_critical_scripts' ), 10, 3 );
	}

	/**
	 * Optimize admin assets
	 *
	 * @since    2.0.0
	 * @param    string $hook Current admin page hook.
	 */
	public function optimize_admin_assets( $hook ) {
		// Only load admin assets on plugin pages.
		if ( strpos( $hook, 'connect2form' ) === false ) {
			return;
		}

		// Combine admin CSS/JS.
		add_filter( 'connect2form_combine_admin_assets', '__return_true' );
	}

	/**
	 * Check if current page needs Connect2Form assets
	 *
	 * @since    2.0.0
	 * @access   private
	 * @return   bool
	 */
	private function is_connect2form_page() {
		global $post;

		if ( ! $post ) {
			return false;
		}

		// Check if post contains shortcode.
		return has_shortcode( $post->post_content, 'connect2form' ) ||
			   has_shortcode( $post->post_content, 'connect2form' );
	}

	/**
	 * Check if page has Connect2Form shortcode
	 *
	 * @since    2.0.0
	 * @access   private
	 * @return   bool
	 */
	private function has_connect2form_shortcode() {
		global $post;

		if ( ! $post ) {
			return false;
		}

		return strpos( $post->post_content, '[connect2form' ) !== false ||
			   strpos( $post->post_content, '[Connect2Form' ) !== false;
	}

	/**
	 * Defer non-critical scripts
	 *
	 * @since    2.0.0
	 * @param    string $tag    Script tag.
	 * @param    string $handle Script handle.
	 * @param    string $src    Script source.
	 * @return   string
	 */
	public function defer_non_critical_scripts( $tag, $handle, $src ) {
		// List of non-critical scripts.
		$defer_scripts = array(
			'connect2form-analytics',
			'connect2form-tracking',
		);

		if ( in_array( $handle, $defer_scripts, true ) ) {
			return str_replace( '<script ', '<script defer ', $tag );
		}

		return $tag;
	}

	/**
	 * Implement lazy loading
	 *
	 * @since    2.0.0
	 * @param    mixed  $content Content to lazy load.
	 * @param    string $type    Type of content.
	 * @return   mixed
	 */
	public function implement_lazy_loading( $content, $type ) {
		switch ( $type ) {
			case 'integrations':
				// Load integrations only when needed.
				return $this->lazy_load_integrations( $content );
			case 'forms':
				// Load form data only when requested.
				return $this->lazy_load_forms( $content );
			default:
				return $content;
		}
	}

	/**
	 * Lazy load integrations
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    mixed $content Content to lazy load.
	 * @return   mixed
	 */
	private function lazy_load_integrations( $content ) {
		// Only load integration classes when actually needed.
		static $loaded_integrations = array();

		if ( ! isset( $loaded_integrations[ $content ] ) ) {
			$loaded_integrations[ $content ] = true;
			// Load integration-specific code.
		}

		return $content;
	}

	/**
	 * Lazy load forms
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    mixed $content Content to lazy load.
	 * @return   mixed
	 */
	private function lazy_load_forms( $content ) {
		// Cache form data to avoid repeated queries.
		$cache_key = $this->cache_prefix . 'forms_' . md5( $content );
		$cached    = wp_cache_get( $cache_key, $this->cache_group );

		if ( $cached !== false ) {
			return $cached;
		}

		// Process and cache.
		wp_cache_set( $cache_key, $content, $this->cache_group, 900 ); // 15 minutes.

		return $content;
	}

	/**
	 * Cleanup memory usage
	 *
	 * @since    2.0.0
	 */
	public function cleanup_memory() {
		// Clear object cache.
		wp_cache_flush_group( $this->cache_group );

		// Force garbage collection.
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}
	}

	/**
	 * Optimize database tables
	 *
	 * @since    2.0.0
	 */
	public function optimize_database_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'connect2form_forms',
			$wpdb->prefix . 'connect2form_submissions',
		);

		foreach ( $tables as $table ) {
			// Validate table identifier before concatenation.
			if ( function_exists( 'c2f_is_valid_prefixed_table' ) && ! c2f_is_valid_prefixed_table( $table ) ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table optimization maintenance query with validated table identifier; no caching needed for OPTIMIZE TABLE operations
			$wpdb->query( "OPTIMIZE TABLE `{$table}`" );
		}
	}

	/**
	 * Daily cleanup routine
	 *
	 * @since    2.0.0
	 */
	public function daily_cleanup() {
		// Clean up old submissions (if enabled).
		$this->cleanup_old_submissions();

		// Clean up temporary files.
		$this->cleanup_temp_files();

		// Optimize database.
		$this->optimize_database_tables();

		// Clear expired caches.
		$this->cleanup_expired_caches();
	}

	/**
	 * Cleanup old submissions
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function cleanup_old_submissions() {
		$retention_days = apply_filters( 'connect2form_submission_retention_days', 365 );

		if ( $retention_days <= 0 ) {
			return; // Keep forever.
		}

		if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$service_manager->submissions()->cleanup_old_submissions( $retention_days );
		} else {
			// Fallback to direct database call if service not available.
			global $wpdb;
			$table = $wpdb->prefix . 'connect2form_submissions';

			// Validate table identifier before concatenation.
			if ( function_exists( 'c2f_is_valid_prefixed_table' ) && ! c2f_is_valid_prefixed_table( $table ) ) {
				return;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- cleanup operation for old submissions with validated table identifier; no caching needed for cleanup operations
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM `{$table}` WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$retention_days
			) );
		}
	}

	/**
	 * Cleanup temporary files
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function cleanup_temp_files() {
		$upload_dir = wp_upload_dir();
		$temp_dir   = $upload_dir['basedir'] . '/connect2form/temp/';

		if ( ! is_dir( $temp_dir ) ) {
			return;
		}

		$files   = glob( $temp_dir . '*' );
		$max_age = 24 * 60 * 60; // 24 hours.

		foreach ( $files as $file ) {
			if ( is_file( $file ) && ( time() - filemtime( $file ) ) > $max_age ) {
				// Use WordPress wrapper instead of unlink().
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Cleanup expired caches
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function cleanup_expired_caches() {
		// This would be implemented based on cache backend.
		do_action( 'connect2form_cleanup_expired_caches' );
	}

	/**
	 * Get performance metrics
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function get_performance_metrics() {
		return array(
			'memory_usage'   => memory_get_usage( true ),
			'memory_peak'    => memory_get_peak_usage( true ),
			'queries_count'  => get_num_queries(),
			'cache_hits'     => $this->get_cache_hits(),
			'page_load_time' => $this->get_page_load_time(),
		);
	}

	/**
	 * Get cache hit ratio
	 *
	 * @since    2.0.0
	 * @access   private
	 * @return   int
	 */
	private function get_cache_hits() {
		// Implementation depends on caching backend.
		return 0;
	}

	/**
	 * Get page load time
	 *
	 * @since    2.0.0
	 * @access   private
	 * @return   float
	 */
	private function get_page_load_time() {
		if ( defined( 'CONNECT2FORM_START_TIME' ) ) {
			return microtime( true ) - CONNECT2FORM_START_TIME;
		}
		return 0;
	}
}

// Initialize performance optimizer.
new Connect2Form_Performance();

