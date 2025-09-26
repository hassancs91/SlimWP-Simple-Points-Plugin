<?php
if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_Documentation {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_documentation_menu'), 25);
    }
    
    public function add_documentation_menu() {
        add_submenu_page(
            'slimwp-points',
            __('Documentation', 'SlimWp-Simple-Points'),
            __('Documentation', 'SlimWp-Simple-Points'),
            'manage_options',
            'slimwp-points-documentation',
            array($this, 'documentation_page')
        );
    }
    
    public function documentation_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('SlimWP Simple Points - Documentation', 'SlimWp-Simple-Points'); ?></h1>
            
            <div style="max-width: 1200px;">
                
                <!-- Navigation Tabs -->
                <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
                    <a href="#overview" class="nav-tab nav-tab-active" onclick="showTab('overview')"><?php _e('Overview', 'SlimWp-Simple-Points'); ?></a>
                    <a href="#balance-system" class="nav-tab" onclick="showTab('balance-system')"><?php _e('Balance System', 'SlimWp-Simple-Points'); ?></a>
                    <a href="#woocommerce" class="nav-tab" onclick="showTab('woocommerce')"><?php _e('WooCommerce', 'SlimWp-Simple-Points'); ?></a>
                    <a href="#stripe" class="nav-tab" onclick="showTab('stripe')"><?php _e('Stripe', 'SlimWp-Simple-Points'); ?></a>
                    <a href="#shortcodes" class="nav-tab" onclick="showTab('shortcodes')"><?php _e('Shortcodes', 'SlimWp-Simple-Points'); ?></a>
                    <a href="#developer" class="nav-tab" onclick="showTab('developer')"><?php _e('Developer API', 'SlimWp-Simple-Points'); ?></a>
                </nav>
                
                <!-- Overview Tab -->
                <div id="overview" class="tab-content">
                    <?php $this->render_overview_section(); ?>
                </div>
                
                <!-- Balance System Tab -->
                <div id="balance-system" class="tab-content" style="display:none;">
                    <?php $this->render_balance_system_section(); ?>
                </div>
                
                <!-- WooCommerce Tab -->
                <div id="woocommerce" class="tab-content" style="display:none;">
                    <?php $this->render_woocommerce_section(); ?>
                </div>
                
                <!-- Stripe Tab -->
                <div id="stripe" class="tab-content" style="display:none;">
                    <?php $this->render_stripe_section(); ?>
                </div>
                
                <!-- Shortcodes Tab -->
                <div id="shortcodes" class="tab-content" style="display:none;">
                    <?php $this->render_shortcodes_section(); ?>
                </div>
                
                <!-- Developer API Tab -->
                <div id="developer" class="tab-content" style="display:none;">
                    <?php $this->render_developer_section(); ?>
                </div>
                
            </div>
        </div>
        
        <style>
        .tab-content {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
        }
        .doc-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            border-left: 4px solid #0073aa;
        }
        .doc-section h3 {
            margin-top: 0;
            color: #0073aa;
        }
        .doc-code {
            background: #f1f1f1;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
            line-height: 1.5;
            overflow-x: auto;
            margin: 10px 0;
        }
        .doc-list {
            margin: 15px 0;
            padding-left: 20px;
        }
        .doc-list li {
            margin-bottom: 8px;
        }
        .doc-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 12px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .doc-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 12px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .doc-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin: 15px 0;
        }
        </style>
        
        <script>
        function showTab(tabName) {
            // Hide all tab contents
            var contents = document.querySelectorAll('.tab-content');
            contents.forEach(function(content) {
                content.style.display = 'none';
            });
            
            // Remove active class from all tabs
            var tabs = document.querySelectorAll('.nav-tab');
            tabs.forEach(function(tab) {
                tab.classList.remove('nav-tab-active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).style.display = 'block';
            
            // Add active class to clicked tab
            event.target.classList.add('nav-tab-active');
        }
        </script>
        <?php
    }
    
    private function render_overview_section() {
        ?>
        <div class="doc-section">
            <h3><?php _e('Welcome to SlimWP Simple Points', 'SlimWp-Simple-Points'); ?></h3>
            <p><?php _e('SlimWP Simple Points is a lightweight dual-balance points system for WordPress that allows you to reward users with both temporary and permanent points.', 'SlimWp-Simple-Points'); ?></p>
            
            <h4><?php _e('Key Features', 'SlimWp-Simple-Points'); ?></h4>
            <ul class="doc-list">
                <li><strong><?php _e('Dual Balance System:', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Free Balance and Permanent Balance for different use cases', 'SlimWp-Simple-Points'); ?></li>
                <li><strong><?php _e('WooCommerce Integration:', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Award points for digital product purchases', 'SlimWp-Simple-Points'); ?></li>
                <li><strong><?php _e('Stripe Integration:', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Allow users to purchase points packages', 'SlimWp-Simple-Points'); ?></li>
                <li><strong><?php _e('Flexible Hooks:', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Registration bonuses, daily login rewards, and scheduled resets', 'SlimWp-Simple-Points'); ?></li>
                <li><strong><?php _e('Shortcodes:', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Easy display of points and purchase options', 'SlimWp-Simple-Points'); ?></li>
                <li><strong><?php _e('Developer API:', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Full programmatic control for custom implementations', 'SlimWp-Simple-Points'); ?></li>
            </ul>
            
            <div class="doc-info">
                <strong><?php _e('Getting Started:', 'SlimWp-Simple-Points'); ?></strong><br>
                <?php _e('1. Configure your point rewards in Settings', 'SlimWp-Simple-Points'); ?><br>
                <?php _e('2. Set up integrations (WooCommerce/Stripe) if needed', 'SlimWp-Simple-Points'); ?><br>
                <?php _e('3. Use shortcodes to display points on your site', 'SlimWp-Simple-Points'); ?><br>
                <?php _e('4. Monitor user activity in the Points Dashboard', 'SlimWp-Simple-Points'); ?>
            </div>
        </div>
        <?php
    }
    
    private function render_balance_system_section() {
        ?>
        <div class="doc-section">
            <h3><?php _e('Understanding the Dual Balance System', 'SlimWp-Simple-Points'); ?></h3>
            <p><?php _e('SlimWP Simple Points uses two separate balance types to give you maximum flexibility in how you reward users.', 'SlimWp-Simple-Points'); ?></p>
            
            <h4><?php _e('Free Balance', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-info">
                <strong><?php _e('Best for:', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Daily rewards, login bonuses, temporary promotions, trial periods', 'SlimWp-Simple-Points'); ?><br>
                <strong><?php _e('Features:', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Can be reset daily/monthly, temporary rewards, promotional campaigns', 'SlimWp-Simple-Points'); ?>
            </div>
            
            <h4><?php _e('Permanent Balance', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-success">
                <strong><?php _e('Best for:', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Purchases, achievements, referral rewards, one-time bonuses', 'SlimWp-Simple-Points'); ?><br>
                <strong><?php _e('Features:', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Never automatically reset, secure for valuable rewards, persistent value', 'SlimWp-Simple-Points'); ?>
            </div>
            
            <h4><?php _e('Balance Reset Functionality', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-warning">
                <strong><?php _e('Important:', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Balance resets only affect the Free Balance. Permanent Balance is never reset.', 'SlimWp-Simple-Points'); ?><br>
                <strong><?php _e('Warning:', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Reset operations REPLACE the user\'s free balance, they don\'t add to it.', 'SlimWp-Simple-Points'); ?>
            </div>
            
            <h4><?php _e('Usage Examples', 'SlimWp-Simple-Points'); ?></h4>
            <ul class="doc-list">
                <li><strong><?php _e('Daily Login (Free):', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Give 10 points daily, reset monthly to 100', 'SlimWp-Simple-Points'); ?></li>
                <li><strong><?php _e('Purchase Reward (Permanent):', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Award 500 permanent points for buying a course', 'SlimWp-Simple-Points'); ?></li>
                <li><strong><?php _e('Achievement (Permanent):', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Give 1000 points for completing a milestone', 'SlimWp-Simple-Points'); ?></li>
                <li><strong><?php _e('Promotional (Free):', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Weekly contest with temporary rewards', 'SlimWp-Simple-Points'); ?></li>
            </ul>
        </div>
        <?php
    }
    
    private function render_woocommerce_section() {
        ?>
        <div class="doc-section">
            <h3><?php _e('WooCommerce Integration Guide', 'SlimWp-Simple-Points'); ?></h3>
            
            <?php
            $wc_status = slimwp_get_woocommerce_status_message();
            if ($wc_status['status'] !== 'active'):
            ?>
                <div class="doc-warning">
                    <strong><?php _e('Requirements Not Met:', 'SlimWp-Simple-Points'); ?></strong><br>
                    <?php echo esc_html($wc_status['message']); ?><br>
                    <strong><?php _e('Action Required:', 'SlimWp-Simple-Points'); ?></strong> <?php echo esc_html($wc_status['action']); ?>
                </div>
            <?php else: ?>
                <div class="doc-success">
                    <?php echo esc_html($wc_status['message']); ?> <?php echo esc_html($wc_status['action']); ?>
                </div>
            <?php endif; ?>
            
            <h4><?php _e('How WooCommerce Integration Works', 'SlimWp-Simple-Points'); ?></h4>
            <ol class="doc-list">
                <li><?php _e('Add a "SlimWP Points" tab to digital product edit pages', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Set points amount per product', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Points awarded when order status changes to "completed"', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Points go to customer\'s permanent balance', 'SlimWp-Simple-Points'); ?></li>
            </ol>
            
            <h4><?php _e('Setup Instructions', 'SlimWp-Simple-Points'); ?></h4>
            <ol class="doc-list">
                <li><?php _e('Go to SlimWP Points → Settings', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Scroll to "WooCommerce Integration" section', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Enable the integration toggle', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Set your default points amount', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Choose when to award points (Processing vs Completed)', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Save settings', 'SlimWp-Simple-Points'); ?></li>
            </ol>
            
            <h4><?php _e('Configuring Products', 'SlimWp-Simple-Points'); ?></h4>
            <ol class="doc-list">
                <li><?php _e('Edit any digital (virtual) product in WooCommerce', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Look for the "SlimWP Points" tab in the product data section', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Check "Award Points" to enable points for this product', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Set the points amount (defaults to your global setting)', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Optionally customize the transaction description', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Save the product', 'SlimWp-Simple-Points'); ?></li>
            </ol>
            
            <h4><?php _e('Order Status Guide', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-info">
                <strong><?php _e('Processing (Immediate):', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Points awarded immediately after purchase (automatic)', 'SlimWp-Simple-Points'); ?><br>
                <strong><?php _e('Completed (Manual):', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Points awarded when you manually complete the order (manual control)', 'SlimWp-Simple-Points'); ?>
            </div>
            
            <div class="doc-warning">
                <strong><?php _e('Important Notes:', 'SlimWp-Simple-Points'); ?></strong><br>
                • <?php _e('Only works with digital (virtual) products', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Points are only awarded once per order', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Guest purchases (users not logged in) do not receive points', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Points go to the customer\'s permanent balance', 'SlimWp-Simple-Points'); ?>
            </div>
        </div>
        <?php
    }
    
    private function render_stripe_section() {
        ?>
        <div class="doc-section">
            <h3><?php _e('Stripe Integration Guide', 'SlimWp-Simple-Points'); ?></h3>
            
            <h4><?php _e('How Stripe Integration Works', 'SlimWp-Simple-Points'); ?></h4>
            <ul class="doc-list">
                <li><?php _e('Users purchase points packages via secure Stripe checkout', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Points are automatically added to permanent balance', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Use shortcode [slimwp_stripe_packages] to display packages', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Webhook verification ensures secure transactions', 'SlimWp-Simple-Points'); ?></li>
            </ul>
            
            <h4><?php _e('Setup Instructions', 'SlimWp-Simple-Points'); ?></h4>
            <ol class="doc-list">
                <li><?php _e('Create a Stripe account at stripe.com', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Get your API keys from the Stripe Dashboard', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Go to SlimWP Points → Settings → Stripe Integration', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Enable Stripe integration', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Enter your test API keys', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Set up webhooks (see instructions below)', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Test thoroughly in test mode', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Switch to live mode when ready', 'SlimWp-Simple-Points'); ?></li>
            </ol>
            
            <h4><?php _e('Webhook Setup Instructions', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-info">
                <strong><?php _e('Webhook URL:', 'SlimWp-Simple-Points'); ?></strong><br>
                <code><?php echo home_url('/wp-json/slimwp/v1/stripe/webhook'); ?></code>
            </div>
            
            <ol class="doc-list">
                <li><?php _e('Go to your Stripe Dashboard → Webhooks', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Click "Add endpoint"', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Paste the webhook URL above', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Select events: checkout.session.completed and checkout.session.expired', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Copy the webhook secret and paste it in the API keys section', 'SlimWp-Simple-Points'); ?></li>
                <li><?php _e('Save your settings', 'SlimWp-Simple-Points'); ?></li>
            </ol>
            
            <h4><?php _e('Email Template Placeholders', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-code">
{user_name} - <?php _e('Customer\'s display name', 'SlimWp-Simple-Points'); ?><br>
{points} - <?php _e('Points purchased', 'SlimWp-Simple-Points'); ?><br>
{amount} - <?php _e('Amount paid', 'SlimWp-Simple-Points'); ?><br>
{currency} - <?php _e('Currency code', 'SlimWp-Simple-Points'); ?><br>
{site_name} - <?php _e('Your site name', 'SlimWp-Simple-Points'); ?><br>
{site_url} - <?php _e('Your site URL', 'SlimWp-Simple-Points'); ?>
            </div>
            
            <div class="doc-warning">
                <strong><?php _e('Important Security Notes:', 'SlimWp-Simple-Points'); ?></strong><br>
                • <?php _e('Always test thoroughly in test mode before switching to live mode', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Keep your API keys secure and never share them', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Webhook secret is required for security verification', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Monitor your Stripe dashboard for any unusual activity', 'SlimWp-Simple-Points'); ?>
            </div>
        </div>
        <?php
    }
    
    private function render_shortcodes_section() {
        ?>
        <div class="doc-section">
            <h3><?php _e('Available Shortcodes', 'SlimWp-Simple-Points'); ?></h3>
            
            <h4><?php _e('🚀 Live Points Display (Real-time Updates!)', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-success">
                <strong><?php _e('NEW!', 'SlimWp-Simple-Points'); ?></strong> <?php _e('Live shortcodes automatically update when points change, without page refresh!', 'SlimWp-Simple-Points'); ?>
            </div>
            
            <h5><?php _e('Basic Live Shortcodes', 'SlimWp-Simple-Points'); ?></h5>
            <div class="doc-code">
[slimwp_points_live] - <?php _e('Current user\'s total balance with live updates', 'SlimWp-Simple-Points'); ?><br>
[slimwp_points_live type="free"] - <?php _e('Current user\'s free balance with live updates', 'SlimWp-Simple-Points'); ?><br>
[slimwp_points_live type="permanent"] - <?php _e('Current user\'s permanent balance with live updates', 'SlimWp-Simple-Points'); ?><br>
[slimwp_points_live user_id="123"] - <?php _e('Specific user\'s balance with live updates', 'SlimWp-Simple-Points'); ?>
            </div>
            
            <h5><?php _e('Advanced Live Shortcode Features', 'SlimWp-Simple-Points'); ?></h5>
            <div class="doc-code">
[slimwp_points_live refresh="5"] - <?php _e('Auto-refresh every 5 seconds', 'SlimWp-Simple-Points'); ?><br>
[slimwp_points_live animate="true"] - <?php _e('Flash animation when balance changes', 'SlimWp-Simple-Points'); ?><br>
[slimwp_points_live format="currency" currency_symbol="$"] - <?php _e('Display as currency ($1,234)', 'SlimWp-Simple-Points'); ?><br>
[slimwp_points_live decimals="2"] - <?php _e('Show decimal places (123.45)', 'SlimWp-Simple-Points'); ?><br>
[slimwp_points_live label="Your Balance:"] - <?php _e('Custom label text', 'SlimWp-Simple-Points'); ?><br>
[slimwp_points_live show_type="true"] - <?php _e('Auto label: "Free Points: 100"', 'SlimWp-Simple-Points'); ?><br>
[slimwp_points_live class="my-custom-class"] - <?php _e('Add custom CSS classes', 'SlimWp-Simple-Points'); ?>
            </div>
            
            <h5><?php _e('Live Shortcode Examples', 'SlimWp-Simple-Points'); ?></h5>
            <div class="doc-info">
                <strong><?php _e('Sidebar Widget:', 'SlimWp-Simple-Points'); ?></strong><br>
                <code>[slimwp_points_live label="Your Points:" animate="true" refresh="30"]</code><br><br>
                
                <strong><?php _e('Account Page:', 'SlimWp-Simple-Points'); ?></strong><br>
                <code>[slimwp_points_live type="free" show_type="true" animate="true"]</code><br>
                <code>[slimwp_points_live type="permanent" show_type="true" animate="true"]</code><br><br>
                
                <strong><?php _e('Shop Page:', 'SlimWp-Simple-Points'); ?></strong><br>
                <code>[slimwp_points_live format="currency" currency_symbol="💎" label="Available:"]</code>
            </div>
            
            <h4><?php _e('📊 JavaScript API for Developers', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-code">
// Manual refresh all live shortcodes
slimwp_refresh_all();

// Refresh specific user's shortcodes  
slimwp_refresh_user(123);

// Trigger update event (for custom tools)
slimwp_trigger_update(123); // Update user 123
slimwp_trigger_update(null, true); // Update all users

// jQuery plugin style
$('.slimwp-points-live').slimwp_refresh();

// Event-based updates
$(document).trigger('slimwp:balance:updated', {
    userId: 123,
    allUsers: false
});
            </div>
            
            <h4><?php _e('Static Points Display (Traditional)', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-code">
[slimwp_points] - <?php _e('Show total balance for current user', 'SlimWp-Simple-Points'); ?><br>
[slimwp_points type="free"] - <?php _e('Show only free balance', 'SlimWp-Simple-Points'); ?><br>
[slimwp_points type="permanent"] - <?php _e('Show only permanent balance', 'SlimWp-Simple-Points'); ?><br>
[slimwp_points user_id="123"] - <?php _e('Show specific user\'s balance', 'SlimWp-Simple-Points'); ?>
            </div>
            
            <h4><?php _e('Dedicated Balance Shortcodes', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-code">
[slimwp_points_free] - <?php _e('Show current user\'s free balance only', 'SlimWp-Simple-Points'); ?><br>
[slimwp_points_permanent] - <?php _e('Show current user\'s permanent balance only', 'SlimWp-Simple-Points'); ?><br>
[slimwp_points_free user_id="123"] - <?php _e('Show specific user\'s free balance', 'SlimWp-Simple-Points'); ?><br>
[slimwp_points_permanent user_id="123"] - <?php _e('Show specific user\'s permanent balance', 'SlimWp-Simple-Points'); ?>
            </div>
            
            <h4><?php _e('Stripe Packages (if enabled)', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-code">
[slimwp_stripe_packages] - <?php _e('Display available points packages for purchase', 'SlimWp-Simple-Points'); ?>
            </div>
            
            <h4><?php _e('Usage in Different Contexts', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-info">
                <strong><?php _e('In Posts/Pages:', 'SlimWp-Simple-Points'); ?></strong><br>
                <?php _e('Add any shortcode directly in the WordPress editor (Gutenberg or Classic).', 'SlimWp-Simple-Points'); ?><br><br>
                
                <strong><?php _e('In Themes/Templates:', 'SlimWp-Simple-Points'); ?></strong><br>
                <code>&lt;?php echo do_shortcode('[slimwp_points_live animate="true"]'); ?&gt;</code><br><br>
                
                <strong><?php _e('In Widgets:', 'SlimWp-Simple-Points'); ?></strong><br>
                <?php _e('Add a Text/HTML widget and include any shortcode.', 'SlimWp-Simple-Points'); ?><br><br>
                
                <strong><?php _e('In Custom HTML Blocks:', 'SlimWp-Simple-Points'); ?></strong><br>
                <?php _e('Use Gutenberg\'s Custom HTML block to add shortcodes with custom styling.', 'SlimWp-Simple-Points'); ?>
            </div>
            
            <h4><?php _e('⚡ Real-time Integration Tips', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-warning">
                <strong><?php _e('Performance Considerations:', 'SlimWp-Simple-Points'); ?></strong><br>
                • <?php _e('Auto-refresh shortcodes consume bandwidth - use reasonable intervals (30+ seconds)', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Live shortcodes work best with logged-in users for security', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Use manual refresh for better control in custom applications', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Animations enhance user experience but can be disabled if needed', 'SlimWp-Simple-Points'); ?>
            </div>
            
            <div class="doc-success">
                <strong><?php _e('🎯 Best Practices:', 'SlimWp-Simple-Points'); ?></strong><br>
                • <?php _e('Use live shortcodes for user-facing balance displays', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Use static shortcodes for reports and administrative views', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Combine with your custom JavaScript for enhanced user experiences', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Test live updates with the debug tool to verify functionality', 'SlimWp-Simple-Points'); ?>
            </div>
        </div>
        <?php
    }
    
    private function render_developer_section() {
        ?>
        <div class="doc-section">
            <h3><?php _e('Developer API Reference', 'SlimWp-Simple-Points'); ?></h3>
            
            <h4><?php _e('Getting Points Instance', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-code">
&lt;?php
$points = SlimWP_Points::get_instance();
?&gt;
            </div>
            
            <h4><?php _e('Getting User Balances', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-code">
&lt;?php
// Get total balance (free + permanent)
$total_balance = $points-&gt;get_balance($user_id);
// Returns: numeric value (e.g., 650)

// Get free balance only
$free_balance = $points-&gt;get_free_balance($user_id);
// Returns: numeric value (e.g., 150)

// Get permanent balance only
$permanent_balance = $points-&gt;get_permanent_balance($user_id);
// Returns: numeric value (e.g., 500)

// Get both balances separately
$free = $points-&gt;get_free_balance($user_id);
$permanent = $points-&gt;get_permanent_balance($user_id);
$total = $free + $permanent;
?&gt;
            </div>
            
            <h4><?php _e('Adding Points', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-code">
&lt;?php
// Add to free balance
$result = $points-&gt;add_points($user_id, 100, 'Daily bonus', 'manual', 'free');

// Add to permanent balance
$result = $points-&gt;add_points($user_id, 500, 'Purchase reward', 'purchase', 'permanent');

// Check if successful
if (!is_wp_error($result)) {
    echo 'Points added successfully!';
}
?&gt;
            </div>
            
            <h4><?php _e('Subtracting Points', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-code">
&lt;?php
// Subtract points (automatically deducts from free first, then permanent if needed)
$result = $points-&gt;subtract_points($user_id, 50, 'Used points', 'usage');

// Subtract larger amount (will use free balance first, then permanent)
$result = $points-&gt;subtract_points($user_id, 200, 'Redemption', 'redemption');

// Note: The method automatically handles which balance to deduct from
// It will use free balance first, and if insufficient, will use permanent balance
?&gt;
            </div>
            
            <h4><?php _e('Setting Specific Balance', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-code">
&lt;?php
// Set free balance to specific amount
$result = $points-&gt;set_balance($user_id, 1000, 'Monthly reset', 'reset', 'free');

// Set permanent balance to specific amount
$result = $points-&gt;set_balance($user_id, 2500, 'Admin adjustment', 'adjustment', 'permanent');
?&gt;
            </div>
            
            <h4><?php _e('Helper Functions', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-code">
&lt;?php
// Global helper functions (from functions.php)
slimwp_get_user_points($user_id);           // Get total balance
slimwp_get_user_free_points($user_id);      // Get free balance only
slimwp_get_user_permanent_points($user_id); // Get permanent balance only

slimwp_add_user_points($user_id, $amount, $description, $balance_type);
slimwp_subtract_user_points($user_id, $amount, $description);
slimwp_set_user_balance($user_id, $amount, $description, $balance_type);

// Check if user has enough points (manual check)
$total_balance = $points-&gt;get_balance($user_id);
if ($total_balance &gt;= 100) {
    // User has at least 100 points
}

// Check specific balance
$free_balance = $points-&gt;get_free_balance($user_id);
if ($free_balance &gt;= 100) {
    // User has at least 100 free points
}
?&gt;
            </div>
            
            <h4><?php _e('🚀 Live Points JavaScript API', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-code">
// Global refresh functions
slimwp_refresh_all();           // Refresh all live shortcodes
slimwp_refresh_user(123);       // Refresh specific user's shortcodes

// Trigger update events  
slimwp_trigger_update(123);                    // Update user 123
slimwp_trigger_update(null, true);             // Update all users

// jQuery plugin style
$('.slimwp-points-live').slimwp_refresh();     // Refresh specific elements

// Event-based updates
$(document).trigger('slimwp:balance:updated', {
    userId: 123,
    allUsers: false
});

// Listen for shortcode updates
$(document).on('slimwp:shortcode:updated', function(event, data) {
    console.log('Shortcode updated:', data.shortcode.id, 'New balance:', data.newBalance);
});

// Manual refresh with callback
$(document).on('slimwp:refresh:all', function() {
    console.log('Manual refresh requested');
});
            </div>
            
            <h4><?php _e('Available WordPress Hooks', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-code">
&lt;?php
// After any balance update (includes live update trigger)
add_action('slimwp_points_balance_updated', function($user_id, $amount, $new_total, $description) {
    // Your custom code here
    // This automatically triggers live shortcode updates
}, 10, 4);

// When live update is triggered
add_action('slimwp_live_update_triggered', function($user_id, $balance_type) {
    // Custom logic when live updates are sent to frontend
}, 10, 2);

// After user registration (if enabled in settings)
add_action('slimwp_points_after_registration', function($user_id, $points_awarded) {
    // Custom code after registration bonus
}, 10, 2);

// WooCommerce points awarded
add_action('slimwp_woocommerce_points_awarded', function($user_id, $points, $order_id, $products) {
    // Your custom code here
}, 10, 4);

// Stripe purchase completed
add_action('slimwp_stripe_purchase_completed', function($user_id, $points, $amount, $currency) {
    // Custom code after Stripe purchase
}, 10, 4);
?&gt;
            </div>
            
            <h4><?php _e('Error Handling', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-code">
&lt;?php
$result = $points-&gt;add_points($user_id, 100, 'Test', 'manual', 'free');

if (is_wp_error($result)) {
    $error_message = $result-&gt;get_error_message();
    echo 'Error: ' . $error_message;
} else {
    echo 'Success! New balance: ' . $result;
}
?&gt;
            </div>
            
            <h4><?php _e('🔗 Integration with Custom Tools', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-info">
                <strong><?php _e('Real-world Integration Examples:', 'SlimWp-Simple-Points'); ?></strong>
            </div>
            
            <h5><?php _e('Custom AJAX Tool Integration', 'SlimWp-Simple-Points'); ?></h5>
            <div class="doc-code">
// In your custom tool's JavaScript:
&lt;script&gt;
function consumePoints(userId, amount) {
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'my_custom_consume_points',
            user_id: userId,
            amount: amount,
            nonce: my_nonce
        },
        success: function(response) {
            if (response.success) {
                // Trigger live shortcode updates
                slimwp_trigger_update(userId);

                // Or refresh all shortcodes
                slimwp_refresh_all();
            }
        }
    });
}
&lt;/script&gt;

// In your custom tool's PHP:
&lt;?php
add_action('wp_ajax_my_custom_consume_points', function() {
    $user_id = intval($_POST['user_id']);
    $amount = intval($_POST['amount']);

    $points = SlimWP_Points::get_instance();
    $result = $points-&gt;subtract_points($user_id, $amount, 'Custom tool usage', 'custom_tool');

    if (!is_wp_error($result)) {
        wp_send_json_success(array('new_balance' =&gt; $result));
        // Live shortcodes update automatically via hooks!
    } else {
        wp_send_json_error($result-&gt;get_error_message());
    }
});
?&gt;
            </div>
            
            <h5><?php _e('Game/Quiz Integration Example', 'SlimWp-Simple-Points'); ?></h5>
            <div class="doc-code">
&lt;?php
// Award points for quiz completion
function award_quiz_points($user_id, $quiz_score) {
    $points = SlimWP_Points::get_instance();

    // Calculate points based on score
    $points_earned = $quiz_score * 10;

    $result = $points-&gt;add_points(
        $user_id,
        $points_earned,
        "Quiz completed with score: {$quiz_score}%",
        'quiz_completion',
        'permanent'
    );

    if (!is_wp_error($result)) {
        // Trigger frontend update
        echo "&lt;script&gt;slimwp_trigger_update({$user_id});&lt;/script&gt;";

        // Show success message
        echo "Congratulations! You earned {$points_earned} points!";
    }
}
?&gt;
            </div>
            
            <h5><?php _e('Membership Site Integration', 'SlimWp-Simple-Points'); ?></h5>
            <div class="doc-code">
&lt;?php
// Daily login bonus with live updates
add_action('wp_login', function($user_login, $user) {
    $points = SlimWP_Points::get_instance();

    // Check if user already got bonus today
    $last_bonus = get_user_meta($user-&gt;ID, 'slimwp_last_login_bonus', true);
    $today = date('Y-m-d');

    if ($last_bonus !== $today) {
        $result = $points-&gt;add_points(
            $user-&gt;ID,
            25,
            'Daily login bonus',
            'daily_login',
            'free'
        );

        if (!is_wp_error($result)) {
            update_user_meta($user-&gt;ID, 'slimwp_last_login_bonus', $today);

            // Set a transient to show notification on next page load
            set_transient('slimwp_login_bonus_' . $user-&gt;ID, true, 300);
        }
    }
}, 10, 2);

// Show notification with live shortcode
add_action('wp_footer', function() {
    $user_id = get_current_user_id();
    if ($user_id &amp;&amp; get_transient('slimwp_login_bonus_' . $user_id)) {
        delete_transient('slimwp_login_bonus_' . $user_id);
        echo '&amp;lt;div id="points-notification"&amp;gt;';
        echo '🎉 Daily bonus: +25 points! Balance: [slimwp_points_live animate="true"]';
        echo '&amp;lt;/div&amp;gt;';
        echo '&amp;lt;script&amp;gt;/* notification code */&amp;lt;/script&amp;gt;';
    }
});
?&gt;
            </div>
            
            <h4><?php _e('🎯 Advanced Use Cases', 'SlimWp-Simple-Points'); ?></h4>
            <div class="doc-code">
&lt;?php
// Real-time leaderboard updates
function create_live_leaderboard() {
    ?&gt;
    &amp;lt;div id="live-leaderboard"&amp;gt;
        &amp;lt;h3&amp;gt;Top Users&amp;lt;/h3&amp;gt;
        &amp;lt;div id="leaderboard-content"&amp;gt;
            &amp;lt;!-- Will be populated by JavaScript --&amp;gt;
        &amp;lt;/div&amp;gt;
    &amp;lt;/div&amp;gt;

    &amp;lt;script&amp;gt;
    function updateLeaderboard() {
        // Fetch leaderboard data
        $.get('&amp;lt;?php echo admin_url('admin-ajax.php'); ?&amp;gt;', {
            action: 'get_leaderboard'
        }, function(data) {
            $('#leaderboard-content').html(data);
        });
    }

    // Update leaderboard when any user's points change
    $(document).on('slimwp:shortcode:updated', function() {
        updateLeaderboard();
    });

    // Initial load
    updateLeaderboard();
    &amp;lt;/script&amp;gt;
    &amp;lt;?php
}

// Social media sharing with points
function share_for_points_button() {
    ?&amp;gt;
    &amp;lt;button onclick="shareAndEarnPoints()" class="share-points-btn"&amp;gt;
        Share &amp;amp; Earn 10 Points! Current: [slimwp_points_live]
    &amp;lt;/button&amp;gt;

    &amp;lt;script&amp;gt;
    function shareAndEarnPoints() {
        // Share logic here...

        // Award points via AJAX
        $.post('&amp;lt;?php echo admin_url('admin-ajax.php'); ?&amp;gt;', {
            action: 'award_share_points',
            nonce: '&amp;lt;?php echo wp_create_nonce('share_points'); ?&amp;gt;'
        }, function(response) {
            if (response.success) {
                // Points automatically update via live shortcode!
                alert('Thanks for sharing! You earned 10 points!');
            }
        });
    }
    &amp;lt;/script&amp;gt;
    &amp;lt;?php
}
?&gt;
            </div>
            
            <div class="doc-warning">
                <strong><?php _e('Important Notes:', 'SlimWp-Simple-Points'); ?></strong><br>
                • <?php _e('Always check if functions return WP_Error objects', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('User ID must be valid and exist in WordPress', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Points amounts should be positive numbers', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Balance types must be "free", "permanent", or "total"', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Live shortcodes update automatically when using the points API', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Use manual JavaScript triggers for external integrations', 'SlimWp-Simple-Points'); ?>
            </div>
            
            <div class="doc-success">
                <strong><?php _e('🚀 Pro Tips:', 'SlimWp-Simple-Points'); ?></strong><br>
                • <?php _e('Test live updates with the debug tool before deploying', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Use descriptive transaction descriptions for better tracking', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Combine live shortcodes with custom notifications for better UX', 'SlimWp-Simple-Points'); ?><br>
                • <?php _e('Monitor the browser console for live update debugging info', 'SlimWp-Simple-Points'); ?>
            </div>
        </div>
        <?php
    }
}