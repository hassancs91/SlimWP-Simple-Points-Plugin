<?php
/**
 * SlimWP Stripe Debug Script
 * 
 * This file helps diagnose Stripe integration issues.
 * Place this file in your WordPress root directory and access it via browser.
 * Remove this file after debugging for security.
 */

// Load WordPress
require_once('wp-config.php');
require_once('wp-load.php');

// Security check - only allow admins
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator to use this debug tool.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>SlimWP Stripe Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background-color: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .test-button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
        .test-button:hover { background: #005a87; }
    </style>
</head>
<body>
    <h1>SlimWP Stripe Debug Tool</h1>
    
    <?php
    // Get Stripe settings
    $stripe_settings = get_option('slimwp_stripe_settings', array());
    $mode = $stripe_settings['mode'] ?? 'test';
    
    echo '<div class="debug-section info">';
    echo '<h2>Current Configuration</h2>';
    echo '<p><strong>Mode:</strong> ' . esc_html($mode) . '</p>';
    echo '<p><strong>Enabled:</strong> ' . ((!empty($stripe_settings['enabled']) && ($stripe_settings['enabled'] === '1' || $stripe_settings['enabled'] === true)) ? 'Yes' : 'No') . '</p>';
    echo '</div>';
    
    // Check API Keys
    $publishable_key = $stripe_settings[$mode . '_publishable_key'] ?? '';
    $secret_key = $stripe_settings[$mode . '_secret_key'] ?? '';
    $webhook_secret = $stripe_settings[$mode . '_webhook_secret'] ?? '';
    
    echo '<div class="debug-section ' . (!empty($publishable_key) && !empty($secret_key) ? 'success' : 'error') . '">';
    echo '<h2>API Keys Status</h2>';
    echo '<p><strong>Publishable Key:</strong> ' . (!empty($publishable_key) ? 'Set (' . substr($publishable_key, 0, 12) . '...)' : 'Not set') . '</p>';
    echo '<p><strong>Secret Key:</strong> ' . (!empty($secret_key) ? 'Set (' . substr($secret_key, 0, 12) . '...)' : 'Not set') . '</p>';
    echo '<p><strong>Webhook Secret:</strong> ' . (!empty($webhook_secret) ? 'Set' : 'Not set') . '</p>';
    echo '</div>';
    
    // Check Stripe Library
    $stripe_library_loaded = false;
    $stripe_error = '';
    try {
        require_once(ABSPATH . 'wp-content/plugins/SlimWp-Simple-Points/includes/stripe-php/init.php');
        $stripe_library_loaded = class_exists('\Stripe\Stripe');
    } catch (Exception $e) {
        $stripe_error = $e->getMessage();
    }
    
    echo '<div class="debug-section ' . ($stripe_library_loaded ? 'success' : 'error') . '">';
    echo '<h2>Stripe Library</h2>';
    echo '<p><strong>Status:</strong> ' . ($stripe_library_loaded ? 'Loaded successfully' : 'Failed to load') . '</p>';
    if (!empty($stripe_error)) {
        echo '<p><strong>Error:</strong> ' . esc_html($stripe_error) . '</p>';
    }
    echo '</div>';
    
    // Check Database Tables
    global $wpdb;
    $packages_table = $wpdb->prefix . 'slimwp_stripe_packages';
    $purchases_table = $wpdb->prefix . 'slimwp_stripe_purchases';
    
    $packages_exists = $wpdb->get_var("SHOW TABLES LIKE '{$packages_table}'") === $packages_table;
    $purchases_exists = $wpdb->get_var("SHOW TABLES LIKE '{$purchases_table}'") === $purchases_table;
    
    echo '<div class="debug-section ' . ($packages_exists && $purchases_exists ? 'success' : 'error') . '">';
    echo '<h2>Database Tables</h2>';
    echo '<p><strong>Packages Table:</strong> ' . ($packages_exists ? 'Exists' : 'Missing') . '</p>';
    echo '<p><strong>Purchases Table:</strong> ' . ($purchases_exists ? 'Exists' : 'Missing') . '</p>';
    echo '</div>';
    
    // Check Packages
    if ($packages_exists) {
        $total_packages = $wpdb->get_var("SELECT COUNT(*) FROM {$packages_table}");
        $active_packages = $wpdb->get_var("SELECT COUNT(*) FROM {$packages_table} WHERE status = 'active'");
        
        echo '<div class="debug-section ' . ($active_packages > 0 ? 'success' : 'warning') . '">';
        echo '<h2>Packages</h2>';
        echo '<p><strong>Total Packages:</strong> ' . $total_packages . '</p>';
        echo '<p><strong>Active Packages:</strong> ' . $active_packages . '</p>';
        
        if ($active_packages > 0) {
            $packages = $wpdb->get_results("SELECT * FROM {$packages_table} WHERE status = 'active' ORDER BY price ASC");
            echo '<h3>Active Packages:</h3>';
            echo '<ul>';
            foreach ($packages as $package) {
                echo '<li>' . esc_html($package->name) . ' - ' . esc_html($package->points) . ' points for ' . esc_html($package->currency) . ' ' . esc_html($package->price) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }
    
    // Test Stripe Connection
    if (!empty($secret_key) && $stripe_library_loaded) {
        echo '<div class="debug-section info">';
        echo '<h2>Stripe Connection Test</h2>';
        echo '<button class="test-button" onclick="testStripeConnection()">Test Stripe API Connection</button>';
        echo '<div id="stripe-test-result" style="margin-top: 10px;"></div>';
        echo '</div>';
    }
    
    // Show recent error logs
    $log_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
        $stripe_logs = array();
        $lines = explode("\n", $log_content);
        
        foreach (array_reverse($lines) as $line) {
            if (stripos($line, 'SlimWP Stripe') !== false) {
                $stripe_logs[] = $line;
                if (count($stripe_logs) >= 10) break; // Show last 10 entries
            }
        }
        
        if (!empty($stripe_logs)) {
            echo '<div class="debug-section info">';
            echo '<h2>Recent Stripe Log Entries</h2>';
            echo '<pre>' . esc_html(implode("\n", $stripe_logs)) . '</pre>';
            echo '</div>';
        }
    }
    
    // Show all settings for debugging
    echo '<div class="debug-section info">';
    echo '<h2>All Stripe Settings</h2>';
    echo '<pre>' . esc_html(print_r($stripe_settings, true)) . '</pre>';
    echo '</div>';
    ?>
    
    <script>
    function testStripeConnection() {
        const resultDiv = document.getElementById('stripe-test-result');
        resultDiv.innerHTML = 'Testing...';
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=slimwp_debug_stripe'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<div class="success"><h3>Debug Information:</h3><pre>' + JSON.stringify(data.data, null, 2) + '</pre></div>';
                resultDiv.innerHTML = html;
            } else {
                resultDiv.innerHTML = '<div class="error">Error: ' + data.data + '</div>';
            }
        })
        .catch(error => {
            resultDiv.innerHTML = '<div class="error">Network error: ' + error.message + '</div>';
        });
    }
    </script>
    
    <div class="debug-section warning">
        <h2>⚠️ Security Notice</h2>
        <p><strong>Important:</strong> Delete this debug file after troubleshooting for security reasons.</p>
        <p>This file exposes sensitive configuration information and should not be left on a production server.</p>
    </div>
    
</body>
</html>
