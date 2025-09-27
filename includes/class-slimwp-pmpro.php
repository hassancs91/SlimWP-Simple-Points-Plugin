<?php
if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_PMPro {

    private $points_system;
    private $check_interval = DAY_IN_SECONDS; // Check once per day per user

    public function __construct($points_system) {
        $this->points_system = $points_system;

        // Check if PMPro is active
        if (!$this->is_pmpro_active()) {
            return;
        }

        // Check if integration is enabled
        if (!$this->is_integration_enabled()) {
            return;
        }

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Check if PMPro is active
     */
    private function is_pmpro_active() {
        return defined('PMPRO_VERSION') || function_exists('pmpro_getMembershipLevelForUser');
    }

    /**
     * Check if PMPro integration is enabled in settings
     */
    private function is_integration_enabled() {
        $settings = get_option('slimwp_pmpro_settings', array());
        return !empty($settings['enabled']);
    }

    /**
     * Initialize PMPro hooks
     */
    private function init_hooks() {
        // Event-driven point checks
        add_action('wp_login', array($this, 'check_and_award_points_on_login'), 10, 2);
        add_action('init', array($this, 'check_and_award_points_on_page_load'));

        // PMPro specific hooks
        add_action('pmpro_after_change_membership_level', array($this, 'handle_membership_level_change'), 10, 3);
        add_action('pmpro_membership_post_membership_expiry', array($this, 'handle_membership_expiry'), 10, 2);

        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    /**
     * Check and award points on user login
     */
    public function check_and_award_points_on_login($user_login, $user) {
        if (!$user || !isset($user->ID)) {
            return;
        }

        $this->process_user_points($user->ID, true); // Force check on login
    }

    /**
     * Check and award points on page load (with caching)
     */
    public function check_and_award_points_on_page_load() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        // Check if we've already checked recently
        $last_check = get_user_meta($user_id, '_slimwp_pmpro_points_last_check', true);
        if ($last_check && (time() - $last_check < $this->check_interval)) {
            return; // Already checked within the interval
        }

        $this->process_user_points($user_id);
    }

    /**
     * Process points for a user based on their membership level
     */
    private function process_user_points($user_id, $force_check = false) {
        // Get user's membership level
        if (!function_exists('pmpro_getMembershipLevelForUser')) {
            return;
        }

        $membership_level = pmpro_getMembershipLevelForUser($user_id);
        if (empty($membership_level) || empty($membership_level->id)) {
            return; // No active membership
        }

        // Get points configuration for this membership level
        $level_config = $this->get_level_points_config($membership_level->id);
        if (!$level_config) {
            return; // No configuration for this level
        }

        // Update last check time
        update_user_meta($user_id, '_slimwp_pmpro_points_last_check', time());

        // Check each schedule type (daily, weekly, monthly, yearly)
        $this->award_scheduled_points($user_id, $membership_level->id, 'daily', $level_config['daily_points'], $level_config['balance_type']);
        $this->award_scheduled_points($user_id, $membership_level->id, 'weekly', $level_config['weekly_points'], $level_config['balance_type']);
        $this->award_scheduled_points($user_id, $membership_level->id, 'monthly', $level_config['monthly_points'], $level_config['balance_type']);
        $this->award_scheduled_points($user_id, $membership_level->id, 'yearly', $level_config['yearly_points'], $level_config['balance_type']);
    }

    /**
     * Award scheduled points based on schedule type
     */
    private function award_scheduled_points($user_id, $level_id, $schedule_type, $points_amount, $balance_type) {
        if ($points_amount <= 0) {
            return;
        }

        // Get last award time for this schedule
        $meta_key = "_slimwp_pmpro_last_{$schedule_type}_award";
        $last_award = get_user_meta($user_id, $meta_key, true);
        $current_time = time();

        // Calculate if it's time to award
        $should_award = false;
        switch ($schedule_type) {
            case 'daily':
                $should_award = !$last_award || ($current_time - $last_award >= DAY_IN_SECONDS);
                break;
            case 'weekly':
                $should_award = !$last_award || ($current_time - $last_award >= WEEK_IN_SECONDS);
                break;
            case 'monthly':
                $should_award = !$last_award || ($current_time - $last_award >= MONTH_IN_SECONDS);
                break;
            case 'yearly':
                $should_award = !$last_award || ($current_time - $last_award >= YEAR_IN_SECONDS);
                break;
        }

        if (!$should_award) {
            return;
        }

        // Award points
        $membership_level = pmpro_getLevel($level_id);
        $level_name = $membership_level ? $membership_level->name : "Level #{$level_id}";

        $description = sprintf(
            __('%s %s points for %s membership', 'SlimWp-Simple-Points'),
            ucfirst($schedule_type),
            $balance_type === 'permanent' ? 'permanent' : 'free',
            $level_name
        );

        $result = $this->points_system->add_points(
            $user_id,
            $points_amount,
            $description,
            'pmpro_' . $schedule_type,
            $balance_type
        );

        if (!is_wp_error($result)) {
            // Update last award time
            update_user_meta($user_id, $meta_key, $current_time);

            // Trigger action for other plugins/themes
            do_action('slimwp_pmpro_points_awarded', $user_id, $points_amount, $schedule_type, $level_id, $balance_type);
        }
    }

    /**
     * Handle membership level changes
     */
    public function handle_membership_level_change($level_id, $user_id, $cancel_level) {
        // Clear all last award timestamps to reset the schedule
        delete_user_meta($user_id, '_slimwp_pmpro_last_daily_award');
        delete_user_meta($user_id, '_slimwp_pmpro_last_weekly_award');
        delete_user_meta($user_id, '_slimwp_pmpro_last_monthly_award');
        delete_user_meta($user_id, '_slimwp_pmpro_last_yearly_award');
        delete_user_meta($user_id, '_slimwp_pmpro_points_last_check');

        // If user got a new level (not cancellation), process points immediately
        if ($level_id > 0) {
            $this->process_user_points($user_id, true);
        }
    }

    /**
     * Handle membership expiry
     */
    public function handle_membership_expiry($user_id, $level_id) {
        // Clear all last award timestamps
        delete_user_meta($user_id, '_slimwp_pmpro_last_daily_award');
        delete_user_meta($user_id, '_slimwp_pmpro_last_weekly_award');
        delete_user_meta($user_id, '_slimwp_pmpro_last_monthly_award');
        delete_user_meta($user_id, '_slimwp_pmpro_last_yearly_award');
        delete_user_meta($user_id, '_slimwp_pmpro_points_last_check');
    }

    /**
     * Get points configuration for a membership level
     */
    public function get_level_points_config($level_id) {
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

        if (!$config) {
            return false;
        }

        return array(
            'daily_points' => intval($config['daily_points']),
            'weekly_points' => intval($config['weekly_points']),
            'monthly_points' => intval($config['monthly_points']),
            'yearly_points' => intval($config['yearly_points']),
            'balance_type' => $config['balance_type'] ?: 'free'
        );
    }

    /**
     * Save points configuration for a membership level
     */
    public function save_level_points_config($level_id, $config) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'slimwp_pmpro_level_points';

        // Check if record exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE membership_level_id = %d",
            $level_id
        ));

        $data = array(
            'membership_level_id' => $level_id,
            'daily_points' => intval($config['daily_points']),
            'weekly_points' => intval($config['weekly_points']),
            'monthly_points' => intval($config['monthly_points']),
            'yearly_points' => intval($config['yearly_points']),
            'balance_type' => sanitize_text_field($config['balance_type'])
        );

        if ($exists) {
            return $wpdb->update(
                $table_name,
                $data,
                array('membership_level_id' => $level_id),
                array('%d', '%d', '%d', '%d', '%d', '%s'),
                array('%d')
            );
        } else {
            return $wpdb->insert(
                $table_name,
                $data,
                array('%d', '%d', '%d', '%d', '%d', '%s')
            );
        }
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        if (!$this->is_pmpro_active()) {
            $settings = get_option('slimwp_pmpro_settings', array());
            if (!empty($settings['enabled'])) {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <strong><?php _e('SlimWP Points:', 'SlimWp-Simple-Points'); ?></strong>
                        <?php _e('PMPro integration is enabled but Paid Memberships Pro plugin is not active.', 'SlimWp-Simple-Points'); ?>
                    </p>
                </div>
                <?php
            }
        }
    }
}