<?php
/**
 * Plugin Name: AI Image Generator (Corrected)
 * Description: Professional image generator using n8n webhook via generic function - CORRECTED VERSION
 * Version: 3.3
 */

// ============================================
// ADDED: Define the cost per image generation
// ============================================
define('IMAGE_GENERATION_COST', 10); // Points required per image

// Register the custom REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/n8n-webhook', array(
        'methods' => 'POST',
        'callback' => 'call_n8n_webhook',
        // ============================================
        // CHANGED: Now requires authentication
        // ============================================
        'permission_callback' => 'is_user_logged_in', // Changed from '__return_true'
    ));
});

function call_n8n_webhook($request) {
    // Get parameters from the request
    $params = $request->get_json_params();

    // Validate required inputs
    if (!isset($params['topic'])) {
        return new WP_Error('missing_params', 'topic is required', array('status' => 400));
    }

    // ============================================
    // CORRECTED: Points system integration - Check user balance
    // Using the correct method names from SlimWP_Points class
    // ============================================
    $user_id = get_current_user_id();
    $points = SlimWP_Points::get_instance();

    // CORRECTED: Use get_balance() instead of get_user_points()
    $total_balance = $points->get_balance($user_id);

    // Check if user has enough points
    if ($total_balance < IMAGE_GENERATION_COST) {
        return new WP_Error(
            'insufficient_balance',
            'Insufficient points balance. You need ' . IMAGE_GENERATION_COST . ' points to generate an image.',
            array('status' => 403)
        );
    }
    // ============================================
    // END OF CORRECTION: Balance check complete
    // ============================================

    $input1 = sanitize_text_field($params['topic']);

    // Your n8n webhook URL - replace with your actual webhook URL
    $n8n_webhook_url = 'https://n8n.powerkit.dev/webhook/ai_text_to_image_generator';

    // Prepare data to send to n8n
    $webhook_data = array(
        'prompt' => $input1,
        'timestamp' => current_time('mysql'),
        'source' => 'wordpress',
        // ============================================
        // ADDED: Include user info for tracking
        // ============================================
        'user_id' => $user_id,
        'user_email' => wp_get_current_user()->user_email
    );

    // Make the HTTP request to n8n webhook
    $response = wp_remote_post($n8n_webhook_url, array(
        'method' => 'POST',
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode($webhook_data),
    ));

    // Handle errors
    if (is_wp_error($response)) {
        return new WP_Error('webhook_error', 'Failed to call n8n webhook: ' . $response->get_error_message(), array('status' => 500));
    }

    // Get response code and body
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    // Check if the webhook call was successful
    if ($response_code !== 200) {
        return new WP_Error('webhook_failed', 'n8n webhook returned error code: ' . $response_code, array('status' => $response_code));
    }

    // ============================================
    // CORRECTED: Deduct points after successful generation
    // Using the correct subtract_points() method signature (4 params, not 5)
    // ============================================
    // The subtract_points method automatically handles deducting from free first, then permanent
    $deduct_result = $points->subtract_points(
        $user_id,
        IMAGE_GENERATION_COST,
        'AI Image Generation: ' . substr($input1, 0, 50), // Truncate prompt for description
        'image_generation'
        // REMOVED the 5th parameter - balance_type is not accepted by subtract_points
    );

    // Log the transaction for debugging (optional)
    if (is_wp_error($deduct_result)) {
        error_log('Points deduction failed: ' . $deduct_result->get_error_message());
    }
    // ============================================
    // END OF CORRECTION: Points deducted correctly
    // ============================================

    // Try to decode JSON response
    $decoded_response = json_decode($response_body, true);

    // Return the response from n8n
    return rest_ensure_response(array(
        'success' => true,
        'data' => $decoded_response ? $decoded_response : $response_body,
        'timestamp' => current_time('mysql'),
        // ============================================
        // CORRECTED: Include remaining balance in response
        // ============================================
        'remaining_balance' => $points->get_balance($user_id) // Using correct method
    ));
}


// Register shortcode
add_shortcode('ui1_img-gen', function() {
    // ============================================
    // ADDED: Check if user is logged in before showing the interface
    // ============================================
    if (!is_user_logged_in()) {
        return '<div class="css_img_gen_login_notice">
            <div class="css_img_gen_login_icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                    <path d="M2 17l10 5 10-5"/>
                    <path d="M2 12l10 5 10-5"/>
                </svg>
            </div>
            <h2>Login Required</h2>
            <p>You need to be logged in to generate AI images.</p>
            <a href="' . wp_login_url(get_permalink()) . '" class="css_img_gen_login_btn">Login to Continue</a>
        </div>
        <style>
            .css_img_gen_login_notice {
                text-align: center;
                padding: 3rem 2rem;
                background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
                border-radius: 16px;
                max-width: 400px;
                margin: 2rem auto;
            }
            .css_img_gen_login_icon {
                color: #667eea;
                margin-bottom: 1rem;
            }
            .css_img_gen_login_notice h2 {
                margin: 1rem 0;
                color: #111827;
            }
            .css_img_gen_login_notice p {
                color: #6b7280;
                margin-bottom: 1.5rem;
            }
            .css_img_gen_login_btn {
                display: inline-block;
                padding: 0.75rem 2rem;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                transition: transform 0.2s;
            }
            .css_img_gen_login_btn:hover {
                transform: translateY(-2px);
            }
        </style>';
    }

    // ============================================
    // CORRECTED: Get user's current points balance using correct methods
    // ============================================
    $user_id = get_current_user_id();
    $points = SlimWP_Points::get_instance();

    // CORRECTED: Using the actual existing methods
    $total_balance = $points->get_balance($user_id);           // Total balance
    $free_balance = $points->get_free_balance($user_id);       // Free balance only
    $permanent_balance = $points->get_permanent_balance($user_id); // Permanent balance only
    // ============================================
    // END OF CORRECTION
    // ============================================

    ob_start();
    ?>
    <div id="css_img_gen_container" class="css_img_gen_wrapper">
        <div class="css_img_gen_layout">
            <!-- Left Panel - Input Section -->
            <div class="css_img_gen_left_panel">
                <div class="css_img_gen_header">
                    <div class="css_img_gen_logo">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="css_img_gen_title">AI Image Generator</h1>
                        <p class="css_img_gen_subtitle">Transform your ideas into stunning visuals</p>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- ADDED: Points balance display section -->
                <!-- ============================================ -->
                <div class="css_img_gen_balance_card">
                    <div class="css_img_gen_balance_header">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 6v6l4 2"/>
                        </svg>
                        <span>Your Points Balance</span>
                    </div>
                    <div class="css_img_gen_balance_content">
                        <div class="css_img_gen_balance_item">
                            <span class="css_img_gen_balance_label">Total:</span>
                            <span class="css_img_gen_balance_value" id="total_balance"><?php echo $total_balance; ?></span>
                        </div>
                        <div class="css_img_gen_balance_item">
                            <span class="css_img_gen_balance_label">Free:</span>
                            <span class="css_img_gen_balance_value"><?php echo $free_balance; ?></span>
                        </div>
                        <div class="css_img_gen_balance_item">
                            <span class="css_img_gen_balance_label">Permanent:</span>
                            <span class="css_img_gen_balance_value"><?php echo $permanent_balance; ?></span>
                        </div>
                    </div>
                    <div class="css_img_gen_cost_info">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <span>Cost per image: <strong><?php echo IMAGE_GENERATION_COST; ?> points</strong></span>
                    </div>
                </div>
                <!-- ============================================ -->
                <!-- END OF ADDITION: Balance display -->
                <!-- ============================================ -->

                <div class="css_img_gen_input_section">
                    <label class="css_img_gen_label" for="css_img_gen_prompt">Describe your image</label>
                    <div class="css_img_gen_textarea_wrapper">
                        <textarea
                            id="css_img_gen_prompt"
                            class="css_img_gen_textarea"
                            placeholder="Be specific about what you want to see. For example: 'A serene mountain landscape at sunset with snow-capped peaks and a crystal clear lake reflecting the orange sky'"
                            rows="6"
                        ></textarea>
                        <div class="css_img_gen_char_count">
                            <span id="css_img_gen_char_current">0</span> / 500
                        </div>
                    </div>

                    <div class="css_img_gen_tips">
                        <div class="css_img_gen_tip_icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="16" x2="12" y2="12"/>
                                <line x1="12" y1="8" x2="12.01" y2="8"/>
                            </svg>
                        </div>
                        <p class="css_img_gen_tip_text">
                            <strong>Pro tip:</strong> Include details about style, lighting, colors, and composition for better results
                        </p>
                    </div>

                    <button id="css_img_gen_button" class="css_img_gen_button">
                        <svg class="css_img_gen_button_icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14"/>
                            <path d="m12 5 7 7-7 7"/>
                        </svg>
                        <span class="css_img_gen_button_text">Generate Image</span>
                        <span class="css_img_gen_spinner" style="display: none;">
                            <svg class="css_img_gen_spin" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 12a9 9 0 11-6-8.49"/>
                            </svg>
                            <span>Generating...</span>
                        </span>
                    </button>

                    <div id="css_img_gen_error" class="css_img_gen_error" style="display: none;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <span id="css_img_gen_error_text"></span>
                    </div>
                </div>

                <div class="css_img_gen_footer">
                    <p class="css_img_gen_footer_text">Powered by Advanced AI • <?php echo IMAGE_GENERATION_COST; ?> points per generation</p>
                </div>
            </div>

            <!-- Right Panel - Image Display -->
            <div class="css_img_gen_right_panel">
                <div id="css_img_gen_loading_state" class="css_img_gen_loading_state" style="display: none;">
                    <div class="css_img_gen_loading_animation">
                        <div class="css_img_gen_pulse"></div>
                    </div>
                    <p class="css_img_gen_loading_text">Creating your masterpiece...</p>
                    <p class="css_img_gen_loading_subtext">This usually takes 10-30 seconds</p>
                </div>

                <div id="css_img_gen_placeholder" class="css_img_gen_placeholder">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21 15 16 10 5 21"/>
                    </svg>
                    <p class="css_img_gen_placeholder_text">Your generated image will appear here</p>
                </div>

                <div id="css_img_gen_result" class="css_img_gen_result" style="display: none;">
                    <div class="css_img_gen_image_wrapper">
                        <div class="css_img_gen_image_container">
                            <img id="css_img_gen_image" class="css_img_gen_image" alt="Generated image">
                            <div class="css_img_gen_image_overlay">
                                <button class="css_img_gen_overlay_btn css_img_gen_fullscreen" title="View fullscreen">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="css_img_gen_image_actions">
                            <div class="css_img_gen_action_group">
                                <button id="css_img_gen_download" class="css_img_gen_action_btn css_img_gen_primary">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="7 10 12 15 17 10"/>
                                        <line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                    Download
                                </button>
                                <button id="css_img_gen_copy_prompt" class="css_img_gen_action_btn">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                    </svg>
                                    <span id="css_img_gen_copy_text">Copy Prompt</span>
                                </button>
                            </div>
                            <div class="css_img_gen_image_meta">
                                <span id="css_img_gen_dimensions" class="css_img_gen_dimensions"></span>
                                <span class="css_img_gen_separator">•</span>
                                <span id="css_img_gen_timestamp" class="css_img_gen_timestamp"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fullscreen Modal -->
    <div id="css_img_gen_modal" class="css_img_gen_modal" style="display: none;">
        <button class="css_img_gen_modal_close">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
        <img id="css_img_gen_modal_image" class="css_img_gen_modal_image" alt="Fullscreen view">
    </div>

    <style>
    * {
        box-sizing: border-box;
    }

    .css_img_gen_wrapper {
        width: 100%;
        min-height: 600px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", Arial, sans-serif;
    }

    .css_img_gen_layout {
        display: flex;
        gap: 2rem;
        height: 100%;
    }

    /* ============================================ */
    /* ADDED: Balance card styles */
    /* ============================================ */
    .css_img_gen_balance_card {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border: 2px solid rgba(102, 126, 234, 0.3);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }

    .css_img_gen_balance_header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #667eea;
        font-weight: 600;
        margin-bottom: 0.75rem;
    }

    .css_img_gen_balance_content {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }

    .css_img_gen_balance_item {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .css_img_gen_balance_label {
        font-size: 0.75rem;
        color: #6b7280;
        margin-bottom: 0.25rem;
    }

    .css_img_gen_balance_value {
        font-size: 1.25rem;
        font-weight: 700;
        color: #111827;
    }

    .css_img_gen_cost_info {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding-top: 0.75rem;
        border-top: 1px solid rgba(102, 126, 234, 0.2);
        font-size: 0.875rem;
        color: #4b5563;
    }

    .css_img_gen_cost_info strong {
        color: #667eea;
    }
    /* ============================================ */
    /* END OF ADDITION: Balance card styles */
    /* ============================================ */

    /* Left Panel Styles */
    .css_img_gen_left_panel {
        flex: 0 0 420px;
        display: flex;
        flex-direction: column;
    }

    .css_img_gen_header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .css_img_gen_logo {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        flex-shrink: 0;
    }

    .css_img_gen_title {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        color: #111827;
        letter-spacing: -0.025em;
    }

    .css_img_gen_subtitle {
        margin: 0.25rem 0 0;
        font-size: 0.875rem;
        color: #6b7280;
    }

    .css_img_gen_input_section {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .css_img_gen_label {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.5rem;
    }

    .css_img_gen_textarea_wrapper {
        position: relative;
        margin-bottom: 1rem;
    }

    .css_img_gen_textarea {
        width: 100%;
        padding: 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 0.9375rem;
        line-height: 1.5;
        resize: vertical;
        transition: all 0.2s ease;
        font-family: inherit;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
    }

    .css_img_gen_textarea:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        background: rgba(255, 255, 255, 0.95);
    }

    .css_img_gen_textarea::placeholder {
        color: #9ca3af;
        font-size: 0.875rem;
    }

    .css_img_gen_char_count {
        position: absolute;
        bottom: 0.75rem;
        right: 0.75rem;
        font-size: 0.75rem;
        color: #9ca3af;
        background: rgba(255, 255, 255, 0.9);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
    }

    .css_img_gen_tips {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        padding: 0.75rem;
        background: rgba(249, 250, 251, 0.5);
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }

    .css_img_gen_tip_icon {
        color: #667eea;
        flex-shrink: 0;
        margin-top: 0.125rem;
    }

    .css_img_gen_tip_text {
        margin: 0;
        font-size: 0.8125rem;
        color: #4b5563;
        line-height: 1.5;
    }

    .css_img_gen_tip_text strong {
        color: #374151;
    }

    .css_img_gen_button {
        padding: 0.875rem 2rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        width: 100%;
        justify-content: center;
    }

    .css_img_gen_button:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
    }

    .css_img_gen_button:active:not(:disabled) {
        transform: translateY(0);
    }

    .css_img_gen_button:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none !important;
    }

    .css_img_gen_button_icon {
        flex-shrink: 0;
    }

    .css_img_gen_spinner {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .css_img_gen_spin {
        animation: css_img_gen_rotate 1s linear infinite;
    }

    @keyframes css_img_gen_rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .css_img_gen_error {
        padding: 0.75rem;
        background-color: rgba(254, 242, 242, 0.8);
        border: 1px solid #fecaca;
        border-radius: 8px;
        color: #991b1b;
        margin-top: 1rem;
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        font-size: 0.875rem;
        animation: css_img_gen_shake 0.3s ease-in-out;
    }

    .css_img_gen_error svg {
        flex-shrink: 0;
        margin-top: 0.125rem;
    }

    @keyframes css_img_gen_shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }

    .css_img_gen_footer {
        margin-top: auto;
        padding-top: 1.5rem;
        text-align: center;
    }

    .css_img_gen_footer_text {
        font-size: 0.75rem;
        color: #9ca3af;
        margin: 0;
    }

    /* Right Panel Styles */
    .css_img_gen_right_panel {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        min-height: 500px;
    }

    .css_img_gen_placeholder {
        text-align: center;
        color: #9ca3af;
    }

    .css_img_gen_placeholder svg {
        opacity: 0.3;
        margin-bottom: 1rem;
    }

    .css_img_gen_placeholder_text {
        font-size: 0.875rem;
        margin: 0;
    }

    .css_img_gen_loading_state {
        text-align: center;
    }

    .css_img_gen_loading_animation {
        width: 80px;
        height: 80px;
        margin: 0 auto 2rem;
        position: relative;
    }

    .css_img_gen_pulse {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        animation: css_img_gen_pulse 2s ease-in-out infinite;
    }

    @keyframes css_img_gen_pulse {
        0%, 100% {
            transform: scale(0.8);
            opacity: 0.5;
        }
        50% {
            transform: scale(1.2);
            opacity: 0.2;
        }
    }

    .css_img_gen_loading_text {
        font-size: 1.125rem;
        font-weight: 600;
        color: #374151;
        margin: 0 0 0.5rem;
    }

    .css_img_gen_loading_subtext {
        font-size: 0.875rem;
        color: #6b7280;
        margin: 0;
    }

    .css_img_gen_result {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: css_img_gen_fadeIn 0.5s ease-in-out;
    }

    @keyframes css_img_gen_fadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    .css_img_gen_image_wrapper {
        width: 100%;
        max-width: 800px;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
    }

    .css_img_gen_image_container {
        position: relative;
        background: #f9fafb;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .css_img_gen_image {
        width: 100%;
        height: auto;
        display: block;
    }

    .css_img_gen_image_overlay {
        position: absolute;
        top: 1rem;
        right: 1rem;
        display: flex;
        gap: 0.5rem;
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .css_img_gen_image_container:hover .css_img_gen_image_overlay {
        opacity: 1;
    }

    .css_img_gen_overlay_btn {
        width: 40px;
        height: 40px;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .css_img_gen_overlay_btn:hover {
        background: rgba(0, 0, 0, 0.9);
        transform: scale(1.05);
    }

    .css_img_gen_image_actions {
        padding: 1rem;
        background: rgba(255, 255, 255, 0.95);
        border-top: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .css_img_gen_action_group {
        display: flex;
        gap: 0.5rem;
    }

    .css_img_gen_action_btn {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.5rem 1rem;
        background: rgba(255, 255, 255, 0.8);
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #374151;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .css_img_gen_action_btn:hover {
        background: white;
        border-color: #d1d5db;
        transform: translateY(-1px);
    }

    .css_img_gen_action_btn.css_img_gen_primary {
        background: #111827;
        color: white;
        border-color: #111827;
    }

    .css_img_gen_action_btn.css_img_gen_primary:hover {
        background: #1f2937;
        border-color: #1f2937;
    }

    .css_img_gen_action_btn svg {
        flex-shrink: 0;
    }

    .css_img_gen_image_meta {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.75rem;
        color: #6b7280;
    }

    .css_img_gen_dimensions {
        font-weight: 600;
        color: #4b5563;
    }

    .css_img_gen_separator {
        color: #d1d5db;
    }

    /* Modal Styles */
    .css_img_gen_modal {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.95);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        animation: css_img_gen_fadeIn 0.3s ease-in-out;
    }

    .css_img_gen_modal_close {
        position: absolute;
        top: 2rem;
        right: 2rem;
        width: 48px;
        height: 48px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .css_img_gen_modal_close:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.1);
    }

    .css_img_gen_modal_image {
        max-width: 90%;
        max-height: 90vh;
        border-radius: 8px;
    }

    /* Responsive Design */
    @media (max-width: 968px) {
        .css_img_gen_layout {
            flex-direction: column;
        }

        .css_img_gen_left_panel {
            flex: 1;
            width: 100%;
        }

        .css_img_gen_right_panel {
            min-height: 400px;
        }
    }

    @media (max-width: 640px) {
        .css_img_gen_header {
            flex-direction: column;
            text-align: center;
        }

        .css_img_gen_action_group {
            width: 100%;
        }

        .css_img_gen_action_btn {
            flex: 1;
            justify-content: center;
        }

        .css_img_gen_image_actions {
            flex-direction: column;
        }

        .css_img_gen_image_meta {
            width: 100%;
            justify-content: center;
        }
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const promptInput = document.getElementById('css_img_gen_prompt');
        const generateBtn = document.getElementById('css_img_gen_button');
        const buttonText = document.querySelector('.css_img_gen_button_text');
        const buttonIcon = document.querySelector('.css_img_gen_button_icon');
        const spinner = document.querySelector('.css_img_gen_spinner');
        const errorDiv = document.getElementById('css_img_gen_error');
        const errorText = document.getElementById('css_img_gen_error_text');
        const loadingState = document.getElementById('css_img_gen_loading_state');
        const placeholder = document.getElementById('css_img_gen_placeholder');
        const resultDiv = document.getElementById('css_img_gen_result');
        const imageElement = document.getElementById('css_img_gen_image');
        const downloadBtn = document.getElementById('css_img_gen_download');
        const copyBtn = document.getElementById('css_img_gen_copy_prompt');
        const copyText = document.getElementById('css_img_gen_copy_text');
        const dimensionsSpan = document.getElementById('css_img_gen_dimensions');
        const timestampSpan = document.getElementById('css_img_gen_timestamp');
        const charCount = document.getElementById('css_img_gen_char_current');
        const fullscreenBtn = document.querySelector('.css_img_gen_fullscreen');
        const modal = document.getElementById('css_img_gen_modal');
        const modalImage = document.getElementById('css_img_gen_modal_image');
        const modalClose = document.querySelector('.css_img_gen_modal_close');

        // ============================================
        // ADDED: Get the balance display element
        // ============================================
        const totalBalanceElement = document.getElementById('total_balance');
        const imageCost = <?php echo IMAGE_GENERATION_COST; ?>;
        // ============================================

        let currentImageUrl = '';
        let currentPrompt = '';

        // Character counter
        promptInput.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = Math.min(length, 500);
            if (length > 500) {
                this.value = this.value.substring(0, 500);
            }
        });

        // Generate button click
        generateBtn.addEventListener('click', async function() {
            const prompt = promptInput.value.trim();

            if (!prompt) {
                showError('Please describe what image you want to generate');
                promptInput.focus();
                return;
            }

            if (prompt.length < 10) {
                showError('Please provide a more detailed description (at least 10 characters)');
                promptInput.focus();
                return;
            }

            // ============================================
            // ADDED: Check if user has enough balance (client-side check)
            // ============================================
            const currentBalance = parseInt(totalBalanceElement.textContent);
            if (currentBalance < imageCost) {
                showError('Insufficient balance. You need ' + imageCost + ' points to generate an image.');
                return;
            }
            // ============================================

            currentPrompt = prompt;

            // Reset UI
            errorDiv.style.display = 'none';
            resultDiv.style.display = 'none';
            placeholder.style.display = 'none';

            // Show loading state
            loadingState.style.display = 'block';
            generateBtn.disabled = true;
            buttonText.style.display = 'none';
            buttonIcon.style.display = 'none';
            spinner.style.display = 'inline-flex';

            try {
                // Use the generic n8n webhook function via REST API
                const response = await fetch('/wp-json/custom/v1/n8n-webhook', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        // ============================================
                        // ADDED: Include nonce for authentication
                        // ============================================
                        'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                    },
                    body: JSON.stringify({
                        topic: prompt  // Using 'topic' as the parameter name to match the generic function
                    })
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    // ============================================
                    // ADDED: Handle authentication and balance errors specifically
                    // ============================================
                    if (response.status === 401) {
                        throw new Error('Please login to generate images');
                    } else if (response.status === 403 && errorData.code === 'insufficient_balance') {
                        throw new Error(errorData.message || 'Insufficient points balance');
                    }
                    // ============================================
                    throw new Error(errorData.message || `Server error: ${response.status}`);
                }

                const responseData = await response.json();

                // Check if the response has success flag
                if (!responseData.success) {
                    throw new Error(responseData.message || 'Failed to generate image');
                }

                // ============================================
                // ADDED: Update the displayed balance after successful generation
                // ============================================
                if (responseData.remaining_balance !== undefined) {
                    totalBalanceElement.textContent = responseData.remaining_balance;
                    // Update all balance displays if they exist
                    const allBalances = document.querySelectorAll('.css_img_gen_balance_value');
                    if (allBalances.length > 0) {
                        allBalances[0].textContent = responseData.remaining_balance;
                    }
                }
                // ============================================

                // Extract the actual data from the response
                const data = responseData.data;

                if (data.error) {
                    throw new Error(data.error);
                }

                // Handle the image data - adjust based on your n8n webhook response structure
                if (data.result && data.result.length > 0) {
                    const image = data.result[0];
                    currentImageUrl = image.url;

                    // Display image
                    imageElement.src = image.url;
                    dimensionsSpan.textContent = `${image.width || 1024} × ${image.height || 1024}px`;
                    timestampSpan.textContent = new Date().toLocaleTimeString();

                    // Hide loading, show result
                    loadingState.style.display = 'none';
                    resultDiv.style.display = 'flex';
                } else if (data.imageUrl) {
                    // Alternative response structure
                    currentImageUrl = data.imageUrl;

                    // Display image
                    imageElement.src = data.imageUrl;
                    dimensionsSpan.textContent = `${data.width || 1024} × ${data.height || 1024}px`;
                    timestampSpan.textContent = new Date().toLocaleTimeString();

                    // Hide loading, show result
                    loadingState.style.display = 'none';
                    resultDiv.style.display = 'flex';
                } else if (typeof data === 'string' && data.startsWith('http')) {
                    // If the response is just a URL string
                    currentImageUrl = data;

                    // Display image
                    imageElement.src = data;
                    dimensionsSpan.textContent = '1024 × 1024px'; // Default dimensions
                    timestampSpan.textContent = new Date().toLocaleTimeString();

                    // Hide loading, show result
                    loadingState.style.display = 'none';
                    resultDiv.style.display = 'flex';
                } else {
                    throw new Error('No image was generated. Please check your n8n webhook configuration.');
                }

            } catch (error) {
                loadingState.style.display = 'none';
                placeholder.style.display = 'block';
                showError(error.message || 'Failed to generate image. Please try again.');
                console.error('Image generation error:', error);
            } finally {
                // Reset button state
                generateBtn.disabled = false;
                buttonText.style.display = 'inline';
                buttonIcon.style.display = 'inline';
                spinner.style.display = 'none';
            }
        });

        // Download button
        downloadBtn.addEventListener('click', async function() {
            if (!currentImageUrl) return;

            this.disabled = true;
            const originalText = this.innerHTML;
            this.innerHTML = '<svg class="css_img_gen_spin" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-6-8.49"/></svg> Downloading...';

            try {
                const response = await fetch(currentImageUrl);
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `ai-generated-${Date.now()}.jpeg`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } catch (error) {
                showError('Failed to download image');
            } finally {
                this.disabled = false;
                this.innerHTML = originalText;
            }
        });

        // Copy prompt button
        copyBtn.addEventListener('click', async function() {
            if (!currentPrompt) return;

            try {
                await navigator.clipboard.writeText(currentPrompt);
                copyText.textContent = 'Copied!';
                setTimeout(() => {
                    copyText.textContent = 'Copy Prompt';
                }, 2000);
            } catch (error) {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = currentPrompt;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                copyText.textContent = 'Copied!';
                setTimeout(() => {
                    copyText.textContent = 'Copy Prompt';
                }, 2000);
            }
        });

        // Fullscreen functionality
        if (fullscreenBtn) {
            fullscreenBtn.addEventListener('click', function() {
                modalImage.src = currentImageUrl;
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            });
        }

        modalClose.addEventListener('click', function() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        });

        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });

        // ESC key to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });

        function showError(message) {
            errorText.textContent = message;
            errorDiv.style.display = 'flex';
        }

        // Enter key support (Ctrl/Cmd + Enter to generate)
        promptInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                generateBtn.click();
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
});
?>