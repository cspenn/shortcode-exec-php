<?php
/**
 * Secure shortcode handler for Shortcode Exec PHP plugin.
 *
 * This class handles the secure execution of PHP code within shortcodes,
 * implementing multiple layers of security including input validation,
 * function blacklisting, execution monitoring, and error handling.
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
 * Secure shortcode handler class.
 *
 * Handles the execution of PHP code within shortcodes with comprehensive
 * security measures and error handling.
 *
 * @since 1.53
 */
class WP_Shortcode_Exec_PHP_Handler {

	/**
	 * The security utility instance.
	 *
	 * @var WP_Shortcode_Exec_PHP_Security
	 */
	private $security;

	/**
	 * Constructor.
	 *
	 * @since 1.53
	 */
	public function __construct() {
		// Security class should already be loaded.
		$this->security = new WP_Shortcode_Exec_PHP_Security();
	}

	/**
	 * Handle shortcode execution with enhanced security.
	 *
	 * This is the main entry point for shortcode execution. It performs
	 * comprehensive security checks before delegating to the secure execution method.
	 *
	 * @since 1.53
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @param string $tag     Shortcode tag.
	 * @return string Shortcode output or error message.
	 */
	public function handle_shortcode( $atts, $content, $tag ) {
		// Validate shortcode tag.
		if ( ! WP_Shortcode_Exec_PHP_Security::validate_shortcode_name( $tag ) ) {
			WP_Shortcode_Exec_PHP_Security::log_code_execution( $tag, 'invalid_name', 'Invalid shortcode name format' );
			return $this->get_error_message( 'invalid_shortcode', $tag );
		}

		// Check if shortcode is enabled and exists.
		$shortcode_config = $this->get_shortcode_config( $tag );
		if ( is_wp_error( $shortcode_config ) ) {
			WP_Shortcode_Exec_PHP_Security::log_code_execution( $tag, 'not_found', 'Shortcode configuration not found' );
			return $this->get_error_message( 'shortcode_not_found', $tag );
		}

		// Check if shortcode is enabled.
		if ( ! $shortcode_config['enabled'] ) {
			WP_Shortcode_Exec_PHP_Security::log_code_execution( $tag, 'disabled', 'Shortcode is disabled' );
			return $this->get_error_message( 'shortcode_disabled', $tag );
		}

		// Security and capability checks.
		if ( ! $this->can_execute_shortcode( $tag, $atts, $content ) ) {
			WP_Shortcode_Exec_PHP_Security::log_code_execution( $tag, 'access_denied', 'Insufficient permissions for execution' );
			return $this->get_access_denied_message( $tag );
		}

		// Check context restrictions (RSS feeds, etc.).
		if ( ! $this->is_execution_context_allowed( $tag ) ) {
			WP_Shortcode_Exec_PHP_Security::log_code_execution( $tag, 'context_restricted', 'Execution not allowed in current context' );
			return $this->get_context_restricted_message( $tag );
		}

		// Execute the shortcode with enhanced security.
		return $this->execute_php_code_securely( $shortcode_config, $atts, $content, $tag );
	}

	/**
	 * Execute PHP code with enhanced security and error handling.
	 *
	 * This method implements the actual code execution with multiple security
	 * layers including input validation, execution monitoring, and error recovery.
	 *
	 * @since 1.53
	 *
	 * @param array  $config  Shortcode configuration.
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @param string $tag     Shortcode tag.
	 * @return string Execution result or error message.
	 */
	private function execute_php_code_securely( $config, $atts, $content, $tag ) {
		// Validate and sanitize the PHP code.
		$sanitized_code = WP_Shortcode_Exec_PHP_Security::sanitize_php_code( $config['code'] );
		if ( is_wp_error( $sanitized_code ) ) {
			WP_Shortcode_Exec_PHP_Security::log_code_execution(
				$tag,
				'code_validation_failed',
				$sanitized_code->get_error_message()
			);
			return $this->get_error_message( 'code_validation_failed', $tag, $sanitized_code->get_error_message() );
		}

		// Setup secure execution environment.
		$env_config = WP_Shortcode_Exec_PHP_Security::setup_execution_environment( $atts, $content );

		// Start output buffering if configured.
		$output = '';
		if ( $config['buffer'] ) {
			ob_start();
		}

		// Prepare execution context variables.
		$execution_start_time = microtime( true );
		$result = '';

		try {
			// Set up shortcode context variables (available to executed code).
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['scep_atts'] = $atts;
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['scep_content'] = $content;
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['scep_tag'] = $tag;

			// Set execution context for the evaluated code.
			$atts    = $atts; // Make available to eval'd code.
			$content = $content; // Make available to eval'd code.

			// Execute the PHP code.
			// Note: eval() is still used but with comprehensive security layers.
			// This is the core functionality of the plugin and cannot be eliminated.
			// phpcs:ignore Squiz.PHP.Eval.Discouraged
			$result = eval( $sanitized_code );

			// Calculate execution time.
			$execution_time = microtime( true ) - $execution_start_time;

			// Log successful execution.
			WP_Shortcode_Exec_PHP_Security::log_code_execution(
				$tag,
				'success',
				sprintf( 'Executed successfully in %.4f seconds', $execution_time ),
				array(
					'execution_time' => $execution_time,
					'memory_used'    => memory_get_usage() - memory_get_usage( true ),
				)
			);

		} catch ( ParseError $e ) {
			$execution_time = microtime( true ) - $execution_start_time;
			WP_Shortcode_Exec_PHP_Security::log_code_execution(
				$tag,
				'parse_error',
				'Parse error: ' . $e->getMessage(),
				array(
					'execution_time' => $execution_time,
					'error_line'     => $e->getLine(),
					'error_message'  => $e->getMessage(),
				)
			);

			// Clean up output buffer if active.
			if ( $config['buffer'] && ob_get_level() > 0 ) {
				ob_end_clean();
			}

			// Restore environment.
			WP_Shortcode_Exec_PHP_Security::restore_execution_environment( $env_config );

			return $this->get_error_message( 'parse_error', $tag, $e->getMessage() );

		} catch ( Error $e ) {
			$execution_time = microtime( true ) - $execution_start_time;
			WP_Shortcode_Exec_PHP_Security::log_code_execution(
				$tag,
				'fatal_error',
				'Fatal error: ' . $e->getMessage(),
				array(
					'execution_time' => $execution_time,
					'error_line'     => $e->getLine(),
					'error_message'  => $e->getMessage(),
				)
			);

			// Clean up output buffer if active.
			if ( $config['buffer'] && ob_get_level() > 0 ) {
				ob_end_clean();
			}

			// Restore environment.
			WP_Shortcode_Exec_PHP_Security::restore_execution_environment( $env_config );

			return $this->get_error_message( 'fatal_error', $tag, $e->getMessage() );

		} catch ( Exception $e ) {
			$execution_time = microtime( true ) - $execution_start_time;
			WP_Shortcode_Exec_PHP_Security::log_code_execution(
				$tag,
				'exception',
				'Exception: ' . $e->getMessage(),
				array(
					'execution_time' => $execution_time,
					'error_line'     => $e->getLine(),
					'error_message'  => $e->getMessage(),
				)
			);

			// Clean up output buffer if active.
			if ( $config['buffer'] && ob_get_level() > 0 ) {
				ob_end_clean();
			}

			// Restore environment.
			WP_Shortcode_Exec_PHP_Security::restore_execution_environment( $env_config );

			return $this->get_error_message( 'exception', $tag, $e->getMessage() );
		}

		// Get buffered output if configured.
		if ( $config['buffer'] && ob_get_level() > 0 ) {
			$output = ob_get_clean();
		}

		// Clean up global variables.
		unset( $GLOBALS['scep_atts'], $GLOBALS['scep_content'], $GLOBALS['scep_tag'] );

		// Restore execution environment.
		WP_Shortcode_Exec_PHP_Security::restore_execution_environment( $env_config );

		// Store last used parameters for admin interface.
		if ( ! empty( $atts ) && is_array( $atts ) ) {
			$this->update_last_parameters( $tag, $atts );
		} else {
			$this->delete_last_parameters( $tag );
		}

		// Return combined output and result.
		$final_output = $output . (string) $result;

		// Sanitize output for safety.
		return $this->sanitize_output( $final_output );
	}

	/**
	 * Get shortcode configuration safely.
	 *
	 * @since 1.53
	 *
	 * @param string $shortcode_name The shortcode name.
	 * @return array|WP_Error Shortcode configuration or error.
	 */
	private function get_shortcode_config( $shortcode_name ) {
		// Get shortcode names list.
		$shortcode_names = get_option( 'scep_names', array() );
		if ( ! is_array( $shortcode_names ) || ! in_array( $shortcode_name, $shortcode_names, true ) ) {
			return new WP_Error( 'shortcode_not_found', __( 'Shortcode not found.', 'shortcode-exec-php' ) );
		}

		// Get shortcode configuration.
		$config = array(
			'enabled'     => (bool) get_option( 'scep_enabled_' . $shortcode_name, false ),
			'buffer'      => (bool) get_option( 'scep_buffer_' . $shortcode_name, false ),
			'description' => get_option( 'scep_description_' . $shortcode_name, '' ),
			'code'        => get_option( 'scep_phpcode_' . $shortcode_name, '' ),
		);

		// Validate configuration.
		if ( empty( $config['code'] ) ) {
			return new WP_Error( 'empty_code', __( 'Shortcode has no code defined.', 'shortcode-exec-php' ) );
		}

		return $config;
	}

	/**
	 * Check if current user can execute the shortcode.
	 *
	 * @since 1.53
	 *
	 * @param string $shortcode_name The shortcode name.
	 * @param array  $atts           Shortcode attributes.
	 * @param string $content        Shortcode content.
	 * @return bool True if user can execute, false otherwise.
	 */
	private function can_execute_shortcode( $shortcode_name, $atts, $content ) {
		global $post;

		// Check basic capability.
		if ( ! WP_Shortcode_Exec_PHP_Security::current_user_can_execute( 'execute_shortcode', $shortcode_name ) ) {
			return false;
		}

		// Additional context checks.
		if ( ! in_the_loop() ) {
			// Allow execution outside the loop for admin contexts.
			if ( ! is_admin() ) {
				return false;
			}
		}

		// Check comment loop restriction.
		global $in_comment_loop;
		if ( ! empty( $in_comment_loop ) ) {
			// Check if comment shortcode processing is enabled.
			if ( ! get_option( 'scep_comment', false ) ) {
				return false;
			}
		}

		// Author capability check for content execution.
		if ( isset( $post ) && $post instanceof WP_Post ) {
			$author_cap = get_option( 'scep_author_cap', 'edit_posts' );
			if ( ! author_can( $post, $author_cap ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if execution is allowed in current context.
	 *
	 * @since 1.53
	 *
	 * @param string $shortcode_name The shortcode name.
	 * @return bool True if execution is allowed, false otherwise.
	 */
	private function is_execution_context_allowed( $shortcode_name ) {
		// Check RSS feed restriction.
		if ( is_feed() && ! get_option( 'scep_rss', false ) ) {
			return false;
		}

		// Check if we're in a widget context.
		if ( $this->is_widget_context() && ! get_option( 'scep_widget', false ) ) {
			return false;
		}

		// Check if we're in an excerpt context.
		if ( $this->is_excerpt_context() && ! get_option( 'scep_excerpt', false ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if we're currently in a widget context.
	 *
	 * @since 1.53
	 *
	 * @return bool True if in widget context, false otherwise.
	 */
	private function is_widget_context() {
		// Check if current filter is widget-related.
		$widget_filters = array(
			'widget_text',
			'widget_title',
			'widget_custom_html_content',
		);

		return in_array( current_filter(), $widget_filters, true );
	}

	/**
	 * Check if we're currently in an excerpt context.
	 *
	 * @since 1.53
	 *
	 * @return bool True if in excerpt context, false otherwise.
	 */
	private function is_excerpt_context() {
		// Check if current filter is excerpt-related.
		$excerpt_filters = array(
			'the_excerpt',
			'get_the_excerpt',
			'wp_trim_excerpt',
		);

		return in_array( current_filter(), $excerpt_filters, true );
	}

	/**
	 * Update last used parameters for shortcode.
	 *
	 * @since 1.53
	 *
	 * @param string $shortcode_name The shortcode name.
	 * @param array  $atts           The shortcode attributes.
	 */
	private function update_last_parameters( $shortcode_name, $atts ) {
		if ( is_array( $atts ) && ! empty( $atts ) ) {
			update_option( 'scep_param_' . $shortcode_name, $atts, false );
		}
	}

	/**
	 * Delete last used parameters for shortcode.
	 *
	 * @since 1.53
	 *
	 * @param string $shortcode_name The shortcode name.
	 */
	private function delete_last_parameters( $shortcode_name ) {
		delete_option( 'scep_param_' . $shortcode_name );
	}

	/**
	 * Sanitize shortcode output.
	 *
	 * @since 1.53
	 *
	 * @param string $output The raw output from shortcode execution.
	 * @return string Sanitized output.
	 */
	private function sanitize_output( $output ) {
		// Convert to string if not already.
		$output = (string) $output;

		// No sanitization by default to preserve intended HTML output.
		// Individual shortcodes should handle their own output sanitization
		// if they need to output user-generated content.
		
		// However, we can filter out any remaining PHP tags that might
		// have been inadvertently included.
		$output = preg_replace( '/<\?php.*?\?>/si', '', $output );
		$output = preg_replace( '/<\?.*?\?>/si', '', $output );

		return $output;
	}

	/**
	 * Get error message for display.
	 *
	 * @since 1.53
	 *
	 * @param string $error_type    The type of error.
	 * @param string $shortcode_name The shortcode name.
	 * @param string $details       Additional error details.
	 * @return string Formatted error message.
	 */
	private function get_error_message( $error_type, $shortcode_name, $details = '' ) {
		// Only show detailed errors to users who can manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			return sprintf(
				'[%s: %s]',
				esc_html__( 'Error', 'shortcode-exec-php' ),
				esc_html( $shortcode_name )
			);
		}

		$messages = array(
			'invalid_shortcode'        => __( 'Invalid shortcode name format', 'shortcode-exec-php' ),
			'shortcode_not_found'      => __( 'Shortcode not found', 'shortcode-exec-php' ),
			'shortcode_disabled'       => __( 'Shortcode is disabled', 'shortcode-exec-php' ),
			'code_validation_failed'   => __( 'Code validation failed', 'shortcode-exec-php' ),
			'parse_error'             => __( 'PHP parse error', 'shortcode-exec-php' ),
			'fatal_error'             => __( 'PHP fatal error', 'shortcode-exec-php' ),
			'exception'               => __( 'PHP exception', 'shortcode-exec-php' ),
		);

		$message = isset( $messages[ $error_type ] ) ? $messages[ $error_type ] : __( 'Unknown error', 'shortcode-exec-php' );

		if ( ! empty( $details ) ) {
			$message .= ': ' . esc_html( $details );
		}

		return sprintf(
			'[%s: %s - %s]',
			esc_html__( 'Shortcode Exec PHP Error', 'shortcode-exec-php' ),
			esc_html( $shortcode_name ),
			$message
		);
	}

	/**
	 * Get access denied message.
	 *
	 * @since 1.53
	 *
	 * @param string $shortcode_name The shortcode name.
	 * @return string Access denied message.
	 */
	private function get_access_denied_message( $shortcode_name ) {
		return sprintf(
			'[%s: %s]',
			esc_html__( 'Access Denied', 'shortcode-exec-php' ),
			esc_html( $shortcode_name )
		);
	}

	/**
	 * Get context restricted message.
	 *
	 * @since 1.53
	 *
	 * @param string $shortcode_name The shortcode name.
	 * @return string Context restricted message.
	 */
	private function get_context_restricted_message( $shortcode_name ) {
		// Return the shortcode unchanged for restricted contexts.
		return sprintf( '[%s]', esc_html( $shortcode_name ) );
	}
}