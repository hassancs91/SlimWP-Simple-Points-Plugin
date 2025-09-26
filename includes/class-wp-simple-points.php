<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Simple_Points {
    
    private static $instance = null;
    private $table_name;
    private $hooks_option = 'slimwp__points_hooks';
    
    // Component instances
    private $database;
    private $hooks;
    private $shortcodes;
    private $ajax;
    private $user_profile;
    private $admin;
    private $settings;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'slimwp_user_points_transactions';
        
        // Initialize components
        $this->database = new WP_Points_Database();
        $this->hooks = new WP_Points_Hooks($this);
        $this->shortcodes = new WP_Points_Shortcodes($this);
        $this->ajax = new WP_Points_Ajax($this);
        $this->user_profile = new WP_Points_User_Profile($this);
        
        if (is_admin()) {
            $this->admin = new WP_Points_Admin($this);
            $this->settings = new WP_Points_Settings($this);
        }
        
        // Initialize everything
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Ensure tables exist
        $this->database->create_tables();
    }
    
    // Keep all the core balance methods here
    public function get_balance($user_id) {
        $free_balance = $this->get_free_balance($user_id);
        $permanent_balance = $this->get_permanent_balance($user_id);
        return $free_balance + $permanent_balance;
    }
    
    public function get_free_balance($user_id) {
        global $wpdb;
        
        // Get from user meta (cached value)
        $balance = get_user_meta($user_id, 'slimwp_points_balance', true);
        
        if ($balance === '') {
            // If no cached value, calculate from transactions
            $balance = $wpdb->get_var($wpdb->prepare(
                "SELECT balance_after FROM {$this->table_name} 
                WHERE user_id = %d 
                ORDER BY id DESC 
                LIMIT 1",
                $user_id
            ));
            
            $balance = $balance !== null ? floatval($balance) : 0;
            update_user_meta($user_id, 'slimwp_points_balance', $balance);
        }
        
        return floatval($balance);
    }
    
    public function get_permanent_balance($user_id) {
        global $wpdb;
        
        // Get from user meta (cached value)
        $balance = get_user_meta($user_id, 'slimwp_points_balance_permanent', true);
        
        if ($balance === '') {
            // If no cached value, calculate from transactions
            $balance = $wpdb->get_var($wpdb->prepare(
                "SELECT permanent_balance_after FROM {$this->table_name} 
                WHERE user_id = %d 
                ORDER BY id DESC 
                LIMIT 1",
                $user_id
            ));
            
            $balance = $balance !== null ? floatval($balance) : 0;
            update_user_meta($user_id, 'slimwp_points_balance_permanent', $balance);
        }
        
        return floatval($balance);
    }
    
    public function add_points($user_id, $amount, $description = '', $type = 'manual', $balance_type = 'free') {
       global $wpdb;
        
        // Start transaction for atomicity
        $wpdb->query('START TRANSACTION');
        
        try {
            // Get current balances
            $current_free = $this->get_free_balance($user_id);
            $current_permanent = $this->get_permanent_balance($user_id);
            
            if ($balance_type === 'free') {
                $new_free = $current_free + abs($amount);
                $new_permanent = $current_permanent;
            } else {
                $new_free = $current_free;
                $new_permanent = $current_permanent + abs($amount);
            }
            
            // Insert transaction
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'user_id' => $user_id,
                    'amount' => abs($amount),
                    'balance_after' => $new_free,
                    'permanent_balance_after' => $new_permanent,
                    'balance_type' => $balance_type,
                    'description' => $description,
                    'transaction_type' => $type,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                error_log('WP Points Error (add_points): ' . $wpdb->last_error);
                return new WP_Error('db_error', 'Database error: ' . $wpdb->last_error);
            }
            
            // Update cached balances
            update_user_meta($user_id, 'slimwp_points_balance', $new_free);
            update_user_meta($user_id, 'slimwp_points_balance_permanent', $new_permanent);
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Trigger action for other plugins
            do_action('slimwp_points_balance_updated', $user_id, abs($amount), $new_free + $new_permanent, $description);
            
            return $new_free + $new_permanent;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('transaction_error', $e->getMessage());
        }
    }
    
    public function subtract_points($user_id, $amount, $description = '', $type = 'manual') {
        global $wpdb;
        
        $amount = abs($amount);
        
        // Start transaction for atomicity
        $wpdb->query('START TRANSACTION');
        
        try {
            // Get current balances
            $current_free = $this->get_free_balance($user_id);
            $current_permanent = $this->get_permanent_balance($user_id);
            $total_balance = $current_free + $current_permanent;
            
            // Check if user has enough points
            if ($total_balance < $amount) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('insufficient_balance', 'Insufficient balance');
            }
            
            // Calculate how to deduct
            if ($current_free >= $amount) {
                // Deduct all from free balance
                $new_free = $current_free - $amount;
                $new_permanent = $current_permanent;
                $balance_type = 'free';
            } else {
                // Deduct what we can from free, rest from permanent
                $deduct_from_permanent = $amount - $current_free;
                $new_free = 0;
                $new_permanent = $current_permanent - $deduct_from_permanent;
                $balance_type = 'mixed';
            }
            
            // Insert transaction
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'user_id' => $user_id,
                    'amount' => -$amount,
                    'balance_after' => $new_free,
                    'permanent_balance_after' => $new_permanent,
                    'balance_type' => $balance_type,
                    'description' => $description,
                    'transaction_type' => $type,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                error_log('WP Points Error (subtract_points): ' . $wpdb->last_error);
                return new WP_Error('db_error', 'Database error: ' . $wpdb->last_error);
            }
            
            // Update cached balances
            update_user_meta($user_id, 'slimwp_points_balance', $new_free);
            update_user_meta($user_id, 'slimwp_points_balance_permanent', $new_permanent);
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Trigger action for other plugins
            do_action('slimwp_points_balance_updated', $user_id, -$amount, $new_free + $new_permanent, $description);
            
            return $new_free + $new_permanent;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('transaction_error', $e->getMessage());
        }
    }
    
    public function set_balance($user_id, $new_balance, $description = '', $type = 'balance_reset', $balance_type = 'free') {
                global $wpdb;
        
        // Start transaction for atomicity
        $wpdb->query('START TRANSACTION');
        
        try {
            // Get current balances
            $current_free = $this->get_free_balance($user_id);
            $current_permanent = $this->get_permanent_balance($user_id);
            
            if ($balance_type === 'free') {
                $amount = $new_balance - $current_free;
                $new_free = $new_balance;
                $new_permanent = $current_permanent;
            } else {
                $amount = $new_balance - $current_permanent;
                $new_free = $current_free;
                $new_permanent = $new_balance;
            }
            
            // Insert transaction
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'user_id' => $user_id,
                    'amount' => $amount,
                    'balance_after' => $new_free,
                    'permanent_balance_after' => $new_permanent,
                    'balance_type' => $balance_type,
                    'description' => $description,
                    'transaction_type' => $type,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                error_log('WP Points Error: ' . $wpdb->last_error);
                return new WP_Error('db_error', 'Database error: ' . $wpdb->last_error);
            }
            
            // Update cached balances
            update_user_meta($user_id, 'slimwp_points_balance', $new_free);
            update_user_meta($user_id, 'slimwp_points_balance_permanent', $new_permanent);
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Trigger action for other plugins
            do_action('slimwp_points_balance_updated', $user_id, $amount, $new_free + $new_permanent, $description);
            
            return $new_free + $new_permanent;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('transaction_error', $e->getMessage());
        }
    }
    
    // Getters for components
    public function get_table_name() {
        return $this->table_name;
    }
    
    public function get_hooks_option() {
        return $this->hooks_option;
    }
}