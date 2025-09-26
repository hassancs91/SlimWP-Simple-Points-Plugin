<?php
if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_Stripe {
    
    private $points_system;
    private $settings;
    
    public function __construct($points_system) {
        $this->points_system = $points_system;
        $this->settings = get_option('slimwp_stripe_settings', array());
        
        // Initialize hooks
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_slimwp_create_checkout_session', array($this, 'create_checkout_session'));
        add_action('wp_ajax_nopriv_slimwp_create_checkout_session', array($this, 'create_checkout_session'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Webhook handler
        add_action('wp_ajax_slimwp_stripe_webhook', array($this, 'handle_webhook'));
        add_action('wp_ajax_nopriv_slimwp_stripe_webhook', array($this, 'handle_webhook'));
        
        // Debug handler
        add_action('wp_ajax_slimwp_debug_stripe', array($this, 'debug_stripe_config'));
        
        // Shortcode
        add_shortcode('slimwp_stripe_packages', array($this, 'packages_shortcode'));
        
        // Return URLs
        add_action('template_redirect', array($this, 'handle_return_urls'));
    }
    
    public function init() {
        // Create database tables
        SlimWP_Stripe_Database::create_tables();
    }
    
    public function enqueue_scripts() {
        if (!$this->is_enabled()) {
            return;
        }
        
        // Enqueue Stripe.js (external CDN, version managed by Stripe)
        wp_enqueue_script(
            'stripe-js',
            'https://js.stripe.com/v3/',
            array(),
            '3.0', // Stripe API version
            true
        );
        
        wp_enqueue_script(
            'slimwp-stripe-checkout',
            SLIMWP_PLUGIN_URL . 'includes/assets/js/stripe-checkout.js',
            array('jquery', 'stripe-js'),
            SLIMWP_VERSION,
            true
        );
        
        wp_enqueue_style(
            'slimwp-stripe-frontend',
            SLIMWP_PLUGIN_URL . 'includes/assets/css/stripe-frontend.css',
            array(),
            SLIMWP_VERSION
        );
        
        wp_localize_script('slimwp-stripe-checkout', 'slimwp_stripe', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('slimwp_stripe_nonce'),
            'publishable_key' => $this->get_publishable_key(),
            'loading_text' => __('Processing...', 'SlimWp-Simple-Points'),
            'error_text' => __('An error occurred. Please try again.', 'SlimWp-Simple-Points')
        ));
    }
    
    public function packages_shortcode($atts) {
        if (!$this->is_enabled()) {
            return '<p>' . __('Stripe integration is currently disabled.', 'SlimWp-Simple-Points') . '</p>';
        }
        
        $atts = shortcode_atts(array(
            'columns' => 3,
            'show_description' => 'yes'
        ), $atts);
        
        $packages = SlimWP_Stripe_Database::get_packages('active');
        
        if (empty($packages)) {
            return '<p>' . __('No packages available at the moment.', 'SlimWp-Simple-Points') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="slimwp-stripe-packages" data-columns="<?php echo esc_attr($atts['columns']); ?>">
            <?php foreach ($packages as $package): ?>
                <div class="slimwp-package-card">
                    <div class="package-header">
                        <h3 class="package-name"><?php echo esc_html($package->name); ?></h3>
                        <div class="package-price">
                            <span class="currency"><?php echo esc_html($package->currency); ?></span>
                            <span class="amount"><?php echo esc_html(number_format($package->price, 2)); ?></span>
                        </div>
                    </div>
                    
                    <div class="package-content">
                        <div class="package-points">
                            <span class="points-amount"><?php echo esc_html(number_format($package->points)); ?></span>
                            <span class="points-label"><?php _e('Points', 'SlimWp-Simple-Points'); ?></span>
                        </div>
                        
                        <?php if ($atts['show_description'] === 'yes' && !empty($package->description)): ?>
                            <p class="package-description"><?php echo esc_html($package->description); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="package-footer">
                        <?php if (is_user_logged_in()): ?>
                            <button class="slimwp-buy-button" 
                                    data-package-id="<?php echo esc_attr($package->id); ?>"
                                    data-package-name="<?php echo esc_attr($package->name); ?>">
                                <?php _e('Buy Now', 'SlimWp-Simple-Points'); ?>
                            </button>
                        <?php else: ?>
                            <a href="<?php echo wp_login_url(get_permalink()); ?>" class="slimwp-login-button">
                                <?php _e('Login to Purchase', 'SlimWp-Simple-Points'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function create_checkout_session() {
        // Log the start of the function
        error_log('SlimWP Stripe: create_checkout_session called');
        
        // Check if Stripe is enabled
        if (!$this->is_enabled()) {
            error_log('SlimWP Stripe Error: Stripe integration is disabled');
            wp_send_json_error('Payment system is currently disabled');
            return;
        }
        
        // Check if POST data exists
        if (empty($_POST)) {
            error_log('SlimWP Stripe Error: No POST data received');
            wp_send_json_error('No data received');
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'slimwp_stripe_nonce')) {
            error_log('SlimWP Stripe Error: Nonce verification failed');
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!is_user_logged_in()) {
            error_log('SlimWP Stripe Error: User not logged in');
            wp_send_json_error('User must be logged in');
            return;
        }
        
        // Validate package ID
        if (!isset($_POST['package_id'])) {
            error_log('SlimWP Stripe Error: No package_id provided');
            wp_send_json_error('Package ID missing');
            return;
        }
        
        $package_id = intval($_POST['package_id']);
        error_log('SlimWP Stripe: Processing package ID: ' . $package_id);
        
        // Check if packages exist at all
        $all_packages = SlimWP_Stripe_Database::get_packages('all');
        if (empty($all_packages)) {
            error_log('SlimWP Stripe Error: No packages found in database');
            wp_send_json_error('No packages available. Please contact the administrator.');
            return;
        }
        
        $package = SlimWP_Stripe_Database::get_package($package_id);
        
        if (!$package) {
            error_log('SlimWP Stripe Error: Package not found for ID: ' . $package_id);
            wp_send_json_error('Package not found');
            return;
        }
        
        if ($package->status !== 'active') {
            error_log('SlimWP Stripe Error: Package not active for ID: ' . $package_id);
            wp_send_json_error('Package not available');
            return;
        }
        
        // Check Stripe settings
        $publishable_key = $this->get_publishable_key();
        $secret_key = $this->get_secret_key();
        
        if (empty($publishable_key)) {
            error_log('SlimWP Stripe Error: Publishable key not configured');
            wp_send_json_error('Payment system not configured (missing publishable key)');
            return;
        }
        
        if (empty($secret_key)) {
            error_log('SlimWP Stripe Error: Secret key not configured');
            wp_send_json_error('Payment system not configured (missing secret key)');
            return;
        }
        
        try {
            // Load Stripe library
            $this->load_stripe_library();
            
            if (!class_exists('\Stripe\Stripe')) {
                error_log('SlimWP Stripe Error: Stripe library not loaded');
                wp_send_json_error('Payment library not available');
                return;
            }
            
            \Stripe\Stripe::setApiKey($secret_key);
            
            // Configure SSL certificate handling
            try {
                // Load SSL fix helper
                require_once SLIMWP_PLUGIN_DIR . 'includes/class-slimwp-stripe-ssl-fix.php';
                
                // Try to use system CA bundle first
                $ca_bundle_set = false;
                if (function_exists('curl_version')) {
                    $curl_info = curl_version();
                    if (!empty($curl_info['cainfo']) && file_exists($curl_info['cainfo'])) {
                        \Stripe\Stripe::setCABundlePath($curl_info['cainfo']);
                        error_log('SlimWP Stripe: Using system CA bundle: ' . $curl_info['cainfo']);
                        $ca_bundle_set = true;
                    }
                }
                
                // If system CA bundle not available, create our own
                if (!$ca_bundle_set) {
                    $ca_bundle_path = SlimWP_Stripe_SSL_Fix::ensure_ca_bundle();
                    if ($ca_bundle_path && file_exists($ca_bundle_path)) {
                        \Stripe\Stripe::setCABundlePath($ca_bundle_path);
                        error_log('SlimWP Stripe: Using custom CA bundle: ' . $ca_bundle_path);
                        $ca_bundle_set = true;
                    }
                }
                
                // Final fallback: disable SSL verification
                if (!$ca_bundle_set) {
                    \Stripe\Stripe::setVerifySslCerts(false);
                    error_log('SlimWP Stripe: SSL verification disabled - no CA bundle available');
                }
                
            } catch (Exception $e) {
                // Fallback: disable SSL verification
                \Stripe\Stripe::setVerifySslCerts(false);
                error_log('SlimWP Stripe: SSL verification disabled due to error: ' . $e->getMessage());
            }
            
            error_log('SlimWP Stripe: API key set successfully');
            
            $user_id = get_current_user_id();
            $user = get_userdata($user_id);
            
            if (!$user) {
                error_log('SlimWP Stripe Error: Could not get user data for ID: ' . $user_id);
                wp_send_json_error('User data not available');
                return;
            }
            
            // Prepare session data
            $session_data = [
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($package->currency),
                        'product_data' => [
                            'name' => $package->name,
                            'description' => sprintf(
                                __('%s points for %s', 'SlimWp-Simple-Points'),
                                number_format($package->points),
                                get_bloginfo('name')
                            ),
                        ],
                        'unit_amount' => intval($package->price * 100), // Convert to cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => add_query_arg(array(
                    'slimwp_stripe_success' => '1',
                    'session_id' => '{CHECKOUT_SESSION_ID}'
                ), home_url()),
                'cancel_url' => add_query_arg(array(
                    'slimwp_stripe_cancel' => '1'
                ), home_url()),
                'customer_email' => $user->user_email,
                'metadata' => [
                    'user_id' => $user_id,
                    'package_id' => $package_id,
                    'points' => $package->points,
                    'site_url' => home_url()
                ]
            ];
            
            error_log('SlimWP Stripe: Creating checkout session with data: ' . json_encode($session_data));
            
            // Create checkout session
            $session = \Stripe\Checkout\Session::create($session_data);
            
            error_log('SlimWP Stripe: Checkout session created with ID: ' . $session->id);
            
            // Store pending purchase
            $purchase_data = array(
                'user_id' => $user_id,
                'package_id' => $package_id,
                'stripe_session_id' => $session->id,
                'stripe_payment_intent_id' => '',
                'amount_paid' => $package->price,
                'currency' => $package->currency,
                'points_awarded' => $package->points,
                'status' => 'pending'
            );
            
            $purchase_result = SlimWP_Stripe_Database::create_purchase($purchase_data);
            
            if (!$purchase_result) {
                error_log('SlimWP Stripe Error: Failed to create purchase record');
                wp_send_json_error('Failed to create purchase record');
                return;
            }
            
            error_log('SlimWP Stripe: Purchase record created with ID: ' . $purchase_result);
            
            wp_send_json_success(array(
                'session_id' => $session->id
            ));
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('SlimWP Stripe API Error: ' . $e->getMessage());
            error_log('SlimWP Stripe API Error Code: ' . $e->getStripeCode());
            wp_send_json_error('Stripe API error: ' . $e->getMessage());
        } catch (Exception $e) {
            error_log('SlimWP Stripe General Error: ' . $e->getMessage());
            error_log('SlimWP Stripe Error Trace: ' . $e->getTraceAsString());
            wp_send_json_error('Payment processing error: ' . $e->getMessage());
        }
    }
    
    public function handle_webhook() {
        // Security: Log webhook attempts for monitoring
        $ip_address = $this->get_client_ip();
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $endpoint_secret = $this->get_webhook_secret();
        
        // Enhanced logging for security monitoring
        error_log("SlimWP Stripe Webhook: Attempt from IP {$ip_address}, UA: {$user_agent}");
        
        if (empty($endpoint_secret)) {
            error_log("SlimWP Stripe Webhook: Missing webhook secret");
            http_response_code(400);
            exit('Webhook secret not configured');
        }
        
        if (empty($payload)) {
            error_log("SlimWP Stripe Webhook: Empty payload from IP {$ip_address}");
            http_response_code(400);
            exit('Empty payload');
        }
        
        if (empty($sig_header)) {
            error_log("SlimWP Stripe Webhook: Missing signature from IP {$ip_address}");
            http_response_code(400);
            exit('Missing signature');
        }
        
        try {
            $this->load_stripe_library();
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (\UnexpectedValueException $e) {
            error_log("SlimWP Stripe Webhook: Invalid payload from IP {$ip_address}: " . $e->getMessage());
            http_response_code(400);
            exit('Invalid payload');
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            error_log("SlimWP Stripe Webhook: Invalid signature from IP {$ip_address}: " . $e->getMessage());
            http_response_code(400);
            exit('Invalid signature');
        }
        
        // Replay attack protection: Check event timestamp
        $event_time = $event['created'] ?? 0;
        $current_time = time();
        $time_tolerance = 300; // 5 minutes
        
        if (abs($current_time - $event_time) > $time_tolerance) {
            error_log("SlimWP Stripe Webhook: Event too old or from future. Event time: {$event_time}, Current: {$current_time}");
            http_response_code(400);
            exit('Event timestamp out of tolerance');
        }
        
        // Duplicate event protection: Check if we've already processed this event
        $event_id = $event['id'] ?? '';
        if (!empty($event_id)) {
            $processed_key = 'slimwp_stripe_processed_' . $event_id;
            if (get_transient($processed_key)) {
                error_log("SlimWP Stripe Webhook: Duplicate event {$event_id} ignored");
                http_response_code(200);
                exit('Event already processed');
            }
            
            // Mark event as processed (store for 24 hours)
            set_transient($processed_key, true, DAY_IN_SECONDS);
        }
        
        // Validate event data structure
        if (!isset($event['type']) || !isset($event['data']['object'])) {
            error_log("SlimWP Stripe Webhook: Invalid event structure");
            http_response_code(400);
            exit('Invalid event structure');
        }
        
        // Rate limiting: Prevent webhook spam
        $rate_limit_key = 'slimwp_stripe_webhook_rate_' . md5($ip_address);
        $current_requests = get_transient($rate_limit_key) ?: 0;
        
        if ($current_requests > 100) { // Max 100 requests per hour per IP
            error_log("SlimWP Stripe Webhook: Rate limit exceeded for IP {$ip_address}");
            http_response_code(429);
            exit('Rate limit exceeded');
        }
        
        set_transient($rate_limit_key, $current_requests + 1, HOUR_IN_SECONDS);
        
        // Handle the event
        $event_type = sanitize_text_field($event['type']);
        switch ($event_type) {
            case 'checkout.session.completed':
                $this->handle_successful_payment($event['data']['object']);
                break;
            case 'checkout.session.expired':
                $this->handle_expired_session($event['data']['object']);
                break;
            default:
                error_log("SlimWP Stripe: Unhandled event type {$event_type}");
        }
        
        error_log("SlimWP Stripe Webhook: Successfully processed {$event_type} event {$event_id}");
        http_response_code(200);
        exit('OK');
    }
    
    private function handle_successful_payment($session) {
        $session_id = $session['id'];
        $payment_intent_id = $session['payment_intent'] ?? '';
        
        // Get purchase record
        $purchase = SlimWP_Stripe_Database::get_purchase_by_session($session_id);
        
        if (!$purchase || $purchase->status === 'completed') {
            return; // Already processed or not found
        }
        
        // Update purchase status
        SlimWP_Stripe_Database::update_purchase_status($session_id, 'completed', $payment_intent_id);
        
        // Award points to permanent balance
        $result = $this->points_system->add_points(
            $purchase->user_id,
            $purchase->points_awarded,
            sprintf(__('Purchased %s package', 'SlimWp-Simple-Points'), $purchase->package_name ?? 'points'),
            'stripe_purchase',
            'permanent'
        );
        
        if (!is_wp_error($result)) {
            // Send confirmation email
            $this->send_purchase_confirmation_email($purchase);
            
            error_log("SlimWP Stripe: Successfully awarded {$purchase->points_awarded} points to user {$purchase->user_id}");
        } else {
            error_log("SlimWP Stripe: Failed to award points - " . $result->get_error_message());
        }
    }
    
    private function handle_expired_session($session) {
        $session_id = $session['id'];
        SlimWP_Stripe_Database::update_purchase_status($session_id, 'expired');
    }
    
    public function handle_return_urls() {
        if (isset($_GET['slimwp_stripe_success'])) {
            $this->handle_success_return();
        } elseif (isset($_GET['slimwp_stripe_cancel'])) {
            $this->handle_cancel_return();
        }
    }
    
    private function handle_success_return() {
        $session_id = sanitize_text_field($_GET['session_id'] ?? '');
        
        if (empty($session_id)) {
            return;
        }
        
        $purchase = SlimWP_Stripe_Database::get_purchase_by_session($session_id);
        
        if ($purchase && $purchase->status === 'completed') {
            // Show success message
            add_action('wp_footer', function() use ($purchase) {
                echo '<div class="slimwp-stripe-success-notice">';
                echo '<p>' . sprintf(
                    __('Thank you! Your purchase was successful. %s points have been added to your account.', 'SlimWp-Simple-Points'),
                    number_format($purchase->points_awarded)
                ) . '</p>';
                echo '</div>';
            });
        }
    }
    
    private function handle_cancel_return() {
        add_action('wp_footer', function() {
            echo '<div class="slimwp-stripe-cancel-notice">';
            echo '<p>' . __('Your purchase was cancelled. No charges were made.', 'SlimWp-Simple-Points') . '</p>';
            echo '</div>';
        });
    }
    
    private function send_purchase_confirmation_email($purchase) {
        $template = $this->get_email_template();
        $user = get_userdata($purchase->user_id);
        
        if (!$user || empty($template)) {
            return;
        }
        
        $subject = sprintf(__('[%s] Points Purchase Confirmation', 'SlimWp-Simple-Points'), get_bloginfo('name'));
        
        $message = str_replace(
            array(
                '{user_name}',
                '{points}',
                '{amount}',
                '{currency}',
                '{site_name}',
                '{site_url}'
            ),
            array(
                $user->display_name,
                number_format($purchase->points_awarded),
                number_format($purchase->amount_paid, 2),
                $purchase->currency,
                get_bloginfo('name'),
                home_url()
            ),
            $template
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    private function load_stripe_library() {
        if (!class_exists('\Stripe\Stripe')) {
            require_once SLIMWP_PLUGIN_DIR . 'includes/stripe-php/init.php';
        }
    }
    
    private function is_enabled() {
        return !empty($this->settings['enabled']) && ($this->settings['enabled'] === '1' || $this->settings['enabled'] === true);
    }
    
    private function get_publishable_key() {
        $mode = $this->settings['mode'] ?? 'test';
        return $this->settings[$mode . '_publishable_key'] ?? '';
    }
    
    private function get_secret_key() {
        $mode = $this->settings['mode'] ?? 'test';
        return $this->settings[$mode . '_secret_key'] ?? '';
    }
    
    private function get_webhook_secret() {
        $mode = $this->settings['mode'] ?? 'test';
        return $this->settings[$mode . '_webhook_secret'] ?? '';
    }
    
    private function get_email_template() {
        $default_template = "Hello {user_name},\n\nThank you for your purchase!\n\nYou have successfully purchased {points} points for {amount} {currency}.\n\nThe points have been added to your permanent balance and are ready to use.\n\nBest regards,\n{site_name}\n{site_url}";
        
        return $this->settings['email_template'] ?? $default_template;
    }
    
    public function get_webhook_url() {
        return admin_url('admin-ajax.php?action=slimwp_stripe_webhook');
    }
    
    /**
     * Debug function to check Stripe configuration
     * Can be called via AJAX for troubleshooting
     */
    public function debug_stripe_config() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $debug_info = array();
        
        // Check if Stripe is enabled
        $debug_info['stripe_enabled'] = $this->is_enabled();
        
        // Check API keys
        $debug_info['publishable_key_set'] = !empty($this->get_publishable_key());
        $debug_info['secret_key_set'] = !empty($this->get_secret_key());
        $debug_info['webhook_secret_set'] = !empty($this->get_webhook_secret());
        
        // Check current mode
        $debug_info['current_mode'] = $this->settings['mode'] ?? 'test';
        
        // Check if Stripe library loads
        try {
            $this->load_stripe_library();
            $debug_info['stripe_library_loaded'] = class_exists('\Stripe\Stripe');
        } catch (Exception $e) {
            $debug_info['stripe_library_loaded'] = false;
            $debug_info['stripe_library_error'] = $e->getMessage();
        }
        
        // Check database tables
        global $wpdb;
        $packages_table = $wpdb->prefix . 'slimwp_stripe_packages';
        $purchases_table = $wpdb->prefix . 'slimwp_stripe_purchases';
        
        $debug_info['packages_table_exists'] = $wpdb->get_var("SHOW TABLES LIKE '{$packages_table}'") === $packages_table;
        $debug_info['purchases_table_exists'] = $wpdb->get_var("SHOW TABLES LIKE '{$purchases_table}'") === $purchases_table;
        
        // Check packages
        $packages = SlimWP_Stripe_Database::get_packages('all');
        $debug_info['total_packages'] = count($packages);
        $debug_info['active_packages'] = count(SlimWP_Stripe_Database::get_packages('active'));
        
        // Check settings
        $debug_info['all_settings'] = array_keys($this->settings);
        
        wp_send_json_success($debug_info);
    }
    
    /**
     * Get client IP address securely
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = sanitize_text_field($_SERVER[$key]);
                
                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback to REMOTE_ADDR even if it's private/reserved
        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
