<?php
if (!defined('ABSPATH')) {
    exit;
}

// Global helper functions
function slimwp() {
    return SlimWP_Points::get_instance();
}

function slimwp_get_user_points($user_id) {
    return slimwp()->get_balance($user_id);
}

function slimwp_add_user_points($user_id, $amount, $description = '', $balance_type = 'free') {
    return slimwp()->add_points($user_id, $amount, $description, 'manual', $balance_type);
}

function slimwp_subtract_user_points($user_id, $amount, $description = '') {
    return slimwp()->subtract_points($user_id, $amount, $description);
}

function slimwp_set_user_balance($user_id, $amount, $description = '', $balance_type = 'free') {
    return slimwp()->set_balance($user_id, $amount, $description, 'balance_reset', $balance_type);
}

function slimwp_get_user_free_points($user_id) {
    return slimwp()->get_free_balance($user_id);
}

function slimwp_get_user_permanent_points($user_id) {
    return slimwp()->get_permanent_balance($user_id);
}

// Backward compatibility functions (deprecated - use slimwp_ prefixed functions)
function wp_points() {
    return slimwp();
}

function get_user_points($user_id) {
    return slimwp_get_user_points($user_id);
}

function add_user_points($user_id, $amount, $description = '', $balance_type = 'free') {
    return slimwp_add_user_points($user_id, $amount, $description, $balance_type);
}

function subtract_user_points($user_id, $amount, $description = '') {
    return slimwp_subtract_user_points($user_id, $amount, $description);
}

function set_user_balance($user_id, $amount, $description = '', $balance_type = 'free') {
    return slimwp_set_user_balance($user_id, $amount, $description, $balance_type);
}

function get_user_free_points($user_id) {
    return slimwp_get_user_free_points($user_id);
}

function get_user_permanent_points($user_id) {
    return slimwp_get_user_permanent_points($user_id);
}
