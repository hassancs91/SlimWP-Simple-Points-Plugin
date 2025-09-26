<?php
if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_WooCommerce {
    
    private $points_system;
    
    public function __construct($points_system) {
        $this->points_system = $points_system;
        
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
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
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * Check if WooCommerce integration is enabled in settings
     */
    private function is_integration_enabled() {
        $settings = get_option('slimwp_woocommerce_settings', array());
        return !empty($settings['enabled']);
    }
    
    /**
     * Initialize WooCommerce hooks
     */
    private function init_hooks() {
        // Product admin hooks
        add_action('woocommerce_product_data_tabs', array($this, 'add_product_data_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'add_product_data_panel'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_data'));
        
        // Order status hooks - configurable
        $settings = get_option('slimwp_woocommerce_settings', array());
        $award_on_status = isset($settings['award_on_status']) ? $settings['award_on_status'] : 'completed';
        
        if ($award_on_status === 'processing') {
            add_action('woocommerce_order_status_processing', array($this, 'award_points_on_order_completion'));
        } else {
            add_action('woocommerce_order_status_completed', array($this, 'award_points_on_order_completion'));
        }
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    /**
     * Add SlimWP Points tab to product data tabs (only for digital products)
     */
    public function add_product_data_tab($tabs) {
        global $post;
        
        if (!$post) {
            return $tabs;
        }
        
        $product = wc_get_product($post->ID);
        if (!$product) {
            return $tabs;
        }
        
        // Only show for digital products (virtual)
        if ($product->is_virtual()) {
            $tabs['slimwp_points'] = array(
                'label'    => __('SlimWP Points', 'SlimWp-Simple-Points'),
                'target'   => 'slimwp_points_product_data',
                'class'    => array('show_if_virtual'),
                'priority' => 25,
            );
        }
        
        return $tabs;
    }
    
    /**
     * Add SlimWP Points product data panel
     */
    public function add_product_data_panel() {
        global $post;
        
        $product_id = $post->ID;
        $points_enabled = get_post_meta($product_id, '_slimwp_points_enabled', true);
        $points_amount = get_post_meta($product_id, '_slimwp_points_amount', true);
        $points_description = get_post_meta($product_id, '_slimwp_points_description', true);
        
        // Get default settings
        $settings = get_option('slimwp_woocommerce_settings', array());
        $default_points = isset($settings['default_points']) ? intval($settings['default_points']) : 50;
        
        if (empty($points_amount)) {
            $points_amount = $default_points;
        }
        
        ?>
        <div id="slimwp_points_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <h3 style="padding: 12px; margin: 0; border-bottom: 1px solid #eee; background: #f9f9f9;">
                    <?php _e('SlimWP Points Settings', 'SlimWp-Simple-Points'); ?>
                </h3>
                
                <div style="padding: 12px;">
                    <?php
                    woocommerce_wp_checkbox(array(
                        'id'          => '_slimwp_points_enabled',
                        'label'       => __('Award Points', 'SlimWp-Simple-Points'),
                        'description' => __('Enable points reward for this digital product', 'SlimWp-Simple-Points'),
                        'value'       => $points_enabled,
                    ));
                    
                    woocommerce_wp_text_input(array(
                        'id'          => '_slimwp_points_amount',
                        'label'       => __('Points Amount', 'SlimWp-Simple-Points'),
                        'description' => __('Number of points to award when this product is purchased', 'SlimWp-Simple-Points'),
                        'type'        => 'number',
                        'value'       => $points_amount,
                        'custom_attributes' => array(
                            'min'  => '0',
                            'step' => '1',
                        ),
                    ));
                    
                    woocommerce_wp_text_input(array(
                        'id'          => '_slimwp_points_description',
                        'label'       => __('Points Description', 'SlimWp-Simple-Points'),
                        'description' => __('Optional description for the points transaction (leave empty for default)', 'SlimWp-Simple-Points'),
                        'value'       => $points_description,
                        'placeholder' => __('Purchase of [Product Name]', 'SlimWp-Simple-Points'),
                    ));
                    ?>
                    
                    <div class="form-field">
                        <div style="background: #e7f2fd; border: 1px solid #bee5eb; border-radius: 4px; padding: 12px; margin-top: 10px;">
                            <strong><?php _e('ℹ️ Points Information:', 'SlimWp-Simple-Points'); ?></strong><br>
                            • <?php _e('Points will be added to the customer\'s <strong>permanent balance</strong>', 'SlimWp-Simple-Points'); ?><br>
                            • <?php _e('Points are awarded when the order status changes to <strong>completed</strong>', 'SlimWp-Simple-Points'); ?><br>
                            • <?php _e('Only works for digital products (virtual)', 'SlimWp-Simple-Points'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            #slimwp_points_product_data .form-field label {
                font-weight: 600;
            }
            #slimwp_points_product_data .description {
                font-style: italic;
                color: #666;
            }
        </style>
        <?php
    }
    
    /**
     * Save product data
     */
    public function save_product_data($post_id) {
        // Verify nonce for security
        if (!isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce($_POST['woocommerce_meta_nonce'], 'woocommerce_save_data')) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Verify this is not an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Save points enabled
        $points_enabled = isset($_POST['_slimwp_points_enabled']) ? 'yes' : 'no';
        update_post_meta($post_id, '_slimwp_points_enabled', sanitize_text_field($points_enabled));
        
        // Save points amount
        if (isset($_POST['_slimwp_points_amount'])) {
            $points_amount = intval($_POST['_slimwp_points_amount']);
            // Validate points amount is not negative
            if ($points_amount < 0) {
                $points_amount = 0;
            }
            update_post_meta($post_id, '_slimwp_points_amount', $points_amount);
        }
        
        // Save points description
        if (isset($_POST['_slimwp_points_description'])) {
            $points_description = sanitize_text_field($_POST['_slimwp_points_description']);
            update_post_meta($post_id, '_slimwp_points_description', $points_description);
        }
    }
    
    /**
     * Award points when order is completed
     */
    public function award_points_on_order_completion($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Check if points have already been awarded for this order
        if (get_post_meta($order_id, '_slimwp_points_awarded', true)) {
            return;
        }
        
        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            return; // Guest orders not supported
        }
        
        $total_points_awarded = 0;
        $awarded_products = array();
        
        // Loop through order items
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            
            if (!$product) {
                continue;
            }
            
            // Check if product is digital (virtual)
            if (!$product->is_virtual()) {
                continue;
            }
            
            // Check if points are enabled for this product
            $points_enabled = get_post_meta($product_id, '_slimwp_points_enabled', true);
            if ($points_enabled !== 'yes') {
                continue;
            }
            
            // Get points amount
            $points_amount = get_post_meta($product_id, '_slimwp_points_amount', true);
            $points_amount = intval($points_amount);
            
            if ($points_amount <= 0) {
                continue;
            }
            
            // Get custom description or use default
            $custom_description = get_post_meta($product_id, '_slimwp_points_description', true);
            $description = !empty($custom_description) 
                ? $custom_description 
                : sprintf(__('Purchase of %s (Order #%s)', 'SlimWp-Simple-Points'), $product->get_name(), $order->get_order_number());
            
            // Award points to permanent balance
            $result = $this->points_system->add_points(
                $customer_id,
                $points_amount,
                $description,
                'woocommerce_purchase',
                'permanent'
            );
            
            if (!is_wp_error($result)) {
                $total_points_awarded += $points_amount;
                $awarded_products[] = array(
                    'product_id' => $product_id,
                    'product_name' => $product->get_name(),
                    'points' => $points_amount
                );
            }
        }
        
        // Mark order as points awarded and save details
        if ($total_points_awarded > 0) {
            update_post_meta($order_id, '_slimwp_points_awarded', 'yes');
            update_post_meta($order_id, '_slimwp_points_total', $total_points_awarded);
            update_post_meta($order_id, '_slimwp_points_products', $awarded_products);
            
            // Add order note
            $order->add_order_note(
                sprintf(
                    __('SlimWP Points: %d points awarded to customer\'s permanent balance', 'SlimWp-Simple-Points'),
                    $total_points_awarded
                )
            );
            
            // Trigger action for other plugins/themes
            do_action('slimwp_woocommerce_points_awarded', $customer_id, $total_points_awarded, $order_id, $awarded_products);
        }
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            $settings = get_option('slimwp_woocommerce_settings', array());
            if (!empty($settings['enabled'])) {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <strong><?php _e('SlimWP Points:', 'SlimWp-Simple-Points'); ?></strong>
                        <?php _e('WooCommerce integration is enabled but WooCommerce plugin is not active.', 'SlimWp-Simple-Points'); ?>
                    </p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Get points awarded for an order
     */
    public function get_order_points($order_id) {
        return get_post_meta($order_id, '_slimwp_points_total', true);
    }
    
    /**
     * Get products that awarded points for an order
     */
    public function get_order_points_products($order_id) {
        return get_post_meta($order_id, '_slimwp_points_products', true);
    }
}
