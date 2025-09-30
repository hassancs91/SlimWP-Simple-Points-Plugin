<?php
if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_Dashboard {

    private $points_system;
    private $table_name;
    private $content_table;

    public function __construct($points_system) {
        $this->points_system = $points_system;
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'slimwp_user_points_transactions';
        $this->content_table = $wpdb->prefix . 'slimwp_dashboard_content';

        add_action('admin_menu', array($this, 'add_dashboard_menu'), 10);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_assets'));
        add_action('wp_ajax_slimwp_dashboard_refresh', array($this, 'ajax_refresh_dashboard'));
    }

    public function add_dashboard_menu() {
        // Create the main menu with dashboard as the default page
        add_menu_page(
            __('SlimWP Points System', 'SlimWp-Simple-Points'),
            __('SlimWP Points', 'SlimWp-Simple-Points'),
            'manage_options',
            'slimwp-points',
            array($this, 'render_dashboard'),
            'dashicons-awards',
            30
        );

        // Add Dashboard as first submenu item (this replaces the duplicate main menu item)
        add_submenu_page(
            'slimwp-points',
            __('Dashboard', 'SlimWp-Simple-Points'),
            __('Dashboard', 'SlimWp-Simple-Points'),
            'manage_options',
            'slimwp-points',
            array($this, 'render_dashboard')
        );
    }

    public function enqueue_dashboard_assets($hook) {
        // Check if we're on the main dashboard page
        if ($hook === 'toplevel_page_slimwp-points') {
            wp_enqueue_style('slimwp-dashboard', SLIMWP_PLUGIN_URL . 'includes/assets/css/dashboard.css', array(), SLIMWP_VERSION);
            wp_enqueue_script('slimwp-dashboard', SLIMWP_PLUGIN_URL . 'includes/assets/js/dashboard.js', array('jquery'), SLIMWP_VERSION, true);

            wp_localize_script('slimwp-dashboard', 'slimwp_dashboard', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('slimwp_dashboard_nonce'),
                'refresh_interval' => 30000 // Refresh every 30 seconds
            ));
        }
    }

    public function render_dashboard() {
        global $wpdb;

        // Get statistics
        $stats = $this->get_dashboard_stats();

        // Get announcements
        $announcements = $this->get_active_content('announcement');

        // Get ads/promotions
        $promotions = $this->get_active_content('promotion');
        error_log('SlimWP Dashboard: Got ' . count($promotions) . ' promotions to display');

        // Get recent activity
        $recent_activity = $this->get_recent_activity();

        // Get top users
        $top_users = $this->get_top_users();

        ?>
        <div class="wrap slimwp-dashboard-wrap">
            <div class="slimwp-dashboard">
                <!-- Header -->
                <div class="slimwp-dashboard-header">
                    <h1><?php _e('SlimWP Points Dashboard', 'SlimWp-Simple-Points'); ?></h1>
                    <div class="slimwp-header-actions">
                        <button class="button button-primary" id="slimwp-refresh-dashboard">
                            <span class="dashicons dashicons-update"></span> <?php _e('Refresh', 'SlimWp-Simple-Points'); ?>
                        </button>
                        <a href="<?php echo admin_url('admin.php?page=slimwp-transactions'); ?>" class="button">
                            <?php _e('View Transactions', 'SlimWp-Simple-Points'); ?>
                        </a>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="slimwp-stats-grid">
                    <div class="slimwp-stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <span class="dashicons dashicons-groups"></span>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                            <div class="stat-label"><?php _e('Total Users', 'SlimWp-Simple-Points'); ?></div>
                            <div class="stat-meta">
                                <?php printf(__('%d active today', 'SlimWp-Simple-Points'), $stats['active_today']); ?>
                            </div>
                        </div>
                    </div>

                    <div class="slimwp-stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <span class="dashicons dashicons-awards"></span>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($stats['total_points']); ?></div>
                            <div class="stat-label"><?php _e('Total Points', 'SlimWp-Simple-Points'); ?></div>
                            <div class="stat-meta">
                                <?php printf(__('Free: %s | Perm: %s', 'SlimWp-Simple-Points'),
                                    number_format($stats['total_free']),
                                    number_format($stats['total_permanent'])); ?>
                            </div>
                        </div>
                    </div>

                    <div class="slimwp-stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                            <span class="dashicons dashicons-chart-area"></span>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($stats['transactions_today']); ?></div>
                            <div class="stat-label"><?php _e("Today's Transactions", 'SlimWp-Simple-Points'); ?></div>
                            <div class="stat-meta">
                                <?php printf(__('%s points exchanged', 'SlimWp-Simple-Points'),
                                    number_format($stats['points_exchanged_today'])); ?>
                            </div>
                        </div>
                    </div>

                    <div class="slimwp-stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <span class="dashicons dashicons-performance"></span>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($stats['avg_balance']); ?></div>
                            <div class="stat-label"><?php _e('Average Balance', 'SlimWp-Simple-Points'); ?></div>
                            <div class="stat-meta">
                                <?php _e('Per user', 'SlimWp-Simple-Points'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="slimwp-dashboard-grid">
                    <!-- Left Column -->
                    <div class="slimwp-dashboard-left">
                        <!-- Announcements Section -->
                        <?php if (!empty($announcements)): ?>
                        <div class="slimwp-dashboard-widget">
                            <div class="widget-header">
                                <h2><?php _e('What\'s New', 'SlimWp-Simple-Points'); ?></h2>
                                <div class="announcement-nav">
                                    <button class="ann-nav-prev" aria-label="Previous">
                                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                                    </button>
                                    <div class="ann-nav-dots">
                                        <?php foreach ($announcements as $index => $ann): ?>
                                            <span class="ann-dot <?php echo $index === 0 ? 'active' : ''; ?>" data-slide="<?php echo $index; ?>"></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <button class="ann-nav-next" aria-label="Next">
                                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="widget-content">
                                <div class="slimwp-announcements-slider">
                                    <div class="announcements-wrapper">
                                        <?php foreach ($announcements as $index => $announcement): ?>
                                        <div class="announcement-slide <?php echo $index === 0 ? 'active' : ''; ?>"
                                             data-slide="<?php echo $index; ?>">
                                            <?php if (!empty($announcement->is_remote)): ?>
                                                <span class="remote-badge"><?php _e('From SlimWP', 'SlimWp-Simple-Points'); ?></span>
                                            <?php endif; ?>
                                            <h3><?php echo esc_html($announcement->title); ?></h3>
                                            <?php if ($announcement->excerpt): ?>
                                                <p class="announcement-excerpt"><?php echo esc_html($announcement->excerpt); ?></p>
                                            <?php endif; ?>
                                            <div class="announcement-content">
                                                <?php echo wp_kses_post($announcement->content); ?>
                                            </div>
                                            <div class="announcement-meta">
                                                <span class="announcement-date">
                                                    <?php echo esc_html(human_time_diff(strtotime($announcement->created_at), current_time('timestamp'))); ?> ago
                                                </span>
                                                <?php
                                                // Show importance badge for remote announcements
                                                if (!empty($announcement->is_remote) && !empty($announcement->meta_data)) {
                                                    $meta = is_string($announcement->meta_data) ? json_decode($announcement->meta_data, true) : $announcement->meta_data;
                                                    if (!empty($meta['importance'])): ?>
                                                        <span class="importance-badge importance-<?php echo esc_attr($meta['importance']); ?>">
                                                            <?php echo esc_html(ucfirst($meta['importance'])); ?>
                                                        </span>
                                                    <?php endif;
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Recent Activity -->
                        <div class="slimwp-dashboard-widget">
                            <div class="widget-header">
                                <h2><?php _e('Recent Activity', 'SlimWp-Simple-Points'); ?></h2>
                                <a href="<?php echo admin_url('admin.php?page=slimwp-transactions'); ?>" class="widget-link">
                                    <?php _e('View All', 'SlimWp-Simple-Points'); ?> →
                                </a>
                            </div>
                            <div class="widget-content">
                                <div class="activity-feed">
                                    <?php foreach ($recent_activity as $activity): ?>
                                    <?php
                                        $user = get_userdata($activity->user_id);
                                        $is_positive = $activity->amount >= 0;
                                    ?>
                                    <div class="activity-item">
                                        <div class="activity-avatar">
                                            <?php echo get_avatar($activity->user_id, 32); ?>
                                        </div>
                                        <div class="activity-details">
                                            <div class="activity-main">
                                                <strong><?php echo $user ? esc_html($user->display_name) : 'User #' . $activity->user_id; ?></strong>
                                                <?php echo esc_html($activity->description); ?>
                                            </div>
                                            <div class="activity-meta">
                                                <span class="activity-amount <?php echo $is_positive ? 'positive' : 'negative'; ?>">
                                                    <?php echo $is_positive ? '+' : ''; ?><?php echo number_format($activity->amount, 2); ?> points
                                                </span>
                                                <span class="activity-time">
                                                    <?php echo human_time_diff(strtotime($activity->created_at), current_time('timestamp')); ?> ago
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="slimwp-dashboard-right">
                        <!-- Quick Actions -->
                        <div class="slimwp-dashboard-widget">
                            <div class="widget-header">
                                <h2><?php _e('Quick Actions', 'SlimWp-Simple-Points'); ?></h2>
                            </div>
                            <div class="widget-content">
                                <div class="quick-actions">
                                    <a href="<?php echo admin_url('admin.php?page=slimwp-transactions'); ?>" class="quick-action-btn">
                                        <span class="dashicons dashicons-plus-alt"></span>
                                        <span><?php _e('Add Points', 'SlimWp-Simple-Points'); ?></span>
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=slimwp-user-consumption'); ?>" class="quick-action-btn">
                                        <span class="dashicons dashicons-chart-line"></span>
                                        <span><?php _e('User Stats', 'SlimWp-Simple-Points'); ?></span>
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=slimwp-points-settings'); ?>" class="quick-action-btn">
                                        <span class="dashicons dashicons-admin-settings"></span>
                                        <span><?php _e('Settings', 'SlimWp-Simple-Points'); ?></span>
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=slimwp-stripe-packages'); ?>" class="quick-action-btn">
                                        <span class="dashicons dashicons-cart"></span>
                                        <span><?php _e('Packages', 'SlimWp-Simple-Points'); ?></span>
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=slimwp-points-documentation'); ?>" class="quick-action-btn">
                                        <span class="dashicons dashicons-book-alt"></span>
                                        <span><?php _e('Documentation', 'SlimWp-Simple-Points'); ?></span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Featured & Promotions -->
                        <?php if (!empty($promotions)): ?>
                        <div class="slimwp-dashboard-widget">
                            <div class="widget-header">
                                <h2><?php _e('Featured & Promotions', 'SlimWp-Simple-Points'); ?></h2>
                                <div class="promotion-nav">
                                    <button class="promo-nav-prev" aria-label="Previous">
                                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                                    </button>
                                    <div class="promo-nav-dots">
                                        <?php
                                        $dot_index = 0;
                                        foreach ($promotions as $promo):
                                            $index = $dot_index++;
                                        ?>
                                            <span class="promo-dot <?php echo $index === 0 ? 'active' : ''; ?>" data-slide="<?php echo $index; ?>"></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <button class="promo-nav-next" aria-label="Next">
                                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="widget-content">
                                <div class="slimwp-promotions-slider">
                                    <div class="promotions-wrapper">
                                        <?php
                                        // Debug: Check what we're getting
                                        error_log('SlimWP Dashboard: Rendering ' . count($promotions) . ' promotions');
                                        $promo_index = 0;
                                        foreach ($promotions as $promotion):
                                            $index = $promo_index++;
                                        ?>
                                        <?php
                                            // Handle meta_data whether it's a string or array
                                            $meta = is_string($promotion->meta_data) ? json_decode($promotion->meta_data, true) : (array)$promotion->meta_data;
                                        ?>
                                        <div class="promotion-slide <?php echo $index === 0 ? 'active' : ''; ?>"
                                             data-slide="<?php echo $index; ?>"
                                             data-id="<?php echo esc_attr($promotion->id); ?>">
                                            <?php if (!empty($promotion->is_remote)): ?>
                                                <span class="remote-badge"><?php _e('From SlimWP', 'SlimWp-Simple-Points'); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($meta['badge_text'])): ?>
                                                <span class="promo-badge" style="<?php echo !empty($meta['badge_color']) ? 'background-color: ' . esc_attr($meta['badge_color']) . ';' : ''; ?>">
                                                    <?php echo esc_html($meta['badge_text']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($meta['image_url'])): ?>
                                            <div class="promotion-slide-image">
                                                <img src="<?php echo esc_url($meta['image_url']); ?>" alt="<?php echo esc_attr($promotion->title); ?>">
                                            </div>
                                            <?php endif; ?>
                                            <div class="promotion-slide-content" <?php echo !empty($meta['background_color']) ? 'style="background-color: ' . esc_attr($meta['background_color']) . ';"' : ''; ?>>
                                                <h3><?php echo esc_html($promotion->title); ?></h3>
                                                <?php if (!empty($promotion->excerpt)): ?>
                                                    <p class="promotion-excerpt"><?php echo esc_html($promotion->excerpt); ?></p>
                                                <?php endif; ?>
                                                <div class="promotion-text">
                                                    <?php echo wp_kses_post($promotion->content); ?>
                                                </div>
                                                <?php if (!empty($meta['button_text']) && !empty($meta['button_url'])): ?>
                                                <a href="<?php echo esc_url($meta['button_url']); ?>"
                                                   class="promotion-button"
                                                   target="<?php echo esc_attr($meta['button_target'] ?? '_blank'); ?>"
                                                   data-promotion-id="<?php echo esc_attr($promotion->id); ?>"
                                                   data-remote="<?php echo !empty($promotion->is_remote) ? '1' : '0'; ?>">
                                                    <?php echo esc_html($meta['button_text']); ?>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Top Users -->
                        <div class="slimwp-dashboard-widget">
                            <div class="widget-header">
                                <h2><?php _e('Top Users', 'SlimWp-Simple-Points'); ?></h2>
                                <a href="<?php echo admin_url('admin.php?page=slimwp-user-consumption'); ?>" class="widget-link">
                                    <?php _e('View All', 'SlimWp-Simple-Points'); ?> →
                                </a>
                            </div>
                            <div class="widget-content">
                                <div class="top-users-list">
                                    <?php
                                    $position = 1;
                                    foreach ($top_users as $user):
                                        $user_data = get_userdata($user->user_id);
                                        if (!$user_data) continue;
                                    ?>
                                    <div class="top-user-item">
                                        <div class="user-rank <?php echo $position <= 3 ? 'top-' . $position : ''; ?>">
                                            <?php echo $position; ?>
                                        </div>
                                        <div class="user-avatar">
                                            <?php echo get_avatar($user->user_id, 32); ?>
                                        </div>
                                        <div class="user-info">
                                            <div class="user-name">
                                                <?php echo esc_html($user_data->display_name); ?>
                                            </div>
                                            <div class="user-points">
                                                <?php echo number_format($user->total_balance); ?> points
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                    $position++;
                                    endforeach;
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- System Health -->
                        <div class="slimwp-dashboard-widget">
                            <div class="widget-header">
                                <h2><?php _e('System Health', 'SlimWp-Simple-Points'); ?></h2>
                            </div>
                            <div class="widget-content">
                                <div class="system-health">
                                    <?php
                                    $health_checks = $this->get_system_health();
                                    foreach ($health_checks as $check):
                                    ?>
                                    <div class="health-item">
                                        <span class="health-indicator <?php echo $check['status']; ?>"></span>
                                        <span class="health-label"><?php echo esc_html($check['label']); ?></span>
                                        <?php if ($check['message']): ?>
                                            <span class="health-message"><?php echo esc_html($check['message']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function get_dashboard_stats() {
        global $wpdb;

        $stats = array();

        // Total users with points
        $stats['total_users'] = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta}
            WHERE meta_key IN ('slimwp_points_balance', 'slimwp_points_balance_permanent')
            AND meta_value > 0"
        );

        // Total points
        $stats['total_free'] = $wpdb->get_var(
            "SELECT SUM(meta_value) FROM {$wpdb->usermeta}
            WHERE meta_key = 'slimwp_points_balance'"
        ) ?: 0;

        $stats['total_permanent'] = $wpdb->get_var(
            "SELECT SUM(meta_value) FROM {$wpdb->usermeta}
            WHERE meta_key = 'slimwp_points_balance_permanent'"
        ) ?: 0;

        $stats['total_points'] = $stats['total_free'] + $stats['total_permanent'];

        // Today's activity
        $stats['transactions_today'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name}
            WHERE DATE(created_at) = CURDATE()"
        );

        $stats['active_today'] = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$this->table_name}
            WHERE DATE(created_at) = CURDATE()"
        );

        $stats['points_exchanged_today'] = $wpdb->get_var(
            "SELECT SUM(ABS(amount)) FROM {$this->table_name}
            WHERE DATE(created_at) = CURDATE()"
        ) ?: 0;

        // Average balance
        $stats['avg_balance'] = $stats['total_users'] > 0 ?
            round($stats['total_points'] / $stats['total_users']) : 0;

        return $stats;
    }

    private function get_active_content($type) {
        global $wpdb;

        // Get local content
        $current_time = current_time('mysql');
        $local_content = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->content_table}
            WHERE content_type = %s
            AND status = 'active'
            AND (display_from IS NULL OR display_from <= %s)
            AND (display_until IS NULL OR display_until >= %s)
            ORDER BY priority DESC, created_at DESC
            LIMIT 5",
            $type,
            $current_time,
            $current_time
        ));

        // Get remote content based on type
        $remote_content = array();
        if ($type === 'announcement') {
            $remote_content = $this->get_remote_announcements();
        } elseif ($type === 'promotion') {
            $remote_content = $this->get_remote_promotions();
        }

        // Convert to objects and add source indicator
        foreach ($remote_content as &$item) {
            $item = (object) $item;
            $item->is_remote = true;
        }

        // Merge and sort by priority
        $all_content = array_merge($local_content, $remote_content);
        usort($all_content, function($a, $b) {
            return ($b->priority ?? 0) - ($a->priority ?? 0);
        });

        // Return top 5 items
        return array_slice($all_content, 0, 5);
    }

    private function get_remote_announcements() {
        $transient_key = 'slimwp_remote_announcements';
        $cached = get_transient($transient_key);

        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get('https://slimwp.com/wp-json/slimwp/v1/announcements', array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'SlimWP-Points/' . SLIMWP_VERSION,
                'X-Site-URL' => site_url(),
                'X-Plugin-Version' => SLIMWP_VERSION
            )
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            // Check both possible response structures for compatibility
            $announcements_data = null;
            if (isset($body['announcements']) && is_array($body['announcements'])) {
                $announcements_data = $body['announcements'];
            } elseif (isset($body['data']['announcements']) && is_array($body['data']['announcements'])) {
                $announcements_data = $body['data']['announcements'];
            }

            if ($announcements_data) {
                // Filter only for active status, ignore date restrictions
                $announcements = array_filter($announcements_data, function($item) {
                    // Only check if status is active
                    return $item['status'] === 'active';
                });

                // Store backup for offline use
                update_option('slimwp_remote_announcements_backup', array_values($announcements));

                $cache_time = isset($body['cache_time']) ? intval($body['cache_time']) : 12 * HOUR_IN_SECONDS;
                set_transient($transient_key, array_values($announcements), $cache_time);
                return array_values($announcements);
            }
        }

        // On error, try to return stale cache if available
        $stale_cache = get_option('slimwp_remote_announcements_backup');
        return is_array($stale_cache) ? $stale_cache : array();
    }

    private function get_remote_promotions() {
        $transient_key = 'slimwp_remote_promotions';

        // Temporarily bypass cache for debugging
        $cached = false; // get_transient($transient_key);

        if ($cached !== false) {
            error_log('SlimWP: Using cached promotions: ' . count($cached) . ' items');
            return $cached;
        }

        $response = wp_remote_get('https://slimwp.com/wp-json/slimwp/v1/promotions', array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'SlimWP-Points/' . SLIMWP_VERSION,
                'X-Site-URL' => site_url(),
                'X-Plugin-Version' => SLIMWP_VERSION
            )
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            // Check both possible response structures for compatibility
            $promotions_data = null;
            if (isset($body['promotions']) && is_array($body['promotions'])) {
                $promotions_data = $body['promotions'];
            } elseif (isset($body['data']['promotions']) && is_array($body['data']['promotions'])) {
                $promotions_data = $body['data']['promotions'];
            }

            if ($promotions_data) {
                error_log('SlimWP: Raw promotions from API: ' . count($promotions_data) . ' items');

                // Filter only for active status, ignore date restrictions
                $promotions = array_filter($promotions_data, function($item) {
                    // Only check if status is active
                    return $item['status'] === 'active';
                });

                error_log('SlimWP: Active promotions after filter: ' . count($promotions) . ' items');

                // Re-index array to ensure numeric keys starting from 0
                $promotions = array_values($promotions);

                error_log('SlimWP: Final promotions array: ' . json_encode(array_map(function($p) {
                    return ['id' => $p['id'], 'title' => $p['title']];
                }, $promotions)));

                // Store backup for offline use
                update_option('slimwp_remote_promotions_backup', $promotions);

                $cache_time = isset($body['cache_time']) ? intval($body['cache_time']) : 12 * HOUR_IN_SECONDS;
                set_transient($transient_key, $promotions, $cache_time);
                return $promotions;
            }
        }

        // On error, try to return stale cache if available
        $stale_cache = get_option('slimwp_remote_promotions_backup');
        return is_array($stale_cache) ? $stale_cache : array();
    }

    private function get_recent_activity($limit = 10) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
            ORDER BY created_at DESC
            LIMIT %d",
            $limit
        ));
    }

    private function get_top_users($limit = 5) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                u.ID as user_id,
                (COALESCE(m1.meta_value, 0) + COALESCE(m2.meta_value, 0)) as total_balance
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} m1 ON u.ID = m1.user_id AND m1.meta_key = 'slimwp_points_balance'
            LEFT JOIN {$wpdb->usermeta} m2 ON u.ID = m2.user_id AND m2.meta_key = 'slimwp_points_balance_permanent'
            HAVING total_balance > 0
            ORDER BY total_balance DESC
            LIMIT %d",
            $limit
        ));
    }

    private function get_system_health() {
        $checks = array();

        // Check database tables
        global $wpdb;
        $tables_exist = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") &&
                       $wpdb->get_var("SHOW TABLES LIKE '{$this->content_table}'");

        $checks[] = array(
            'label' => __('Database Tables', 'SlimWp-Simple-Points'),
            'status' => $tables_exist ? 'good' : 'error',
            'message' => $tables_exist ? __('OK', 'SlimWp-Simple-Points') : __('Missing tables', 'SlimWp-Simple-Points')
        );

        // Check WooCommerce integration
        if (get_option('slimwp_woocommerce_settings')['enabled']) {
            $woo_active = class_exists('WooCommerce');
            $checks[] = array(
                'label' => __('WooCommerce Integration', 'SlimWp-Simple-Points'),
                'status' => $woo_active ? 'good' : 'warning',
                'message' => $woo_active ? __('Active', 'SlimWp-Simple-Points') : __('WooCommerce not found', 'SlimWp-Simple-Points')
            );
        }

        // Check Stripe integration
        if (get_option('slimwp_stripe_settings')['enabled']) {
            $stripe_configured = !empty(get_option('slimwp_stripe_settings')['test_secret_key']);
            $checks[] = array(
                'label' => __('Stripe Integration', 'SlimWp-Simple-Points'),
                'status' => $stripe_configured ? 'good' : 'warning',
                'message' => $stripe_configured ? __('Configured', 'SlimWp-Simple-Points') : __('Not configured', 'SlimWp-Simple-Points')
            );
        }

        // Check scheduled hooks
        $daily_hook = wp_next_scheduled('slimwp_daily_reset');
        $checks[] = array(
            'label' => __('Scheduled Tasks', 'SlimWp-Simple-Points'),
            'status' => $daily_hook ? 'good' : 'info',
            'message' => $daily_hook ? __('Active', 'SlimWp-Simple-Points') : __('No active schedules', 'SlimWp-Simple-Points')
        );

        return $checks;
    }

    public function ajax_refresh_dashboard() {
        check_ajax_referer('slimwp_dashboard_nonce', 'nonce');

        $stats = $this->get_dashboard_stats();
        $recent_activity = $this->get_recent_activity(5);

        wp_send_json_success(array(
            'stats' => $stats,
            'activity' => $recent_activity
        ));
    }

}