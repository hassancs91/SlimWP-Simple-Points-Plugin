<?php
if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_Hooks {
    
    private $points_system;
    private $hooks_option;
    
    public function __construct($points_system) {
        $this->points_system = $points_system;
        $this->hooks_option = $points_system->get_hooks_option();
        
        // Core hooks
        add_action('user_register', array($this, 'points_on_register'));
        add_action('wp_login', array($this, 'points_on_login'), 10, 2);
    }
    
    public function points_on_register($user_id) {
        $hooks = get_option($this->hooks_option, array());
        if (!empty($hooks['register'])) {
            $points = isset($hooks['register_points']) ? intval($hooks['register_points']) : 100;
            $balance_type = isset($hooks['register_balance_type']) ? $hooks['register_balance_type'] : 'permanent';
            $this->points_system->add_points($user_id, $points, 'Welcome bonus', 'registration', $balance_type);
            do_action('slimwp_points_after_registration', $user_id);
        }
    }
    
    public function points_on_login($user_login, $user) {
        $hooks = get_option($this->hooks_option, array());
        
        // Daily Login Bonus (adds to balance)
        if (!empty($hooks['daily_login'])) {
            // Check if user already got points today
            $last_login_bonus = get_user_meta($user->ID, 'slimwp_last_login_bonus_date', true);
            $today = date('Y-m-d');
            
            if ($last_login_bonus !== $today) {
                $points = isset($hooks['daily_login_points']) ? intval($hooks['daily_login_points']) : 10;
                $balance_type = isset($hooks['daily_login_balance_type']) ? $hooks['daily_login_balance_type'] : 'free';
                $this->points_system->add_points($user->ID, $points, 'Daily login bonus', 'daily_login', $balance_type);
                update_user_meta($user->ID, 'slimwp_last_login_bonus_date', $today);
            }
        }
        
        // Daily Balance Reset (sets FREE balance to fixed amount)
        if (!empty($hooks['daily_reset'])) {
            $last_daily_reset = get_user_meta($user->ID, 'slimwp_last_daily_reset_date', true);
            $today = date('Y-m-d');
            
            if ($last_daily_reset !== $today) {
                $reset_amount = isset($hooks['daily_reset_points']) ? intval($hooks['daily_reset_points']) : 100;
                $this->points_system->set_balance($user->ID, $reset_amount, 'Daily balance reset', 'daily_reset', 'free');
                update_user_meta($user->ID, 'slimwp_last_daily_reset_date', $today);
            }
        }
        
        // Monthly Balance Reset (sets FREE balance to fixed amount)
        if (!empty($hooks['monthly_reset'])) {
            $last_monthly_reset = get_user_meta($user->ID, 'slimwp_last_monthly_reset_date', true);
            $current_month = date('Y-m');
            
            if ($last_monthly_reset !== $current_month) {
                $reset_amount = isset($hooks['monthly_reset_points']) ? intval($hooks['monthly_reset_points']) : 1000;
                $this->points_system->set_balance($user->ID, $reset_amount, 'Monthly balance reset', 'monthly_reset', 'free');
                update_user_meta($user->ID, 'slimwp_last_monthly_reset_date', $current_month);
            }
        }
    }
}

// Example hook for developers to extend functionality
add_action('slimwp_points_after_registration', function($user_id) {
    // Example: Add extra permanent points based on referral
    if (isset($_COOKIE['referral_code'])) {
        $referral_code = sanitize_text_field($_COOKIE['referral_code']);
        
        // Validate referral code format (alphanumeric, 6-20 characters)
        if (preg_match('/^[a-zA-Z0-9]{6,20}$/', $referral_code)) {
            // Check if this user hasn't already used a referral bonus
            $existing_referral = get_user_meta($user_id, 'slimwp_referral_used', true);
            
            if (empty($existing_referral)) {
                // Rate limiting: Check if this referral code hasn't been used too many times recently
                $referral_usage_key = 'slimwp_referral_usage_' . $referral_code;
                $recent_usage = get_transient($referral_usage_key);
                
                if ($recent_usage === false || intval($recent_usage) < 10) { // Max 10 uses per hour
                    // Award referral bonus
                    $result = slimwp_add_user_points($user_id, 50, 'Referral bonus: ' . $referral_code, 'permanent');
                    
                    if (!is_wp_error($result)) {
                        // Mark user as having used referral
                        update_user_meta($user_id, 'slimwp_referral_used', $referral_code);
                        
                        // Update usage counter
                        $new_count = intval($recent_usage) + 1;
                        set_transient($referral_usage_key, $new_count, HOUR_IN_SECONDS);
                        
                        // Clear the referral cookie for security
                        if (!headers_sent()) {
                            setcookie('referral_code', '', time() - 3600, '/', '', is_ssl(), true);
                        }
                        
                        error_log("SlimWP: Referral bonus awarded to user {$user_id} with code {$referral_code}");
                    }
                } else {
                    error_log("SlimWP: Referral code {$referral_code} usage limit exceeded");
                }
            }
        } else {
            error_log("SlimWP: Invalid referral code format: {$referral_code}");
        }
    }
}, 10, 1);
