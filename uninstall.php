<?php
/**
 * Uninstall SlimWP Simple Points
 * 
 * This file handles complete removal of all plugin data when uninstalled.
 * Removes database tables, options, user meta, post meta, and transients.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Remove database tables
$tables_to_drop = array(
    $wpdb->prefix . 'slimwp_user_points_transactions',
    $wpdb->prefix . 'slimwp_stripe_packages', 
    $wpdb->prefix . 'slimwp_stripe_purchases'
);

foreach ($tables_to_drop as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Remove WordPress options
$options_to_delete = array(
    'slimwp_points_hooks',
    'slimwp_woocommerce_settings',
    'slimwp_stripe_settings'
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// Remove user meta
$user_meta_keys = array(
    'slimwp_points_balance',
    'slimwp_points_balance_permanent', 
    'slimwp_last_login_bonus_date',
    'slimwp_last_daily_reset_date',
    'slimwp_last_monthly_reset_date',
    'slimwp_referral_used'
);

foreach ($user_meta_keys as $meta_key) {
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
        $meta_key
    ));
}

// Remove post meta (WooCommerce integration)
$post_meta_keys = array(
    '_slimwp_points_enabled',
    '_slimwp_points_amount', 
    '_slimwp_points_description',
    '_slimwp_points_awarded',
    '_slimwp_points_total',
    '_slimwp_points_products'
);

foreach ($post_meta_keys as $meta_key) {
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
        $meta_key
    ));
}

// Remove transients (rate limiting, caching, etc.)
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_slimwp_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_slimwp_%'");

// Clear any remaining scheduled events
wp_clear_scheduled_hook('slimwp_daily_reset');
wp_clear_scheduled_hook('slimwp_monthly_reset');
