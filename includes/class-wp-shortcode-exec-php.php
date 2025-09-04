<?php
/**
 * Main plugin class for Shortcode Exec PHP.
 *
 * This is the main plugin class that coordinates all other components.
 * It uses modern PHP patterns, proper security measures, and follows
 * WordPress coding standards.
 *
 * @package WordPress
 * @subpackage Shortcode_Exec_PHP
 * @since 1.53
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 *
 * Coordinates all plugin functionality using modern singleton pattern
 * and component-based architecture.
 *
 * @since 1.53
 */
class WP_Shortcode_Exec_PHP {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '1.53';

	/**
	 * Plugin minimum WordPress version.
	 *
	 * @var string
	 */
	const MIN_WP_VERSION = '5.0';

	/**
	 * Plugin minimum PHP version.
	 *
	 * @var string
	 */
	const MIN_PHP_VERSION = '7.4';

	/**
	 * Plugin instance (Singleton).
	 *
	 * @var WP_Shortcode_Exec_PHP
	 */
	private static $instance = null;

	/**
	 * Main plugin file path.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	private $plugin_dir;

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Admin interface handler.
	 *
	 * @var WP_Shortcode_Exec_PHP_Admin
	 */
	private $admin;

	/**
	 * Shortcode handler.
	 *
	 * @var WP_Shortcode_Exec_PHP_Handler
	 */
	private $handler;

	/**
	 * Security utility.
	 *
	 * @var WP_Shortcode_Exec_PHP_Security
	 */
	private $security;

	/**
	 * Editor integration handler.
	 *
	 * @var WP_Shortcode_Exec_PHP_Editor_Integration
	 */
	private $editor_integration;

	/**
	 * Constructor - Private to enforce singleton pattern.
	 *
	 * @since 1.53
	 *
	 * @param string $plugin_file Main plugin file path.
	 */
	private function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;
		$this->plugin_dir  = plugin_dir_path( $plugin_file );
		$this->plugin_url  = plugin_dir_url( $plugin_file );

		$this->init();
	}

	/**
	 * Get plugin instance (Singleton pattern).
	 *
	 * @since 1.53
	 *
	 * @param string $plugin_file Main plugin file path.
	 * @return WP_Shortcode_Exec_PHP
	 */
	public static function get_instance( $plugin_file = '' ) {
		if ( null === self::$instance ) {
			if ( empty( $plugin_file ) ) {
				// Fallback for backward compatibility.
				$plugin_file = WP_PLUGIN_DIR . '/shortcode-exec-php/shortcode-exec-php.php';
			}
			self::$instance = new self( $plugin_file );
		}

		return self::$instance;
	}

	/**
	 * Initialize plugin.
	 *
	 * @since 1.53
	 */
	private function init() {
		// Check system requirements.
		if ( ! $this->check_requirements() ) {
			return;
		}

		// Load dependencies.
		$this->load_dependencies();

		// Initialize core functionality.
		add_action( 'init', array( $this, 'init_plugin' ), 0 );

		// Register activation/deactivation hooks.
		register_activation_hook( $this->plugin_file, array( $this, 'activate' ) );
		register_deactivation_hook( $this->plugin_file, array( $this, 'deactivate' ) );

		// Handle plugin updates.
		add_action( 'plugins_loaded', array( $this, 'handle_plugin_updates' ) );
	}

	/**
	 * Check system requirements.
	 *
	 * @since 1.53
	 *
	 * @return bool True if requirements are met, false otherwise.
	 */
	private function check_requirements() {
		global $wp_version;

		// Check WordPress version.
		if ( version_compare( $wp_version, self::MIN_WP_VERSION, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'display_wp_version_error' ) );
			return false;
		}

		// Check PHP version.
		if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'display_php_version_error' ) );
			return false;
		}

		return true;
	}

	/**
	 * Load plugin dependencies.
	 *
	 * @since 1.53
	 */
	private function load_dependencies() {
		// Load security class first.
		require_once $this->plugin_dir . 'includes/class-wp-shortcode-exec-php-security.php';

		// Load handler class.
		require_once $this->plugin_dir . 'includes/class-wp-shortcode-exec-php-handler.php';

		// Load editor integration class.
		require_once $this->plugin_dir . 'includes/class-wp-shortcode-exec-php-editor-integration.php';

		// Load admin class only in admin.
		if ( is_admin() ) {
			require_once $this->plugin_dir . 'includes/class-wp-shortcode-exec-php-admin.php';
		}
	}

	/**
	 * Initialize plugin functionality.
	 *
	 * @since 1.53
	 */
	public function init_plugin() {
		// Initialize security utility.
		$this->security = new WP_Shortcode_Exec_PHP_Security();

		// Initialize shortcode handler.
		$this->handler = new WP_Shortcode_Exec_PHP_Handler();

		// Initialize editor integration.
		$this->editor_integration = new WP_Shortcode_Exec_PHP_Editor_Integration();

		// Initialize admin interface.
		if ( is_admin() ) {
			$this->admin = new WP_Shortcode_Exec_PHP_Admin( $this->plugin_file, $this->plugin_url );
		}

		// Register shortcodes.
		$this->register_shortcodes();

		// Setup WordPress integration.
		$this->setup_wordpress_integration();

		// Configure PCRE limits.
		$this->configure_pcre_limits();

		// Load text domain.
		load_plugin_textdomain(
			'shortcode-exec-php',
			false,
			dirname( plugin_basename( $this->plugin_file ) ) . '/languages/'
		);
	}

	/**
	 * Register dynamic shortcodes.
	 *
	 * @since 1.53
	 */
	private function register_shortcodes() {
		$shortcode_names = get_option( 'scep_names', array() );

		if ( ! is_array( $shortcode_names ) ) {
			return;
		}

		foreach ( $shortcode_names as $shortcode_name ) {
			// Only register enabled shortcodes.
			if ( get_option( 'scep_enabled_' . $shortcode_name, false ) ) {
				add_shortcode( $shortcode_name, array( $this->handler, 'handle_shortcode' ) );
			}
		}
	}

	/**
	 * Setup WordPress integration (widgets, excerpts, comments, RSS).
	 *
	 * @since 1.53
	 */
	private function setup_wordpress_integration() {
		// Enable shortcodes in widgets.
		if ( get_option( 'scep_widget', false ) ) {
			add_filter( 'widget_text', 'do_shortcode' );
		}

		// Enable shortcodes in excerpts.
		if ( get_option( 'scep_excerpt', false ) ) {
			add_filter( 'the_excerpt', 'do_shortcode' );
			if ( get_option( 'scep_comment', false ) ) {
				add_filter( 'comment_excerpt', 'do_shortcode' );
			}
		}

		// Enable shortcodes in comments.
		if ( get_option( 'scep_comment', false ) ) {
			add_filter( 'comment_text', 'do_shortcode' );
		}

		// Enable shortcodes in RSS feeds.
		if ( get_option( 'scep_rss', false ) ) {
			add_filter( 'the_content_feed', 'do_shortcode' );
			if ( get_option( 'scep_excerpt', false ) ) {
				add_filter( 'the_excerpt_rss', 'do_shortcode' );
			}
			if ( get_option( 'scep_comment', false ) ) {
				add_filter( 'comment_text_rss', 'do_shortcode' );
			}
		}

		// Handle wpautop interaction.
		if ( get_option( 'scep_noautop', false ) ) {
			add_filter( 'the_content', array( $this, 'handle_noautop' ), 1 );
			add_filter( 'the_excerpt', array( $this, 'handle_noautop' ), 1 );
		}
	}

	/**
	 * Configure PCRE limits for shortcode processing.
	 *
	 * @since 1.53
	 */
	private function configure_pcre_limits() {
		$backtrack_limit = get_option( 'scep_backtrack_limit', 100000 );
		$recursion_limit = get_option( 'scep_recursion_limit', 100000 );

		// Get current limits.
		$current_backtrack = ini_get( 'pcre.backtrack_limit' );
		$current_recursion = ini_get( 'pcre.recursion_limit' );

		// Only increase limits, never decrease them.
		if ( $backtrack_limit > $current_backtrack ) {
			ini_set( 'pcre.backtrack_limit', $backtrack_limit );
		}

		if ( $recursion_limit > $current_recursion ) {
			ini_set( 'pcre.recursion_limit', $recursion_limit );
		}
	}

	/**
	 * Handle wpautop interaction with shortcodes.
	 *
	 * @since 1.53
	 *
	 * @param string $content The content to process.
	 * @return string Processed content.
	 */
	public function handle_noautop( $content ) {
		// Get all registered shortcodes for this plugin.
		$shortcode_names = get_option( 'scep_names', array() );

		if ( empty( $shortcode_names ) || ! is_array( $shortcode_names ) ) {
			return $content;
		}

		// Create regex pattern for all our shortcodes.
		$pattern = '(\[(?:' . implode( '|', array_map( 'preg_quote', $shortcode_names ) ) . ')(?:[^\]]*)\])';

		// Remove wpautop around our shortcodes.
		$content = preg_replace( '/(<p>)?' . $pattern . '(<\/p>)?/', '$2', $content );

		return $content;
	}

	/**
	 * Plugin activation handler.
	 *
	 * @since 1.53
	 */
	public function activate() {
		// Check requirements during activation.
		if ( ! $this->check_requirements() ) {
			// Requirements not met, deactivate and show error.
			deactivate_plugins( plugin_basename( $this->plugin_file ) );
			return;
		}

		// Initialize default options.
		$this->initialize_default_options();

		// Create necessary database entries.
		$this->setup_database();

		// Log activation.
		if ( class_exists( 'WP_Shortcode_Exec_PHP_Security' ) ) {
			WP_Shortcode_Exec_PHP_Security::log_code_execution(
				'plugin',
				'activated',
				sprintf( 'Plugin activated by user %d', get_current_user_id() )
			);
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation handler.
	 *
	 * @since 1.53
	 */
	public function deactivate() {
		// Log deactivation.
		if ( class_exists( 'WP_Shortcode_Exec_PHP_Security' ) ) {
			WP_Shortcode_Exec_PHP_Security::log_code_execution(
				'plugin',
				'deactivated',
				sprintf( 'Plugin deactivated by user %d', get_current_user_id() )
			);
		}

		// Clean up if cleanup is enabled.
		if ( get_option( 'scep_cleanup', false ) ) {
			$this->cleanup_plugin_data();
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Initialize default plugin options.
	 *
	 * @since 1.53
	 */
	private function initialize_default_options() {
		$default_options = array(
			'scep_widget'           => false,
			'scep_excerpt'          => false,
			'scep_comment'          => false,
			'scep_rss'              => false,
			'scep_noautop'          => false,
			'scep_cleanup'          => true,
			'scep_codewidth'        => 670,
			'scep_codeheight'       => 350,
			'scep_backtrack_limit'  => 100000,
			'scep_recursion_limit'  => 100000,
			'scep_tinymce'          => false,
			'scep_tinymce_cap'      => 'edit_posts',
			'scep_author_cap'       => 'edit_posts',
			'scep_names'            => array(),
			'scep_deleted'          => 0,
		);

		foreach ( $default_options as $option_name => $default_value ) {
			if ( false === get_option( $option_name, false ) ) {
				add_option( $option_name, $default_value, '', true );
			}
		}
	}

	/**
	 * Setup database entries.
	 *
	 * @since 1.53
	 */
	private function setup_database() {
		// Add plugin version for future updates.
		update_option( 'scep_version', self::VERSION, true );

		// Record installation time.
		if ( ! get_option( 'scep_installed_time' ) ) {
			add_option( 'scep_installed_time', time(), '', true );
		}
	}

	/**
	 * Handle plugin updates.
	 *
	 * @since 1.53
	 */
	public function handle_plugin_updates() {
		$installed_version = get_option( 'scep_version', '1.0' );

		if ( version_compare( $installed_version, self::VERSION, '<' ) ) {
			$this->run_plugin_update( $installed_version );
			update_option( 'scep_version', self::VERSION, true );
		}
	}

	/**
	 * Run plugin update procedures.
	 *
	 * @since 1.53
	 *
	 * @param string $from_version The version being updated from.
	 */
	private function run_plugin_update( $from_version ) {
		// Log update.
		if ( class_exists( 'WP_Shortcode_Exec_PHP_Security' ) ) {
			WP_Shortcode_Exec_PHP_Security::log_code_execution(
				'plugin',
				'updated',
				sprintf( 'Plugin updated from %s to %s', $from_version, self::VERSION )
			);
		}

		// Run version-specific updates.
		if ( version_compare( $from_version, '1.53', '<' ) ) {
			$this->update_to_153();
		}
	}

	/**
	 * Update to version 1.53.
	 *
	 * @since 1.53
	 */
	private function update_to_153() {
		// Initialize new default options introduced in 1.53.
		$new_options = array(
			'scep_cleanup' => true,
		);

		foreach ( $new_options as $option_name => $default_value ) {
			if ( false === get_option( $option_name, false ) ) {
				add_option( $option_name, $default_value, '', true );
			}
		}
	}

	/**
	 * Clean up plugin data on deactivation.
	 *
	 * @since 1.53
	 */
	private function cleanup_plugin_data() {
		// Get all shortcode names.
		$shortcode_names = get_option( 'scep_names', array() );

		// Delete shortcode-specific options.
		if ( is_array( $shortcode_names ) ) {
			foreach ( $shortcode_names as $name ) {
				delete_option( 'scep_enabled_' . $name );
				delete_option( 'scep_buffer_' . $name );
				delete_option( 'scep_description_' . $name );
				delete_option( 'scep_param_' . $name );
				delete_option( 'scep_phpcode_' . $name );
			}
		}

		// Delete global options.
		$global_options = array(
			'scep_global',
			'scep_widget',
			'scep_excerpt',
			'scep_comment',
			'scep_rss',
			'scep_noautop',
			'scep_cleanup',
			'scep_codewidth',
			'scep_codeheight',
			'scep_backtrack_limit',
			'scep_recursion_limit',
			'scep_editarea_later',
			'scep_tinymce',
			'scep_tinymce_cap',
			'scep_author_cap',
			'scep_names',
			'scep_deleted',
			'scep_version',
			'scep_installed_time',
		);

		foreach ( $global_options as $option_name ) {
			delete_option( $option_name );
		}
	}

	/**
	 * Display WordPress version error.
	 *
	 * @since 1.53
	 */
	public function display_wp_version_error() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Shortcode Exec PHP Error:', 'shortcode-exec-php' ); ?></strong>
				<?php
				printf(
					/* translators: %s: Required WordPress version */
					esc_html__( 'This plugin requires WordPress %s or later.', 'shortcode-exec-php' ),
					esc_html( self::MIN_WP_VERSION )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Display PHP version error.
	 *
	 * @since 1.53
	 */
	public function display_php_version_error() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Shortcode Exec PHP Error:', 'shortcode-exec-php' ); ?></strong>
				<?php
				printf(
					/* translators: %s: Required PHP version */
					esc_html__( 'This plugin requires PHP %s or later.', 'shortcode-exec-php' ),
					esc_html( self::MIN_PHP_VERSION )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Get plugin version.
	 *
	 * @since 1.53
	 *
	 * @return string Plugin version.
	 */
	public function get_version() {
		return self::VERSION;
	}

	/**
	 * Get plugin directory path.
	 *
	 * @since 1.53
	 *
	 * @return string Plugin directory path.
	 */
	public function get_plugin_dir() {
		return $this->plugin_dir;
	}

	/**
	 * Get plugin URL.
	 *
	 * @since 1.53
	 *
	 * @return string Plugin URL.
	 */
	public function get_plugin_url() {
		return $this->plugin_url;
	}

	/**
	 * Check if plugin is network activated in multisite.
	 *
	 * @since 1.53
	 *
	 * @return bool True if network activated, false otherwise.
	 */
	public function is_network_activated() {
		if ( ! is_multisite() ) {
			return false;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		return is_plugin_active_for_network( plugin_basename( $this->plugin_file ) );
	}

	/**
	 * Get option with network support.
	 *
	 * @since 1.53
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $default     Default value.
	 * @return mixed Option value.
	 */
	public function get_option( $option_name, $default = false ) {
		if ( $this->is_network_activated() && get_option( 'scep_global', false ) ) {
			return get_site_option( $option_name, $default );
		}

		return get_option( $option_name, $default );
	}

	/**
	 * Update option with network support.
	 *
	 * @since 1.53
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $value       Option value.
	 * @return bool True on success, false on failure.
	 */
	public function update_option( $option_name, $value ) {
		if ( $this->is_network_activated() && get_option( 'scep_global', false ) ) {
			return update_site_option( $option_name, $value );
		}

		return update_option( $option_name, $value );
	}

	/**
	 * Delete option with network support.
	 *
	 * @since 1.53
	 *
	 * @param string $option_name Option name.
	 * @return bool True on success, false on failure.
	 */
	public function delete_option( $option_name ) {
		if ( $this->is_network_activated() && get_option( 'scep_global', false ) ) {
			return delete_site_option( $option_name );
		}

		return delete_option( $option_name );
	}
}