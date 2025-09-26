<?php
if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_User_Profile {
    
    private $points_system;
    private $table_name;
    
    public function __construct($points_system) {
        $this->points_system = $points_system;
        $this->table_name = $points_system->get_table_name();
        
        add_action('show_user_profile', array($this, 'show_user_points_field'));
        add_action('edit_user_profile', array($this, 'show_user_points_field'));
        add_action('personal_options_update', array($this, 'save_user_points_field'));
        add_action('edit_user_profile_update', array($this, 'save_user_points_field'));
    }
    
    public function show_user_points_field($user) {
        if (!current_user_can('edit_users')) {
            return;
        }
        
        $free_balance = $this->points_system->get_free_balance($user->ID);
        $permanent_balance = $this->points_system->get_permanent_balance($user->ID);
        $total_balance = $free_balance + $permanent_balance;
        ?>
        <style>
            .slimwp-section { margin-top: 40px; }
            .slimwp-section h3 { font-size: 16px; font-weight: 600; margin-bottom: 20px; }
            .slimwp-balance-box { background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
            .balance-display { display: flex; align-items: baseline; gap: 8px; margin-bottom: 12px; }
            .balance-value { font-size: 32px; font-weight: 600; color: #1d2327; }
            .balance-label { font-size: 14px; color: #50575e; }
            .balance-breakdown { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px; padding-top: 16px; border-top: 1px solid #e1e1e1; }
            .balance-item { background: #fff; padding: 12px 16px; border-radius: 4px; border: 1px solid #e1e1e1; }
            .balance-item-label { font-size: 12px; color: #50575e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
            .balance-item-value { font-size: 20px; font-weight: 600; color: #1d2327; }
            .balance-input-wrap { margin-top: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
            .balance-input-group { }
            .balance-input-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
            .balance-input-group input { width: 100%; padding: 8px 12px; border: 1px solid #dcdcde; border-radius: 4px; }
            .balance-input-group .description { margin-top: 8px; font-size: 12px; color: #50575e; }
            .slimwp-transactions { margin-top: 30px; }
            .slimwp-transactions h4 { font-size: 15px; font-weight: 600; margin-bottom: 16px; }
            .slimwp-table { border: 1px solid #dcdcde; border-radius: 8px; overflow: hidden; background: #fff; }
            .slimwp-table table { margin: 0; border: none; }
            .slimwp-table th { background: #f6f7f7; font-weight: 500; font-size: 13px; color: #50575e; padding: 12px 16px; text-align: left; border-bottom: 1px solid #e1e1e1; }
            .slimwp-table td { padding: 12px 16px; font-size: 13px; }
            .slimwp-table tr:nth-child(even) td { background: #f9f9f9; }
            .slimwp-amount { font-weight: 600; }
            .slimwp-amount.positive { color: #00a32a; }
            .slimwp-amount.negative { color: #d63638; }
            .balance-type-tag { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 500; text-transform: uppercase; margin-left: 6px; }
            .balance-type-tag.free { background: #e7f2fd; color: #135e96; }
            .balance-type-tag.permanent { background: #edfaef; color: #00a32a; }
            .balance-type-tag.mixed { background: #fcf0e4; color: #996800; }
            .view-all-link { display: inline-block; margin-top: 12px; color: #2271b1; text-decoration: none; font-size: 14px; }
            .view-all-link:hover { color: #135e96; text-decoration: underline; }
            .no-transactions { text-align: center; padding: 40px; color: #8c8f94; }
        </style>
        
        <div class="slimwp-section">
            <h3>💰 SlimWP Points Balance</h3>
            
            <div class="slimwp-balance-box">
                <div class="balance-display">
                    <span class="balance-value"><?php echo number_format($total_balance, 2); ?></span>
                    <span class="balance-label">total points</span>
                </div>
                
                <div class="balance-breakdown">
                    <div class="balance-item">
                        <div class="balance-item-label">Free Balance</div>
                        <div class="balance-item-value"><?php echo number_format($free_balance, 2); ?></div>
                    </div>
                    <div class="balance-item">
                        <div class="balance-item-label">Permanent Balance</div>
                        <div class="balance-item-value"><?php echo number_format($permanent_balance, 2); ?></div>
                    </div>
                </div>
                
                <div class="balance-input-wrap">
                    <?php wp_nonce_field('slimwp_update_user_points', 'slimwp_user_points_nonce'); ?>
                    <div class="balance-input-group">
                        <label for="slimwp_points_balance_free">Free Balance</label>
                        <input type="number" name="slimwp_points_balance_free" id="slimwp_points_balance_free" value="<?php echo esc_attr($free_balance); ?>" step="0.01" />
                        <p class="description">This balance can be reset daily/monthly</p>
                    </div>
                    <div class="balance-input-group">
                        <label for="slimwp_points_balance_permanent">Permanent Balance</label>
                        <input type="number" name="slimwp_points_balance_permanent" id="slimwp_points_balance_permanent" value="<?php echo esc_attr($permanent_balance); ?>" step="0.01" />
                        <p class="description">This balance is never automatically reset</p>
                    </div>
                </div>
            </div>
            
            <div class="slimwp-transactions">
                <h4>Recent Transactions</h4>
                
                <?php
                global $wpdb;
                $transactions = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$this->table_name} WHERE user_id = %d ORDER BY id DESC LIMIT 10",
                    $user->ID
                ));
                
                if ($transactions): ?>
                    <div class="slimwp-table">
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Balance After</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $t): ?>
                                    <?php
                                    $balance_type = isset($t->balance_type) ? $t->balance_type : 'free';
                                    $free_after = isset($t->balance_after) ? $t->balance_after : 0;
                                    $permanent_after = isset($t->permanent_balance_after) ? $t->permanent_balance_after : 0;
                                    $total_after = $free_after + $permanent_after;
                                    ?>
                                    <tr>
                                        <td><?php echo date('M d, Y g:i A', strtotime($t->created_at)); ?></td>
                                        <td>
                                            <span class="slimwp-amount <?php echo $t->amount >= 0 ? 'positive' : 'negative'; ?>">
                                                <?php echo $t->amount >= 0 ? '+' : ''; ?><?php echo number_format($t->amount, 2); ?>
                                            </span>
                                            <span class="balance-type-tag <?php echo esc_attr($balance_type); ?>">
                                                <?php echo esc_html($balance_type); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo number_format($total_after, 2); ?>
                                            <div style="font-size: 11px; color: #8c8f94; margin-top: 2px;">
                                                F: <?php echo number_format($free_after, 2); ?> | P: <?php echo number_format($permanent_after, 2); ?>
                                            </div>
                                        </td>
                                        <td><?php echo esc_html($t->description); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="<?php echo admin_url('admin.php?page=slimwp-points&user_id=' . $user->ID); ?>" class="view-all-link">
                        View all transactions →
                    </a>
                <?php else: ?>
                    <div class="slimwp-table">
                        <div class="no-transactions">
                            No transactions found for this user yet.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function save_user_points_field($user_id) {
        if (!current_user_can('edit_users')) {
            return;
        }
        
        // Verify nonce for security
        if (!isset($_POST['slimwp_user_points_nonce']) || !wp_verify_nonce($_POST['slimwp_user_points_nonce'], 'slimwp_update_user_points')) {
            wp_die(__('Security check failed. Please try again.', 'SlimWp-Simple-Points'));
        }
        
        // Additional security: Verify user can edit this specific user
        if (!current_user_can('edit_user', $user_id)) {
            wp_die(__('You do not have permission to edit this user.', 'SlimWp-Simple-Points'));
        }
        
        $admin_user = wp_get_current_user()->display_name;
        
        // Handle free balance update
        if (isset($_POST['slimwp_points_balance_free'])) {
            $new_free_balance = floatval(sanitize_text_field($_POST['slimwp_points_balance_free']));
            
            // Validate balance is not negative
            if ($new_free_balance < 0) {
                wp_die(__('Balance cannot be negative.', 'SlimWp-Simple-Points'));
            }
            
            // Validate balance is not unreasonably large (prevent overflow)
            if ($new_free_balance > 999999999.99) {
                wp_die(__('Balance value is too large.', 'SlimWp-Simple-Points'));
            }
            
            $current_free_balance = $this->points_system->get_free_balance($user_id);
            
            if ($new_free_balance != $current_free_balance) {
                $result = $this->points_system->set_balance(
                    $user_id,
                    $new_free_balance,
                    'Free balance adjustment by ' . sanitize_text_field($admin_user),
                    'admin_adjustment',
                    'free'
                );
                
                if (is_wp_error($result)) {
                    wp_die($result->get_error_message());
                }
            }
        }
        
        // Handle permanent balance update
        if (isset($_POST['slimwp_points_balance_permanent'])) {
            $new_permanent_balance = floatval(sanitize_text_field($_POST['slimwp_points_balance_permanent']));
            
            // Validate balance is not negative
            if ($new_permanent_balance < 0) {
                wp_die(__('Balance cannot be negative.', 'SlimWp-Simple-Points'));
            }
            
            // Validate balance is not unreasonably large (prevent overflow)
            if ($new_permanent_balance > 999999999.99) {
                wp_die(__('Balance value is too large.', 'SlimWp-Simple-Points'));
            }
            
            $current_permanent_balance = $this->points_system->get_permanent_balance($user_id);
            
            if ($new_permanent_balance != $current_permanent_balance) {
                $result = $this->points_system->set_balance(
                    $user_id,
                    $new_permanent_balance,
                    'Permanent balance adjustment by ' . sanitize_text_field($admin_user),
                    'admin_adjustment',
                    'permanent'
                );
                
                if (is_wp_error($result)) {
                    wp_die($result->get_error_message());
                }
            }
        }
    }
}
