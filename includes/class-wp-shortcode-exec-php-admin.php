<?php
/**
 * Admin interface for Shortcode Exec PHP plugin.
 *
 * Handles the WordPress admin interface for managing shortcodes, settings,
 * and plugin configuration. Implements proper security measures including
 * XSS prevention, CSRF protection, and input validation.
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
 * Admin interface class for Shortcode Exec PHP.
 *
 * Manages the WordPress admin interface with enhanced security.
 *
 * @since 1.53
 */
class WP_Shortcode_Exec_PHP_Admin {

	/**
	 * The security utility instance.
	 *
	 * @var WP_Shortcode_Exec_PHP_Security
	 */
	private $security;

	/**
	 * Plugin main file path.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Constructor.
	 *
	 * @since 1.53
	 *
	 * @param string $plugin_file Main plugin file path.
	 * @param string $plugin_url  Plugin URL.
	 */
	public function __construct( $plugin_file, $plugin_url ) {
		$this->plugin_file = $plugin_file;
		$this->plugin_url = $plugin_url;
		$this->security = new WP_Shortcode_Exec_PHP_Security();

		$this->init();
	}

	/**
	 * Initialize admin functionality.
	 *
	 * @since 1.53
	 */
	private function init() {
		// Add admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'network_admin_menu', array( $this, 'add_network_admin_menu' ) );

		// Enqueue admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Handle AJAX requests.
		add_action( 'wp_ajax_scep_ajax', array( $this, 'handle_ajax_request' ) );

		// Add TinyMCE integration.
		add_action( 'init', array( $this, 'init_tinymce_integration' ) );

		// Load text domain.
		add_action( 'plugins_loaded', array( $this, 'load_text_domain' ) );
	}

	/**
	 * Add admin menu items.
	 *
	 * @since 1.53
	 */
	public function add_admin_menu() {
		// Check if multisite and network activated.
		if ( is_multisite() && is_plugin_active_for_network( plugin_basename( $this->plugin_file ) ) ) {
			return; // Will be handled by network admin menu.
		}

		$page_title = __( 'Shortcode Exec PHP', 'shortcode-exec-php' );
		$menu_title = __( 'Shortcode Exec PHP', 'shortcode-exec-php' );
		$capability = 'manage_options';
		$menu_slug  = 'shortcode-exec-php';
		$callback   = array( $this, 'admin_page' );

		add_management_page(
			$page_title,
			$menu_title,
			$capability,
			$menu_slug,
			$callback
		);
	}

	/**
	 * Add network admin menu items.
	 *
	 * @since 1.53
	 */
	public function add_network_admin_menu() {
		if ( ! is_multisite() ) {
			return;
		}

		$page_title = __( 'Shortcode Exec PHP', 'shortcode-exec-php' );
		$menu_title = __( 'Shortcode Exec PHP', 'shortcode-exec-php' );
		$capability = 'manage_network';
		$menu_slug  = 'shortcode-exec-php';
		$callback   = array( $this, 'admin_page' );

		add_submenu_page(
			'settings.php',
			$page_title,
			$menu_title,
			$capability,
			$menu_slug,
			$callback
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since 1.53
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only enqueue on our admin page.
		if ( 'tools_page_shortcode-exec-php' !== $hook && 'settings_page_shortcode-exec-php' !== $hook ) {
			return;
		}

		// Enqueue WordPress core scripts.
		wp_enqueue_script( 'jquery' );

		// Enqueue CodeMirror for syntax highlighting.
		wp_enqueue_code_editor( array( 'type' => 'text/x-php' ) );

		// Enqueue our admin script.
		wp_enqueue_script(
			'scep-admin',
			$this->plugin_url . '/js/shortcode-exec-php-admin.js',
			array( 'jquery', 'wp-code-editor' ),
			'1.53',
			true
		);

		// Localize script with nonces and translations.
		wp_localize_script(
			'scep-admin',
			'scepAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => WP_Shortcode_Exec_PHP_Security::create_nonce( 'admin_action' ),
				'testNonce'   => WP_Shortcode_Exec_PHP_Security::create_nonce( 'test_shortcode' ),
				'deleteNonce' => WP_Shortcode_Exec_PHP_Security::create_nonce( 'delete_shortcode' ),
				'strings'     => array(
					'confirmDelete'   => __( 'Are you sure you want to delete this shortcode?', 'shortcode-exec-php' ),
					'testFailed'      => __( 'Test failed. Please try again.', 'shortcode-exec-php' ),
					'testResults'     => __( 'Test Results', 'shortcode-exec-php' ),
					'wysiwyg'         => __( 'WYSIWYG View', 'shortcode-exec-php' ),
					'html'            => __( 'HTML Source', 'shortcode-exec-php' ),
					'close'           => __( 'Close', 'shortcode-exec-php' ),
				),
			)
		);

		// Enqueue admin styles.
		wp_enqueue_style(
			'scep-admin',
			$this->plugin_url . '/shortcode-exec-php.css',
			array(),
			'1.53'
		);

		// Enqueue WordPress core modal styles.
		wp_enqueue_style( 'wp-jquery-ui-dialog' );
	}

	/**
	 * Render the admin page.
	 *
	 * @since 1.53
	 */
	public function admin_page() {
		// Security check.
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'shortcode-exec-php' ) );
		}

		// Handle form submissions.
		$message = '';
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$message = $this->handle_form_submission();
		}

		// Get current shortcodes.
		$shortcodes = $this->get_shortcodes();

		// Render the page.
		include __DIR__ . '/admin-template.php';
	}

	/**
	 * Handle form submission with proper security.
	 *
	 * @since 1.53
	 *
	 * @return string Success or error message.
	 */
	private function handle_form_submission() {
		// Verify nonce.
		$nonce = isset( $_POST['scep_admin_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['scep_admin_nonce'] ) ) : '';
		if ( ! WP_Shortcode_Exec_PHP_Security::verify_nonce( $nonce, 'admin_action' ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'Security check failed. Please try again.', 'shortcode-exec-php' ) . '</p></div>';
		}

		// Get and validate action.
		$action = isset( $_POST['scep_action'] ) ? sanitize_key( $_POST['scep_action'] ) : '';

		switch ( $action ) {
			case 'save_shortcode':
				return $this->handle_save_shortcode();

			case 'delete_shortcode':
				return $this->handle_delete_shortcode();

			case 'save_settings':
				return $this->handle_save_settings();

			case 'import_shortcodes':
				return $this->handle_import_shortcodes();

			case 'export_shortcodes':
				return $this->handle_export_shortcodes();

			default:
				return '<div class="notice notice-error"><p>' . esc_html__( 'Invalid action.', 'shortcode-exec-php' ) . '</p></div>';
		}
	}

	/**
	 * Handle shortcode save action.
	 *
	 * @since 1.53
	 *
	 * @return string Success or error message.
	 */
	private function handle_save_shortcode() {
		// Check capability.
		if ( ! WP_Shortcode_Exec_PHP_Security::current_user_can_execute( 'edit_shortcode' ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to edit shortcodes.', 'shortcode-exec-php' ) . '</p></div>';
		}

		// Get and validate shortcode name.
		$shortcode_name = isset( $_POST['scep_shortcode_name'] ) ? sanitize_text_field( wp_unslash( $_POST['scep_shortcode_name'] ) ) : '';
		if ( ! WP_Shortcode_Exec_PHP_Security::validate_shortcode_name( $shortcode_name ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'Invalid shortcode name. Please use only letters, numbers, underscores, and hyphens.', 'shortcode-exec-php' ) . '</p></div>';
		}

		// Get other form data.
		$enabled = isset( $_POST['scep_enabled'] ) && '1' === $_POST['scep_enabled'];
		$buffer = isset( $_POST['scep_buffer'] ) && '1' === $_POST['scep_buffer'];
		$description = isset( $_POST['scep_description'] ) ? WP_Shortcode_Exec_PHP_Security::sanitize_shortcode_description( wp_unslash( $_POST['scep_description'] ) ) : '';
		$php_code = isset( $_POST['scep_phpcode'] ) ? wp_unslash( $_POST['scep_phpcode'] ) : '';

		// Validate and sanitize PHP code.
		$sanitized_code = WP_Shortcode_Exec_PHP_Security::sanitize_php_code( $php_code );
		if ( is_wp_error( $sanitized_code ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'Code validation failed: ', 'shortcode-exec-php' ) . esc_html( $sanitized_code->get_error_message() ) . '</p></div>';
		}

		// Check if this is a new shortcode.
		$shortcode_names = get_option( 'scep_names', array() );
		$is_new = ! in_array( $shortcode_name, $shortcode_names, true );

		if ( $is_new ) {
			// Add to names list.
			$shortcode_names[] = $shortcode_name;
			update_option( 'scep_names', $shortcode_names, true );
		}

		// Save shortcode configuration.
		update_option( 'scep_enabled_' . $shortcode_name, $enabled, false );
		update_option( 'scep_buffer_' . $shortcode_name, $buffer, false );
		update_option( 'scep_description_' . $shortcode_name, $description, false );
		update_option( 'scep_phpcode_' . $shortcode_name, $sanitized_code, false );

		// Log the action.
		WP_Shortcode_Exec_PHP_Security::log_code_execution(
			$shortcode_name,
			$is_new ? 'created' : 'updated',
			sprintf( 'Shortcode %s by user %d', $is_new ? 'created' : 'updated', get_current_user_id() )
		);

		$action_text = $is_new ? __( 'created', 'shortcode-exec-php' ) : __( 'updated', 'shortcode-exec-php' );
		return '<div class="notice notice-success"><p>' . sprintf(
			/* translators: 1: Action (created/updated), 2: Shortcode name */
			esc_html__( 'Shortcode %1$s successfully: %2$s', 'shortcode-exec-php' ),
			esc_html( $action_text ),
			'<code>' . esc_html( $shortcode_name ) . '</code>'
		) . '</p></div>';
	}

	/**
	 * Handle shortcode delete action.
	 *
	 * @since 1.53
	 *
	 * @return string Success or error message.
	 */
	private function handle_delete_shortcode() {
		// Check capability.
		if ( ! WP_Shortcode_Exec_PHP_Security::current_user_can_execute( 'delete_shortcode' ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to delete shortcodes.', 'shortcode-exec-php' ) . '</p></div>';
		}

		// Get shortcode name.
		$shortcode_name = isset( $_POST['scep_shortcode_name'] ) ? sanitize_text_field( wp_unslash( $_POST['scep_shortcode_name'] ) ) : '';
		if ( empty( $shortcode_name ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'No shortcode specified for deletion.', 'shortcode-exec-php' ) . '</p></div>';
		}

		// Remove from names list.
		$shortcode_names = get_option( 'scep_names', array() );
		$key = array_search( $shortcode_name, $shortcode_names, true );
		if ( false !== $key ) {
			unset( $shortcode_names[ $key ] );
			update_option( 'scep_names', array_values( $shortcode_names ), true );
		}

		// Delete shortcode options.
		delete_option( 'scep_enabled_' . $shortcode_name );
		delete_option( 'scep_buffer_' . $shortcode_name );
		delete_option( 'scep_description_' . $shortcode_name );
		delete_option( 'scep_phpcode_' . $shortcode_name );
		delete_option( 'scep_param_' . $shortcode_name );

		// Log the action.
		WP_Shortcode_Exec_PHP_Security::log_code_execution(
			$shortcode_name,
			'deleted',
			sprintf( 'Shortcode deleted by user %d', get_current_user_id() )
		);

		return '<div class="notice notice-success"><p>' . sprintf(
			/* translators: %s: Shortcode name */
			esc_html__( 'Shortcode deleted successfully: %s', 'shortcode-exec-php' ),
			'<code>' . esc_html( $shortcode_name ) . '</code>'
		) . '</p></div>';
	}

	/**
	 * Handle settings save action.
	 *
	 * @since 1.53
	 *
	 * @return string Success or error message.
	 */
	private function handle_save_settings() {
		// Check capability.
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network' ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to save settings.', 'shortcode-exec-php' ) . '</p></div>';
		}

		// Get settings values with proper sanitization.
		$settings = array(
			'scep_widget'           => isset( $_POST['scep_widget'] ),
			'scep_excerpt'          => isset( $_POST['scep_excerpt'] ),
			'scep_comment'          => isset( $_POST['scep_comment'] ),
			'scep_rss'              => isset( $_POST['scep_rss'] ),
			'scep_noautop'          => isset( $_POST['scep_noautop'] ),
			'scep_cleanup'          => isset( $_POST['scep_cleanup'] ),
			'scep_codewidth'        => isset( $_POST['scep_codewidth'] ) ? absint( $_POST['scep_codewidth'] ) : 670,
			'scep_codeheight'       => isset( $_POST['scep_codeheight'] ) ? absint( $_POST['scep_codeheight'] ) : 350,
			'scep_backtrack_limit'  => isset( $_POST['scep_backtrack_limit'] ) ? absint( $_POST['scep_backtrack_limit'] ) : 100000,
			'scep_recursion_limit'  => isset( $_POST['scep_recursion_limit'] ) ? absint( $_POST['scep_recursion_limit'] ) : 100000,
			'scep_tinymce'          => isset( $_POST['scep_tinymce'] ),
			'scep_tinymce_cap'      => isset( $_POST['scep_tinymce_cap'] ) ? sanitize_key( $_POST['scep_tinymce_cap'] ) : 'edit_posts',
			'scep_author_cap'       => isset( $_POST['scep_author_cap'] ) ? sanitize_key( $_POST['scep_author_cap'] ) : 'edit_posts',
		);

		// Validate numeric settings.
		$settings['scep_codewidth'] = max( 200, min( 2000, $settings['scep_codewidth'] ) );
		$settings['scep_codeheight'] = max( 100, min( 1000, $settings['scep_codeheight'] ) );
		$settings['scep_backtrack_limit'] = max( 1000, $settings['scep_backtrack_limit'] );
		$settings['scep_recursion_limit'] = max( 100, $settings['scep_recursion_limit'] );

		// Save settings.
		foreach ( $settings as $option_name => $value ) {
			update_option( $option_name, $value, true );
		}

		return '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved successfully.', 'shortcode-exec-php' ) . '</p></div>';
	}

	/**
	 * Handle AJAX requests.
	 *
	 * @since 1.53
	 */
	public function handle_ajax_request() {
		// Get action.
		$scep_action = isset( $_REQUEST['scep_action'] ) ? sanitize_key( $_REQUEST['scep_action'] ) : '';

		switch ( $scep_action ) {
			case 'test_shortcode':
				$this->handle_ajax_test_shortcode();
				break;

			case 'tinymce':
				$this->handle_ajax_tinymce();
				break;

			default:
				wp_die( esc_html__( 'Invalid AJAX action.', 'shortcode-exec-php' ), 400 );
		}
	}

	/**
	 * Handle AJAX shortcode testing.
	 *
	 * @since 1.53
	 */
	private function handle_ajax_test_shortcode() {
		// Verify nonce.
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! WP_Shortcode_Exec_PHP_Security::verify_nonce( $nonce, 'test_shortcode' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'shortcode-exec-php' ), 403 );
		}

		// Check capability.
		if ( ! WP_Shortcode_Exec_PHP_Security::current_user_can_execute( 'execute_shortcode' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'shortcode-exec-php' ), 403 );
		}

		// Get shortcode name.
		$shortcode_name = isset( $_REQUEST['shortcode'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['shortcode'] ) ) : '';
		if ( empty( $shortcode_name ) ) {
			wp_die( esc_html__( 'No shortcode specified.', 'shortcode-exec-php' ), 400 );
		}

		// Execute shortcode.
		$handler = new WP_Shortcode_Exec_PHP_Handler();
		$result = $handler->handle_shortcode( array(), '', $shortcode_name );

		// Return result.
		echo 'OK=' . wp_kses_post( $result );
		wp_die();
	}

	/**
	 * Handle AJAX TinyMCE integration.
	 *
	 * @since 1.53
	 */
	private function handle_ajax_tinymce() {
		// Check capability.
		$tinymce_cap = get_option( 'scep_tinymce_cap', 'edit_posts' );
		if ( ! current_user_can( $tinymce_cap ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'shortcode-exec-php' ), 403 );
		}

		// Get shortcodes for TinyMCE dialog.
		$shortcodes = $this->get_shortcodes();

		// Output TinyMCE dialog HTML.
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
			<title><?php esc_html_e( 'Insert Shortcode', 'shortcode-exec-php' ); ?></title>
			<?php wp_print_styles( 'buttons' ); ?>
		</head>
		<body>
			<div style="padding: 10px;">
				<h3><?php esc_html_e( 'Select Shortcode to Insert', 'shortcode-exec-php' ); ?></h3>
				<select id="shortcode-select" style="width: 100%; margin-bottom: 10px;">
					<option value=""><?php esc_html_e( '-- Select Shortcode --', 'shortcode-exec-php' ); ?></option>
					<?php foreach ( $shortcodes as $shortcode ) : ?>
						<?php if ( $shortcode['enabled'] ) : ?>
							<option value="<?php echo esc_attr( $shortcode['name'] ); ?>">
								<?php echo esc_html( $shortcode['name'] ); ?>
								<?php if ( ! empty( $shortcode['description'] ) ) : ?>
									- <?php echo esc_html( wp_trim_words( $shortcode['description'], 8 ) ); ?>
								<?php endif; ?>
							</option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
				<p>
					<button type="button" id="insert-shortcode" class="button button-primary"><?php esc_html_e( 'Insert', 'shortcode-exec-php' ); ?></button>
					<button type="button" id="cancel" class="button" onclick="parent.tb_remove();"><?php esc_html_e( 'Cancel', 'shortcode-exec-php' ); ?></button>
				</p>
			</div>
			<script>
			document.getElementById('insert-shortcode').addEventListener('click', function() {
				var select = document.getElementById('shortcode-select');
				var shortcode = select.value;
				if (shortcode) {
					if (parent.tinymce) {
						parent.tinymce.activeEditor.insertContent('[' + shortcode + ']');
					}
					parent.tb_remove();
				} else {
					alert('<?php esc_html_e( 'Please select a shortcode.', 'shortcode-exec-php' ); ?>');
				}
			});
			</script>
		</body>
		</html>
		<?php
		wp_die();
	}

	/**
	 * Initialize TinyMCE integration.
	 *
	 * @since 1.53
	 */
	public function init_tinymce_integration() {
		// Check if TinyMCE integration is enabled.
		if ( ! get_option( 'scep_tinymce', false ) ) {
			return;
		}

		// Check user capability.
		$tinymce_cap = get_option( 'scep_tinymce_cap', 'edit_posts' );
		if ( ! current_user_can( $tinymce_cap ) ) {
			return;
		}

		// Add TinyMCE plugin.
		add_filter( 'mce_external_plugins', array( $this, 'add_tinymce_plugin' ) );
		add_filter( 'mce_buttons', array( $this, 'add_tinymce_button' ) );
	}

	/**
	 * Add TinyMCE plugin.
	 *
	 * @since 1.53
	 *
	 * @param array $plugin_array Array of TinyMCE plugins.
	 * @return array Modified plugin array.
	 */
	public function add_tinymce_plugin( $plugin_array ) {
		$plugin_array['ShortcodeExecPHP'] = $this->plugin_url . '/js/tinymce-plugin.js';
		return $plugin_array;
	}

	/**
	 * Add TinyMCE button.
	 *
	 * @since 1.53
	 *
	 * @param array $buttons Array of TinyMCE buttons.
	 * @return array Modified button array.
	 */
	public function add_tinymce_button( $buttons ) {
		array_push( $buttons, 'ShortcodeExecPHP' );
		return $buttons;
	}

	/**
	 * Load plugin text domain.
	 *
	 * @since 1.53
	 */
	public function load_text_domain() {
		load_plugin_textdomain(
			'shortcode-exec-php',
			false,
			dirname( plugin_basename( $this->plugin_file ) ) . '/languages/'
		);
	}

	/**
	 * Get all shortcodes with their configurations.
	 *
	 * @since 1.53
	 *
	 * @return array Array of shortcode configurations.
	 */
	private function get_shortcodes() {
		$shortcode_names = get_option( 'scep_names', array() );
		$shortcodes = array();

		foreach ( $shortcode_names as $name ) {
			$shortcodes[] = array(
				'name'        => $name,
				'enabled'     => (bool) get_option( 'scep_enabled_' . $name, false ),
				'buffer'      => (bool) get_option( 'scep_buffer_' . $name, false ),
				'description' => get_option( 'scep_description_' . $name, '' ),
				'code'        => get_option( 'scep_phpcode_' . $name, '' ),
				'params'      => get_option( 'scep_param_' . $name, array() ),
			);
		}

		return $shortcodes;
	}

	/**
	 * Handle import shortcodes.
	 *
	 * @since 1.53
	 *
	 * @return string Success or error message.
	 */
	private function handle_import_shortcodes() {
		// Check capability.
		if ( ! WP_Shortcode_Exec_PHP_Security::current_user_can_execute( 'import_shortcodes' ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to import shortcodes.', 'shortcode-exec-php' ) . '</p></div>';
		}

		// Implementation for import functionality would go here.
		return '<div class="notice notice-info"><p>' . esc_html__( 'Import functionality is not yet implemented in this version.', 'shortcode-exec-php' ) . '</p></div>';
	}

	/**
	 * Handle export shortcodes.
	 *
	 * @since 1.53
	 *
	 * @return string Success or error message.
	 */
	private function handle_export_shortcodes() {
		// Check capability.
		if ( ! WP_Shortcode_Exec_PHP_Security::current_user_can_execute( 'export_shortcodes' ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to export shortcodes.', 'shortcode-exec-php' ) . '</p></div>';
		}

		// Implementation for export functionality would go here.
		return '<div class="notice notice-info"><p>' . esc_html__( 'Export functionality is not yet implemented in this version.', 'shortcode-exec-php' ) . '</p></div>';
	}
}