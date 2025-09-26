<?php
/**
 * Plugin Name: SlimWP Simple Points
 * Plugin URI: https://github.com/hassancs91/SlimWP-Simple-Points
 * Description: A lightweight dual-balance points system for WordPress with free and permanent points tracking.
 * Version: 1.0.7
 * Author: Hasan Aboul Hasan
 * Author URI: https://learnwithhasan.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: SlimWp-Simple-Points
 * Domain Path: /languages
 * GitHub URI: hassancs91/SlimWP-Simple-Points
 */


// Include the update checker
// Include the update checker
require_once 'plugin-update-checker-master/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5p5\PucFactory;

// Setup the update checker for GitHub public repository
$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/hassancs91/SlimWP-Simple-Points',
    __FILE__,
    'slimwp-simple-points'
);

// Set the branch to main
$myUpdateChecker->setBranch('main');




// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SLIMWP_VERSION', '1.0.7');
define('SLIMWP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SLIMWP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SLIMWP_PLUGIN_FILE', __FILE__);

// Define minimum requirements
define('SLIMWP_MIN_PHP_VERSION', '7.4');
define('SLIMWP_MIN_WP_VERSION', '5.0');

/**
 * Check if PHP version meets minimum requirements
 */
function slimwp_check_php_version() {
    if (version_compare(PHP_VERSION, SLIMWP_MIN_PHP_VERSION, '<')) {
        add_action('admin_notices', 'slimwp_php_version_notice');
        return false;
    }
    return true;
}

/**
 * Display PHP version requirement notice
 */
function slimwp_php_version_notice() {
    $message = sprintf(
        /* translators: %1$s: minimum required PHP version, %2$s: current PHP version */
        __('SlimWP Simple Points requires PHP version %1$s or higher. You are running PHP %2$s. Please upgrade PHP to use this plugin.', 'SlimWp-Simple-Points'),
        SLIMWP_MIN_PHP_VERSION,
        PHP_VERSION
    );
    printf('<div class="notice notice-error"><p><strong>%s</strong></p></div>', esc_html($message));
}

/**
 * Check if WordPress version meets minimum requirements
 */
function slimwp_check_wp_version() {
    global $wp_version;
    if (version_compare($wp_version, SLIMWP_MIN_WP_VERSION, '<')) {
        add_action('admin_notices', 'slimwp_wp_version_notice');
        return false;
    }
    return true;
}

/**
 * Display WordPress version requirement notice
 */
function slimwp_wp_version_notice() {
    global $wp_version;
    $message = sprintf(
        /* translators: %1$s: minimum required WordPress version, %2$s: current WordPress version */
        __('SlimWP Simple Points requires WordPress version %1$s or higher. You are running WordPress %2$s. Please upgrade WordPress to use this plugin.', 'SlimWp-Simple-Points'),
        SLIMWP_MIN_WP_VERSION,
        $wp_version
    );
    printf('<div class="notice notice-error"><p><strong>%s</strong></p></div>', esc_html($message));
}

/**
 * Check system requirements
 */
function slimwp_check_requirements() {
    $php_ok = slimwp_check_php_version();
    $wp_ok = slimwp_check_wp_version();
    
    if (!$php_ok || !$wp_ok) {
        // Deactivate plugin if requirements not met
        deactivate_plugins(plugin_basename(__FILE__));
        return false;
    }
    
    return true;
}

/**
 * Check if WooCommerce is active and meets minimum requirements
 */
function slimwp_check_woocommerce_dependency() {
    if (!class_exists('WooCommerce')) {
        return false;
    }
    
    // Check WooCommerce version if needed
    if (defined('WC_VERSION')) {
        return version_compare(WC_VERSION, '3.0', '>=');
    }
    
    return true;
}

/**
 * Display WooCommerce dependency notice
 */
function slimwp_woocommerce_dependency_notice() {
    $settings = get_option('slimwp_woocommerce_settings', array());
    
    // Only show notice if WooCommerce integration is enabled
    if (empty($settings['enabled'])) {
        return;
    }
    
    $message = '';
    
    if (!class_exists('WooCommerce')) {
        $message = sprintf(
            /* translators: %1$s: opening link tag, %2$s: closing link tag */
            __('SlimWP Simple Points: WooCommerce integration is enabled but WooCommerce plugin is not installed or activated. %1$sInstall WooCommerce%2$s to use this feature.', 'SlimWp-Simple-Points'),
            '<a href="' . admin_url('plugin-install.php?tab=plugin-information&plugin=woocommerce') . '" target="_blank">',
            '</a>'
        );
    } elseif (defined('WC_VERSION') && version_compare(WC_VERSION, '3.0', '<')) {
        $message = sprintf(
            /* translators: %s: current WooCommerce version */
            __('SlimWP Simple Points: WooCommerce integration requires WooCommerce version 3.0 or higher. You are running version %s. Please update WooCommerce.', 'SlimWp-Simple-Points'),
            WC_VERSION
        );
    }
    
    if (!empty($message)) {
        printf(
            '<div class="notice notice-warning is-dismissible"><p><strong>%s</strong></p></div>',
            wp_kses($message, array(
                'a' => array('href' => array(), 'target' => array())
            ))
        );
    }
}

/**
 * Check if WooCommerce integration can be enabled
 */
function slimwp_can_enable_woocommerce_integration() {
    return slimwp_check_woocommerce_dependency();
}

/**
 * Get WooCommerce integration status message
 */
function slimwp_get_woocommerce_status_message() {
    if (!class_exists('WooCommerce')) {
        return array(
            'status' => 'missing',
            'message' => __('WooCommerce plugin is not installed or activated.', 'SlimWp-Simple-Points'),
            'action' => __('Install WooCommerce to enable this integration.', 'SlimWp-Simple-Points')
        );
    }
    
    if (defined('WC_VERSION') && version_compare(WC_VERSION, '3.0', '<')) {
        return array(
            'status' => 'outdated',
            /* translators: %s: current WooCommerce version */
            'message' => sprintf(__('WooCommerce version %s is installed.', 'SlimWp-Simple-Points'), WC_VERSION),
            'action' => __('WooCommerce 3.0+ is required. Please update WooCommerce.', 'SlimWp-Simple-Points')
        );
    }
    
    return array(
        'status' => 'active',
        /* translators: %s: current WooCommerce version */
        'message' => sprintf(__('WooCommerce version %s is active and compatible.', 'SlimWp-Simple-Points'), defined('WC_VERSION') ? WC_VERSION : __('Unknown', 'SlimWp-Simple-Points')),
        'action' => __('WooCommerce integration is ready to use.', 'SlimWp-Simple-Points')
    );
}

// Include required files
require_once SLIMWP_PLUGIN_DIR . 'includes/class-slimwp-security-utils.php';
require_once SLIMWP_PLUGIN_DIR . 'includes/class-slimwp-database.php';
require_once SLIMWP_PLUGIN_DIR . 'includes/class-slimwp-hooks.php';
require_once SLIMWP_PLUGIN_DIR . 'includes/class-slimwp-shortcodes.php';
require_once SLIMWP_PLUGIN_DIR . 'includes/class-slimwp-ajax.php';
require_once SLIMWP_PLUGIN_DIR . 'includes/class-slimwp-user-profile.php';
require_once SLIMWP_PLUGIN_DIR . 'includes/class-slimwp-admin.php';
require_once SLIMWP_PLUGIN_DIR . 'includes/class-slimwp-settings.php';
require_once SLIMWP_PLUGIN_DIR . 'includes/class-slimwp-documentation.php';
require_once SLIMWP_PLUGIN_DIR . 'includes/class-slimwp-points.php';
require_once SLIMWP_PLUGIN_DIR . 'includes/class-slimwp-woocommerce.php';
require_once SLIMWP_PLUGIN_DIR . 'includes/class-slimwp-stripe-database.php';
require_once SLIMWP_PLUGIN_DIR . 'includes/class-slimwp-stripe-ssl-fix.php';
require_once SLIMWP_PLUGIN_DIR . 'includes/class-slimwp-stripe.php';
require_once SLIMWP_PLUGIN_DIR . 'includes/class-slimwp-stripe-packages.php';
require_once SLIMWP_PLUGIN_DIR . 'includes/functions.php';

// Initialize the plugin
function slimwp_init() {
    // Check system requirements before initialization
    if (!slimwp_check_requirements()) {
        return;
    }
    
    // Hook WooCommerce dependency notice
    add_action('admin_notices', 'slimwp_woocommerce_dependency_notice');
    
    return SlimWP_Points::get_instance();
}
add_action('plugins_loaded', 'slimwp_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Check system requirements before activation
    if (!slimwp_check_requirements()) {
        wp_die(
            sprintf(
                /* translators: %1$s: minimum required PHP version, %2$s: minimum required WordPress version */
                __('SlimWP Simple Points cannot be activated. Please ensure you have PHP %1$s+ and WordPress %2$s+.', 'SlimWp-Simple-Points'),
                SLIMWP_MIN_PHP_VERSION,
                SLIMWP_MIN_WP_VERSION
            ),
            __('Plugin Activation Error', 'SlimWp-Simple-Points'),
            array('back_link' => true)
        );
    }
    
    SlimWP_Database::create_tables();
    SlimWP_Stripe_Database::create_tables();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clear any scheduled events
    wp_clear_scheduled_hook('slimwp_daily_reset');
    wp_clear_scheduled_hook('slimwp_monthly_reset');
    
    // Clear transients
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_slimwp_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_slimwp_%'");
    
    // Flush rewrite rules
    flush_rewrite_rules();
});
