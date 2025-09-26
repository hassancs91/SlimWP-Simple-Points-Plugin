<?php
if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_Stripe_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create packages table
        $packages_table = $wpdb->prefix . 'slimwp_stripe_packages';
        $packages_sql = "CREATE TABLE IF NOT EXISTS {$packages_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            points bigint(20) NOT NULL,
            price decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Create purchases table
        $purchases_table = $wpdb->prefix . 'slimwp_stripe_purchases';
        $purchases_sql = "CREATE TABLE IF NOT EXISTS {$purchases_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            package_id bigint(20) NOT NULL,
            stripe_session_id varchar(255) NOT NULL,
            stripe_payment_intent_id varchar(255),
            amount_paid decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL,
            points_awarded bigint(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY package_id (package_id),
            KEY stripe_session_id (stripe_session_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($packages_sql);
        dbDelta($purchases_sql);
        
        // Create default packages after tables are created
        self::create_default_packages();
    }
    
    private static function create_default_packages() {
        global $wpdb;
        
        $packages_table = $wpdb->prefix . 'slimwp_stripe_packages';
        
        // Validate table name for security
        if (!preg_match('/^[a-zA-Z0-9_]+$/', str_replace($wpdb->prefix, '', $packages_table))) {
            error_log('SlimWP Security: Invalid table name in create_default_packages: ' . $packages_table);
            return false;
        }
        
        // Check if any packages exist
        $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM {$packages_table}");
        
        if ($existing_count == 0) {
            // Create some default packages
            $default_packages = array(
                array(
                    'name' => 'Starter Pack',
                    'description' => 'Perfect for getting started',
                    'points' => 1000,
                    'price' => 9.99,
                    'currency' => 'USD'
                ),
                array(
                    'name' => 'Popular Pack',
                    'description' => 'Most popular choice',
                    'points' => 2500,
                    'price' => 19.99,
                    'currency' => 'USD'
                ),
                array(
                    'name' => 'Premium Pack',
                    'description' => 'Best value for money',
                    'points' => 5000,
                    'price' => 34.99,
                    'currency' => 'USD'
                )
            );
            
            foreach ($default_packages as $package) {
                $wpdb->insert($packages_table, $package);
            }
        }
    }
    
    public static function get_packages($status = 'active') {
        global $wpdb;
        
        $packages_table = $wpdb->prefix . 'slimwp_stripe_packages';
        
        if ($status === 'all') {
            return $wpdb->get_results("SELECT * FROM {$packages_table} ORDER BY price ASC");
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$packages_table} WHERE status = %s ORDER BY price ASC",
            $status
        ));
    }
    
    public static function get_package($id) {
        global $wpdb;
        
        $packages_table = $wpdb->prefix . 'slimwp_stripe_packages';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$packages_table} WHERE id = %d",
            $id
        ));
    }
    
    public static function create_package($data) {
        global $wpdb;
        
        $packages_table = $wpdb->prefix . 'slimwp_stripe_packages';
        
        $result = $wpdb->insert(
            $packages_table,
            array(
                'name' => sanitize_text_field($data['name']),
                'description' => sanitize_textarea_field($data['description']),
                'points' => intval($data['points']),
                'price' => floatval($data['price']),
                'currency' => sanitize_text_field($data['currency']),
                'status' => sanitize_text_field($data['status'])
            ),
            array('%s', '%s', '%d', '%f', '%s', '%s')
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    public static function update_package($id, $data) {
        global $wpdb;
        
        $packages_table = $wpdb->prefix . 'slimwp_stripe_packages';
        
        return $wpdb->update(
            $packages_table,
            array(
                'name' => sanitize_text_field($data['name']),
                'description' => sanitize_textarea_field($data['description']),
                'points' => intval($data['points']),
                'price' => floatval($data['price']),
                'currency' => sanitize_text_field($data['currency']),
                'status' => sanitize_text_field($data['status'])
            ),
            array('id' => $id),
            array('%s', '%s', '%d', '%f', '%s', '%s'),
            array('%d')
        );
    }
    
    public static function delete_package($id) {
        global $wpdb;
        
        $packages_table = $wpdb->prefix . 'slimwp_stripe_packages';
        
        return $wpdb->delete($packages_table, array('id' => $id), array('%d'));
    }
    
    public static function create_purchase($data) {
        global $wpdb;
        
        $purchases_table = $wpdb->prefix . 'slimwp_stripe_purchases';
        
        $result = $wpdb->insert(
            $purchases_table,
            array(
                'user_id' => intval($data['user_id']),
                'package_id' => intval($data['package_id']),
                'stripe_session_id' => sanitize_text_field($data['stripe_session_id']),
                'stripe_payment_intent_id' => sanitize_text_field($data['stripe_payment_intent_id']),
                'amount_paid' => floatval($data['amount_paid']),
                'currency' => sanitize_text_field($data['currency']),
                'points_awarded' => intval($data['points_awarded']),
                'status' => sanitize_text_field($data['status'])
            ),
            array('%d', '%d', '%s', '%s', '%f', '%s', '%d', '%s')
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    public static function update_purchase_status($session_id, $status, $payment_intent_id = null) {
        global $wpdb;
        
        $purchases_table = $wpdb->prefix . 'slimwp_stripe_purchases';
        
        $update_data = array(
            'status' => sanitize_text_field($status)
        );
        $update_format = array('%s');
        
        if ($status === 'completed') {
            $update_data['completed_at'] = current_time('mysql');
            $update_format[] = '%s';
        }
        
        if ($payment_intent_id) {
            $update_data['stripe_payment_intent_id'] = sanitize_text_field($payment_intent_id);
            $update_format[] = '%s';
        }
        
        return $wpdb->update(
            $purchases_table,
            $update_data,
            array('stripe_session_id' => $session_id),
            $update_format,
            array('%s')
        );
    }
    
    public static function get_purchase_by_session($session_id) {
        global $wpdb;
        
        $purchases_table = $wpdb->prefix . 'slimwp_stripe_purchases';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$purchases_table} WHERE stripe_session_id = %s",
            $session_id
        ));
    }
    
    public static function get_user_purchases($user_id, $limit = 10) {
        global $wpdb;
        
        $purchases_table = $wpdb->prefix . 'slimwp_stripe_purchases';
        $packages_table = $wpdb->prefix . 'slimwp_stripe_packages';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, pkg.name as package_name 
             FROM {$purchases_table} p 
             LEFT JOIN {$packages_table} pkg ON p.package_id = pkg.id 
             WHERE p.user_id = %d 
             ORDER BY p.created_at DESC 
             LIMIT %d",
            $user_id,
            $limit
        ));
    }
}
