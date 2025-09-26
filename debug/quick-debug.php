<?php
/**
 * Quick debug script to identify the Stripe 500 error
 * Delete this file after debugging
 */

// Load WordPress
if (file_exists('../../../wp-config.php')) {
    require_once('../../../wp-config.php');
    require_once('../../../wp-load.php');
} else {
    die('WordPress not found');
}

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo "<h1>SlimWP Stripe Quick Debug</h1>";

// 1. Check if tables exist
global $wpdb;
$packages_table = $wpdb->prefix . 'slimwp_stripe_packages';
$purchases_table = $wpdb->prefix . 'slimwp_stripe_purchases';

$packages_exists = $wpdb->get_var("SHOW TABLES LIKE '{$packages_table}'") === $packages_table;
$purchases_exists = $wpdb->get_var("SHOW TABLES LIKE '{$purchases_table}'") === $purchases_table;

echo "<h2>Database Tables</h2>";
echo "Packages table: " . ($packages_exists ? "✓ EXISTS" : "✗ MISSING") . "<br>";
echo "Purchases table: " . ($purchases_exists ? "✓ EXISTS" : "✗ MISSING") . "<br>";

if ($packages_exists) {
    $package_count = $wpdb->get_var("SELECT COUNT(*) FROM {$packages_table}");
    $active_count = $wpdb->get_var("SELECT COUNT(*) FROM {$packages_table} WHERE status = 'active'");
    echo "Total packages: {$package_count}<br>";
    echo "Active packages: {$active_count}<br>";
    
    if ($active_count > 0) {
        $packages = $wpdb->get_results("SELECT * FROM {$packages_table} WHERE status = 'active' LIMIT 3");
        echo "<h3>Sample Active Packages:</h3>";
        foreach ($packages as $pkg) {
            echo "ID: {$pkg->id}, Name: {$pkg->name}, Points: {$pkg->points}, Price: {$pkg->price} {$pkg->currency}<br>";
        }
    }
}

// 2. Check Stripe settings
$stripe_settings = get_option('slimwp_stripe_settings', array());
echo "<h2>Stripe Settings</h2>";
echo "Settings exist: " . (empty($stripe_settings) ? "✗ NO" : "✓ YES") . "<br>";

if (!empty($stripe_settings)) {
    $mode = $stripe_settings['mode'] ?? 'test';
    $enabled = !empty($stripe_settings['enabled']) && ($stripe_settings['enabled'] === '1' || $stripe_settings['enabled'] === true);
    
    echo "Mode: {$mode}<br>";
    echo "Enabled: " . ($enabled ? "✓ YES" : "✗ NO") . "<br>";
    
    $pub_key = $stripe_settings[$mode . '_publishable_key'] ?? '';
    $secret_key = $stripe_settings[$mode . '_secret_key'] ?? '';
    
    echo "Publishable key: " . (!empty($pub_key) ? "✓ SET" : "✗ NOT SET") . "<br>";
    echo "Secret key: " . (!empty($secret_key) ? "✓ SET" : "✗ NOT SET") . "<br>";
}

// 3. Test Stripe library loading
echo "<h2>Stripe Library</h2>";
try {
    require_once(dirname(__FILE__) . '/includes/stripe-php/init.php');
    $library_loaded = class_exists('\Stripe\Stripe');
    echo "Library loaded: " . ($library_loaded ? "✓ YES" : "✗ NO") . "<br>";
    
    if ($library_loaded) {
        echo "ApiVersion class: " . (class_exists('\Stripe\Util\ApiVersion') ? "✓ YES" : "✗ NO") . "<br>";
        echo "Checkout Session class: " . (class_exists('\Stripe\Checkout\Session') ? "✓ YES" : "✗ NO") . "<br>";
    }
} catch (Exception $e) {
    echo "Library error: ✗ " . $e->getMessage() . "<br>";
} catch (Error $e) {
    echo "Library fatal error: ✗ " . $e->getMessage() . "<br>";
}

// 4. Simulate the AJAX call conditions
echo "<h2>AJAX Call Simulation</h2>";

if (!empty($stripe_settings) && $packages_exists && $active_count > 0) {
    echo "Simulating create_checkout_session conditions...<br>";
    
    // Get first active package
    $test_package = $wpdb->get_row("SELECT * FROM {$packages_table} WHERE status = 'active' LIMIT 1");
    
    if ($test_package) {
        echo "Test package: ID {$test_package->id}, {$test_package->name}<br>";
        
        // Check if current user is logged in
        $user_id = get_current_user_id();
        echo "User logged in: " . ($user_id > 0 ? "✓ YES (ID: {$user_id})" : "✗ NO") . "<br>";
        
        if ($user_id > 0) {
            // Test the conditions that might cause 500 error
            $mode = $stripe_settings['mode'] ?? 'test';
            $publishable_key = $stripe_settings[$mode . '_publishable_key'] ?? '';
            $secret_key = $stripe_settings[$mode . '_secret_key'] ?? '';
            
            if (empty($publishable_key) || empty($secret_key)) {
                echo "❌ ISSUE FOUND: Missing Stripe API keys for {$mode} mode<br>";
            } else {
                echo "✓ API keys are set<br>";
                
                // Test Stripe API call
                try {
                    if (class_exists('\Stripe\Stripe')) {
                        \Stripe\Stripe::setApiKey($secret_key);
                        
                        // Configure SSL for local development
                        $is_local = (
                            defined('WP_DEBUG') && WP_DEBUG ||
                            strpos(home_url(), 'localhost') !== false ||
                            strpos(home_url(), '127.0.0.1') !== false ||
                            strpos(home_url(), '.local') !== false ||
                            strpos(home_url(), '.test') !== false
                        );
                        
                        if ($is_local) {
                            \Stripe\Stripe::setVerifySslCerts(false);
                            echo "ℹ️ SSL verification disabled for local development<br>";
                        }
                        
                        echo "✓ Stripe API key set successfully<br>";
                        
                        // Try to create a test session (this might reveal the actual error)
                        echo "<br><strong>Attempting to create test checkout session...</strong><br>";
                        
                        $session_data = [
                            'payment_method_types' => ['card'],
                            'line_items' => [[
                                'price_data' => [
                                    'currency' => strtolower($test_package->currency),
                                    'product_data' => [
                                        'name' => $test_package->name,
                                        'description' => sprintf('%s points', number_format($test_package->points)),
                                    ],
                                    'unit_amount' => intval($test_package->price * 100),
                                ],
                                'quantity' => 1,
                            ]],
                            'mode' => 'payment',
                            'success_url' => home_url() . '?success=1',
                            'cancel_url' => home_url() . '?cancel=1',
                            'metadata' => [
                                'user_id' => $user_id,
                                'package_id' => $test_package->id,
                                'points' => $test_package->points,
                            ]
                        ];
                        
                        $session = \Stripe\Checkout\Session::create($session_data);
                        echo "✓ SUCCESS: Test session created with ID: " . $session->id . "<br>";
                        echo "The Stripe integration should be working. The issue might be elsewhere.<br>";
                        
                    } else {
                        echo "❌ ISSUE: Stripe class not available<br>";
                    }
                } catch (Exception $e) {
                    echo "❌ STRIPE ERROR: " . $e->getMessage() . "<br>";
                    echo "This is likely the cause of your 500 error.<br>";
                }
            }
        }
    }
} else {
    echo "❌ Prerequisites not met for AJAX call<br>";
}

echo "<hr>";
echo "<hr>";
echo "<h2>🔄 Refresh Test</h2>";
echo "<button onclick='location.reload()'>Refresh This Page</button>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li>If you see any ❌ issues above, fix those first</li>";
echo "<li>If everything shows ✓, try the Buy Now button on your site</li>";
echo "<li>Make sure you're logged in when testing the Buy Now button</li>";
echo "<li>Delete this debug file when done for security</li>";
echo "</ul>";
?>