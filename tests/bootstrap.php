<?php
/**
 * Bootstrap file for PHPUnit tests
 */

// Composer autoloader
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

// Define WordPress test environment
if (!defined('WP_TESTS_DIR')) {
    define('WP_TESTS_DIR', __DIR__ . '/wordpress-tests-lib');
}

// WordPress test configuration
if (!defined('WP_TESTS_CONFIG_FILE_PATH')) {
    define('WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-config-test.php');
}

// Load WordPress test framework if available
if (file_exists(WP_TESTS_DIR . '/includes/functions.php')) {
    require_once WP_TESTS_DIR . '/includes/functions.php';
    
    function _manually_load_plugin() {
        require dirname(__DIR__) . '/shortcode-exec-php.php';
    }
    tests_add_filter('muplugins_loaded', '_manually_load_plugin');
    
    require WP_TESTS_DIR . '/includes/bootstrap.php';
} else {
    // Fallback for basic testing without WordPress
    echo "WordPress test suite not found. Running basic PHP tests only.\n";
    
    // Load the plugin files directly for basic testing
    require_once dirname(__DIR__) . '/shortcode-exec-php.php';
}

// Test helper functions
function get_test_data_dir() {
    return __DIR__ . '/data';
}

function get_plugin_dir() {
    return dirname(__DIR__);
}