<?php
/**
 * XSS Security Tests for SlimWP Simple Points Plugin
 * 
 * This file contains tests to verify XSS vulnerabilities have been properly fixed.
 * Run these tests after applying security fixes to ensure they work correctly.
 * 
 * IMPORTANT: This file contains intentional XSS test patterns for security validation.
 * These patterns are used to verify that the plugin properly sanitizes input and
 * prevents Cross-Site Scripting attacks. The patterns are NOT executed - they are
 * tested against the plugin's sanitization functions to ensure they are blocked.
 * 
 * WordPress Plugin Check warnings about image functions are expected in this file
 * as it contains test patterns that include HTML elements for security validation.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_XSS_Security_Tests {
    
    private $points_system;
    private $shortcodes;
    
    public function __construct() {
        $this->points_system = SlimWP_Points::get_instance();
        $this->shortcodes = new SlimWP_Shortcodes($this->points_system);
    }
    
    /**
     * Run all XSS security tests
     */
    public function run_all_tests() {
        $results = array();
        
        $results['currency_symbol_xss'] = $this->test_currency_symbol_xss();
        $results['balance_type_xss'] = $this->test_balance_type_xss();
        $results['shortcode_attribute_xss'] = $this->test_shortcode_attribute_xss();
        $results['css_class_injection'] = $this->test_css_class_injection();
        $results['label_xss'] = $this->test_label_xss();
        
        return $results;
    }
    
    /**
     * Test currency symbol XSS protection
     * Note: These are intentional XSS test patterns, not actual malicious content
     */
    private function test_currency_symbol_xss() {
        $malicious_symbols = array(
            '<script>alert("XSS")</script>',
            '"><script>alert("XSS")</script>',
            'javascript:alert("XSS")',
            '&lt;script&gt;alert("XSS")&lt;/script&gt;',
            '\'; alert("XSS"); \'',
            '<svg onload=alert("XSS")>'
        );
        
        foreach ($malicious_symbols as $symbol) {
            // Test live shortcode with malicious currency symbol
            $output = $this->shortcodes->points_live_shortcode(array(
                'user_id' => 1,
                'format' => 'currency',
                'currency_symbol' => $symbol
            ));
            
            // Check if malicious content is present in output
            if (strpos($output, '<script>') !== false ||
                strpos($output, 'javascript:') !== false ||
                strpos($output, 'onload=') !== false ||
                strpos($output, $symbol) !== false) {
                return array(
                    'status' => 'FAIL',
                    'message' => 'Currency symbol XSS vulnerability detected',
                    'malicious_input' => $symbol,
                    'output' => $output
                );
            }
        }
        
        return array(
            'status' => 'PASS',
            'message' => 'Currency symbol XSS protection working correctly'
        );
    }
    
    /**
     * Test balance type XSS protection
     */
    private function test_balance_type_xss() {
        $malicious_types = array(
            '<script>alert("XSS")</script>',
            '\'; alert("XSS"); \'',
            'total<script>alert("XSS")</script>',
            'free"; alert("XSS"); "',
            'permanent</script><script>alert("XSS")</script>'
        );
        
        foreach ($malicious_types as $type) {
            // Test shortcode with malicious type
            $output = $this->shortcodes->points_shortcode(array(
                'user_id' => 1,
                'type' => $type
            ));
            
            // Check if malicious content is present
            if (strpos($output, '<script>') !== false ||
                strpos($output, 'alert(') !== false) {
                return array(
                    'status' => 'FAIL',
                    'message' => 'Balance type XSS vulnerability detected',
                    'malicious_input' => $type,
                    'output' => $output
                );
            }
        }
        
        return array(
            'status' => 'PASS',
            'message' => 'Balance type XSS protection working correctly'
        );
    }
    
    /**
     * Test shortcode attribute XSS protection
     */
    private function test_shortcode_attribute_xss() {
        $malicious_attrs = array(
            'class' => 'test" onmouseover="alert(\'XSS\')" "',
            'label' => '<script>alert("XSS")</script>',
            'refresh' => '5"><script>alert("XSS")</script>',
            'animate' => 'true" onload="alert(\'XSS\')" "'
        );
        
        foreach ($malicious_attrs as $attr => $value) {
            $shortcode_attrs = array(
                'user_id' => 1,
                $attr => $value
            );
            
            $output = $this->shortcodes->points_live_shortcode($shortcode_attrs);
            
            // Check for XSS patterns
            if (preg_match('/on\w+\s*=/', $output) ||
                strpos($output, '<script>') !== false ||
                strpos($output, 'javascript:') !== false) {
                return array(
                    'status' => 'FAIL',
                    'message' => 'Shortcode attribute XSS vulnerability detected',
                    'malicious_attribute' => $attr,
                    'malicious_input' => $value,
                    'output' => $output
                );
            }
        }
        
        return array(
            'status' => 'PASS',
            'message' => 'Shortcode attribute XSS protection working correctly'
        );
    }
    
    /**
     * Test CSS class injection protection
     */
    private function test_css_class_injection() {
        $malicious_classes = array(
            'test" style="background:url(javascript:alert(\'XSS\'))" "',
            'test</style><script>alert("XSS")</script><style>',
            'test" onmouseover="alert(\'XSS\')" "',
            'test\"><script>alert(\"XSS\")</script>'
        );
        
        foreach ($malicious_classes as $class) {
            $output = $this->shortcodes->points_live_shortcode(array(
                'user_id' => 1,
                'class' => $class
            ));
            
            // Check for dangerous patterns
            if (strpos($output, 'javascript:') !== false ||
                strpos($output, '<script>') !== false ||
                preg_match('/on\w+\s*=/', $output) ||
                strpos($output, '</style>') !== false) {
                return array(
                    'status' => 'FAIL',
                    'message' => 'CSS class injection vulnerability detected',
                    'malicious_input' => $class,
                    'output' => $output
                );
            }
        }
        
        return array(
            'status' => 'PASS',
            'message' => 'CSS class injection protection working correctly'
        );
    }
    
    /**
     * Test label XSS protection
     * Note: These are intentional XSS test patterns, not actual malicious content
     */
    private function test_label_xss() {
        $malicious_labels = array(
            '<script>alert("XSS")</script>',
            '<iframe src="javascript:alert(XSS)">',
            '<svg onload=alert("XSS")>',
            'Balance: </span><script>alert("XSS")</script><span>'
        );
        
        foreach ($malicious_labels as $label) {
            $output = $this->shortcodes->points_live_shortcode(array(
                'user_id' => 1,
                'label' => $label
            ));
            
            // Check for XSS patterns
            if (strpos($output, '<script>') !== false ||
                strpos($output, 'onerror=') !== false ||
                strpos($output, 'onload=') !== false ||
                preg_match('/<(img|svg|iframe|object|embed)/i', $output)) {
                return array(
                    'status' => 'FAIL',
                    'message' => 'Label XSS vulnerability detected',
                    'malicious_input' => $label,
                    'output' => $output
                );
            }
        }
        
        return array(
            'status' => 'PASS',
            'message' => 'Label XSS protection working correctly'
        );
    }
    
    /**
     * Generate a comprehensive security report
     */
    public function generate_security_report() {
        $results = $this->run_all_tests();
        
        $report = array(
            'timestamp' => current_time('mysql'),
            'overall_status' => 'PASS',
            'tests_run' => count($results),
            'tests_passed' => 0,
            'tests_failed' => 0,
            'details' => $results,
            'recommendations' => array()
        );
        
        foreach ($results as $test_name => $result) {
            if ($result['status'] === 'PASS') {
                $report['tests_passed']++;
            } else {
                $report['tests_failed']++;
                $report['overall_status'] = 'FAIL';
            }
        }
        
        // Add security recommendations
        if ($report['overall_status'] === 'PASS') {
            $report['recommendations'][] = 'All XSS tests passed. Continue monitoring for new vulnerabilities.';
            $report['recommendations'][] = 'Consider implementing Content Security Policy (CSP) headers.';
            $report['recommendations'][] = 'Regular security audits are recommended.';
        } else {
            $report['recommendations'][] = 'URGENT: Fix failing XSS tests before production deployment.';
            $report['recommendations'][] = 'Review and strengthen input validation and output escaping.';
            $report['recommendations'][] = 'Consider additional security measures like WAF protection.';
        }
        
        return $report;
    }
    
    /**
     * Format security report for display
     */
    public function format_report_for_display($report) {
        $output = "\n=== SLIMWP XSS SECURITY TEST REPORT ===\n";
        $output .= "Generated: " . $report['timestamp'] . "\n";
        $output .= "Overall Status: " . $report['overall_status'] . "\n";
        $output .= "Tests Passed: " . $report['tests_passed'] . "/" . $report['tests_run'] . "\n\n";
        
        foreach ($report['details'] as $test_name => $result) {
            $status_icon = $result['status'] === 'PASS' ? '✓' : '✗';
            $output .= sprintf("%s %s: %s\n", $status_icon, strtoupper($test_name), $result['message']);
            
            if ($result['status'] === 'FAIL' && isset($result['malicious_input'])) {
                $output .= "   Malicious Input: " . $result['malicious_input'] . "\n";
            }
        }
        
        $output .= "\nRECOMMENDATIONS:\n";
        foreach ($report['recommendations'] as $recommendation) {
            $output .= "• " . $recommendation . "\n";
        }
        
        return $output;
    }
}

/**
 * Run XSS security tests if accessed directly (for testing purposes)
 */
if (defined('WP_CLI') || (isset($_GET['run_xss_tests']) && current_user_can('manage_options'))) {
    $security_tests = new SlimWP_XSS_Security_Tests();
    $report = $security_tests->generate_security_report();
    
    if (defined('WP_CLI')) {
        WP_CLI::line($security_tests->format_report_for_display($report));
    } else {
        header('Content-Type: text/plain');
        echo $security_tests->format_report_for_display($report);
        exit;
    }
}