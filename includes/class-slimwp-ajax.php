<?php
if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_Ajax {
    
    private $points_system;
    
    public function __construct($points_system) {
        $this->points_system = $points_system;
        
        // AJAX handlers
        add_action('wp_ajax_slimwp_update_user_points', array($this, 'ajax_update_user_points'));
        
        // Debug testing AJAX handlers (always available for admins)
        add_action('wp_ajax_slimwp_debug_get_balance', array($this, 'ajax_debug_get_balance'));
        add_action('wp_ajax_slimwp_debug_add_points', array($this, 'ajax_debug_add_points'));
        add_action('wp_ajax_slimwp_debug_subtract_points', array($this, 'ajax_debug_subtract_points'));
        add_action('wp_ajax_slimwp_debug_set_balance', array($this, 'ajax_debug_set_balance'));
        add_action('wp_ajax_slimwp_debug_reset_user', array($this, 'ajax_debug_reset_user'));
        add_action('wp_ajax_slimwp_debug_test_api', array($this, 'ajax_debug_test_api'));
        add_action('wp_ajax_slimwp_debug_performance', array($this, 'ajax_debug_performance'));
        
        // Live points AJAX handlers (for logged-in users)
        add_action('wp_ajax_slimwp_get_live_balance', array($this, 'ajax_get_live_balance'));
        add_action('wp_ajax_nopriv_slimwp_get_live_balance', array($this, 'ajax_get_live_balance'));
    }
    
    /**
     * Rate limiting for AJAX requests
     */
    private function check_rate_limit($action, $limit = 10, $window = 300) {
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        $rate_key = 'slimwp_rate_' . $action . '_' . $user_id . '_' . md5($ip_address);
        
        $current_requests = get_transient($rate_key) ?: 0;
        
        if ($current_requests >= $limit) {
            error_log('SlimWP Security: Rate limit exceeded for user ' . $user_id . ' from IP ' . $ip_address . ' on action ' . $action);
            wp_die('Rate limit exceeded. Please wait before trying again.', 'Rate Limit', array('response' => 429));
        }
        
        set_transient($rate_key, $current_requests + 1, $window);
    }
    
    /**
     * Get client IP address securely
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = sanitize_text_field($_SERVER[$key]);
                
                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback to REMOTE_ADDR even if it's private/reserved
        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
    
    public function ajax_update_user_points() {
        // Rate limiting check
        $this->check_rate_limit('update_user_points', 20, 300); // 20 requests per 5 minutes
        
        // Verify nonce
        if (!check_ajax_referer('slimwp_nonce', 'nonce', false)) {
            error_log('SlimWP Security: Invalid nonce in ajax_update_user_points from user ' . get_current_user_id());
            wp_send_json_error('Security check failed.');
        }
        
        // Check capabilities
        if (!current_user_can('edit_users')) {
            error_log('SlimWP Security: Unauthorized ajax_update_user_points attempt from user ' . get_current_user_id());
            wp_send_json_error('Unauthorized access.');
        }
        
        // Validate and sanitize input
        $user_id = intval($_POST['user_id']);
        $amount = floatval($_POST['amount']);
        $description = sanitize_text_field($_POST['description']);
        $operation = sanitize_text_field($_POST['operation']);
        $balance_type = isset($_POST['balance_type']) ? sanitize_text_field($_POST['balance_type']) : 'free';
        
        // Additional input validation
        if ($user_id <= 0 || !user_can($user_id, 'read')) {
            wp_send_json_error('Invalid user ID.');
        }
        
        if ($amount < 0 || $amount > 999999999) {
            wp_send_json_error('Invalid amount. Must be between 0 and 999,999,999.');
        }
        
        if (empty($description) || strlen($description) > 255) {
            wp_send_json_error('Description is required and must be less than 255 characters.');
        }
        
        $allowed_operations = array('add', 'subtract');
        if (!in_array($operation, $allowed_operations)) {
            error_log('SlimWP Security: Invalid operation attempted in AJAX: ' . $operation);
            wp_send_json_error('Invalid operation.');
        }
        
        $allowed_balance_types = array('free', 'permanent');
        if (!in_array($balance_type, $allowed_balance_types)) {
            error_log('SlimWP Security: Invalid balance type attempted in AJAX: ' . $balance_type);
            wp_send_json_error('Invalid balance type.');
        }
        
        // Log the operation attempt
        error_log('SlimWP AJAX: Points update - User: ' . $user_id . ', Operation: ' . $operation . ', Amount: ' . $amount . ', Balance Type: ' . $balance_type);
        
        // Perform the operation
        if ($operation === 'add') {
            $result = $this->points_system->add_points($user_id, $amount, $description, 'manual', $balance_type);
        } else {
            $result = $this->points_system->subtract_points($user_id, $amount, $description);
        }
        
        if (is_wp_error($result)) {
            error_log('SlimWP AJAX Error: ' . $result->get_error_message());
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'new_balance' => $result,
                'free_balance' => $this->points_system->get_free_balance($user_id),
                'permanent_balance' => $this->points_system->get_permanent_balance($user_id)
            ));
        }
    }
    
    /**
     * Debug AJAX Handlers - Only available in debug mode
     */
    
    private function verify_debug_access() {
        // Verify nonce
        if (!check_ajax_referer('slimwp_debug_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed.');
        }
        
        // Check admin capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
    }
    
    public function ajax_debug_get_balance() {
        $this->verify_debug_access();
        
        $user_id = intval($_POST['user_id']);
        if ($user_id <= 0) {
            wp_send_json_error('Invalid user ID.');
        }
        
        $free_balance = $this->points_system->get_free_balance($user_id);
        $permanent_balance = $this->points_system->get_permanent_balance($user_id);
        $total_balance = $free_balance + $permanent_balance;
        
        wp_send_json_success(array(
            'free' => $free_balance,
            'permanent' => $permanent_balance,
            'total' => $total_balance
        ));
    }
    
    public function ajax_debug_add_points() {
        $this->verify_debug_access();
        
        $user_id = intval($_POST['user_id']);
        $amount = floatval($_POST['amount']);
        $balance_type = sanitize_text_field($_POST['balance_type'] ?? 'free');
        $description = sanitize_text_field($_POST['description'] ?? 'Debug test');
        
        if ($user_id <= 0) {
            wp_send_json_error('Invalid user ID.');
        }
        
        if ($amount <= 0) {
            wp_send_json_error('Amount must be positive.');
        }
        
        $result = $this->points_system->add_points($user_id, $amount, $description, 'debug_test', $balance_type);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'balances' => array(
                'free' => $this->points_system->get_free_balance($user_id),
                'permanent' => $this->points_system->get_permanent_balance($user_id),
                'total' => $this->points_system->get_balance($user_id)
            ),
            'new_total' => $result
        ));
    }
    
    public function ajax_debug_subtract_points() {
        $this->verify_debug_access();
        
        $user_id = intval($_POST['user_id']);
        $amount = floatval($_POST['amount']);
        $description = sanitize_text_field($_POST['description'] ?? 'Debug test');
        
        if ($user_id <= 0) {
            wp_send_json_error('Invalid user ID.');
        }
        
        if ($amount <= 0) {
            wp_send_json_error('Amount must be positive.');
        }
        
        // Check balance before subtraction
        $current_balance = $this->points_system->get_balance($user_id);
        
        $result = $this->points_system->subtract_points($user_id, $amount, $description, 'debug_test');
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        $message = '';
        if ($current_balance < $amount) {
            $message = 'Warning: Attempted to subtract more than available balance.';
        }
        
        wp_send_json_success(array(
            'balances' => array(
                'free' => $this->points_system->get_free_balance($user_id),
                'permanent' => $this->points_system->get_permanent_balance($user_id),
                'total' => $this->points_system->get_balance($user_id)
            ),
            'new_total' => $result,
            'message' => $message
        ));
    }
    
    public function ajax_debug_set_balance() {
        $this->verify_debug_access();
        
        $user_id = intval($_POST['user_id']);
        $amount = floatval($_POST['amount']);
        $balance_type = sanitize_text_field($_POST['balance_type'] ?? 'free');
        
        if ($user_id <= 0) {
            wp_send_json_error('Invalid user ID.');
        }
        
        if ($amount < 0) {
            wp_send_json_error('Amount cannot be negative.');
        }
        
        $result = $this->points_system->set_balance($user_id, $amount, 'Debug balance set', 'debug_test', $balance_type);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'balances' => array(
                'free' => $this->points_system->get_free_balance($user_id),
                'permanent' => $this->points_system->get_permanent_balance($user_id),
                'total' => $this->points_system->get_balance($user_id)
            ),
            'new_total' => $result
        ));
    }
    
    public function ajax_debug_reset_user() {
        $this->verify_debug_access();
        
        $user_id = intval($_POST['user_id']);
        
        if ($user_id <= 0) {
            wp_send_json_error('Invalid user ID.');
        }
        
        // Set free balance to 100, permanent to 50
        $free_result = $this->points_system->set_balance($user_id, 100, 'Debug user reset - Free balance', 'debug_reset', 'free');
        $permanent_result = $this->points_system->set_balance($user_id, 50, 'Debug user reset - Permanent balance', 'debug_reset', 'permanent');
        
        if (is_wp_error($free_result) || is_wp_error($permanent_result)) {
            $error_msg = 'Failed to reset user: ';
            if (is_wp_error($free_result)) {
                $error_msg .= $free_result->get_error_message() . ' ';
            }
            if (is_wp_error($permanent_result)) {
                $error_msg .= $permanent_result->get_error_message();
            }
            wp_send_json_error($error_msg);
        }
        
        wp_send_json_success(array(
            'free' => 100,
            'permanent' => 50,
            'total' => 150
        ));
    }
    
    public function ajax_debug_test_api() {
        $this->verify_debug_access();
        
        $test_type = sanitize_text_field($_POST['test_type'] ?? '');
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if ($user_id <= 0) {
            wp_send_json_error('Invalid user ID.');
        }
        
        $results = array();
        
        switch ($test_type) {
            case 'global_functions':
                $results = $this->test_global_functions($user_id);
                break;
            case 'class_methods':
                $results = $this->test_class_methods($user_id);
                break;
            case 'error_handling':
                $results = $this->test_error_handling($user_id);
                break;
            default:
                wp_send_json_error('Invalid test type.');
        }
        
        wp_send_json_success($results);
    }
    
    public function ajax_debug_performance() {
        $this->verify_debug_access();
        
        $test_type = sanitize_text_field($_POST['test_type'] ?? '');
        $iterations = intval($_POST['iterations'] ?? 100);
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if ($user_id <= 0) {
            wp_send_json_error('Invalid user ID.');
        }
        
        if ($iterations < 1 || $iterations > 1000) {
            wp_send_json_error('Iterations must be between 1 and 1000.');
        }
        
        $results = array();
        
        switch ($test_type) {
            case 'add':
                $results = $this->performance_test_add($user_id, $iterations);
                break;
            case 'subtract':
                $results = $this->performance_test_subtract($user_id, $iterations);
                break;
            case 'read':
                $results = $this->performance_test_read($user_id, $iterations);
                break;
            default:
                wp_send_json_error('Invalid test type.');
        }
        
        wp_send_json_success($results);
    }
    
    private function test_global_functions($user_id) {
        $results = array();
        $start_time = microtime(true);
        
        try {
            // Test slimwp() function
            $instance = slimwp();
            $results['slimwp()'] = $instance ? 'PASS' : 'FAIL';
            
            // Test slimwp_get_user_points()
            $total_points = slimwp_get_user_points($user_id);
            $results['slimwp_get_user_points()'] = is_numeric($total_points) ? 'PASS' : 'FAIL';
            
            // Test slimwp_get_user_free_points()
            $free_points = slimwp_get_user_free_points($user_id);
            $results['slimwp_get_user_free_points()'] = is_numeric($free_points) ? 'PASS' : 'FAIL';
            
            // Test slimwp_get_user_permanent_points()
            $permanent_points = slimwp_get_user_permanent_points($user_id);
            $results['slimwp_get_user_permanent_points()'] = is_numeric($permanent_points) ? 'PASS' : 'FAIL';
            
            // Test slimwp_add_user_points()
            $add_result = slimwp_add_user_points($user_id, 1, 'Global function test', 'free');
            $results['slimwp_add_user_points()'] = (!is_wp_error($add_result) && is_numeric($add_result)) ? 'PASS' : 'FAIL';
            
            // Test slimwp_subtract_user_points()
            $subtract_result = slimwp_subtract_user_points($user_id, 1, 'Global function test');
            $results['slimwp_subtract_user_points()'] = (!is_wp_error($subtract_result) && is_numeric($subtract_result)) ? 'PASS' : 'FAIL';
            
            // Test slimwp_set_user_balance()
            $original_free = slimwp_get_user_free_points($user_id);
            $set_result = slimwp_set_user_balance($user_id, $original_free, 'Global function test', 'free');
            $results['slimwp_set_user_balance()'] = (!is_wp_error($set_result) && is_numeric($set_result)) ? 'PASS' : 'FAIL';
            
        } catch (Exception $e) {
            $results['exception'] = $e->getMessage();
        }
        
        $end_time = microtime(true);
        $results['execution_time'] = round(($end_time - $start_time) * 1000, 2) . 'ms';
        
        return $results;
    }
    
    private function test_class_methods($user_id) {
        $results = array();
        $start_time = microtime(true);
        
        try {
            $instance = slimwp();
            
            // Test get_balance()
            $balance = $instance->get_balance($user_id);
            $results['get_balance()'] = is_numeric($balance) ? 'PASS' : 'FAIL';
            
            // Test get_free_balance()
            $free_balance = $instance->get_free_balance($user_id);
            $results['get_free_balance()'] = is_numeric($free_balance) ? 'PASS' : 'FAIL';
            
            // Test get_permanent_balance()
            $permanent_balance = $instance->get_permanent_balance($user_id);
            $results['get_permanent_balance()'] = is_numeric($permanent_balance) ? 'PASS' : 'FAIL';
            
            // Test add_points()
            $add_result = $instance->add_points($user_id, 1, 'Class method test', 'api_test', 'free');
            $results['add_points()'] = (!is_wp_error($add_result) && is_numeric($add_result)) ? 'PASS' : 'FAIL';
            
            // Test subtract_points()
            $subtract_result = $instance->subtract_points($user_id, 1, 'Class method test', 'api_test');
            $results['subtract_points()'] = (!is_wp_error($subtract_result) && is_numeric($subtract_result)) ? 'PASS' : 'FAIL';
            
            // Test set_balance()
            $original_free = $instance->get_free_balance($user_id);
            $set_result = $instance->set_balance($user_id, $original_free, 'Class method test', 'api_test', 'free');
            $results['set_balance()'] = (!is_wp_error($set_result) && is_numeric($set_result)) ? 'PASS' : 'FAIL';
            
        } catch (Exception $e) {
            $results['exception'] = $e->getMessage();
        }
        
        $end_time = microtime(true);
        $results['execution_time'] = round(($end_time - $start_time) * 1000, 2) . 'ms';
        
        return $results;
    }
    
    private function test_error_handling($user_id) {
        $results = array();
        $start_time = microtime(true);
        
        try {
            $instance = slimwp();
            
            // Test invalid user ID
            $invalid_result = $instance->add_points(0, 10, 'Test', 'test', 'free');
            $results['invalid_user_id'] = is_wp_error($invalid_result) ? 'PASS' : 'FAIL';
            
            // Test negative amount (should be converted to positive)
            $negative_result = $instance->add_points($user_id, -5, 'Negative test', 'test', 'free');
            $results['negative_amount_handling'] = (!is_wp_error($negative_result)) ? 'PASS' : 'FAIL';
            
            // Test insufficient balance
            $current_balance = $instance->get_balance($user_id);
            $insufficient_result = $instance->subtract_points($user_id, $current_balance + 1000, 'Insufficient test', 'test');
            $results['insufficient_balance'] = is_wp_error($insufficient_result) ? 'PASS' : 'FAIL';
            
            // Test invalid balance type
            $invalid_balance_result = $instance->add_points($user_id, 1, 'Invalid balance type', 'test', 'invalid');
            $results['invalid_balance_type'] = (!is_wp_error($invalid_balance_result)) ? 'PASS' : 'FAIL'; // Should handle gracefully
            
        } catch (Exception $e) {
            $results['exception'] = $e->getMessage();
        }
        
        $end_time = microtime(true);
        $results['execution_time'] = round(($end_time - $start_time) * 1000, 2) . 'ms';
        
        return $results;
    }
    
    private function performance_test_add($user_id, $iterations) {
        $times = array();
        $total_start = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $result = $this->points_system->add_points($user_id, 1, "Performance test #{$i}", 'performance_test', 'free');
            $end = microtime(true);
            
            if (!is_wp_error($result)) {
                $times[] = ($end - $start) * 1000; // Convert to milliseconds
            }
        }
        
        $total_end = microtime(true);
        
        return array(
            'operation' => 'add_points',
            'iterations' => $iterations,
            'successful' => count($times),
            'total_time' => round(($total_end - $total_start) * 1000, 2),
            'average_time' => count($times) > 0 ? round(array_sum($times) / count($times), 2) : 0,
            'min_time' => count($times) > 0 ? round(min($times), 2) : 0,
            'max_time' => count($times) > 0 ? round(max($times), 2) : 0,
            'operations_per_second' => count($times) > 0 ? round(count($times) / (($total_end - $total_start)), 2) : 0
        );
    }
    
    private function performance_test_subtract($user_id, $iterations) {
        // First, add enough points to subtract
        $this->points_system->add_points($user_id, $iterations + 100, 'Performance test setup', 'performance_test', 'free');
        
        $times = array();
        $total_start = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $result = $this->points_system->subtract_points($user_id, 1, "Performance test #{$i}", 'performance_test');
            $end = microtime(true);
            
            if (!is_wp_error($result)) {
                $times[] = ($end - $start) * 1000;
            }
        }
        
        $total_end = microtime(true);
        
        return array(
            'operation' => 'subtract_points',
            'iterations' => $iterations,
            'successful' => count($times),
            'total_time' => round(($total_end - $total_start) * 1000, 2),
            'average_time' => count($times) > 0 ? round(array_sum($times) / count($times), 2) : 0,
            'min_time' => count($times) > 0 ? round(min($times), 2) : 0,
            'max_time' => count($times) > 0 ? round(max($times), 2) : 0,
            'operations_per_second' => count($times) > 0 ? round(count($times) / (($total_end - $total_start)), 2) : 0
        );
    }
    
    private function performance_test_read($user_id, $iterations) {
        $times = array();
        $total_start = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $balance = $this->points_system->get_balance($user_id);
            $free = $this->points_system->get_free_balance($user_id);
            $permanent = $this->points_system->get_permanent_balance($user_id);
            $end = microtime(true);
            
            if (is_numeric($balance) && is_numeric($free) && is_numeric($permanent)) {
                $times[] = ($end - $start) * 1000;
            }
        }
        
        $total_end = microtime(true);
        
        return array(
            'operation' => 'get_balance (all)',
            'iterations' => $iterations,
            'successful' => count($times),
            'total_time' => round(($total_end - $total_start) * 1000, 2),
            'average_time' => count($times) > 0 ? round(array_sum($times) / count($times), 2) : 0,
            'min_time' => count($times) > 0 ? round(min($times), 2) : 0,
            'max_time' => count($times) > 0 ? round(max($times), 2) : 0,
            'operations_per_second' => count($times) > 0 ? round(count($times) / (($total_end - $total_start)), 2) : 0
        );
    }
    
    /**
     * AJAX handler for live balance requests
     * Used by the live points shortcode to get updated balances
     */
    public function ajax_get_live_balance() {
        // Rate limiting for live balance requests
        $this->check_rate_limit('get_live_balance', 60, 300); // 60 requests per 5 minutes
        
        // Verify nonce
        if (!check_ajax_referer('slimwp_live_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed.');
        }
        
        // Get and validate parameters
        $user_id = intval($_POST['user_id'] ?? 0);
        $type = sanitize_text_field($_POST['type'] ?? 'total');
        
        // Validate user ID
        if ($user_id <= 0) {
            wp_send_json_error('Invalid user ID.');
        }
        
        // Check if current user can view this user's points
        $current_user_id = get_current_user_id();
        if ($current_user_id != $user_id && !current_user_can('edit_users')) {
            // Users can only view their own points unless they're admins
            wp_send_json_error('Permission denied.');
        }
        
        // Validate balance type
        $allowed_types = array('total', 'free', 'permanent');
        if (!in_array($type, $allowed_types)) {
            wp_send_json_error('Invalid balance type.');
        }
        
        try {
            // Get the requested balance
            switch ($type) {
                case 'free':
                    $balance = $this->points_system->get_free_balance($user_id);
                    break;
                case 'permanent':
                    $balance = $this->points_system->get_permanent_balance($user_id);
                    break;
                default: // total
                    $balance = $this->points_system->get_balance($user_id);
            }
            
            // Return the balance
            wp_send_json_success(array(
                'balance' => floatval($balance),
                'user_id' => $user_id,
                'type' => $type,
                'timestamp' => current_time('timestamp')
            ));
            
        } catch (Exception $e) {
            error_log('SlimWP Live Balance Error: ' . $e->getMessage());
            wp_send_json_error('Failed to retrieve balance.');
        }
    }
}
