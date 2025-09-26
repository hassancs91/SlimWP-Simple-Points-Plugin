<?php
if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'slimwp_user_points_transactions';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            balance_after decimal(10,2) NOT NULL,
            permanent_balance_after decimal(10,2) DEFAULT 0,
            balance_type varchar(20) DEFAULT 'free',
            description text NOT NULL,
            transaction_type varchar(50) DEFAULT 'manual',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Add columns if they don't exist
        self::add_column_if_not_exists($table_name, 'permanent_balance_after', "ADD COLUMN permanent_balance_after decimal(10,2) DEFAULT 0 AFTER balance_after");
        self::add_column_if_not_exists($table_name, 'balance_type', "ADD COLUMN balance_type varchar(20) DEFAULT 'free' AFTER permanent_balance_after");
    }
    
    private static function add_column_if_not_exists($table_name, $column_name, $column_definition) {
        global $wpdb;
        
        // Validate table name to prevent SQL injection
        if (!preg_match('/^[a-zA-Z0-9_]+$/', str_replace($wpdb->prefix, '', $table_name))) {
            error_log('SlimWP Security: Invalid table name attempted: ' . $table_name);
            return false;
        }
        
        // Validate column name to prevent SQL injection
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column_name)) {
            error_log('SlimWP Security: Invalid column name attempted: ' . $column_name);
            return false;
        }
        
        // Whitelist allowed column definitions to prevent SQL injection
        $allowed_definitions = array(
            'permanent_balance_after' => "ADD COLUMN permanent_balance_after decimal(10,2) DEFAULT 0 AFTER balance_after",
            'balance_type' => "ADD COLUMN balance_type varchar(20) DEFAULT 'free' AFTER permanent_balance_after"
        );
        
        if (!isset($allowed_definitions[$column_name])) {
            error_log('SlimWP Security: Unauthorized column definition attempted: ' . $column_name);
            return false;
        }
        
        // Check if column exists (table name already validated above)
        $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table_name}`");
        
        if (!in_array($column_name, $columns)) {
            // Use the whitelisted definition
            $safe_definition = $allowed_definitions[$column_name];
            $result = $wpdb->query("ALTER TABLE `{$table_name}` {$safe_definition}");
            
            if ($result === false) {
                error_log('SlimWP Database Error: Failed to add column ' . $column_name . ' - ' . $wpdb->last_error);
                return false;
            }
            
            error_log('SlimWP Database: Successfully added column ' . $column_name . ' to ' . $table_name);
            return true;
        }
        
        return true; // Column already exists
    }
}
