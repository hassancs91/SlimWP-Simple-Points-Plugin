<?php
if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_PMPro_Database {

    /**
     * Create PMPro related tables
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table for membership level points configuration
        $table_name = $wpdb->prefix . 'slimwp_pmpro_level_points';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            membership_level_id bigint(20) NOT NULL,
            daily_points int(11) DEFAULT 0,
            weekly_points int(11) DEFAULT 0,
            monthly_points int(11) DEFAULT 0,
            yearly_points int(11) DEFAULT 0,
            balance_type varchar(20) DEFAULT 'free',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY membership_level_id (membership_level_id),
            KEY balance_type (balance_type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Add indexes for performance
        self::add_indexes();
    }

    /**
     * Add indexes for better performance
     */
    private static function add_indexes() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'slimwp_pmpro_level_points';

        // Check if indexes already exist before adding
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}");
        $index_names = array_column($existing_indexes, 'Key_name');

        // Add composite index for queries
        if (!in_array('idx_level_balance', $index_names)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_level_balance (membership_level_id, balance_type)");
        }
    }

    /**
     * Drop PMPro tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'slimwp_pmpro_level_points';

        // Drop the table if it exists
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }

    /**
     * Get all membership level configurations
     */
    public static function get_all_level_configs() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'slimwp_pmpro_level_points';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }

        $results = $wpdb->get_results(
            "SELECT * FROM {$table_name} ORDER BY membership_level_id ASC",
            ARRAY_A
        );

        return $results ? $results : array();
    }

    /**
     * Get configuration for a specific membership level
     */
    public static function get_level_config($level_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'slimwp_pmpro_level_points';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return false;
        }

        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE membership_level_id = %d",
            $level_id
        ), ARRAY_A);

        return $config;
    }

    /**
     * Save or update configuration for a membership level
     */
    public static function save_level_config($level_id, $config) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'slimwp_pmpro_level_points';

        // Ensure table exists before trying to save
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // Table doesn't exist, create it
            self::create_tables();

            // Double-check table was created
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                error_log('SlimWP PMPro: Failed to create database table ' . $table_name);
                return false;
            }
        }

        // Prepare data
        $data = array(
            'membership_level_id' => intval($level_id),
            'daily_points' => intval($config['daily_points']),
            'weekly_points' => intval($config['weekly_points']),
            'monthly_points' => intval($config['monthly_points']),
            'yearly_points' => intval($config['yearly_points']),
            'balance_type' => sanitize_text_field($config['balance_type'])
        );

        // Check if record exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE membership_level_id = %d",
            $level_id
        ));

        if ($exists) {
            // Update existing record
            $result = $wpdb->update(
                $table_name,
                $data,
                array('membership_level_id' => $level_id),
                array('%d', '%d', '%d', '%d', '%d', '%s'),
                array('%d')
            );

            if ($result === false) {
                error_log('SlimWP PMPro: Failed to update config for level ' . $level_id . '. Error: ' . $wpdb->last_error);
            } else {
                error_log('SlimWP PMPro: Successfully updated config for level ' . $level_id);
            }
        } else {
            // Insert new record
            $result = $wpdb->insert(
                $table_name,
                $data,
                array('%d', '%d', '%d', '%d', '%d', '%s')
            );

            if ($result === false) {
                error_log('SlimWP PMPro: Failed to insert config for level ' . $level_id . '. Error: ' . $wpdb->last_error);
            } else {
                error_log('SlimWP PMPro: Successfully inserted config for level ' . $level_id);
            }
        }

        return $result !== false;
    }

    /**
     * Delete configuration for a membership level
     */
    public static function delete_level_config($level_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'slimwp_pmpro_level_points';

        return $wpdb->delete(
            $table_name,
            array('membership_level_id' => $level_id),
            array('%d')
        );
    }

    /**
     * Reset user's PMPro point tracking meta
     */
    public static function reset_user_tracking($user_id) {
        delete_user_meta($user_id, '_slimwp_pmpro_last_daily_award');
        delete_user_meta($user_id, '_slimwp_pmpro_last_weekly_award');
        delete_user_meta($user_id, '_slimwp_pmpro_last_monthly_award');
        delete_user_meta($user_id, '_slimwp_pmpro_last_yearly_award');
        delete_user_meta($user_id, '_slimwp_pmpro_points_last_check');
    }

    /**
     * Reset all users' PMPro point tracking meta
     */
    public static function reset_all_users_tracking() {
        global $wpdb;

        // Delete all PMPro point tracking meta
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_slimwp_pmpro_%'");
    }

    /**
     * Get statistics for PMPro points awarded
     */
    public static function get_pmpro_points_stats() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'slimwp_user_points_transactions';

        $stats = array(
            'daily' => 0,
            'weekly' => 0,
            'monthly' => 0,
            'yearly' => 0,
            'total' => 0
        );

        // Get counts for each type
        $results = $wpdb->get_results(
            "SELECT
                transaction_type,
                COUNT(*) as count,
                SUM(amount) as total_points
            FROM {$table_name}
            WHERE transaction_type LIKE 'pmpro_%'
            GROUP BY transaction_type",
            ARRAY_A
        );

        if ($results) {
            foreach ($results as $row) {
                $type = str_replace('pmpro_', '', $row['transaction_type']);
                if (isset($stats[$type])) {
                    $stats[$type] = intval($row['total_points']);
                }
            }
            $stats['total'] = array_sum(array_slice($stats, 0, 4));
        }

        return $stats;
    }

    /**
     * Clean up old PMPro point transactions (optional maintenance)
     */
    public static function cleanup_old_transactions($days = 365) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'slimwp_user_points_transactions';

        // Delete old PMPro transactions older than specified days
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name}
            WHERE transaction_type LIKE 'pmpro_%'
            AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
}