<?php
if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_Shortcodes {
    
    private $points_system;
    
    public function __construct($points_system) {
        $this->points_system = $points_system;
        
        // Register shortcodes
        add_shortcode('slimwp_points', array($this, 'points_shortcode'));
        add_shortcode('slimwp_points_free', array($this, 'points_free_shortcode'));
        add_shortcode('slimwp_points_permanent', array($this, 'points_permanent_shortcode'));
        add_shortcode('slimwp_points_live', array($this, 'points_live_shortcode'));
        
        // Backward compatibility shortcodes
        add_shortcode('user_points', array($this, 'points_shortcode'));
        add_shortcode('user_points_free', array($this, 'points_free_shortcode'));
        add_shortcode('user_points_permanent', array($this, 'points_permanent_shortcode'));
        
        // Enqueue scripts for live updates
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'add_inline_scripts'));
    }
    
    public function points_shortcode($atts) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'type' => 'total' // 'total', 'free', 'permanent'
        ), $atts);
        
        // Sanitize inputs
        $atts['user_id'] = intval($atts['user_id']);
        $atts['type'] = sanitize_text_field($atts['type']);
        
        // Validate type
        $allowed_types = array('total', 'free', 'permanent');
        if (!in_array($atts['type'], $allowed_types)) {
            $atts['type'] = 'total';
        }
        
        if (!$atts['user_id']) {
            return '';
        }
        
        switch ($atts['type']) {
            case 'free':
                $balance = $this->points_system->get_free_balance($atts['user_id']);
                break;
            case 'permanent':
                $balance = $this->points_system->get_permanent_balance($atts['user_id']);
                break;
            default:
                $balance = $this->points_system->get_balance($atts['user_id']);
        }
        
        return '<span class="slimwp-points-balance">' . esc_html(number_format($balance, 2)) . '</span>';
    }
    
    public function points_free_shortcode($atts) {
        $atts = shortcode_atts(array('user_id' => get_current_user_id()), $atts);
        $atts['user_id'] = intval($atts['user_id']);
        if (!$atts['user_id']) return '';
        return '<span class="slimwp-points-balance-free">' . esc_html(number_format($this->points_system->get_free_balance($atts['user_id']), 2)) . '</span>';
    }
    
    public function points_permanent_shortcode($atts) {
        $atts = shortcode_atts(array('user_id' => get_current_user_id()), $atts);
        $atts['user_id'] = intval($atts['user_id']);
        if (!$atts['user_id']) return '';
        return '<span class="slimwp-points-balance-permanent">' . esc_html(number_format($this->points_system->get_permanent_balance($atts['user_id']), 2)) . '</span>';
    }
    
    /**
     * Live updating points shortcode with real-time refresh capability
     * 
     * Usage examples:
     * [slimwp_points_live] - Current user's total balance with live updates
     * [slimwp_points_live type="free"] - Current user's free balance
     * [slimwp_points_live type="permanent" user_id="123"] - Specific user's permanent balance
     * [slimwp_points_live refresh="5"] - Auto-refresh every 5 seconds
     * [slimwp_points_live animate="true"] - Add animation on balance changes
     */
    public function points_live_shortcode($atts) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'type' => 'total', // 'total', 'free', 'permanent'
            'refresh' => '0', // Auto-refresh interval in seconds (0 = manual only)
            'animate' => 'true', // Enable animation on updates
            'format' => 'number', // 'number', 'currency'
            'decimals' => '0', // Number of decimal places
            'currency_symbol' => '$', // Currency symbol if format is currency
            'class' => '', // Additional CSS classes
            'label' => '', // Optional label text
            'show_type' => 'false' // Show balance type in label
        ), $atts);
        
        // Sanitize and validate all inputs
        $atts = $this->sanitize_shortcode_attributes($atts);
        
        if (!$atts['user_id']) {
            return '<span class="slimwp-error">Not logged in</span>';
        }
        
        // Generate unique ID for this shortcode instance
        $unique_id = 'slimwp-live-' . uniqid();
        
        // Get current balance
        switch ($atts['type']) {
            case 'free':
                $balance = $this->points_system->get_free_balance($atts['user_id']);
                break;
            case 'permanent':
                $balance = $this->points_system->get_permanent_balance($atts['user_id']);
                break;
            default:
                $balance = $this->points_system->get_balance($atts['user_id']);
        }
        
        // Format balance
        $formatted_balance = $this->format_balance($balance, $atts);
        
        // Build label
        $label = $atts['label'];
        if ($atts['show_type'] === 'true' && empty($label)) {
            $label = ucfirst($atts['type']) . ' Points: ';
        } elseif (!empty($label)) {
            $label .= ' ';
        }
        
        // Build CSS classes
        $css_classes = array(
            'slimwp-points-live',
            'slimwp-points-type-' . $atts['type'],
            'slimwp-points-user-' . $atts['user_id']
        );
        
        if ($atts['animate'] === 'true') {
            $css_classes[] = 'slimwp-animated';
        }
        
        if (!empty($atts['class'])) {
            $css_classes[] = $atts['class'];
        }
        
        // Data attributes for JavaScript
        $data_attrs = array(
            'data-user-id' => $atts['user_id'],
            'data-type' => $atts['type'],
            'data-refresh' => $atts['refresh'],
            'data-animate' => $atts['animate'],
            'data-format' => $atts['format'],
            'data-decimals' => $atts['decimals'],
            'data-currency-symbol' => $atts['currency_symbol'],
            'data-raw-balance' => $balance
        );
        
        $data_string = '';
        foreach ($data_attrs as $key => $value) {
            $data_string .= ' ' . $key . '="' . esc_attr($value) . '"';
        }
        
        // Mark that we have live shortcodes on this page
        global $slimwp_has_live_shortcodes;
        $slimwp_has_live_shortcodes = true;
        
        return sprintf(
            '<span id="%s" class="%s"%s><span class="slimwp-label">%s</span><span class="slimwp-balance">%s</span></span>',
            esc_attr($unique_id),
            esc_attr(implode(' ', $css_classes)),
            $data_string,
            esc_html($label),
            esc_html($formatted_balance)
        );
    }
    
    /**
     * Format balance according to shortcode attributes
     */
    private function format_balance($balance, $atts) {
        $decimals = intval($atts['decimals']);
        
        if ($atts['format'] === 'currency') {
            // Sanitize currency symbol to prevent XSS
            $currency_symbol = $this->sanitize_currency_symbol($atts['currency_symbol']);
            return $currency_symbol . number_format($balance, $decimals);
        }
        
        return number_format($balance, $decimals);
    }
    
    /**
     * Enqueue scripts for live updates
     */
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        
        // Enqueue our live points script
        wp_enqueue_script(
            'slimwp-live-points',
            SLIMWP_PLUGIN_URL . 'includes/assets/js/live-points.js',
            array('jquery'),
            SLIMWP_VERSION,
            true
        );
        
        // Localize script with AJAX URL and nonce
        wp_localize_script('slimwp-live-points', 'slimwp_live', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('slimwp_live_nonce'),
            'current_user_id' => get_current_user_id()
        ));
    }
    
    /**
     * Add inline CSS for animations and styling
     */
    public function add_inline_scripts() {
        global $slimwp_has_live_shortcodes;
        
        if (!$slimwp_has_live_shortcodes) {
            return;
        }
        
        ?>
        <style>
        .slimwp-points-live {
            display: inline-block;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .slimwp-points-live.slimwp-animated {
            position: relative;
        }
        
        .slimwp-points-live.slimwp-updating {
            opacity: 0.7;
        }
        
        .slimwp-points-live.slimwp-updated {
            background-color: #d4edda;
            border-radius: 3px;
            padding: 2px 4px;
            animation: slimwp-flash 0.5s ease-in-out;
        }
        
        @keyframes slimwp-flash {
            0% { background-color: #28a745; color: white; }
            100% { background-color: #d4edda; color: inherit; }
        }
        
        .slimwp-points-live .slimwp-label {
            color: #666;
            font-weight: normal;
        }
        
        .slimwp-points-live .slimwp-balance {
            color: #28a745;
            font-weight: bold;
        }
        
        .slimwp-points-live.slimwp-loading {
            position: relative;
        }
        
        .slimwp-points-live.slimwp-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            right: -20px;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            border: 2px solid #007cba;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: slimwp-spin 0.8s linear infinite;
        }
        
        @keyframes slimwp-spin {
            to { transform: translateY(-50%) rotate(360deg); }
        }
        
        .slimwp-error {
            color: #dc3545;
            font-style: italic;
        }
        </style>
        
        <script<?php echo ' nonce="' . esc_attr(wp_create_nonce('slimwp_shortcode_script')) . '"'; ?>>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize live points system
            if (typeof SlimWP_LivePoints !== 'undefined') {
                SlimWP_LivePoints.init();
            }
        });
        </script>
        <?php
    }
    
    /**
     * Sanitize shortcode attributes to prevent XSS
     */
    private function sanitize_shortcode_attributes($atts) {
        // Sanitize user_id
        $atts['user_id'] = intval($atts['user_id']);
        
        // Sanitize and validate type
        $allowed_types = array('total', 'free', 'permanent');
        $atts['type'] = sanitize_text_field($atts['type']);
        if (!in_array($atts['type'], $allowed_types)) {
            $atts['type'] = 'total';
        }
        
        // Sanitize refresh interval (limit to reasonable values)
        $atts['refresh'] = max(0, min(300, intval($atts['refresh']))); // Max 5 minutes
        
        // Sanitize boolean values
        $atts['animate'] = in_array(strtolower($atts['animate']), array('true', '1', 'yes')) ? 'true' : 'false';
        $atts['show_type'] = in_array(strtolower($atts['show_type']), array('true', '1', 'yes')) ? 'true' : 'false';
        
        // Sanitize format
        $allowed_formats = array('number', 'currency');
        $atts['format'] = sanitize_text_field($atts['format']);
        if (!in_array($atts['format'], $allowed_formats)) {
            $atts['format'] = 'number';
        }
        
        // Sanitize decimals (limit to reasonable range)
        $atts['decimals'] = max(0, min(4, intval($atts['decimals'])));
        
        // Sanitize currency symbol
        $atts['currency_symbol'] = $this->sanitize_currency_symbol($atts['currency_symbol']);
        
        // Sanitize CSS class
        $atts['class'] = sanitize_html_class($atts['class']);
        
        // Sanitize label
        $atts['label'] = sanitize_text_field($atts['label']);
        
        return $atts;
    }
    
    /**
     * Sanitize currency symbol to prevent XSS
     */
    private function sanitize_currency_symbol($symbol) {
        // Allow only common currency symbols
        $allowed_symbols = array(
            '$', '€', '£', '¥', '₹', '₽', '¢', '₩', '₪', '₨', 
            '₡', '₦', '₫', '₵', '₴', '₸', '₺', '₼', '₾', '₿'
        );
        
        // Sanitize the input
        $symbol = sanitize_text_field($symbol);
        
        // Check if it's a whitelisted symbol
        if (in_array($symbol, $allowed_symbols)) {
            return $symbol;
        }
        
        // Check if it's a 3-letter currency code (like USD, EUR)
        if (preg_match('/^[A-Z]{3}$/', $symbol)) {
            return $symbol;
        }
        
        // Fallback to safe default
        error_log('SlimWP Security: Invalid currency symbol blocked: ' . $symbol);
        return '$';
    }
}
