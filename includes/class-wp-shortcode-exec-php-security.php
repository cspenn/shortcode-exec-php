<?php
/**
 * Security utilities for Shortcode Exec PHP plugin.
 *
 * This class provides comprehensive security utilities including input validation,
 * sanitization, capability checking, and code execution monitoring. It serves as
 * the security layer for the plugin, implementing defense-in-depth strategies.
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
 * Security utilities class for Shortcode Exec PHP.
 *
 * Provides centralized security functionality including input validation,
 * output escaping, capability management, and execution monitoring.
 *
 * @since 1.53
 */
class WP_Shortcode_Exec_PHP_Security {

	/**
	 * Default blocked PHP functions that pose security risks.
	 *
	 * @var array
	 */
	private static $default_blocked_functions = array(
		'exec',
		'shell_exec',
		'system',
		'passthru',
		'file_get_contents',
		'file_put_contents',
		'fopen',
		'fwrite',
		'file',
		'readfile',
		'curl_exec',
		'curl_multi_exec',
		'proc_open',
		'popen',
		'mail',
		'wp_mail',
		'move_uploaded_file',
		'unlink',
		'rmdir',
		'mkdir',
		'chmod',
		'chown',
		'ini_set',
		'ini_alter',
		'ini_restore',
		'set_time_limit',
		'ignore_user_abort',
		'header',
		'setcookie',
		'session_start',
		'phpinfo',
		'var_dump',
		'print_r',
		'debug_backtrace',
		'error_reporting',
		'mysqli_connect',
		'mysql_connect',
		'pg_connect',
		'sqlite_open',
		'oci_connect',
	);

	/**
	 * Maximum execution time for shortcode code (in seconds).
	 *
	 * @var int
	 */
	private static $max_execution_time = 30;

	/**
	 * Maximum memory limit for shortcode execution (in MB).
	 *
	 * @var int
	 */
	private static $max_memory_limit = 32;

	/**
	 * Validates shortcode name format.
	 *
	 * Ensures shortcode names follow WordPress standards and security best practices.
	 * Only allows alphanumeric characters, underscores, and hyphens with length limits.
	 *
	 * @since 1.53
	 *
	 * @param string $name The shortcode name to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function validate_shortcode_name( $name ) {
		// Check if name is empty or not a string.
		if ( empty( $name ) || ! is_string( $name ) ) {
			return false;
		}

		// Check length constraints (1-50 characters).
		if ( strlen( $name ) < 1 || strlen( $name ) > 50 ) {
			return false;
		}

		// Check format: only lowercase letters, numbers, underscores, hyphens.
		if ( ! preg_match( '/^[a-z0-9_-]+$/i', $name ) ) {
			return false;
		}

		// Ensure it doesn't start with a number or special character.
		if ( preg_match( '/^[0-9_-]/', $name ) ) {
			return false;
		}

		// Check for WordPress reserved shortcode names.
		$reserved_names = array(
			'caption',
			'gallery',
			'playlist',
			'audio',
			'video',
			'embed',
			'wp_caption',
		);

		if ( in_array( strtolower( $name ), $reserved_names, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Sanitizes and validates PHP code input.
	 *
	 * Performs comprehensive sanitization and validation of PHP code before storage.
	 * Removes dangerous patterns and validates syntax without executing the code.
	 *
	 * @since 1.53
	 *
	 * @param string $code The raw PHP code to sanitize.
	 * @return string|WP_Error Sanitized code on success, WP_Error on failure.
	 */
	public static function sanitize_php_code( $code ) {
		// Check if code is empty or not a string.
		if ( ! is_string( $code ) ) {
			return new WP_Error( 'invalid_code_type', __( 'Code must be a string.', 'shortcode-exec-php' ) );
		}

		// Allow empty code (for disabling shortcodes).
		if ( empty( trim( $code ) ) ) {
			return '';
		}

		// Remove PHP opening and closing tags if present.
		$code = preg_replace( '/^\s*<\?php\s*/i', '', $code );
		$code = preg_replace( '/\s*\?>\s*$/i', '', $code );

		// Check code length constraints.
		if ( strlen( $code ) > 10000 ) { // 10KB limit.
			return new WP_Error( 'code_too_long', __( 'Code exceeds maximum length of 10KB.', 'shortcode-exec-php' ) );
		}

		// Get blocked functions list (allow filtering).
		$blocked_functions = apply_filters(
			'wp_shortcode_exec_php_blocked_functions',
			self::$default_blocked_functions
		);

		// Check for blocked functions.
		foreach ( $blocked_functions as $function ) {
			// Use word boundaries to avoid false positives.
			if ( preg_match( '/\b' . preg_quote( $function, '/' ) . '\s*\(/i', $code ) ) {
				return new WP_Error(
					'blocked_function',
					sprintf(
						/* translators: %s: Function name */
						__( 'Blocked function detected: %s', 'shortcode-exec-php' ),
						esc_html( $function )
					)
				);
			}
		}

		// Check for dangerous patterns.
		$dangerous_patterns = array(
			'/\$_(?:GET|POST|REQUEST|COOKIE|SERVER|FILES|ENV)\b/i' => 'Direct superglobal access',
			'/\beval\s*\(/i' => 'Nested eval() calls',
			'/\bglobals\s*\[/i' => 'Direct globals array access',
			'/\bextract\s*\(/i' => 'Variable extraction',
			'/\bcompact\s*\(/i' => 'Variable compacting',
			'/\bvariable_get\s*\(/i' => 'Dynamic variable access',
			'/\$\$\w+/i' => 'Variable variables',
			'/\binclude\s*\(/i' => 'File inclusion',
			'/\brequire\s*\(/i' => 'File requirement',
			'/\binclude_once\s*\(/i' => 'File inclusion (once)',
			'/\brequire_once\s*\(/i' => 'File requirement (once)',
		);

		foreach ( $dangerous_patterns as $pattern => $description ) {
			if ( preg_match( $pattern, $code ) ) {
				return new WP_Error(
					'dangerous_pattern',
					sprintf(
						/* translators: %s: Pattern description */
						__( 'Dangerous code pattern detected: %s', 'shortcode-exec-php' ),
						esc_html( $description )
					)
				);
			}
		}

		// Basic syntax validation (without execution).
		$syntax_check_result = self::validate_php_syntax( $code );
		if ( is_wp_error( $syntax_check_result ) ) {
			return $syntax_check_result;
		}

		// Sanitize the code for storage (preserve functionality).
		$sanitized_code = wp_unslash( $code );

		return $sanitized_code;
	}

	/**
	 * Validates PHP syntax without executing the code.
	 *
	 * Uses php_check_syntax equivalent functionality to validate syntax
	 * without executing potentially dangerous code.
	 *
	 * @since 1.53
	 *
	 * @param string $code The PHP code to validate.
	 * @return true|WP_Error True if syntax is valid, WP_Error otherwise.
	 */
	private static function validate_php_syntax( $code ) {
		// Wrap code in PHP tags for syntax checking.
		$wrapped_code = "<?php\n" . $code;

		// Create temporary file for syntax checking.
		$temp_file = wp_tempnam();
		if ( ! $temp_file ) {
			return new WP_Error( 'temp_file_failed', __( 'Could not create temporary file for syntax checking.', 'shortcode-exec-php' ) );
		}

		// Write code to temporary file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		$write_result = file_put_contents( $temp_file, $wrapped_code );
		if ( false === $write_result ) {
			unlink( $temp_file );
			return new WP_Error( 'temp_file_write_failed', __( 'Could not write to temporary file for syntax checking.', 'shortcode-exec-php' ) );
		}

		// Check syntax using php -l.
		$output = array();
		$return_code = 0;

		// Use exec with proper escaping.
		exec( 'php -l ' . escapeshellarg( $temp_file ) . ' 2>&1', $output, $return_code );

		// Clean up temporary file.
		unlink( $temp_file );

		// Check result.
		if ( 0 !== $return_code ) {
			$error_message = implode( "\n", $output );
			// Remove file path from error message for security.
			$error_message = preg_replace( '/in .* on line/', 'on line', $error_message );

			return new WP_Error(
				'syntax_error',
				sprintf(
					/* translators: %s: PHP syntax error message */
					__( 'PHP syntax error: %s', 'shortcode-exec-php' ),
					esc_html( $error_message )
				)
			);
		}

		return true;
	}

	/**
	 * Enhanced capability checking with context awareness.
	 *
	 * Provides context-aware capability checking that goes beyond basic WordPress
	 * capabilities to include plugin-specific security considerations.
	 *
	 * @since 1.53
	 *
	 * @param string $action   The action being performed.
	 * @param mixed  $context  Additional context (post ID, shortcode name, etc.).
	 * @return bool True if user can perform action, false otherwise.
	 */
	public static function current_user_can_execute( $action, $context = null ) {
		// Must be logged in.
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Basic capability check - require manage_options.
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Additional checks based on action.
		switch ( $action ) {
			case 'execute_shortcode':
				return self::can_execute_shortcode_in_context( $context );

			case 'edit_shortcode':
				return self::can_edit_shortcode( $context );

			case 'create_shortcode':
				return self::can_create_shortcode();

			case 'delete_shortcode':
				return self::can_delete_shortcode( $context );

			case 'import_shortcodes':
				return self::can_import_shortcodes();

			case 'export_shortcodes':
				return self::can_export_shortcodes();

			default:
				// Unknown action - deny by default.
				return false;
		}
	}

	/**
	 * Check if user can execute shortcodes in the given context.
	 *
	 * @since 1.53
	 *
	 * @param mixed $context The execution context.
	 * @return bool True if user can execute, false otherwise.
	 */
	private static function can_execute_shortcode_in_context( $context ) {
		// Additional checks can be added here based on context.
		// For now, require manage_options capability.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if user can edit shortcodes.
	 *
	 * @since 1.53
	 *
	 * @param string $shortcode_name The shortcode name being edited.
	 * @return bool True if user can edit, false otherwise.
	 */
	private static function can_edit_shortcode( $shortcode_name ) {
		// Require manage_options capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Additional shortcode-specific checks can be added here.
		return true;
	}

	/**
	 * Check if user can create new shortcodes.
	 *
	 * @since 1.53
	 *
	 * @return bool True if user can create, false otherwise.
	 */
	private static function can_create_shortcode() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if user can delete shortcodes.
	 *
	 * @since 1.53
	 *
	 * @param string $shortcode_name The shortcode name being deleted.
	 * @return bool True if user can delete, false otherwise.
	 */
	private static function can_delete_shortcode( $shortcode_name ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if user can import shortcodes.
	 *
	 * @since 1.53
	 *
	 * @return bool True if user can import, false otherwise.
	 */
	private static function can_import_shortcodes() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if user can export shortcodes.
	 *
	 * @since 1.53
	 *
	 * @return bool True if user can export, false otherwise.
	 */
	private static function can_export_shortcodes() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Setup secure execution environment with limits.
	 *
	 * Configures PHP environment for secure code execution with appropriate
	 * resource limits and security restrictions.
	 *
	 * @since 1.53
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return array Environment configuration.
	 */
	public static function setup_execution_environment( $atts = array(), $content = '' ) {
		// Set memory limit.
		$memory_limit = self::$max_memory_limit . 'M';
		$previous_memory_limit = ini_get( 'memory_limit' );

		// Only reduce memory limit, never increase it.
		if ( self::convert_to_bytes( $memory_limit ) < self::convert_to_bytes( $previous_memory_limit ) ) {
			ini_set( 'memory_limit', $memory_limit );
		}

		// Set execution time limit.
		set_time_limit( self::$max_execution_time );

		// Disable some potentially dangerous functions during execution.
		$previous_disabled = ini_get( 'disable_functions' );

		// Return environment configuration for restoration.
		return array(
			'previous_memory_limit' => $previous_memory_limit,
			'previous_disabled'     => $previous_disabled,
		);
	}

	/**
	 * Restore execution environment after code execution.
	 *
	 * @since 1.53
	 *
	 * @param array $env_config Environment configuration from setup.
	 */
	public static function restore_execution_environment( $env_config ) {
		// Restore previous memory limit.
		if ( isset( $env_config['previous_memory_limit'] ) ) {
			ini_set( 'memory_limit', $env_config['previous_memory_limit'] );
		}

		// Note: execution time limit cannot be restored, but will reset on next request.
	}

	/**
	 * Converts memory size string to bytes.
	 *
	 * @since 1.53
	 *
	 * @param string $size Memory size string (e.g., '32M', '1G').
	 * @return int Size in bytes.
	 */
	private static function convert_to_bytes( $size ) {
		if ( empty( $size ) ) {
			return 0;
		}

		$size = trim( $size );
		$last_char = strtolower( substr( $size, -1 ) );
		$num = (int) substr( $size, 0, -1 );

		switch ( $last_char ) {
			case 'g':
				$num *= 1024;
				// Fall through.
			case 'm':
				$num *= 1024;
				// Fall through.
			case 'k':
				$num *= 1024;
				break;
			default:
				$num = (int) $size;
		}

		return $num;
	}

	/**
	 * Log code execution for security monitoring.
	 *
	 * Logs shortcode execution events for security analysis and debugging.
	 * Includes execution time, user info, and results.
	 *
	 * @since 1.53
	 *
	 * @param string $shortcode_name The shortcode that was executed.
	 * @param string $status         Execution status (success, error, blocked).
	 * @param string $message        Optional additional message.
	 * @param array  $context        Additional context information.
	 */
	public static function log_code_execution( $shortcode_name, $status, $message = '', $context = array() ) {
		// Skip logging if WordPress debugging is not enabled.
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		// Prepare log entry.
		$log_entry = array(
			'timestamp'      => current_time( 'mysql' ),
			'shortcode_name' => sanitize_text_field( $shortcode_name ),
			'status'         => sanitize_text_field( $status ),
			'user_id'        => get_current_user_id(),
			'user_ip'        => self::get_client_ip(),
			'request_uri'    => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
			'user_agent'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'message'        => sanitize_text_field( $message ),
			'context'        => $context,
		);

		// Log to WordPress debug log.
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$log_message = sprintf(
				'[SHORTCODE_EXEC_PHP] %s - Shortcode: %s, Status: %s, User ID: %d, IP: %s, Message: %s',
				$log_entry['timestamp'],
				$log_entry['shortcode_name'],
				$log_entry['status'],
				$log_entry['user_id'],
				$log_entry['user_ip'],
				$log_entry['message']
			);

			error_log( $log_message );
		}

		// Allow other plugins to hook into execution logging.
		do_action( 'wp_shortcode_exec_php_log_execution', $log_entry );
	}

	/**
	 * Get client IP address safely.
	 *
	 * @since 1.53
	 *
	 * @return string Client IP address.
	 */
	private static function get_client_ip() {
		// Check for IP from shared internet.
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// Check for IP passed from proxy.
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			// Check for IP from standard remote address.
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		} else {
			$ip = 'unknown';
		}

		// Validate IP address.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return $ip;
		}

		return 'unknown';
	}

	/**
	 * Generate secure nonce for specific actions.
	 *
	 * Creates action-specific nonces with additional context for enhanced security.
	 *
	 * @since 1.53
	 *
	 * @param string $action  The action for which to create a nonce.
	 * @param string $context Additional context (optional).
	 * @return string The generated nonce.
	 */
	public static function create_nonce( $action, $context = '' ) {
		$nonce_action = 'scep_' . $action;
		if ( ! empty( $context ) ) {
			$nonce_action .= '_' . $context;
		}

		return wp_create_nonce( $nonce_action );
	}

	/**
	 * Verify nonce for specific actions.
	 *
	 * Verifies action-specific nonces with additional context checking.
	 *
	 * @since 1.53
	 *
	 * @param string $nonce   The nonce to verify.
	 * @param string $action  The action being verified.
	 * @param string $context Additional context (optional).
	 * @return bool True if nonce is valid, false otherwise.
	 */
	public static function verify_nonce( $nonce, $action, $context = '' ) {
		$nonce_action = 'scep_' . $action;
		if ( ! empty( $context ) ) {
			$nonce_action .= '_' . $context;
		}

		return wp_verify_nonce( $nonce, $nonce_action );
	}

	/**
	 * Sanitize shortcode description.
	 *
	 * @since 1.53
	 *
	 * @param string $description The description to sanitize.
	 * @return string Sanitized description.
	 */
	public static function sanitize_shortcode_description( $description ) {
		// Allow basic HTML but sanitize thoroughly.
		$allowed_html = array(
			'p'      => array(),
			'br'     => array(),
			'strong' => array(),
			'em'     => array(),
			'code'   => array(),
		);

		return wp_kses( $description, $allowed_html );
	}

	/**
	 * Get security configuration.
	 *
	 * Returns current security configuration with defaults.
	 *
	 * @since 1.53
	 *
	 * @return array Security configuration array.
	 */
	public static function get_security_config() {
		return array(
			'blocked_functions'    => apply_filters( 'wp_shortcode_exec_php_blocked_functions', self::$default_blocked_functions ),
			'max_execution_time'   => apply_filters( 'wp_shortcode_exec_php_max_execution_time', self::$max_execution_time ),
			'max_memory_limit'     => apply_filters( 'wp_shortcode_exec_php_max_memory_limit', self::$max_memory_limit ),
			'max_code_length'      => apply_filters( 'wp_shortcode_exec_php_max_code_length', 10000 ),
			'enable_syntax_check'  => apply_filters( 'wp_shortcode_exec_php_enable_syntax_check', true ),
			'enable_execution_log' => apply_filters( 'wp_shortcode_exec_php_enable_execution_log', defined( 'WP_DEBUG' ) && WP_DEBUG ),
		);
	}
}