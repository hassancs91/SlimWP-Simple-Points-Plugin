jQuery(document).ready(function($) {
    // Initialize Stripe if publishable key is available
    let stripe = null;
    if (slimwp_stripe.publishable_key) {
        stripe = Stripe(slimwp_stripe.publishable_key);
    }
    
    // Handle buy button clicks
    $('.slimwp-buy-button').on('click', function(e) {
        e.preventDefault();
        
        if (!stripe) {
            console.error('SlimWP Stripe: Stripe not initialized. Check publishable key.');
            alert('Stripe is not properly configured. Please contact the site administrator.');
            return;
        }
        
        const button = $(this);
        const packageId = button.data('package-id');
        const packageName = button.data('package-name');
        
        console.log('SlimWP Stripe: Processing purchase for package ID:', packageId);
        
        // Disable button and show loading state
        button.prop('disabled', true);
        const originalText = button.text();
        button.text(slimwp_stripe.loading_text);
        
        // Create checkout session
        $.ajax({
            url: slimwp_stripe.ajax_url,
            type: 'POST',
            data: {
                action: 'slimwp_create_checkout_session',
                package_id: packageId,
                nonce: slimwp_stripe.nonce
            },
            success: function(response) {
                console.log('SlimWP Stripe: AJAX response:', response);
                
                if (response.success) {
                    console.log('SlimWP Stripe: Redirecting to checkout with session ID:', response.data.session_id);
                    // Redirect to Stripe Checkout
                    stripe.redirectToCheckout({
                        sessionId: response.data.session_id
                    }).then(function(result) {
                        if (result.error) {
                            console.error('SlimWP Stripe: Checkout error:', result.error);
                            alert('Checkout error: ' + result.error.message);
                        }
                    });
                } else {
                    console.error('SlimWP Stripe: Server error:', response.data);
                    const errorMessage = response.data || slimwp_stripe.error_text;
                    alert('Error: ' + errorMessage);
                }
            },
            error: function(xhr, status, error) {
                console.error('SlimWP Stripe: AJAX error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                
                let errorMessage = slimwp_stripe.error_text;
                if (xhr.responseText) {
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.data) {
                            errorMessage = errorResponse.data;
                        }
                    } catch (e) {
                        // If not JSON, show the raw response
                        errorMessage = xhr.responseText.substring(0, 100) + '...';
                    }
                }
                
                alert('Network error: ' + errorMessage);
            },
            complete: function() {
                // Re-enable button
                button.prop('disabled', false);
                button.text(originalText);
            }
        });
    });
    
    // Handle success/cancel notices
    $('.slimwp-stripe-success-notice, .slimwp-stripe-cancel-notice').each(function() {
        const notice = $(this);
        
        // Auto-hide after 10 seconds
        setTimeout(function() {
            notice.fadeOut();
        }, 10000);
        
        // Add close button
        notice.prepend('<button type="button" class="notice-dismiss" aria-label="Dismiss this notice."><span class="screen-reader-text">Dismiss this notice.</span></button>');
        
        // Handle close button click
        notice.on('click', '.notice-dismiss', function() {
            notice.fadeOut();
        });
    });
});
