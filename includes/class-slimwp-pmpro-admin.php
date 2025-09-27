<?php
if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_PMPro_Admin {

    private $points_system;
    private $pmpro_integration;
    private $page_hook;

    public function __construct($points_system, $pmpro_integration) {
        $this->points_system = $points_system;
        $this->pmpro_integration = $pmpro_integration;

        add_action('admin_menu', array($this, 'add_admin_menu'), 30);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'process_form_submission'));
    }

    /**
     * Add admin menu for PMPro points configuration
     */
    public function add_admin_menu() {
        $this->page_hook = add_submenu_page(
            'slimwp-points',
            __('PMPro Membership Points', 'SlimWp-Simple-Points'),
            __('PMPro Points', 'SlimWp-Simple-Points'),
            'manage_options',
            'slimwp-pmpro-points',
            array($this, 'admin_page')
        );
    }

    /**
     * Process form submission before any output
     */
    public function process_form_submission() {
        // Only process on our specific admin page
        if (!isset($_GET['page']) || $_GET['page'] !== 'slimwp-pmpro-points') {
            return;
        }

        // Check if form was submitted
        if (!isset($_POST['submit'])) {
            return;
        }

        // Handle the form submission
        $this->handle_form_submission();
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'slimwp-points_page_slimwp-pmpro-points') {
            return;
        }

        // Add inline styles for the admin page
        wp_add_inline_style('wp-admin', $this->get_admin_styles());
    }

    /**
     * Get admin styles
     */
    private function get_admin_styles() {
        return '
            .slimwp-pmpro-wrap { background: #f0f0f1; min-height: 100vh; margin: 0 -20px; padding: 0; }
            .slimwp-pmpro-header { background: #fff; padding: 20px 32px; margin: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .slimwp-pmpro-header h1 { margin: 0; font-size: 24px; font-weight: 600; color: #1d2327; }
            .slimwp-pmpro-content { padding: 32px; }
            .pmpro-levels-table { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .pmpro-levels-table table { width: 100%; border-collapse: collapse; }
            .pmpro-levels-table th { background: #f8f9fa; padding: 12px 16px; text-align: left; font-weight: 600; color: #1d2327; border-bottom: 2px solid #e1e1e1; }
            .pmpro-levels-table td { padding: 16px; border-bottom: 1px solid #f0f0f1; }
            .pmpro-levels-table tr:hover { background: #f8f9fa; }
            .pmpro-levels-table tr:last-child td { border-bottom: none; }
            .points-input { width: 80px; padding: 6px 8px; border: 1px solid #dcdcde; border-radius: 4px; }
            .points-input:focus { border-color: #2271b1; outline: none; box-shadow: 0 0 0 1px #2271b1; }
            .balance-type-select { padding: 6px 8px; border: 1px solid #dcdcde; border-radius: 4px; }
            .level-name { font-weight: 600; color: #1d2327; }
            .save-button { margin-top: 20px; }
            .button-primary { background: #2271b1; border-color: #2271b1; color: #fff; padding: 8px 24px; font-size: 14px; font-weight: 500; border-radius: 4px; }
            .notice-info { background: #e7f2fd; border: 1px solid #bee5eb; border-radius: 4px; padding: 16px; margin-bottom: 20px; }
            .notice-warning { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 16px; margin-bottom: 20px; }
            .stats-box { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-top: 12px; }
            .stat-item { text-align: center; }
            .stat-value { font-size: 24px; font-weight: 600; color: #2271b1; }
            .stat-label { font-size: 13px; color: #50575e; margin-top: 4px; }
        ';
    }

    /**
     * Admin page content
     */
    public function admin_page() {
        // Check if PMPro is active
        if (!defined('PMPRO_VERSION') && !function_exists('pmpro_getAllLevels')) {
            $this->show_pmpro_required_notice();
            return;
        }

        // Ensure database table exists
        SlimWP_PMPro_Database::create_tables();

        // Get all membership levels
        $levels = pmpro_getAllLevels(true, true);

        // Get existing configurations
        $level_configs = array();
        foreach ($levels as $level) {
            $config = SlimWP_PMPro_Database::get_level_config($level->id);
            if (!$config) {
                $config = array(
                    'daily_points' => 0,
                    'weekly_points' => 0,
                    'monthly_points' => 0,
                    'yearly_points' => 0,
                    'balance_type' => 'free'
                );
            }
            $level_configs[$level->id] = $config;
        }

        // Get statistics
        $stats = SlimWP_PMPro_Database::get_pmpro_points_stats();

        ?>
        <div class="wrap">
            <div class="slimwp-pmpro-wrap">
                <div class="slimwp-pmpro-header">
                    <h1><?php _e('PMPro Membership Points Configuration', 'SlimWp-Simple-Points'); ?></h1>
                </div>

                <div class="slimwp-pmpro-content">
                    <?php if (isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
                        <div class="notice notice-success is-dismissible">
                            <p><?php _e('Settings saved successfully!', 'SlimWp-Simple-Points'); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="notice-info">
                        <p><strong><?php _e('How it works:', 'SlimWp-Simple-Points'); ?></strong></p>
                        <p><?php _e('Configure automatic point awards for each membership level. Points are awarded based on the schedule you set (daily, weekly, monthly, or yearly). The system checks and awards points when users log in or browse the site (with smart caching to prevent performance issues).', 'SlimWp-Simple-Points'); ?></p>
                    </div>

                    <?php if (!empty($stats['total'])): ?>
                    <div class="stats-box">
                        <h3><?php _e('PMPro Points Statistics', 'SlimWp-Simple-Points'); ?></h3>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo number_format($stats['daily']); ?></div>
                                <div class="stat-label"><?php _e('Daily Points', 'SlimWp-Simple-Points'); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo number_format($stats['weekly']); ?></div>
                                <div class="stat-label"><?php _e('Weekly Points', 'SlimWp-Simple-Points'); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo number_format($stats['monthly']); ?></div>
                                <div class="stat-label"><?php _e('Monthly Points', 'SlimWp-Simple-Points'); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo number_format($stats['yearly']); ?></div>
                                <div class="stat-label"><?php _e('Yearly Points', 'SlimWp-Simple-Points'); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                                <div class="stat-label"><?php _e('Total Awarded', 'SlimWp-Simple-Points'); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form method="post">
                        <?php wp_nonce_field('slimwp_pmpro_settings'); ?>

                        <div class="pmpro-levels-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th><?php _e('Membership Level', 'SlimWp-Simple-Points'); ?></th>
                                        <th><?php _e('Daily Points', 'SlimWp-Simple-Points'); ?></th>
                                        <th><?php _e('Weekly Points', 'SlimWp-Simple-Points'); ?></th>
                                        <th><?php _e('Monthly Points', 'SlimWp-Simple-Points'); ?></th>
                                        <th><?php _e('Yearly Points', 'SlimWp-Simple-Points'); ?></th>
                                        <th><?php _e('Balance Type', 'SlimWp-Simple-Points'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($levels)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 40px;">
                                                <?php _e('No membership levels found. Please create membership levels in PMPro first.', 'SlimWp-Simple-Points'); ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($levels as $level): ?>
                                            <?php $config = $level_configs[$level->id]; ?>
                                            <tr>
                                                <td>
                                                    <span class="level-name"><?php echo esc_html($level->name); ?></span>
                                                    <br>
                                                    <small style="color: #666;">ID: <?php echo $level->id; ?></small>
                                                </td>
                                                <td>
                                                    <input type="number"
                                                           name="levels[<?php echo $level->id; ?>][daily_points]"
                                                           value="<?php echo esc_attr($config['daily_points']); ?>"
                                                           class="points-input"
                                                           min="0">
                                                </td>
                                                <td>
                                                    <input type="number"
                                                           name="levels[<?php echo $level->id; ?>][weekly_points]"
                                                           value="<?php echo esc_attr($config['weekly_points']); ?>"
                                                           class="points-input"
                                                           min="0">
                                                </td>
                                                <td>
                                                    <input type="number"
                                                           name="levels[<?php echo $level->id; ?>][monthly_points]"
                                                           value="<?php echo esc_attr($config['monthly_points']); ?>"
                                                           class="points-input"
                                                           min="0">
                                                </td>
                                                <td>
                                                    <input type="number"
                                                           name="levels[<?php echo $level->id; ?>][yearly_points]"
                                                           value="<?php echo esc_attr($config['yearly_points']); ?>"
                                                           class="points-input"
                                                           min="0">
                                                </td>
                                                <td>
                                                    <select name="levels[<?php echo $level->id; ?>][balance_type]" class="balance-type-select">
                                                        <option value="free" <?php selected($config['balance_type'], 'free'); ?>>
                                                            <?php _e('Free Balance', 'SlimWp-Simple-Points'); ?>
                                                        </option>
                                                        <option value="permanent" <?php selected($config['balance_type'], 'permanent'); ?>>
                                                            <?php _e('Permanent Balance', 'SlimWp-Simple-Points'); ?>
                                                        </option>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (!empty($levels)): ?>
                            <div class="save-button">
                                <?php submit_button(__('Save Settings', 'SlimWp-Simple-Points'), 'primary', 'submit', false); ?>
                            </div>
                        <?php endif; ?>
                    </form>

                    <div class="notice-warning" style="margin-top: 40px;">
                        <p><strong><?php _e('Important Notes:', 'SlimWp-Simple-Points'); ?></strong></p>
                        <ul style="margin: 8px 0 0 20px;">
                            <li><?php _e('Points are awarded automatically when users log in or browse the site', 'SlimWp-Simple-Points'); ?></li>
                            <li><?php _e('The system checks once per day per user to prevent performance issues', 'SlimWp-Simple-Points'); ?></li>
                            <li><?php _e('Set values to 0 to disable specific schedules', 'SlimWp-Simple-Points'); ?></li>
                            <li><?php _e('When membership levels change, point schedules are automatically reset', 'SlimWp-Simple-Points'); ?></li>
                            <li><?php _e('Points stop being awarded when memberships expire', 'SlimWp-Simple-Points'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle form submission
     */
    private function handle_form_submission() {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'slimwp_pmpro_settings')) {
            wp_die(__('Security check failed', 'SlimWp-Simple-Points'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'SlimWp-Simple-Points'));
        }

        // Debug: Log received POST data
        error_log('SlimWP PMPro: Received POST data: ' . print_r($_POST, true));

        // Save level configurations
        if (isset($_POST['levels']) && is_array($_POST['levels'])) {
            foreach ($_POST['levels'] as $level_id => $config) {
                $level_id = intval($level_id);

                $data = array(
                    'daily_points' => isset($config['daily_points']) ? intval($config['daily_points']) : 0,
                    'weekly_points' => isset($config['weekly_points']) ? intval($config['weekly_points']) : 0,
                    'monthly_points' => isset($config['monthly_points']) ? intval($config['monthly_points']) : 0,
                    'yearly_points' => isset($config['yearly_points']) ? intval($config['yearly_points']) : 0,
                    'balance_type' => isset($config['balance_type']) && $config['balance_type'] === 'permanent' ? 'permanent' : 'free'
                );

                error_log('SlimWP PMPro: Saving config for level ' . $level_id . ': ' . print_r($data, true));

                $result = SlimWP_PMPro_Database::save_level_config($level_id, $data);

                if (!$result) {
                    error_log('SlimWP PMPro: Failed to save config for level ' . $level_id);
                }
            }
        } else {
            error_log('SlimWP PMPro: No levels data in POST');
        }

        // Redirect with success message
        wp_redirect(admin_url('admin.php?page=slimwp-pmpro-points&saved=1'));
        exit;
    }

    /**
     * Show notice when PMPro is not installed
     */
    private function show_pmpro_required_notice() {
        ?>
        <div class="wrap">
            <h1><?php _e('PMPro Membership Points', 'SlimWp-Simple-Points'); ?></h1>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e('Paid Memberships Pro Required', 'SlimWp-Simple-Points'); ?></strong><br>
                    <?php _e('This feature requires the Paid Memberships Pro plugin to be installed and activated.', 'SlimWp-Simple-Points'); ?>
                </p>
                <p>
                    <a href="<?php echo admin_url('plugin-install.php?s=paid+memberships+pro&tab=search&type=term'); ?>" class="button button-primary">
                        <?php _e('Install Paid Memberships Pro', 'SlimWp-Simple-Points'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
}