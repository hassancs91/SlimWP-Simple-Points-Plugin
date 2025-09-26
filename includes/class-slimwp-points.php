<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_Points {
    
    private static $instance = null;
    private $table_name;
    private $hooks_option = 'slimwp_points_hooks';
    
    // Component instances
    private $hooks;
    private $shortcodes;
    private $ajax;
    private $user_profile;
    private $admin;
    private $settings;
    private $woocommerce;
    private $stripe;
    private $stripe_packages;
    
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
        // Note: SlimWP_Database uses static methods, no instantiation needed
        $this->hooks = new SlimWP_Hooks($this);
        $this->shortcodes = new SlimWP_Shortcodes($this);
        $this->ajax = new SlimWP_Ajax($this);
        $this->user_profile = new SlimWP_User_Profile($this);
        
        if (is_admin()) {
            $this->admin = new SlimWP_Admin($this);
            $this->settings = new SlimWP_Settings($this);
            $this->documentation = new SlimWP_Documentation();
            $this->stripe_packages = new SlimWP_Stripe_Packages($this);
        }
        
        // Initialize WooCommerce integration
        $this->woocommerce = new SlimWP_WooCommerce($this);
        
        // Initialize Stripe integration
        $this->stripe = new SlimWP_Stripe($this);
        
        // Initialize everything
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Ensure tables exist
        SlimWP_Database::create_tables();
    }
    
    // Keep all the core balance methods here
    public function get_balance($user_id) {
        $free_balance = $this->get_free_balance($user_id);
        $permanent_balance = $this->get_permanent_balance($user_id);
        return $free_balance + $permanent_balance;
    }
    
    public function get_free_balance($user_id) {
        global $wpdb;
        
        // Validate user ID
        if (!$user_id || $user_id <= 0) {
            return 0; // Return 0 for invalid users instead of error
        }
        
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
        
        // Validate user ID
        if (!$user_id || $user_id <= 0) {
            return 0; // Return 0 for invalid users instead of error
        }
        
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
        
        // Validate user ID
        if (!$user_id || $user_id <= 0 || !get_user_by('ID', $user_id)) {
            return new WP_Error('invalid_user', 'Invalid user ID provided.');
        }
        
        // Validate amount
        if (!is_numeric($amount)) {
            return new WP_Error('invalid_amount', 'Amount must be numeric.');
        }
        
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
                error_log('SlimWP Error (add_points): ' . $wpdb->last_error);
                return new WP_Error('db_error', 'Database error: ' . $wpdb->last_error);
            }
            
            // Update cached balances
            update_user_meta($user_id, 'slimwp_points_balance', $new_free);
            update_user_meta($user_id, 'slimwp_points_balance_permanent', $new_permanent);
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Trigger action for other plugins
            do_action('slimwp_points_balance_updated', $user_id, abs($amount), $new_free + $new_permanent, $description);
            
            // Trigger live update event
            $this->trigger_live_update($user_id, $balance_type);
            
            return $new_free + $new_permanent;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('transaction_error', $e->getMessage());
        }
    }
    
    public function subtract_points($user_id, $amount, $description = '', $type = 'manual') {
        global $wpdb;
        
        // Validate user ID
        if (!$user_id || $user_id <= 0 || !get_user_by('ID', $user_id)) {
            return new WP_Error('invalid_user', 'Invalid user ID provided.');
        }
        
        // Validate amount
        if (!is_numeric($amount)) {
            return new WP_Error('invalid_amount', 'Amount must be numeric.');
        }
        
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
                error_log('SlimWP Error (subtract_points): ' . $wpdb->last_error);
                return new WP_Error('db_error', 'Database error: ' . $wpdb->last_error);
            }
            
            // Update cached balances
            update_user_meta($user_id, 'slimwp_points_balance', $new_free);
            update_user_meta($user_id, 'slimwp_points_balance_permanent', $new_permanent);
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Trigger action for other plugins
            do_action('slimwp_points_balance_updated', $user_id, -$amount, $new_free + $new_permanent, $description);
            
            // Trigger live update event
            $this->trigger_live_update($user_id, $balance_type);
            
            return $new_free + $new_permanent;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('transaction_error', $e->getMessage());
        }
    }
    
    public function set_balance($user_id, $new_balance, $description = '', $type = 'balance_reset', $balance_type = 'free') {
                global $wpdb;
        
        // Validate user ID
        if (!$user_id || $user_id <= 0 || !get_user_by('ID', $user_id)) {
            return new WP_Error('invalid_user', 'Invalid user ID provided.');
        }
        
        // Validate amount
        if (!is_numeric($new_balance) || $new_balance < 0) {
            return new WP_Error('invalid_amount', 'Balance must be a non-negative number.');
        }
        
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
                error_log('SlimWP Error: ' . $wpdb->last_error);
                return new WP_Error('db_error', 'Database error: ' . $wpdb->last_error);
            }
            
            // Update cached balances
            update_user_meta($user_id, 'slimwp_points_balance', $new_free);
            update_user_meta($user_id, 'slimwp_points_balance_permanent', $new_permanent);
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Trigger action for other plugins
            do_action('slimwp_points_balance_updated', $user_id, $amount, $new_free + $new_permanent, $description);
            
            // Trigger live update event
            $this->trigger_live_update($user_id, $balance_type);
            
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
    
    /**
     * Trigger live update events for real-time shortcode updates
     */
    private function trigger_live_update($user_id, $balance_type = 'all') {
        // Validate and sanitize inputs
        $user_id = intval($user_id);
        $balance_type = $this->sanitize_balance_type($balance_type);
        
        if ($user_id <= 0) {
            error_log('SlimWP Security: Invalid user ID in trigger_live_update: ' . $user_id);
            return;
        }
        
        // Store the update information to be sent to frontend via admin-ajax
        add_action('wp_footer', function() use ($user_id, $balance_type) {
            if (!wp_script_is('slimwp-live-points', 'enqueued')) {
                return;
            }
            
            // Use wp_localize_script for safe data passing instead of inline script
            wp_localize_script('slimwp-live-points', 'slimwp_live_update_data', array(
                'userId' => $user_id,
                'balanceType' => $balance_type,
                'timestamp' => current_time('timestamp'),
                'nonce' => wp_create_nonce('slimwp_live_update_' . $user_id)
            ));
            
            // Trigger the update via a safe inline script with CSP nonce
            $nonce = wp_create_nonce('slimwp_inline_script');
            ?>
            <script<?php echo ' nonce="' . esc_attr($nonce) . '"'; ?>>
            jQuery(document).ready(function($) {
                if (typeof slimwp_live_update_data !== 'undefined') {
                    $(document).trigger('slimwp:balance:updated', slimwp_live_update_data);
                }
            });
            </script>
            <?php
        });
        
        // Also trigger WordPress action for other plugins
        do_action('slimwp_live_update_triggered', $user_id, $balance_type);
    }
    
    /**
     * Sanitize balance type to prevent XSS
     */
    private function sanitize_balance_type($balance_type) {
        $allowed_types = array('all', 'free', 'permanent', 'mixed', 'total');
        
        // Sanitize and validate
        $balance_type = sanitize_text_field($balance_type);
        
        if (!in_array($balance_type, $allowed_types)) {
            error_log('SlimWP Security: Invalid balance type attempted: ' . $balance_type);
            return 'all'; // Safe default
        }
        
        return $balance_type;
    }
}
