<?php
if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_Admin {
    
    private $points_system;
    private $table_name;
    
    public function __construct($points_system) {
        $this->points_system = $points_system;
        $this->table_name = $points_system->get_table_name();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('SlimWP Points System', 'SlimWp-Simple-Points'),
            __('SlimWP Points', 'SlimWp-Simple-Points'),
            'manage_options',
            'slimwp-points',
            array($this, 'admin_page'),
            'dashicons-awards',
            30
        );
        
        add_submenu_page(
            'slimwp-points',
            __('Transactions', 'SlimWp-Simple-Points'),
            __('Transactions', 'SlimWp-Simple-Points'),
            'manage_options',
            'slimwp-points',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'slimwp-points',
            __('User Consumption', 'SlimWp-Simple-Points'),
            __('User Consumption', 'SlimWp-Simple-Points'),
            'manage_options',
            'slimwp-user-consumption',
            array($this, 'user_consumption_page')
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'slimwp-points') !== false) {
            wp_enqueue_style('slimwp-admin', SLIMWP_PLUGIN_URL . 'includes/assets/css/admin.css', array(), SLIMWP_VERSION);
            wp_enqueue_script('slimwp-admin', SLIMWP_PLUGIN_URL . 'includes/assets/js/admin.js', array('jquery'), SLIMWP_VERSION, true);
            
            wp_localize_script('slimwp-admin', 'slimwp_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('slimwp_nonce')
            ));
        }
    }
    
    public function admin_page() {
        global $wpdb;
        
        // Handle bulk actions
        if (isset($_POST['action']) && $_POST['action'] === 'bulk_update' && check_admin_referer('slimwp_bulk_action')) {
            $this->handle_bulk_update();
        }
        
        // Get filter parameters
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 50;
        $offset = ($page - 1) * $per_page;
        
        // Build query
        $where = array('1=1');
        $where_values = array();
        
        if ($user_id > 0) {
            $where[] = 'user_id = %d';
            $where_values[] = $user_id;
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Get total count
        $total_items = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE $where_clause",
            ...$where_values
        ));
        
        // Get transactions
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE $where_clause 
            ORDER BY id DESC 
            LIMIT %d OFFSET %d",
            array_merge($where_values, array($per_page, $offset))
        ));
        
        // Get user summary stats
        $user_stats = $wpdb->get_row(
            "SELECT 
                COUNT(DISTINCT user_id) as total_users,
                COUNT(*) as total_count
            FROM {$wpdb->usermeta}
            WHERE meta_key IN ('slimwp_points_balance', 'slimwp_points_balance_permanent')
            AND meta_value > 0"
        );
        
        // Calculate totals from user meta
        $total_free_points = $wpdb->get_var(
            "SELECT SUM(meta_value) FROM {$wpdb->usermeta} 
            WHERE meta_key = 'slimwp_points_balance' AND meta_value > 0"
        ) ?: 0;
        
        $total_permanent_points = $wpdb->get_var(
            "SELECT SUM(meta_value) FROM {$wpdb->usermeta} 
            WHERE meta_key = 'slimwp_points_balance_permanent' AND meta_value > 0"
        ) ?: 0;
        
        $total_users = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} 
            WHERE meta_key IN ('slimwp_points_balance', 'slimwp_points_balance_permanent')"
        );
        
        $total_points = floatval($total_free_points) + floatval($total_permanent_points);
        
        // Get recent activity stats
        $today_transactions = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE DATE(created_at) = CURDATE()"
        );
        
        $active_users_today = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$this->table_name} WHERE DATE(created_at) = CURDATE()"
        );
        
        ?>
        <style>
            .wrap { margin: 0; }
            .slimwp-wrap { background: #f0f0f1; min-height: 100vh; margin: 0; padding: 0; }
            .slimwp-header { background: #fff; padding: 20px 32px; margin: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); position: relative; z-index: 10; }
            .slimwp-header h1 { margin: 0; font-size: 24px; font-weight: 600; color: #1d2327; line-height: 1.3; }
            .slimwp-content { padding: 32px 20px; }
            .slimwp-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 32px; }
            .stat-card { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; }
            .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
            .stat-card h3 { margin: 0 0 8px; font-size: 13px; font-weight: 500; color: #50575e; text-transform: uppercase; letter-spacing: 0.5px; }
            .stat-card .value { font-size: 32px; font-weight: 600; color: #1d2327; line-height: 1; }
            .stat-card .meta { font-size: 13px; color: #8c8f94; margin-top: 8px; }
            .stat-card .breakdown { font-size: 12px; color: #8c8f94; margin-top: 12px; padding-top: 12px; border-top: 1px solid #e1e1e1; }
            .stat-card .breakdown span { display: block; margin-top: 4px; }
            .bulk-update-card { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 24px; }
            .bulk-update-card h3 { margin: 0 0 20px; font-size: 16px; font-weight: 600; color: #1d2327; }
            .bulk-form { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 2fr auto; gap: 16px; align-items: end; }
            .form-group { display: flex; flex-direction: column; }
            .form-group label { font-size: 13px; font-weight: 500; color: #1d2327; margin-bottom: 6px; }
            .form-group input, .form-group select { padding: 8px 12px; border: 1px solid #dcdcde; border-radius: 4px; font-size: 14px; width: 100%; box-sizing: border-box; }
            .form-group input:focus, .form-group select:focus { border-color: #2271b1; outline: none; box-shadow: 0 0 0 1px #2271b1; }
            .btn-update { background: #2271b1; color: #fff; border: none; padding: 8px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; transition: background 0.2s; white-space: nowrap; }
            .btn-update:hover { background: #135e96; }
            .filter-bar { background: #fff; border-radius: 8px; padding: 16px 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .filter-bar form { display: flex; align-items: center; gap: 12px; margin: 0; flex-wrap: wrap; }
            .transactions-table { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .transactions-table table { margin: 0; border: none; }
            .transactions-table th { background: #f6f7f7; font-weight: 600; color: #1d2327; padding: 16px; text-align: left; border-bottom: 1px solid #e1e1e1; }
            .transactions-table td { padding: 16px; vertical-align: middle; }
            .transactions-table tr:hover td { background: #f6f7f7; }
            .user-info { display: flex; align-items: center; gap: 12px; }
            .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: #f0f0f1; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #50575e; flex-shrink: 0; }
            .user-details a { color: #2271b1; text-decoration: none; font-weight: 500; }
            .user-details a:hover { color: #135e96; }
            .user-details small { display: block; color: #8c8f94; font-size: 12px; margin-top: 2px; }
            .amount { font-weight: 600; font-size: 15px; }
            .amount.positive { color: #00a32a; }
            .amount.negative { color: #d63638; }
            .balance-col { font-weight: 500; }
            .balance-breakdown { font-size: 12px; color: #8c8f94; margin-top: 2px; }
            .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
            .badge-manual { background: #e7f2fd; color: #135e96; }
            .badge-registration { background: #edfaef; color: #00a32a; }
            .badge-daily_login { background: #fcf0e4; color: #996800; }
            .badge-daily_reset { background: #e6f4ea; color: #0f5132; }
            .badge-monthly_reset { background: #f8d7da; color: #842029; }
            .badge-admin_adjustment { background: #fef1f1; color: #d63638; }
            .badge-bulk_update { background: #f0f0f1; color: #50575e; }
            .badge-balance_reset { background: #cff4fc; color: #055160; }
            .balance-type { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 500; text-transform: uppercase; margin-left: 8px; }
            .balance-type-free { background: #e7f2fd; color: #135e96; }
            .balance-type-permanent { background: #edfaef; color: #00a32a; }
            .balance-type-mixed { background: #fcf0e4; color: #996800; }
            .pagination-wrap { margin-top: 24px; text-align: center; }
            .pagination-wrap .page-numbers { padding: 6px 12px; margin: 0 2px; border: 1px solid #dcdcde; border-radius: 4px; text-decoration: none; color: #50575e; transition: all 0.2s; display: inline-block; }
            .pagination-wrap .page-numbers:hover { border-color: #2271b1; color: #2271b1; }
            .pagination-wrap .page-numbers.current { background: #2271b1; color: #fff; border-color: #2271b1; }
            
            /* Responsive Design */
            @media (max-width: 1200px) { 
                .bulk-form { grid-template-columns: 1fr 1fr; }
                .bulk-form .btn-update { grid-column: 1 / -1; }
            }
            @media (max-width: 768px) {
                .slimwp-header { padding: 16px 20px; }
                .slimwp-header h1 { font-size: 20px; }
                .slimwp-content { padding: 20px 16px; }
                .slimwp-stats { grid-template-columns: 1fr; gap: 16px; }
                .stat-card { padding: 20px; }
                .stat-card .value { font-size: 28px; }
                .bulk-form { grid-template-columns: 1fr; }
                .transactions-table { overflow-x: auto; }
                .transactions-table table { min-width: 700px; }
                .filter-bar form { flex-direction: column; align-items: stretch; }
                .filter-bar input, .filter-bar button { width: 100%; }
            }
            @media (max-width: 480px) {
                .user-info { flex-direction: column; align-items: flex-start; gap: 8px; }
                .user-avatar { width: 32px; height: 32px; font-size: 14px; }
                .transactions-table th, .transactions-table td { padding: 12px; font-size: 13px; }
                .amount { font-size: 14px; }
            }
        </style>
        
        <div class="wrap">
            <div class="slimwp-wrap">
                <div class="slimwp-header">
                    <h1>🎯 SlimWP Points System</h1>
                </div>
                
                <div class="slimwp-content">
                    <!-- Summary Cards -->
                    <div class="slimwp-stats">
                        <div class="stat-card">
                            <h3>Total Users</h3>
                            <div class="value"><?php echo number_format($total_users); ?></div>
                            <div class="meta">Active in points system</div>
                        </div>
                        <div class="stat-card">
                            <h3>Total Points</h3>
                            <div class="value"><?php echo number_format($total_points, 0); ?></div>
                            <div class="meta">Across all users</div>
                            <div class="breakdown">
                                <span>Free: <?php echo number_format($total_free_points, 0); ?></span>
                                <span>Permanent: <?php echo number_format($total_permanent_points, 0); ?></span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <h3>Today's Activity</h3>
                            <div class="value"><?php echo number_format($today_transactions); ?></div>
                            <div class="meta"><?php echo number_format($active_users_today); ?> active users</div>
                        </div>
                        <div class="stat-card">
                            <h3>Average Balance</h3>
                            <div class="value"><?php echo $total_users > 0 ? number_format($total_points / $total_users, 0) : '0'; ?></div>
                            <div class="meta">Points per user</div>
                        </div>
                    </div>
                    
                    <!-- Bulk Update Form -->
                    <div class="bulk-update-card">
                        <h3>Bulk Update Points</h3>
                        <form method="post" class="bulk-form">
                            <?php wp_nonce_field('slimwp_bulk_action'); ?>
                            <input type="hidden" name="action" value="bulk_update">
                            <div class="form-group">
                                <label>User(s)</label>
                                <input type="text" name="user_ids" placeholder="User IDs (comma separated) or 'all'">
                            </div>
                            <div class="form-group">
                                <label>Action</label>
                                <select name="operation">
                                    <option value="add">Add Points</option>
                                    <option value="subtract">Subtract Points</option>
                                    <option value="set">Set Balance</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Balance Type</label>
                                <select name="balance_type">
                                    <option value="free">Free Balance</option>
                                    <option value="permanent">Permanent Balance</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Amount</label>
                                <input type="number" name="amount" step="0.01" required placeholder="0.00">
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <input type="text" name="description" required placeholder="Reason for update">
                            </div>
                            <button type="submit" class="btn-update">Update Points</button>
                        </form>
                    </div>
                    
                    <!-- Filters -->
                    <div class="filter-bar">
                        <form method="get">
                            <input type="hidden" name="page" value="slimwp-points">
                            <label style="margin: 0; font-weight: 500;">Filter by User:</label>
                            <input type="number" name="user_id" value="<?php echo esc_attr($user_id); ?>" placeholder="User ID" style="width: 120px; padding: 6px 12px; border: 1px solid #dcdcde; border-radius: 4px;">
                            <button type="submit" class="button button-primary">Filter</button>
                            <?php if ($user_id): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=slimwp-points')); ?>" class="button">Clear Filter</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <!-- Transactions Table -->
                    <div class="transactions-table">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">ID</th>
                                    <th>User</th>
                                    <th style="width: 120px;">Amount</th>
                                    <th style="width: 180px;">Balance After</th>
                                    <th>Description</th>
                                    <th style="width: 120px;">Type</th>
                                    <th style="width: 160px;">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 40px;">
                                            No transactions found. Start by adding points to users.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <?php 
                                        $user = get_userdata($transaction->user_id);
                                        $balance_type = isset($transaction->balance_type) ? $transaction->balance_type : 'free';
                                        $free_after = isset($transaction->balance_after) ? $transaction->balance_after : 0;
                                        $permanent_after = isset($transaction->permanent_balance_after) ? $transaction->permanent_balance_after : 0;
                                        $total_after = $free_after + $permanent_after;
                                        ?>
                                        <tr>
                                            <td style="color: #8c8f94;">#<?php echo esc_html($transaction->id); ?></td>
                                            <td>
                                                <div class="user-info">
                                                    <div class="user-avatar">
                                                        <?php echo $user ? esc_html(strtoupper(substr($user->display_name, 0, 1))) : '?'; ?>
                                                    </div>
                                                    <div class="user-details">
                                                        <?php if ($user): ?>
                                                            <a href="<?php echo esc_url(get_edit_user_link($transaction->user_id)); ?>">
                                                                <?php echo esc_html($user->display_name); ?>
                                                            </a>
                                                            <small><?php echo esc_html($user->user_email); ?></small>
                                                        <?php else: ?>
                                                            <span style="color: #8c8f94;">User #<?php echo esc_html($transaction->user_id); ?> (deleted)</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="amount <?php echo $transaction->amount >= 0 ? 'positive' : 'negative'; ?>">
                                                    <?php echo $transaction->amount >= 0 ? '+' : ''; ?><?php echo number_format($transaction->amount, 2); ?>
                                                </span>
                                                <span class="balance-type balance-type-<?php echo esc_attr($balance_type); ?>">
                                                    <?php echo esc_html($balance_type); ?>
                                                </span>
                                            </td>
                                            <td class="balance-col">
                                                <?php echo number_format($total_after, 2); ?>
                                                <div class="balance-breakdown">
                                                    Free: <?php echo number_format($free_after, 2); ?> | Perm: <?php echo number_format($permanent_after, 2); ?>
                                                </div>
                                            </td>
                                            <td><?php echo esc_html($transaction->description); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo esc_attr($transaction->transaction_type); ?>">
                                                    <?php echo esc_html(str_replace('_', ' ', $transaction->transaction_type)); ?>
                                                </span>
                                            </td>
                                            <td style="color: #50575e;">
                                                <?php echo esc_html(date('M d, Y', strtotime($transaction->created_at))); ?><br>
                                                <small style="color: #8c8f94;"><?php echo esc_html(date('g:i A', strtotime($transaction->created_at))); ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php
                    $total_pages = ceil($total_items / $per_page);
                    if ($total_pages > 1):
                        $args = array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '← Previous',
                            'next_text' => 'Next →',
                            'total' => $total_pages,
                            'current' => $page
                        );
                        ?>
                        <div class="pagination-wrap">
                            <?php echo wp_kses_post(paginate_links($args)); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function handle_bulk_update() {
        // Additional security check
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access attempt logged.', 'Security Error', array('response' => 403));
        }
        
        $user_ids = sanitize_text_field($_POST['user_ids']);
        $operation = sanitize_text_field($_POST['operation']);
        $amount = floatval($_POST['amount']);
        $description = sanitize_text_field($_POST['description']);
        $balance_type = isset($_POST['balance_type']) ? sanitize_text_field($_POST['balance_type']) : 'free';
        
        // Validate operation type
        $allowed_operations = array('add', 'subtract', 'set');
        if (!in_array($operation, $allowed_operations)) {
            error_log('SlimWP Security: Invalid operation attempted: ' . $operation);
            wp_die('Invalid operation.', 'Security Error', array('response' => 400));
        }
        
        // Validate balance type
        $allowed_balance_types = array('free', 'permanent');
        if (!in_array($balance_type, $allowed_balance_types)) {
            error_log('SlimWP Security: Invalid balance type attempted: ' . $balance_type);
            wp_die('Invalid balance type.', 'Security Error', array('response' => 400));
        }
        
        // Validate amount
        if ($amount < 0 || $amount > 999999999) {
            error_log('SlimWP Security: Invalid amount attempted: ' . $amount);
            wp_die('Invalid amount. Must be between 0 and 999,999,999.', 'Security Error', array('response' => 400));
        }
        
        // Validate description
        if (empty($description) || strlen($description) > 255) {
            wp_die('Description is required and must be less than 255 characters.', 'Validation Error', array('response' => 400));
        }
        
        if ($user_ids === 'all') {
            // Additional confirmation for 'all' users operation
            $total_users = count_users();
            if ($total_users['total_users'] > 1000) {
                error_log('SlimWP Security: Bulk operation attempted on ' . $total_users['total_users'] . ' users');
                wp_die('Bulk operation on more than 1000 users requires manual confirmation. Please contact administrator.', 'Security Limit', array('response' => 400));
            }
            $users = get_users(array('fields' => 'ID', 'number' => 1000)); // Limit to 1000 users max
        } else {
            // Validate and sanitize user IDs
            $user_id_array = array_map('trim', explode(',', $user_ids));
            $users = array();
            
            foreach ($user_id_array as $user_id) {
                $user_id = intval($user_id);
                if ($user_id > 0 && user_can($user_id, 'read')) { // Verify user exists and is valid
                    $users[] = $user_id;
                }
            }
            
            // Limit bulk operations to 100 users at once
            if (count($users) > 100) {
                error_log('SlimWP Security: Bulk operation attempted on ' . count($users) . ' users');
                wp_die('Bulk operations are limited to 100 users at once for security reasons.', 'Security Limit', array('response' => 400));
            }
        }
        
        // Log the bulk operation attempt
        error_log('SlimWP Admin: Bulk operation initiated - Operation: ' . $operation . ', Users: ' . count($users) . ', Amount: ' . $amount . ', Balance Type: ' . $balance_type);
        
        $success = 0;
        $errors = array();
        
        foreach ($users as $user_id) {
            $result = false;
            
            if ($operation === 'add') {
                $result = $this->points_system->add_points($user_id, $amount, $description, 'bulk_update', $balance_type);
            } elseif ($operation === 'subtract') {
                $result = $this->points_system->subtract_points($user_id, $amount, $description, 'bulk_update');
            } elseif ($operation === 'set') {
                $result = $this->points_system->set_balance($user_id, $amount, $description, 'bulk_update', $balance_type);
            }
            
            if (!is_wp_error($result)) {
                $success++;
            } else {
                $errors[] = "User $user_id: " . $result->get_error_message();
            }
        }
        
        if ($success > 0) {
            echo '<div class="notice notice-success is-dismissible" style="margin: 20px 20px 0; padding: 12px 20px; border-left: 4px solid #00a32a; background: #fff;">
                    <p style="margin: 0; font-size: 14px;">
                        <strong>✅ Success!</strong> Updated ' . esc_html($balance_type) . ' balance for ' . esc_html($success) . ' user' . ($success !== 1 ? 's' : '') . '.
                    </p>
                  </div>';
        }
        
        if (!empty($errors)) {
            echo '<div class="notice notice-error is-dismissible" style="margin: 20px 20px 0; padding: 12px 20px; border-left: 4px solid #d63638; background: #fff;">
                    <p style="margin: 0 0 8px; font-size: 14px;"><strong>⚠️ Some updates failed:</strong></p>';
            foreach ($errors as $error) {
                echo '<p style="margin: 0 0 4px; font-size: 13px;">• ' . esc_html($error) . '</p>';
            }
            echo '</div>';
        }
    }
    
    public function user_consumption_page() {
        global $wpdb;
        
        // Handle quick point adjustments
        if (isset($_POST['action']) && $_POST['action'] === 'quick_adjust' && check_admin_referer('slimwp_quick_adjust')) {
            $this->handle_quick_adjust();
        }
        
        // Get filter parameters
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $min_balance = isset($_GET['min_balance']) ? floatval($_GET['min_balance']) : 0;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20; // Fixed at 20 users per page as requested
        $offset = ($page - 1) * $per_page;
        
        // Build WHERE conditions
        $where_conditions = array();
        $where_values = array();
        
        if (!empty($search)) {
            $where_conditions[] = "(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if ($min_balance > 0) {
            $where_conditions[] = "(COALESCE(um_free.meta_value, 0) + COALESCE(um_perm.meta_value, 0)) >= %f";
            $where_values[] = $min_balance;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count for pagination
        $count_query = "
            SELECT COUNT(DISTINCT u.ID)
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um_free ON (u.ID = um_free.user_id AND um_free.meta_key = 'slimwp_points_balance')
            LEFT JOIN {$wpdb->usermeta} um_perm ON (u.ID = um_perm.user_id AND um_perm.meta_key = 'slimwp_points_balance_permanent')
            $where_clause
            AND (um_free.meta_value IS NOT NULL OR um_perm.meta_value IS NOT NULL)
        ";
        
        $total_users = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
        
        // Main consumption query - optimized for performance
        $consumption_query = "
            SELECT 
                u.ID, u.display_name, u.user_email, u.user_registered,
                COALESCE(um_free.meta_value, 0) as free_balance,
                COALESCE(um_perm.meta_value, 0) as permanent_balance,
                COALESCE(today.consumed, 0) as today_consumed,
                COALESCE(yesterday.consumed, 0) as yesterday_consumed,
                COALESCE(week.consumed, 0) as week_consumed,
                COALESCE(month.consumed, 0) as month_consumed,
                COALESCE(last_activity.last_transaction, NULL) as last_activity
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um_free ON (u.ID = um_free.user_id AND um_free.meta_key = 'slimwp_points_balance')
            LEFT JOIN {$wpdb->usermeta} um_perm ON (u.ID = um_perm.user_id AND um_perm.meta_key = 'slimwp_points_balance_permanent')
            LEFT JOIN (
                SELECT user_id, SUM(ABS(amount)) as consumed 
                FROM {$this->table_name} 
                WHERE DATE(created_at) = CURDATE() AND amount < 0 
                GROUP BY user_id
            ) today ON u.ID = today.user_id
            LEFT JOIN (
                SELECT user_id, SUM(ABS(amount)) as consumed 
                FROM {$this->table_name} 
                WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND amount < 0 
                GROUP BY user_id
            ) yesterday ON u.ID = yesterday.user_id
            LEFT JOIN (
                SELECT user_id, SUM(ABS(amount)) as consumed 
                FROM {$this->table_name} 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND amount < 0 
                GROUP BY user_id
            ) week ON u.ID = week.user_id
            LEFT JOIN (
                SELECT user_id, SUM(ABS(amount)) as consumed 
                FROM {$this->table_name} 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND amount < 0 
                GROUP BY user_id
            ) month ON u.ID = month.user_id
            LEFT JOIN (
                SELECT user_id, MAX(created_at) as last_transaction 
                FROM {$this->table_name} 
                GROUP BY user_id
            ) last_activity ON u.ID = last_activity.user_id
            $where_clause
            AND (um_free.meta_value IS NOT NULL OR um_perm.meta_value IS NOT NULL)
            ORDER BY today_consumed DESC, (COALESCE(um_free.meta_value, 0) + COALESCE(um_perm.meta_value, 0)) DESC
            LIMIT %d OFFSET %d
        ";
        
        $query_params = array_merge($where_values, array($per_page, $offset));
        $users_data = $wpdb->get_results($wpdb->prepare($consumption_query, $query_params));
        
        // Get summary statistics
        $summary_stats = $wpdb->get_row("
            SELECT 
                COUNT(DISTINCT u.ID) as total_users_with_points,
                SUM(COALESCE(um_free.meta_value, 0)) as total_free_points,
                SUM(COALESCE(um_perm.meta_value, 0)) as total_permanent_points,
                COUNT(DISTINCT today_active.user_id) as active_today,
                COALESCE(SUM(today_consumed.total), 0) as total_consumed_today
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um_free ON (u.ID = um_free.user_id AND um_free.meta_key = 'slimwp_points_balance')
            LEFT JOIN {$wpdb->usermeta} um_perm ON (u.ID = um_perm.user_id AND um_perm.meta_key = 'slimwp_points_balance_permanent')
            LEFT JOIN (
                SELECT DISTINCT user_id FROM {$this->table_name} WHERE DATE(created_at) = CURDATE()
            ) today_active ON u.ID = today_active.user_id
            LEFT JOIN (
                SELECT user_id, SUM(ABS(amount)) as total 
                FROM {$this->table_name} 
                WHERE DATE(created_at) = CURDATE() AND amount < 0 
                GROUP BY user_id
            ) today_consumed ON u.ID = today_consumed.user_id
            WHERE (um_free.meta_value IS NOT NULL OR um_perm.meta_value IS NOT NULL)
        ");
        
        $total_points = floatval($summary_stats->total_free_points) + floatval($summary_stats->total_permanent_points);
        $avg_balance = $summary_stats->total_users_with_points > 0 ? $total_points / $summary_stats->total_users_with_points : 0;
        
        ?>
        <style>
            .wrap { margin: 0; }
            .slimwp-wrap { background: #f0f0f1; min-height: 100vh; margin: 0; padding: 0; }
            .slimwp-header { background: #fff; padding: 20px 32px; margin: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); position: relative; z-index: 10; }
            .slimwp-header h1 { margin: 0; font-size: 24px; font-weight: 600; color: #1d2327; line-height: 1.3; }
            .slimwp-content { padding: 32px 20px; }
            .slimwp-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 32px; }
            .stat-card { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; }
            .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
            .stat-card h3 { margin: 0 0 8px; font-size: 13px; font-weight: 500; color: #50575e; text-transform: uppercase; letter-spacing: 0.5px; }
            .stat-card .value { font-size: 32px; font-weight: 600; color: #1d2327; line-height: 1; }
            .stat-card .meta { font-size: 13px; color: #8c8f94; margin-top: 8px; }
            .stat-card .breakdown { font-size: 12px; color: #8c8f94; margin-top: 12px; padding-top: 12px; border-top: 1px solid #e1e1e1; }
            .stat-card .breakdown span { display: block; margin-top: 4px; }
            .filter-bar { background: #fff; border-radius: 8px; padding: 16px 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .filter-bar form { display: flex; align-items: center; gap: 12px; margin: 0; flex-wrap: wrap; }
            .filter-bar input, .filter-bar button { padding: 8px 12px; border: 1px solid #dcdcde; border-radius: 4px; font-size: 14px; }
            .filter-bar input:focus { border-color: #2271b1; outline: none; box-shadow: 0 0 0 1px #2271b1; }
            .consumption-table { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .consumption-table table { margin: 0; border: none; width: 100%; }
            .consumption-table th { background: #f6f7f7; font-weight: 600; color: #1d2327; padding: 16px; text-align: left; border-bottom: 1px solid #e1e1e1; }
            .consumption-table td { padding: 16px; vertical-align: middle; border-bottom: 1px solid #f0f0f1; }
            .consumption-table tr:hover td { background: #f6f7f7; }
            .user-info { display: flex; align-items: center; gap: 12px; }
            .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: #f0f0f1; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #50575e; flex-shrink: 0; }
            .user-details a { color: #2271b1; text-decoration: none; font-weight: 500; }
            .user-details a:hover { color: #135e96; }
            .user-details small { display: block; color: #8c8f94; font-size: 12px; margin-top: 2px; }
            .balance-display { text-align: center; }
            .balance-total { font-weight: 600; font-size: 16px; color: #1d2327; }
            .balance-breakdown { font-size: 11px; color: #8c8f94; margin-top: 4px; }
            .consumption-cell { text-align: center; font-weight: 500; }
            .consumption-high { color: #d63638; }
            .consumption-medium { color: #996800; }
            .consumption-low { color: #00a32a; }
            .consumption-none { color: #8c8f94; }
            .activity-indicator { font-size: 11px; padding: 2px 6px; border-radius: 3px; text-transform: uppercase; font-weight: 500; }
            .activity-today { background: #edfaef; color: #00a32a; }
            .activity-recent { background: #fcf0e4; color: #996800; }
            .activity-old { background: #f0f0f1; color: #8c8f94; }
            .quick-actions { display: flex; gap: 4px; }
            .quick-btn { padding: 4px 8px; font-size: 11px; border: 1px solid #dcdcde; background: #fff; border-radius: 3px; cursor: pointer; text-decoration: none; color: #50575e; }
            .quick-btn:hover { border-color: #2271b1; color: #2271b1; }
            .pagination-wrap { margin-top: 24px; text-align: center; }
            .pagination-wrap .page-numbers { padding: 6px 12px; margin: 0 2px; border: 1px solid #dcdcde; border-radius: 4px; text-decoration: none; color: #50575e; transition: all 0.2s; display: inline-block; }
            .pagination-wrap .page-numbers:hover { border-color: #2271b1; color: #2271b1; }
            .pagination-wrap .page-numbers.current { background: #2271b1; color: #fff; border-color: #2271b1; }
            
            /* Responsive Design */
            @media (max-width: 768px) {
                .slimwp-header { padding: 16px 20px; }
                .slimwp-header h1 { font-size: 20px; }
                .slimwp-content { padding: 20px 16px; }
                .slimwp-stats { grid-template-columns: 1fr; gap: 16px; }
                .stat-card { padding: 20px; }
                .stat-card .value { font-size: 28px; }
                .consumption-table { overflow-x: auto; }
                .consumption-table table { min-width: 900px; }
                .filter-bar form { flex-direction: column; align-items: stretch; }
                .filter-bar input, .filter-bar button { width: 100%; }
            }
        </style>
        
        <div class="wrap">
            <div class="slimwp-wrap">
                <div class="slimwp-header">
                    <h1>📊 User Consumption Dashboard</h1>
                </div>
                
                <div class="slimwp-content">
                    <!-- Summary Cards -->
                    <div class="slimwp-stats">
                        <div class="stat-card">
                            <h3>Total Users</h3>
                            <div class="value"><?php echo number_format($summary_stats->total_users_with_points); ?></div>
                            <div class="meta">With points balance</div>
                        </div>
                        <div class="stat-card">
                            <h3>Active Today</h3>
                            <div class="value"><?php echo number_format($summary_stats->active_today); ?></div>
                            <div class="meta">Users with transactions</div>
                        </div>
                        <div class="stat-card">
                            <h3>Consumed Today</h3>
                            <div class="value"><?php echo number_format($summary_stats->total_consumed_today, 0); ?></div>
                            <div class="meta">Total points spent</div>
                        </div>
                        <div class="stat-card">
                            <h3>Average Balance</h3>
                            <div class="value"><?php echo number_format($avg_balance, 0); ?></div>
                            <div class="meta">Points per user</div>
                            <div class="breakdown">
                                <span>Free: <?php echo number_format($summary_stats->total_free_points, 0); ?></span>
                                <span>Permanent: <?php echo number_format($summary_stats->total_permanent_points, 0); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <div class="filter-bar">
                        <form method="get">
                            <input type="hidden" name="page" value="slimwp-user-consumption">
                            <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="Search users..." style="width: 200px;">
                            <input type="number" name="min_balance" value="<?php echo esc_attr($min_balance); ?>" placeholder="Min balance" style="width: 120px;" step="0.01">
                            <button type="submit" class="button button-primary">Filter</button>
                            <?php if (!empty($search) || $min_balance > 0): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=slimwp-user-consumption')); ?>" class="button">Clear Filters</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <!-- Consumption Table -->
                    <div class="consumption-table">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 200px;">User</th>
                                    <th style="width: 140px;">Current Balance</th>
                                    <th style="width: 80px;">Today</th>
                                    <th style="width: 80px;">Yesterday</th>
                                    <th style="width: 80px;">Last 7d</th>
                                    <th style="width: 80px;">Last 30d</th>
                                    <th style="width: 100px;">Last Activity</th>
                                    <th style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users_data)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 40px;">
                                            No users found with the current filters.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users_data as $user_data): ?>
                                        <?php 
                                        $total_balance = floatval($user_data->free_balance) + floatval($user_data->permanent_balance);
                                        $today_consumed = floatval($user_data->today_consumed);
                                        $yesterday_consumed = floatval($user_data->yesterday_consumed);
                                        $week_consumed = floatval($user_data->week_consumed);
                                        $month_consumed = floatval($user_data->month_consumed);
                                        
                                        // Determine consumption levels for color coding
                                        $today_class = $today_consumed > 100 ? 'consumption-high' : ($today_consumed > 10 ? 'consumption-medium' : ($today_consumed > 0 ? 'consumption-low' : 'consumption-none'));
                                        $yesterday_class = $yesterday_consumed > 100 ? 'consumption-high' : ($yesterday_consumed > 10 ? 'consumption-medium' : ($yesterday_consumed > 0 ? 'consumption-low' : 'consumption-none'));
                                        $week_class = $week_consumed > 500 ? 'consumption-high' : ($week_consumed > 50 ? 'consumption-medium' : ($week_consumed > 0 ? 'consumption-low' : 'consumption-none'));
                                        $month_class = $month_consumed > 2000 ? 'consumption-high' : ($month_consumed > 200 ? 'consumption-medium' : ($month_consumed > 0 ? 'consumption-low' : 'consumption-none'));
                                        
                                        // Activity indicator
                                        $activity_class = 'activity-old';
                                        $activity_text = 'Inactive';
                                        if ($user_data->last_activity) {
                                            $last_activity_time = strtotime($user_data->last_activity);
                                            $hours_ago = (time() - $last_activity_time) / 3600;
                                            if ($hours_ago < 24) {
                                                $activity_class = 'activity-today';
                                                $activity_text = 'Today';
                                            } elseif ($hours_ago < 168) { // 7 days
                                                $activity_class = 'activity-recent';
                                                $activity_text = 'Recent';
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <div class="user-avatar">
                                                        <?php echo esc_html(strtoupper(substr($user_data->display_name, 0, 1))); ?>
                                                    </div>
                                                    <div class="user-details">
                                                        <a href="<?php echo esc_url(get_edit_user_link($user_data->ID)); ?>">
                                                            <?php echo esc_html($user_data->display_name); ?>
                                                        </a>
                                                        <small><?php echo esc_html($user_data->user_email); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="balance-display">
                                                <div class="balance-total"><?php echo number_format($total_balance, 2); ?></div>
                                                <div class="balance-breakdown">
                                                    Free: <?php echo number_format($user_data->free_balance, 0); ?><br>
                                                    Perm: <?php echo number_format($user_data->permanent_balance, 0); ?>
                                                </div>
                                            </td>
                                            <td class="consumption-cell <?php echo esc_attr($today_class); ?>">
                                                <?php echo $today_consumed > 0 ? number_format($today_consumed, 0) : '-'; ?>
                                            </td>
                                            <td class="consumption-cell <?php echo esc_attr($yesterday_class); ?>">
                                                <?php echo $yesterday_consumed > 0 ? number_format($yesterday_consumed, 0) : '-'; ?>
                                            </td>
                                            <td class="consumption-cell <?php echo esc_attr($week_class); ?>">
                                                <?php echo $week_consumed > 0 ? number_format($week_consumed, 0) : '-'; ?>
                                            </td>
                                            <td class="consumption-cell <?php echo esc_attr($month_class); ?>">
                                                <?php echo $month_consumed > 0 ? number_format($month_consumed, 0) : '-'; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <span class="activity-indicator <?php echo esc_attr($activity_class); ?>">
                                                    <?php echo esc_html($activity_text); ?>
                                                </span>
                                                <?php if ($user_data->last_activity): ?>
                                                    <br><small style="color: #8c8f94; font-size: 10px;">
                                                        <?php echo esc_html(date('M d', strtotime($user_data->last_activity))); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="quick-actions">
                                                    <a href="<?php echo esc_url(admin_url('admin.php?page=slimwp-points&user_id=' . $user_data->ID)); ?>" class="quick-btn">View</a>
                                                    <a href="<?php echo esc_url(get_edit_user_link($user_data->ID)); ?>" class="quick-btn">Edit</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php
                    $total_pages = ceil($total_users / $per_page);
                    if ($total_pages > 1):
                        $base_url = admin_url('admin.php?page=slimwp-user-consumption');
                        if (!empty($search)) $base_url = add_query_arg('search', urlencode($search), $base_url);
                        if ($min_balance > 0) $base_url = add_query_arg('min_balance', $min_balance, $base_url);
                        
                        $args = array(
                            'base' => add_query_arg('paged', '%#%', $base_url),
                            'format' => '',
                            'prev_text' => '← Previous',
                            'next_text' => 'Next →',
                            'total' => $total_pages,
                            'current' => $page
                        );
                        ?>
                        <div class="pagination-wrap">
                            <?php echo wp_kses_post(paginate_links($args)); ?>
                            <p style="margin-top: 16px; color: #8c8f94; font-size: 13px;">
                                Showing <?php echo esc_html((($page - 1) * $per_page) + 1); ?>-<?php echo esc_html(min($page * $per_page, $total_users)); ?> of <?php echo esc_html(number_format($total_users)); ?> users
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function handle_quick_adjust() {
        $user_id = intval($_POST['user_id']);
        $operation = sanitize_text_field($_POST['operation']);
        $amount = floatval($_POST['amount']);
        $description = sanitize_text_field($_POST['description']);
        $balance_type = isset($_POST['balance_type']) ? sanitize_text_field($_POST['balance_type']) : 'free';
        
        $result = false;
        
        if ($operation === 'add') {
            $result = $this->points_system->add_points($user_id, $amount, $description, 'admin_adjustment', $balance_type);
        } elseif ($operation === 'subtract') {
            $result = $this->points_system->subtract_points($user_id, $amount, $description, 'admin_adjustment');
        }
        
        if (!is_wp_error($result)) {
            echo '<div class="notice notice-success is-dismissible">
                    <p><strong>Success!</strong> Points updated for user.</p>
                  </div>';
        } else {
            echo '<div class="notice notice-error is-dismissible">
                    <p><strong>Error:</strong> ' . esc_html($result->get_error_message()) . '</p>
                  </div>';
        }
    }
}
