<?php
/**
 * Modern Editor Integration for Shortcode Exec PHP
 * 
 * Provides dual-editor support for both Gutenberg Block Editor and Classic Editor (TinyMCE)
 * with backward compatibility and modern WordPress standards.
 *
 * @package WordPress
 * @subpackage Shortcode_Exec_PHP
 * @since 1.53
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Editor Integration Manager
 * 
 * Handles both Gutenberg Block Editor and Classic Editor (TinyMCE) integration
 * with automatic detection and appropriate asset loading.
 */
class WP_Shortcode_Exec_PHP_Editor_Integration {
	
	/**
	 * Plugin version for cache busting
	 *
	 * @var string
	 */
	private $version;
	
	/**
	 * Plugin URL for asset loading
	 *
	 * @var string
	 */
	private $plugin_url;
	
	/**
	 * Available shortcodes cache
	 *
	 * @var array
	 */
	private $shortcodes_cache = null;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->version = defined( 'WP_SHORTCODE_EXEC_PHP_VERSION' ) ? WP_SHORTCODE_EXEC_PHP_VERSION : '1.53';
		$this->plugin_url = defined( 'WP_SHORTCODE_EXEC_PHP_PLUGIN_URL' ) ? WP_SHORTCODE_EXEC_PHP_PLUGIN_URL : plugin_dir_url( __DIR__ );
		
		$this->init_hooks();
	}
	
	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Gutenberg Block Editor integration
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
		add_action( 'init', array( $this, 'register_gutenberg_blocks' ) );
		
		// Classic Editor (TinyMCE) integration  
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_classic_editor_assets' ) );
		add_filter( 'mce_external_plugins', array( $this, 'register_tinymce_plugin' ) );
		add_filter( 'mce_buttons', array( $this, 'register_tinymce_button' ) );
		
		// AJAX handlers for both editors
		add_action( 'wp_ajax_scep_get_shortcodes', array( $this, 'ajax_get_shortcodes' ) );
		add_action( 'wp_ajax_scep_insert_shortcode', array( $this, 'ajax_insert_shortcode' ) );
		add_action( 'wp_ajax_scep_preview_shortcode', array( $this, 'ajax_preview_shortcode' ) );
		
		// Editor detection and compatibility
		add_action( 'admin_init', array( $this, 'detect_editor_environment' ) );
	}
	
	/**
	 * Detect which editor environment is active
	 *
	 * @return string 'gutenberg', 'classic', or 'mixed'
	 */
	public function detect_editor_environment() {
		$current_screen = get_current_screen();
		
		if ( ! $current_screen || ! in_array( $current_screen->base, array( 'post', 'post-new' ), true ) ) {
			return 'none';
		}
		
		// Check if Gutenberg is enabled for this post type
		if ( function_exists( 'use_block_editor_for_post_type' ) ) {
			$post_type = $current_screen->post_type;
			if ( use_block_editor_for_post_type( $post_type ) ) {
				return 'gutenberg';
			}
		}
		
		// Check for Classic Editor plugin
		if ( class_exists( 'Classic_Editor' ) ) {
			return 'classic';
		}
		
		// Default to Gutenberg for modern WordPress
		return 'gutenberg';
	}
	
	/**
	 * Register Gutenberg blocks
	 */
	public function register_gutenberg_blocks() {
		// Register the shortcode selector block
		register_block_type( 'shortcode-exec-php/shortcode-selector', array(
			'editor_script' => 'shortcode-exec-php-blocks',
			'editor_style'  => 'shortcode-exec-php-blocks-editor',
			'style'         => 'shortcode-exec-php-blocks-style',
			'render_callback' => array( $this, 'render_shortcode_block' ),
			'attributes' => array(
				'shortcodeName' => array(
					'type' => 'string',
					'default' => '',
				),
				'parameters' => array(
					'type' => 'object',
					'default' => array(),
				),
				'content' => array(
					'type' => 'string',
					'default' => '',
				),
			),
		) );
	}
	
	/**
	 * Enqueue Block Editor assets
	 */
	public function enqueue_block_editor_assets() {
		// Block Editor JavaScript
		wp_enqueue_script(
			'shortcode-exec-php-blocks',
			$this->plugin_url . 'js/blocks.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-editor',
				'wp-components',
				'wp-data',
				'wp-api-fetch',
				'wp-i18n'
			),
			$this->version,
			true
		);
		
		// Block Editor CSS
		wp_enqueue_style(
			'shortcode-exec-php-blocks-editor',
			$this->plugin_url . 'css/blocks-editor.css',
			array( 'wp-edit-blocks' ),
			$this->version
		);
		
		// Frontend block styles
		wp_enqueue_style(
			'shortcode-exec-php-blocks-style',
			$this->plugin_url . 'css/blocks.css',
			array(),
			$this->version
		);
		
		// Localize script with shortcode data
		wp_localize_script(
			'shortcode-exec-php-blocks',
			'shortcodeExecPHP',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'scep_editor_nonce' ),
				'shortcodes' => $this->get_available_shortcodes(),
				'i18n' => array(
					'selectShortcode' => __( 'Select a shortcode', 'shortcode-exec-php' ),
					'shortcodeName' => __( 'Shortcode Name', 'shortcode-exec-php' ),
					'parameters' => __( 'Parameters', 'shortcode-exec-php' ),
					'preview' => __( 'Preview', 'shortcode-exec-php' ),
					'insert' => __( 'Insert', 'shortcode-exec-php' ),
					'noShortcodes' => __( 'No shortcodes available. Create one first.', 'shortcode-exec-php' ),
				),
			)
		);
		
		// Set up translations
		wp_set_script_translations( 'shortcode-exec-php-blocks', 'shortcode-exec-php' );
	}
	
	/**
	 * Enqueue Classic Editor assets
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_classic_editor_assets( $hook ) {
		// Only load on post editing screens
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		
		// Only if rich editing is enabled and not using block editor
		if ( get_user_option( 'rich_editing' ) !== 'true' ) {
			return;
		}
		
		$screen = get_current_screen();
		if ( $screen && function_exists( 'use_block_editor_for_post_type' ) ) {
			if ( use_block_editor_for_post_type( $screen->post_type ) ) {
				return; // Block editor is active, skip TinyMCE assets
			}
		}
		
		// Modern TinyMCE integration assets
		wp_enqueue_script(
			'shortcode-exec-php-tinymce',
			$this->plugin_url . 'js/tinymce-modern.js',
			array( 'jquery', 'wp-util' ),
			$this->version,
			true
		);
		
		// TinyMCE modal styles
		wp_enqueue_style(
			'shortcode-exec-php-tinymce',
			$this->plugin_url . 'css/tinymce-modal.css',
			array(),
			$this->version
		);
		
		// Localize script data
		wp_localize_script(
			'shortcode-exec-php-tinymce',
			'shortcodeExecPHP',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'scep_editor_nonce' ),
				'shortcodes' => $this->get_available_shortcodes(),
				'modalTitle' => __( 'Insert PHP Shortcode', 'shortcode-exec-php' ),
			)
		);
	}
	
	/**
	 * Register TinyMCE plugin
	 *
	 * @param array $plugins Existing TinyMCE plugins
	 * @return array Modified plugins array
	 */
	public function register_tinymce_plugin( $plugins ) {
		if ( $this->should_load_tinymce_plugin() ) {
			$plugins['shortcodeExecPHP'] = $this->plugin_url . 'js/tinymce-plugin-modern.js';
		}
		return $plugins;
	}
	
	/**
	 * Register TinyMCE button
	 *
	 * @param array $buttons Existing TinyMCE buttons
	 * @return array Modified buttons array
	 */
	public function register_tinymce_button( $buttons ) {
		if ( $this->should_load_tinymce_plugin() ) {
			$buttons[] = 'shortcodeExecPHP';
		}
		return $buttons;
	}
	
	/**
	 * Check if TinyMCE plugin should be loaded
	 *
	 * @return bool
	 */
	private function should_load_tinymce_plugin() {
		// Don't load if user can't use visual editor
		if ( get_user_option( 'rich_editing' ) !== 'true' ) {
			return false;
		}
		
		// Don't load if block editor is active
		$screen = get_current_screen();
		if ( $screen && function_exists( 'use_block_editor_for_post_type' ) ) {
			if ( use_block_editor_for_post_type( $screen->post_type ) ) {
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Get available shortcodes for editor integration
	 *
	 * @return array Array of shortcode data
	 */
	public function get_available_shortcodes() {
		if ( $this->shortcodes_cache !== null ) {
			return $this->shortcodes_cache;
		}
		
		$shortcodes = array();
		$names = get_option( 'wp_scep_shortcode_names', array() );
		
		if ( ! empty( $names ) && is_array( $names ) ) {
			foreach ( $names as $name ) {
				$enabled = get_option( "wp_scep_enabled_{$name}", false );
				$description = get_option( "wp_scep_description_{$name}", '' );
				
				if ( $enabled ) {
					$shortcodes[] = array(
						'name' => sanitize_text_field( $name ),
						'description' => sanitize_text_field( $description ),
						'label' => sprintf( '[%s] - %s', $name, $description ),
					);
				}
			}
		}
		
		$this->shortcodes_cache = $shortcodes;
		return $shortcodes;
	}
	
	/**
	 * Render shortcode block for Gutenberg
	 *
	 * @param array $attributes Block attributes
	 * @return string Rendered shortcode output
	 */
	public function render_shortcode_block( $attributes ) {
		if ( empty( $attributes['shortcodeName'] ) ) {
			return '<div class="shortcode-exec-php-placeholder">' . 
				   esc_html__( 'Select a shortcode to display', 'shortcode-exec-php' ) . 
				   '</div>';
		}
		
		$shortcode_name = sanitize_text_field( $attributes['shortcodeName'] );
		$parameters = isset( $attributes['parameters'] ) ? $attributes['parameters'] : array();
		$content = isset( $attributes['content'] ) ? $attributes['content'] : '';
		
		// Build shortcode string
		$shortcode_string = "[{$shortcode_name}";
		
		if ( ! empty( $parameters ) && is_array( $parameters ) ) {
			foreach ( $parameters as $key => $value ) {
				$key = sanitize_key( $key );
				$value = esc_attr( $value );
				$shortcode_string .= " {$key}=\"{$value}\"";
			}
		}
		
		if ( ! empty( $content ) ) {
			$shortcode_string .= "]{$content}[/{$shortcode_name}]";
		} else {
			$shortcode_string .= ']';
		}
		
		// Process the shortcode
		return do_shortcode( $shortcode_string );
	}
	
	/**
	 * AJAX handler to get available shortcodes
	 */
	public function ajax_get_shortcodes() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'scep_editor_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'shortcode-exec-php' ) );
		}
		
		// Check permissions
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'shortcode-exec-php' ) );
		}
		
		wp_send_json_success( $this->get_available_shortcodes() );
	}
	
	/**
	 * AJAX handler for shortcode insertion
	 */
	public function ajax_insert_shortcode() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'scep_editor_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'shortcode-exec-php' ) );
		}
		
		// Check permissions
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'shortcode-exec-php' ) );
		}
		
		$shortcode_name = sanitize_text_field( $_POST['shortcode'] ?? '' );
		$parameters = $_POST['parameters'] ?? array();
		$content = sanitize_textarea_field( $_POST['content'] ?? '' );
		
		if ( empty( $shortcode_name ) ) {
			wp_send_json_error( __( 'Shortcode name is required', 'shortcode-exec-php' ) );
		}
		
		// Validate shortcode exists
		$available = $this->get_available_shortcodes();
		$shortcode_exists = false;
		foreach ( $available as $shortcode ) {
			if ( $shortcode['name'] === $shortcode_name ) {
				$shortcode_exists = true;
				break;
			}
		}
		
		if ( ! $shortcode_exists ) {
			wp_send_json_error( __( 'Invalid shortcode name', 'shortcode-exec-php' ) );
		}
		
		// Build shortcode string
		$shortcode_string = "[{$shortcode_name}";
		
		if ( ! empty( $parameters ) && is_array( $parameters ) ) {
			foreach ( $parameters as $key => $value ) {
				$key = sanitize_key( $key );
				$value = esc_attr( $value );
				$shortcode_string .= " {$key}=\"{$value}\"";
			}
		}
		
		if ( ! empty( $content ) ) {
			$shortcode_string .= "]{$content}[/{$shortcode_name}]";
		} else {
			$shortcode_string .= ']';
		}
		
		wp_send_json_success( array(
			'shortcode' => $shortcode_string,
			'name' => $shortcode_name,
		) );
	}
	
	/**
	 * AJAX handler for shortcode preview
	 */
	public function ajax_preview_shortcode() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'scep_editor_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'shortcode-exec-php' ) );
		}
		
		// Check permissions
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'shortcode-exec-php' ) );
		}
		
		$shortcode_string = sanitize_textarea_field( $_POST['shortcode'] ?? '' );
		
		if ( empty( $shortcode_string ) ) {
			wp_send_json_error( __( 'Shortcode is required', 'shortcode-exec-php' ) );
		}
		
		// Process shortcode in safe environment
		$output = do_shortcode( $shortcode_string );
		
		wp_send_json_success( array(
			'preview' => $output,
			'html' => esc_html( $output ),
		) );
	}
	
	/**
	 * Clear shortcodes cache when shortcodes are modified
	 */
	public function clear_shortcodes_cache() {
		$this->shortcodes_cache = null;
	}
	
	/**
	 * Get editor integration statistics for admin dashboard
	 *
	 * @return array Editor usage statistics
	 */
	public function get_editor_statistics() {
		$stats = array(
			'gutenberg_active' => function_exists( 'use_block_editor_for_post_type' ),
			'classic_editor_plugin' => class_exists( 'Classic_Editor' ),
			'supported_post_types' => array(),
		);
		
		// Check post types that support block editor
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $post_types as $post_type ) {
			if ( function_exists( 'use_block_editor_for_post_type' ) ) {
				$stats['supported_post_types'][ $post_type->name ] = array(
					'label' => $post_type->label,
					'uses_block_editor' => use_block_editor_for_post_type( $post_type->name ),
				);
			}
		}
		
		return $stats;
	}
}