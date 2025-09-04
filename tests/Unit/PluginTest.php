<?php

use PHPUnit\Framework\TestCase;

/**
 * Basic plugin functionality tests for modernized architecture
 */
class PluginTest extends TestCase
{
    public function test_plugin_constants_defined()
    {
        $this->assertTrue(defined('WP_SHORTCODE_EXEC_PHP_VERSION'));
        $this->assertTrue(defined('WP_SHORTCODE_EXEC_PHP_PLUGIN_DIR'));
        $this->assertTrue(defined('WP_SHORTCODE_EXEC_PHP_PLUGIN_URL'));
        $this->assertEquals('1.53', WP_SHORTCODE_EXEC_PHP_VERSION);
    }

    public function test_plugin_file_structure()
    {
        $plugin_dir = get_plugin_dir();
        
        $this->assertFileExists($plugin_dir . '/shortcode-exec-php.php');
        // Legacy monolithic class was archived during v1.53 modernization
        $this->assertFileExists($plugin_dir . '/readme.txt');
        $this->assertDirectoryExists($plugin_dir . '/includes');
        $this->assertFileExists($plugin_dir . '/includes/class-wp-shortcode-exec-php.php');
        $this->assertFileExists($plugin_dir . '/includes/class-wp-shortcode-exec-php-security.php');
        $this->assertFileExists($plugin_dir . '/includes/class-wp-shortcode-exec-php-handler.php');
        $this->assertFileExists($plugin_dir . '/includes/class-wp-shortcode-exec-php-admin.php');
    }

    public function test_plugin_header_information()
    {
        $plugin_file = get_plugin_dir() . '/shortcode-exec-php.php';
        $content = file_get_contents($plugin_file);
        
        $this->assertStringContainsString('Plugin Name: Shortcode Exec PHP', $content);
        $this->assertStringContainsString('Version: 1.53', $content);
        $this->assertStringContainsString('Requires PHP: 7.4', $content);
        $this->assertStringContainsString('Requires at least: 5.0', $content);
    }

    public function test_security_class_methods()
    {
        $plugin_dir = get_plugin_dir();
        require_once $plugin_dir . '/includes/class-wp-shortcode-exec-php-security.php';
        
        $this->assertTrue(class_exists('WP_Shortcode_Exec_PHP_Security'));
        $this->assertTrue(method_exists('WP_Shortcode_Exec_PHP_Security', 'validate_shortcode_name'));
        $this->assertTrue(method_exists('WP_Shortcode_Exec_PHP_Security', 'sanitize_php_code'));
        $this->assertTrue(method_exists('WP_Shortcode_Exec_PHP_Security', 'current_user_can_execute'));
    }

    public function test_shortcode_name_validation()
    {
        $plugin_dir = get_plugin_dir();
        require_once $plugin_dir . '/includes/class-wp-shortcode-exec-php-security.php';
        
        // Valid names
        $this->assertTrue(WP_Shortcode_Exec_PHP_Security::validate_shortcode_name('test_shortcode'));
        $this->assertTrue(WP_Shortcode_Exec_PHP_Security::validate_shortcode_name('my-shortcode'));
        $this->assertTrue(WP_Shortcode_Exec_PHP_Security::validate_shortcode_name('shortcode123'));
        
        // Invalid names
        $this->assertFalse(WP_Shortcode_Exec_PHP_Security::validate_shortcode_name(''));
        $this->assertFalse(WP_Shortcode_Exec_PHP_Security::validate_shortcode_name('123invalid'));
        $this->assertFalse(WP_Shortcode_Exec_PHP_Security::validate_shortcode_name('_invalid'));
        $this->assertFalse(WP_Shortcode_Exec_PHP_Security::validate_shortcode_name('-invalid'));
        $this->assertFalse(WP_Shortcode_Exec_PHP_Security::validate_shortcode_name('invalid space'));
        $this->assertFalse(WP_Shortcode_Exec_PHP_Security::validate_shortcode_name('gallery')); // Reserved
        $this->assertFalse(WP_Shortcode_Exec_PHP_Security::validate_shortcode_name(str_repeat('a', 51))); // Too long
    }

    public function test_php_code_sanitization()
    {
        $plugin_dir = get_plugin_dir();
        require_once $plugin_dir . '/includes/class-wp-shortcode-exec-php-security.php';
        
        // Valid code
        $valid_code = 'echo "Hello World";';
        $result = WP_Shortcode_Exec_PHP_Security::sanitize_php_code($valid_code);
        $this->assertFalse(is_wp_error($result));
        
        // Empty code should be allowed
        $empty_result = WP_Shortcode_Exec_PHP_Security::sanitize_php_code('');
        $this->assertEquals('', $empty_result);
        
        // Code with PHP tags should be cleaned
        $php_tagged_code = '<?php echo "test"; ?>';
        $result = WP_Shortcode_Exec_PHP_Security::sanitize_php_code($php_tagged_code);
        $this->assertFalse(is_wp_error($result));
        $this->assertStringNotContainsString('<?php', $result);
        $this->assertStringNotContainsString('?>', $result);
    }

    public function test_blocked_functions_detection()
    {
        $plugin_dir = get_plugin_dir();
        require_once $plugin_dir . '/includes/class-wp-shortcode-exec-php-security.php';
        
        // Test various blocked functions
        $blocked_functions = array('exec', 'shell_exec', 'system', 'file_get_contents', 'eval');
        
        foreach ($blocked_functions as $func) {
            $malicious_code = $func . '("test");';
            $result = WP_Shortcode_Exec_PHP_Security::sanitize_php_code($malicious_code);
            $this->assertTrue(is_wp_error($result), "Function {$func} should be blocked");
        }
    }
}