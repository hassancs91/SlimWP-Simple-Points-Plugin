<?php
if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_Stripe_Packages {
    
    private $points_system;
    
    public function __construct($points_system) {
        $this->points_system = $points_system;
        
        add_action('admin_menu', array($this, 'add_packages_menu'), 25);
    }
    
    public function add_packages_menu() {
        add_submenu_page(
            'slimwp-points',
            __('Stripe Packages', 'SlimWp-Simple-Points'),
            __('Stripe Packages', 'SlimWp-Simple-Points'),
            'manage_options',
            'slimwp-stripe-packages',
            array($this, 'packages_page')
        );
    }
    
    public function packages_page() {
        // Handle form submissions
        if ((isset($_POST['add_package']) || isset($_POST['edit_package'])) && check_admin_referer('slimwp_packages')) {
            $this->handle_package_actions();
        }
        
        // Handle bulk actions
        if (isset($_POST['bulk_action']) && check_admin_referer('slimwp_packages_bulk')) {
            $this->handle_bulk_actions();
        }
        
        // Handle individual actions
        if (isset($_GET['action']) && isset($_GET['package_id']) && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'slimwp_package_action')) {
                $this->handle_individual_actions();
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>❌ ' . __('Security check failed.', 'SlimWp-Simple-Points') . '</p></div>';
                });
            }
        }
        
        $packages = SlimWP_Stripe_Database::get_packages('all');
        $stripe_settings = get_option('slimwp_stripe_settings', array('currency' => 'USD'));
        ?>
        <style>
            .wrap { margin: 0; }
            .slimwp-packages-wrap { background: #f0f0f1; min-height: 100vh; margin: 0; padding: 0; }
            .slimwp-packages-header { background: #fff; padding: 20px 32px; margin: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); position: relative; z-index: 10; }
            .slimwp-packages-header h1 { margin: 0; font-size: 24px; font-weight: 600; color: #1d2327; line-height: 1.3; }
            .slimwp-packages-content { padding: 32px 20px; }
            .packages-card { background: #fff; border-radius: 8px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 24px; }
            .packages-card h2 { margin: 0 0 24px; font-size: 18px; font-weight: 600; color: #1d2327; padding-bottom: 16px; border-bottom: 1px solid #e1e1e1; }
            .add-package-form { background: #f6f7f7; border-radius: 8px; padding: 24px; margin-bottom: 32px; }
            .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 16px; }
            .form-field { display: flex; flex-direction: column; }
            .form-field label { margin-bottom: 4px; font-weight: 500; color: #1d2327; }
            .form-field input, .form-field textarea { padding: 8px 12px; border: 1px solid #dcdcde; border-radius: 4px; font-size: 14px; }
            .form-field input:focus, .form-field textarea:focus { border-color: #2271b1; outline: none; box-shadow: 0 0 0 1px #2271b1; }
            .packages-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            .packages-table th, .packages-table td { padding: 12px; text-align: left; border-bottom: 1px solid #e1e1e1; }
            .packages-table th { background: #f6f7f7; font-weight: 600; }
            .packages-table tr:hover { background: #f9f9f9; }
            .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
            .status-active { background: #d1e7dd; color: #0f5132; }
            .status-inactive { background: #f8d7da; color: #842029; }
            .action-buttons { display: flex; gap: 8px; }
            .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 13px; font-weight: 500; }
            .btn-primary { background: #2271b1; color: #fff; }
            .btn-secondary { background: #6c757d; color: #fff; }
            .btn-danger { background: #dc3545; color: #fff; }
            .btn:hover { opacity: 0.9; }
            .info-box { background: #e7f2fd; border: 1px solid #bee5eb; border-radius: 4px; padding: 12px 16px; margin: 16px 0; font-size: 13px; color: #004085; }
            .warning-box { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 12px 16px; margin: 16px 0; font-size: 13px; color: #856404; }
            .empty-state { text-align: center; padding: 60px 20px; color: #50575e; }
            .empty-state h3 { margin: 0 0 8px; font-size: 18px; }
            .empty-state p { margin: 0; }
            
            /* Responsive */
            @media (max-width: 768px) {
                .slimwp-packages-header { padding: 16px 20px; }
                .slimwp-packages-content { padding: 20px 16px; }
                .packages-card { padding: 20px; }
                .form-grid { grid-template-columns: 1fr; }
                .packages-table { font-size: 13px; }
                .action-buttons { flex-direction: column; }
            }
        </style>
        
        <div class="wrap">
            <div class="slimwp-packages-wrap">
                <div class="slimwp-packages-header">
                    <h1>📦 Stripe Packages Management</h1>
                </div>
                
                <div class="slimwp-packages-content">
                    <?php $this->render_stripe_status_check(); ?>
                    
                    <div class="packages-card">
                        <h2>Add New Package</h2>
                        
                        <form method="post" class="add-package-form">
                            <?php wp_nonce_field('slimwp_packages'); ?>
                            
                            <div class="form-grid">
                                <div class="form-field">
                                    <label for="package_name"><?php _e('Package Name', 'SlimWp-Simple-Points'); ?> *</label>
                                    <input type="text" id="package_name" name="package_name" placeholder="e.g., Starter Pack" required>
                                </div>
                                
                                <div class="form-field">
                                    <label for="package_points"><?php _e('Points', 'SlimWp-Simple-Points'); ?> *</label>
                                    <input type="number" id="package_points" name="package_points" placeholder="1000" min="1" required>
                                </div>
                                
                                <div class="form-field">
                                    <label for="package_price"><?php printf(__('Price (%s)', 'SlimWp-Simple-Points'), $stripe_settings['currency']); ?> *</label>
                                    <input type="number" id="package_price" name="package_price" placeholder="9.99" min="0.01" step="0.01" required>
                                </div>
                            </div>
                            
                            <div class="form-field">
                                <label for="package_description"><?php _e('Description (Optional)', 'SlimWp-Simple-Points'); ?></label>
                                <textarea id="package_description" name="package_description" rows="2" placeholder="Perfect for getting started"></textarea>
                            </div>
                            
                            <div style="margin-top: 20px;">
                                <button type="submit" name="add_package" class="btn btn-primary">
                                    <?php _e('Add Package', 'SlimWp-Simple-Points'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="packages-card">
                        <h2>Existing Packages</h2>
                        
                        <?php if (!empty($packages)): ?>
                            <?php if (count($packages) > 5): ?>
                                <div class="warning-box">
                                    <strong><?php _e('⚠️ Multiple Packages Detected:', 'SlimWp-Simple-Points'); ?></strong>
                                    <?php printf(__('You have %d packages. If these were created automatically, you can use the bulk delete option below to clean them up.', 'SlimWp-Simple-Points'), count($packages)); ?>
                                </div>
                                
                                <form method="post" style="margin-bottom: 20px;">
                                    <?php wp_nonce_field('slimwp_packages_bulk'); ?>
                                    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                                        <button type="submit" name="bulk_action" value="delete_all" class="btn btn-danger"
                                                onclick="return confirm('<?php _e('Are you sure you want to delete ALL packages? This action cannot be undone.', 'SlimWp-Simple-Points'); ?>')">
                                            <?php _e('Delete All Packages', 'SlimWp-Simple-Points'); ?>
                                        </button>
                                        <button type="submit" name="bulk_action" value="delete_duplicates" class="btn btn-secondary"
                                                onclick="return confirm('<?php _e('This will keep only the first package of each name and delete duplicates. Continue?', 'SlimWp-Simple-Points'); ?>')">
                                            <?php _e('Delete Duplicate Packages', 'SlimWp-Simple-Points'); ?>
                                        </button>
                                        <span style="font-size: 13px; color: #50575e;"><?php _e('Use these options to clean up automatically created packages', 'SlimWp-Simple-Points'); ?></span>
                                    </div>
                                </form>
                            <?php endif; ?>
                            
                            <div style="overflow-x: auto;">
                                <table class="packages-table">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Package', 'SlimWp-Simple-Points'); ?></th>
                                            <th><?php _e('Points', 'SlimWp-Simple-Points'); ?></th>
                                            <th><?php _e('Price', 'SlimWp-Simple-Points'); ?></th>
                                            <th><?php _e('Status', 'SlimWp-Simple-Points'); ?></th>
                                            <th><?php _e('Created', 'SlimWp-Simple-Points'); ?></th>
                                            <th><?php _e('Actions', 'SlimWp-Simple-Points'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($packages as $package): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo esc_html($package->name); ?></strong>
                                                    <?php if (!empty($package->description)): ?>
                                                        <br><small style="color: #50575e;"><?php echo esc_html($package->description); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo number_format($package->points); ?></td>
                                                <td><?php echo $package->currency . ' ' . number_format($package->price, 2); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $package->status; ?>">
                                                        <?php echo ucfirst($package->status); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($package->created_at)); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button type="button" onclick="editPackage(<?php echo $package->id; ?>, '<?php echo esc_js($package->name); ?>', '<?php echo esc_js($package->description); ?>', <?php echo $package->points; ?>, <?php echo $package->price; ?>, '<?php echo $package->status; ?>')" 
                                                                class="btn btn-secondary">
                                                            <?php _e('Edit', 'SlimWp-Simple-Points'); ?>
                                                        </button>
                                                        
                                                        <?php if ($package->status === 'active'): ?>
                                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=slimwp-stripe-packages&action=deactivate&package_id=' . $package->id), 'slimwp_package_action'); ?>" 
                                                               class="btn btn-secondary">
                                                                <?php _e('Deactivate', 'SlimWp-Simple-Points'); ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=slimwp-stripe-packages&action=activate&package_id=' . $package->id), 'slimwp_package_action'); ?>" 
                                                               class="btn btn-primary">
                                                                <?php _e('Activate', 'SlimWp-Simple-Points'); ?>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=slimwp-stripe-packages&action=delete&package_id=' . $package->id), 'slimwp_package_action'); ?>" 
                                                           class="btn btn-danger"
                                                           onclick="return confirm('<?php _e('Are you sure you want to delete this package?', 'SlimWp-Simple-Points'); ?>')">
                                                            <?php _e('Delete', 'SlimWp-Simple-Points'); ?>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <h3><?php _e('No packages yet', 'SlimWp-Simple-Points'); ?></h3>
                                <p><?php _e('Create your first points package above to get started.', 'SlimWp-Simple-Points'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="packages-card">
                        <h2><?php _e('Usage Instructions', 'SlimWp-Simple-Points'); ?></h2>
                        
                        <div class="info-box">
                            <strong><?php _e('Display packages on your site:', 'SlimWp-Simple-Points'); ?></strong><br>
                            <?php _e('Use the shortcode', 'SlimWp-Simple-Points'); ?> <code>[slimwp_stripe_packages]</code> <?php _e('on any page or post.', 'SlimWp-Simple-Points'); ?>
                        </div>
                        
                        <div class="info-box">
                            <strong><?php _e('Shortcode options:', 'SlimWp-Simple-Points'); ?></strong><br>
                            <code>[slimwp_stripe_packages columns="3"]</code> - <?php _e('Set number of columns (1-4)', 'SlimWp-Simple-Points'); ?><br>
                            <code>[slimwp_stripe_packages show_description="no"]</code> - <?php _e('Hide package descriptions', 'SlimWp-Simple-Points'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Edit Package Modal -->
        <div id="edit-package-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 24px; border-radius: 8px; width: 90%; max-width: 500px;">
                <h3 style="margin: 0 0 16px;"><?php _e('Edit Package', 'SlimWp-Simple-Points'); ?></h3>
                
                <form method="post">
                    <?php wp_nonce_field('slimwp_packages'); ?>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 500;"><?php _e('Package Name:', 'SlimWp-Simple-Points'); ?></label>
                        <input type="text" id="edit-package-name" name="package_name" required
                               style="width: 100%; padding: 8px 12px; border: 1px solid #dcdcde; border-radius: 4px;">
                    </div>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 500;"><?php _e('Description:', 'SlimWp-Simple-Points'); ?></label>
                        <textarea id="edit-package-description" name="package_description" rows="2"
                                  style="width: 100%; padding: 8px 12px; border: 1px solid #dcdcde; border-radius: 4px;"></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; margin-bottom: 4px; font-weight: 500;"><?php _e('Points:', 'SlimWp-Simple-Points'); ?></label>
                            <input type="number" id="edit-package-points" name="package_points" min="1" required
                                   style="width: 100%; padding: 8px 12px; border: 1px solid #dcdcde; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 4px; font-weight: 500;"><?php _e('Price:', 'SlimWp-Simple-Points'); ?></label>
                            <input type="number" id="edit-package-price" name="package_price" min="0.01" step="0.01" required
                                   style="width: 100%; padding: 8px 12px; border: 1px solid #dcdcde; border-radius: 4px;">
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 500;"><?php _e('Status:', 'SlimWp-Simple-Points'); ?></label>
                        <select id="edit-package-status" name="package_status"
                                style="width: 100%; padding: 8px 12px; border: 1px solid #dcdcde; border-radius: 4px;">
                            <option value="active"><?php _e('Active', 'SlimWp-Simple-Points'); ?></option>
                            <option value="inactive"><?php _e('Inactive', 'SlimWp-Simple-Points'); ?></option>
                        </select>
                    </div>
                    
                    <div style="text-align: right;">
                        <button type="button" onclick="closeEditModal()" class="btn btn-secondary" style="margin-right: 8px;">
                            <?php _e('Cancel', 'SlimWp-Simple-Points'); ?>
                        </button>
                        <button type="submit" name="edit_package" class="btn btn-primary">
                            <?php _e('Save Changes', 'SlimWp-Simple-Points'); ?>
                        </button>
                    </div>
                    
                    <input type="hidden" id="edit-package-id" name="package_id">
                </form>
            </div>
        </div>
        
        <script>
            function editPackage(id, name, description, points, price, status) {
                document.getElementById('edit-package-id').value = id;
                document.getElementById('edit-package-name').value = name;
                document.getElementById('edit-package-description').value = description;
                document.getElementById('edit-package-points').value = points;
                document.getElementById('edit-package-price').value = price;
                document.getElementById('edit-package-status').value = status;
                document.getElementById('edit-package-modal').style.display = 'block';
            }
            
            function closeEditModal() {
                document.getElementById('edit-package-modal').style.display = 'none';
            }
            
            // Close modal when clicking outside
            document.getElementById('edit-package-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeEditModal();
                }
            });
        </script>
        <?php
    }
    
    private function render_stripe_status_check() {
        $stripe_settings = get_option('slimwp_stripe_settings', array());
        $is_enabled = !empty($stripe_settings['enabled']);
        
        if (!$is_enabled) {
            echo '<div class="warning-box">';
            echo '<strong>' . __('⚠️ Stripe Integration Disabled:', 'SlimWp-Simple-Points') . '</strong> ';
            echo sprintf(
                __('Stripe integration is currently disabled. <a href="%s">Enable it in settings</a> to start selling packages.', 'SlimWp-Simple-Points'),
                admin_url('admin.php?page=slimwp-points-settings')
            );
            echo '</div>';
        } else {
            $mode = $stripe_settings['mode'] ?? 'test';
            $publishable_key = $stripe_settings[$mode . '_publishable_key'] ?? '';
            $secret_key = $stripe_settings[$mode . '_secret_key'] ?? '';
            
            if (empty($publishable_key) || empty($secret_key)) {
                echo '<div class="warning-box">';
                echo '<strong>' . __('⚠️ API Keys Missing:', 'SlimWp-Simple-Points') . '</strong> ';
                echo sprintf(
                    __('Stripe API keys are not configured. <a href="%s">Add your API keys</a> to enable payments.', 'SlimWp-Simple-Points'),
                    admin_url('admin.php?page=slimwp-points-settings')
                );
                echo '</div>';
            } else {
                echo '<div class="info-box">';
                echo '<strong>' . __('✅ Stripe Ready:', 'SlimWp-Simple-Points') . '</strong> ';
                echo sprintf(
                    __('Stripe integration is active in %s mode. Packages will be available for purchase.', 'SlimWp-Simple-Points'),
                    '<strong>' . ucfirst($mode) . '</strong>'
                );
                echo '</div>';
            }
        }
    }
    
    private function handle_package_actions() {
        $stripe_settings = get_option('slimwp_stripe_settings', array('currency' => 'USD'));
        
        if (isset($_POST['add_package'])) {
            $package_data = array(
                'name' => sanitize_text_field($_POST['package_name'] ?? ''),
                'description' => sanitize_textarea_field($_POST['package_description'] ?? ''),
                'points' => intval($_POST['package_points'] ?? 0),
                'price' => floatval($_POST['package_price'] ?? 0),
                'currency' => $stripe_settings['currency'] ?? 'USD',
                'status' => 'active'
            );
            
            if (!empty($package_data['name']) && $package_data['points'] > 0 && $package_data['price'] > 0) {
                $result = SlimWP_Stripe_Database::create_package($package_data);
                if ($result) {
                    echo '<div class="notice notice-success is-dismissible" style="margin: 20px 20px 0;"><p>✅ ' . __('Package created successfully!', 'SlimWp-Simple-Points') . '</p></div>';
                    
                    // Redirect to prevent form resubmission
                    echo '<script>window.location.href = "' . admin_url('admin.php?page=slimwp-stripe-packages') . '";</script>';
                } else {
                    global $wpdb;
                    $error = $wpdb->last_error;
                    echo '<div class="notice notice-error is-dismissible" style="margin: 20px 20px 0;"><p>❌ ' . __('Failed to create package.', 'SlimWp-Simple-Points') . ' ' . ($error ? 'Error: ' . esc_html($error) : '') . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible" style="margin: 20px 20px 0;"><p>❌ ' . __('Please fill in all required fields.', 'SlimWp-Simple-Points') . '</p></div>';
            }
        }
        
        if (isset($_POST['edit_package'])) {
            $package_id = intval($_POST['package_id']);
            $package_data = array(
                'name' => sanitize_text_field($_POST['package_name']),
                'description' => sanitize_textarea_field($_POST['package_description']),
                'points' => intval($_POST['package_points']),
                'price' => floatval($_POST['package_price']),
                'currency' => $stripe_settings['currency'],
                'status' => sanitize_text_field($_POST['package_status'])
            );
            
            if ($package_id > 0 && !empty($package_data['name']) && $package_data['points'] > 0 && $package_data['price'] > 0) {
                $result = SlimWP_Stripe_Database::update_package($package_id, $package_data);
                if ($result) {
                    echo '<div class="notice notice-success is-dismissible" style="margin: 20px 20px 0;"><p>✅ ' . __('Package updated successfully!', 'SlimWp-Simple-Points') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible" style="margin: 20px 20px 0;"><p>❌ ' . __('Failed to update package.', 'SlimWp-Simple-Points') . '</p></div>';
                }
            }
        }
    }
    
    private function handle_individual_actions() {
        $action = sanitize_text_field($_GET['action']);
        $package_id = intval($_GET['package_id']);
        
        if ($package_id <= 0) {
            return;
        }
        
        switch ($action) {
            case 'activate':
                $result = SlimWP_Stripe_Database::update_package($package_id, array('status' => 'active'));
                $message = $result ? __('Package activated successfully!', 'SlimWp-Simple-Points') : __('Failed to activate package.', 'SlimWp-Simple-Points');
                break;
                
            case 'deactivate':
                $result = SlimWP_Stripe_Database::update_package($package_id, array('status' => 'inactive'));
                $message = $result ? __('Package deactivated successfully!', 'SlimWp-Simple-Points') : __('Failed to deactivate package.', 'SlimWp-Simple-Points');
                break;
                
            case 'delete':
                $result = SlimWP_Stripe_Database::delete_package($package_id);
                $message = $result ? __('Package deleted successfully!', 'SlimWp-Simple-Points') : __('Failed to delete package.', 'SlimWp-Simple-Points');
                break;
                
            default:
                return;
        }
        
        $notice_class = $result ? 'notice-success' : 'notice-error';
        $icon = $result ? '✅' : '❌';
        
        add_action('admin_notices', function() use ($message, $notice_class, $icon) {
            echo '<div class="notice ' . $notice_class . ' is-dismissible"><p>' . $icon . ' ' . $message . '</p></div>';
        });
        
        // Redirect to remove the action from URL
        wp_redirect(admin_url('admin.php?page=slimwp-stripe-packages'));
        exit;
    }
    
    private function handle_bulk_actions() {
        $action = sanitize_text_field($_POST['bulk_action']);
        
        switch ($action) {
            case 'delete_all':
                $result = $this->delete_all_packages();
                $message = $result ? __('All packages deleted successfully!', 'SlimWp-Simple-Points') : __('Failed to delete packages.', 'SlimWp-Simple-Points');
                break;
                
            case 'delete_duplicates':
                $result = $this->delete_duplicate_packages();
                $message = $result ? __('Duplicate packages deleted successfully!', 'SlimWp-Simple-Points') : __('Failed to delete duplicate packages.', 'SlimWp-Simple-Points');
                break;
                
            default:
                return;
        }
        
        $notice_class = $result ? 'notice-success' : 'notice-error';
        $icon = $result ? '✅' : '❌';
        
        add_action('admin_notices', function() use ($message, $notice_class, $icon) {
            echo '<div class="notice ' . $notice_class . ' is-dismissible"><p>' . $icon . ' ' . $message . '</p></div>';
        });
        
        // Redirect to remove the action from URL
        wp_redirect(admin_url('admin.php?page=slimwp-stripe-packages'));
        exit;
    }
    
    private function delete_all_packages() {
        global $wpdb;
        
        $packages_table = $wpdb->prefix . 'slimwp_stripe_packages';
        
        // Delete all packages
        $result = $wpdb->query("DELETE FROM {$packages_table}");
        
        return $result !== false;
    }
    
    private function delete_duplicate_packages() {
        global $wpdb;
        
        $packages_table = $wpdb->prefix . 'slimwp_stripe_packages';
        
        // Keep only the first package of each name (by ID)
        $sql = "DELETE p1 FROM {$packages_table} p1
                INNER JOIN {$packages_table} p2 
                WHERE p1.id > p2.id AND p1.name = p2.name";
        
        $result = $wpdb->query($sql);
        
        return $result !== false;
    }
}
