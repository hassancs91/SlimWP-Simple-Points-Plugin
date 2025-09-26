<?php
/**
 * SlimWP Stripe Setup Fix Script
 * 
 * This script manually creates database tables and default packages.
 * Run this once to fix setup issues, then delete the file.
 */

// Load WordPress
require_once('wp-config.php');
require_once('wp-load.php');

// Security check - only allow admins
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator to use this setup tool.');
}

// Load the plugin files
require_once(ABSPATH . 'wp-content/plugins/SlimWp-Simple-Points/includes/class-slimwp-stripe-database.php');

?>
<!DOCTYPE html>
<html>
<head>
    <title>SlimWP Stripe Setup Fix</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .result { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background-color: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        .button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; display: inline-block; }
        .button:hover { background: #005a87; }
    </style>
</head>
<body>
    <h1>SlimWP Stripe Setup Fix</h1>
    
    <?php
    if (isset($_GET['action']) && $_GET['action'] === 'fix') {
        echo '<div class="result info"><h2>Running Setup Fix...</h2></div>';
        
        try {
            // Create tables and default packages
            SlimWP_Stripe_Database::create_tables();
            
            // Check if tables were created
            global $wpdb;
            $packages_table = $wpdb->prefix . 'slimwp_stripe_packages';
            $purchases_table = $wpdb->prefix . 'slimwp_stripe_purchases';
            
            $packages_exists = $wpdb->get_var("SHOW TABLES LIKE '{$packages_table}'") === $packages_table;
            $purchases_exists = $wpdb->get_var("SHOW TABLES LIKE '{$purchases_table}'") === $purchases_table;
            
            if ($packages_exists && $purchases_exists) {
                echo '<div class="result success">';
                echo '<h3>✅ Success!</h3>';
                echo '<p>Database tables created successfully.</p>';
                
                // Check packages
                $package_count = $wpdb->get_var("SELECT COUNT(*) FROM {$packages_table}");
                echo '<p>Total packages in database: ' . $package_count . '</p>';
                
                if ($package_count > 0) {
                    $packages = $wpdb->get_results("SELECT * FROM {$packages_table} ORDER BY price ASC");
                    echo '<h4>Available Packages:</h4><ul>';
                    foreach ($packages as $package) {
                        echo '<li>' . esc_html($package->name) . ' - ' . esc_html($package->points) . ' points for ' . esc_html($package->currency) . ' ' . esc_html($package->price) . ' (Status: ' . esc_html($package->status) . ')</li>';
                    }
                    echo '</ul>';
                }
                
                echo '</div>';
            } else {
                echo '<div class="result error">';
                echo '<h3>❌ Error</h3>';
                echo '<p>Failed to create database tables.</p>';
                echo '<p>Packages table exists: ' . ($packages_exists ? 'Yes' : 'No') . '</p>';
                echo '<p>Purchases table exists: ' . ($purchases_exists ? 'Yes' : 'No') . '</p>';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="result error">';
            echo '<h3>❌ Error</h3>';
            echo '<p>Exception occurred: ' . esc_html($e->getMessage()) . '</p>';
            echo '</div>';
        }
        
        echo '<div class="result info">';
        echo '<h3>Next Steps:</h3>';
        echo '<ol>';
        echo '<li>Go to your WordPress admin dashboard</li>';
        echo '<li>Navigate to the SlimWP Stripe settings</li>';
        echo '<li>Configure your Stripe API keys</li>';
        echo '<li>Enable Stripe integration</li>';
        echo '<li>Test the purchase flow</li>';
        echo '<li><strong>Delete this file for security</strong></li>';
        echo '</ol>';
        echo '</div>';
        
    } else {
        // Show current status
        global $wpdb;
        $packages_table = $wpdb->prefix . 'slimwp_stripe_packages';
        $purchases_table = $wpdb->prefix . 'slimwp_stripe_purchases';
        
        $packages_exists = $wpdb->get_var("SHOW TABLES LIKE '{$packages_table}'") === $packages_table;
        $purchases_exists = $wpdb->get_var("SHOW TABLES LIKE '{$purchases_table}'") === $purchases_table;
        
        echo '<div class="result info">';
        echo '<h2>Current Status</h2>';
        echo '<p><strong>Packages Table:</strong> ' . ($packages_exists ? 'Exists' : 'Missing') . '</p>';
        echo '<p><strong>Purchases Table:</strong> ' . ($purchases_exists ? 'Exists' : 'Missing') . '</p>';
        
        if ($packages_exists) {
            $package_count = $wpdb->get_var("SELECT COUNT(*) FROM {$packages_table}");
            echo '<p><strong>Total Packages:</strong> ' . $package_count . '</p>';
        }
        echo '</div>';
        
        if (!$packages_exists || !$purchases_exists) {
            echo '<div class="result warning">';
            echo '<h2>Setup Required</h2>';
            echo '<p>Some database tables are missing. Click the button below to create them and set up default packages.</p>';
            echo '<a href="?action=fix" class="button">Fix Setup Issues</a>';
            echo '</div>';
        } else {
            echo '<div class="result success">';
            echo '<h2>Setup Complete</h2>';
            echo '<p>Database tables exist. You can still run the fix to ensure default packages are created.</p>';
            echo '<a href="?action=fix" class="button">Run Setup Fix</a>';
            echo '</div>';
        }
    }
    ?>
    
    <div class="result warning">
        <h2>⚠️ Security Notice</h2>
        <p><strong>Important:</strong> Delete this file after running the setup fix.</p>
        <p>This file should not be left on a production server for security reasons.</p>
    </div>
    
</body>
</html>
