<?php
/**
 * Simple WordPress loading test
 * Delete after testing
 */

echo "<h1>WordPress Loading Test</h1>";
echo "<p>Current directory: " . __DIR__ . "</p>";

// Find WordPress root directory
$wp_root = null;
$current_dir = __DIR__;

echo "<h2>Searching for WordPress...</h2>";
echo "<ul>";

// Go up directories to find wp-config.php
for ($i = 0; $i < 10; $i++) {
    $current_dir = dirname($current_dir);
    echo "<li>Checking: $current_dir</li>";
    
    if (file_exists($current_dir . '/wp-config.php')) {
        echo "<li><strong>Found wp-config.php in: $current_dir</strong></li>";
        $wp_root = $current_dir;
        break;
    }
    
    if (file_exists($current_dir . '/wp-load.php')) {
        echo "<li><strong>Found wp-load.php in: $current_dir</strong></li>";
        $wp_root = $current_dir;
        break;
    }
}

echo "</ul>";

if (!$wp_root) {
    die('<p style="color: red;">WordPress not found!</p>');
}

echo "<p style='color: green;'>WordPress root found: $wp_root</p>";

// Check what files exist
$files_to_check = ['wp-load.php', 'wp-config.php', 'wp-settings.php', 'wp-blog-header.php'];
echo "<h2>WordPress Files Check:</h2>";
echo "<ul>";
foreach ($files_to_check as $file) {
    $exists = file_exists($wp_root . '/' . $file);
    $color = $exists ? 'green' : 'red';
    $status = $exists ? 'EXISTS' : 'MISSING';
    echo "<li style='color: $color;'>$file: $status</li>";
}
echo "</ul>";

// Try to load WordPress
echo "<h2>Loading WordPress...</h2>";
try {
    if (file_exists($wp_root . '/wp-load.php')) {
        require_once($wp_root . '/wp-load.php');
        echo "<p style='color: green;'>wp-load.php loaded successfully!</p>";
    } else {
        echo "<p style='color: red;'>wp-load.php not found!</p>";
        die();
    }
    
    // Test WordPress functions
    if (function_exists('wp_get_current_user')) {
        echo "<p style='color: green;'>WordPress functions available!</p>";
    } else {
        echo "<p style='color: red;'>WordPress functions not available!</p>";
    }
    
    // Test admin functions
    if (function_exists('current_user_can')) {
        echo "<p style='color: green;'>Admin functions available!</p>";
        if (current_user_can('manage_options')) {
            echo "<p style='color: green;'>User has admin capabilities!</p>";
        } else {
            echo "<p style='color: orange;'>User does not have admin capabilities (might not be logged in)</p>";
        }
    } else {
        echo "<p style='color: red;'>Admin functions not available!</p>";
    }
    
    // Test SlimWP plugin
    if (class_exists('SlimWP_Points')) {
        echo "<p style='color: green;'>SlimWP_Points class found!</p>";
    } else {
        echo "<p style='color: orange;'>SlimWP_Points class not found (plugin might not be active)</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error loading WordPress: " . $e->getMessage() . "</p>";
} catch (Error $e) {
    echo "<p style='color: red;'>Fatal error loading WordPress: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>If everything above shows green, the debug page should work!</strong></p>";
echo "<p><a href='debug-points-testing.php'>Try the Points Testing Debug Page</a></p>";
?>