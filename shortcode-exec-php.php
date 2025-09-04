<?php
/**
 * Plugin Name: Shortcode Exec PHP
 * Plugin URI: https://wordpress.org/plugins/shortcode-exec-php/
 * Description: Execute arbitrary, reusable PHP code in posts, pages, comments, widgets and RSS feeds using shortcodes in a safe and easy way.
 * Version: 1.53
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: WordPress Community
 * Author URI: https://wordpress.org/
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: shortcode-exec-php
 * Domain Path: /languages
 * Network: true
 *
 * @package WordPress
 * @subpackage Shortcode_Exec_PHP
 * @since 1.53
 */

/*
	Copyright 2010-2015 Marcel Bokhorst

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
	Acknowledgments

	EditArea by Christophe Dolivet
	See http://www.cdolivet.com/index.php?page=editArea for details
	This script is publised under the GNU Lesser General Public License

	jQuery JavaScript Library
	This library is published under both the GNU General Public License and MIT License

	All licenses are GPL compatible (see http://www.gnu.org/philosophy/license-list.html#GPLCompatibleLicenses)
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WP_SHORTCODE_EXEC_PHP_VERSION', '1.53' );
define( 'WP_SHORTCODE_EXEC_PHP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_SHORTCODE_EXEC_PHP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_SHORTCODE_EXEC_PHP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Plugin activation hook.
 *
 * @since 1.53
 */
function wp_shortcode_exec_php_activate() {
	// Check system requirements.
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( WP_SHORTCODE_EXEC_PHP_PLUGIN_BASENAME );
		wp_die(
			esc_html__( 'Shortcode Exec PHP requires PHP 7.4 or later.', 'shortcode-exec-php' ),
			esc_html__( 'Plugin Activation Error', 'shortcode-exec-php' ),
			array( 'back_link' => true )
		);
	}

	if ( version_compare( $GLOBALS['wp_version'], '5.0', '<' ) ) {
		deactivate_plugins( WP_SHORTCODE_EXEC_PHP_PLUGIN_BASENAME );
		wp_die(
			esc_html__( 'Shortcode Exec PHP requires WordPress 5.0 or later.', 'shortcode-exec-php' ),
			esc_html__( 'Plugin Activation Error', 'shortcode-exec-php' ),
			array( 'back_link' => true )
		);
	}

	// Initialize plugin.
	wp_shortcode_exec_php_init();

	// Run activation procedures.
	if ( class_exists( 'WP_Shortcode_Exec_PHP' ) ) {
		WP_Shortcode_Exec_PHP::get_instance( __FILE__ )->activate();
	}
}
register_activation_hook( __FILE__, 'wp_shortcode_exec_php_activate' );

/**
 * Plugin deactivation hook.
 *
 * @since 1.53
 */
function wp_shortcode_exec_php_deactivate() {
	if ( class_exists( 'WP_Shortcode_Exec_PHP' ) ) {
		WP_Shortcode_Exec_PHP::get_instance( __FILE__ )->deactivate();
	}
}
register_deactivation_hook( __FILE__, 'wp_shortcode_exec_php_deactivate' );

/**
 * Initialize plugin after WordPress loads.
 *
 * @since 1.53
 */
function wp_shortcode_exec_php_init() {
	// Load main plugin class.
	require_once WP_SHORTCODE_EXEC_PHP_PLUGIN_DIR . 'includes/class-wp-shortcode-exec-php.php';

	// Initialize plugin.
	WP_Shortcode_Exec_PHP::get_instance( __FILE__ );
}

// Initialize on plugins_loaded hook.
add_action( 'plugins_loaded', 'wp_shortcode_exec_php_init' );

/**
 * Load plugin text domain for translations.
 *
 * @since 1.53
 */
function wp_shortcode_exec_php_load_textdomain() {
	load_plugin_textdomain(
		'shortcode-exec-php',
		false,
		dirname( WP_SHORTCODE_EXEC_PHP_PLUGIN_BASENAME ) . '/languages/'
	);
}
add_action( 'plugins_loaded', 'wp_shortcode_exec_php_load_textdomain' );

/**
 * Display security warning in admin.
 *
 * @since 1.53
 */
function wp_shortcode_exec_php_security_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( 'plugins' !== $screen->id ) {
		return;
	}

	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'Shortcode Exec PHP Security Notice:', 'shortcode-exec-php' ); ?></strong>
			<?php esc_html_e( 'This plugin allows execution of arbitrary PHP code. Only install and use if you trust all administrators on this site.', 'shortcode-exec-php' ); ?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'wp_shortcode_exec_php_security_notice' );
