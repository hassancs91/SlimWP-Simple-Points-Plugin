/**
 * SlimWP Simple Points - Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Handle AJAX point updates
    $('.slimwp-update-points').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $form = $button.closest('form');
        var user_id = $form.find('input[name="user_id"]').val();
        var amount = $form.find('input[name="amount"]').val();
        var description = $form.find('input[name="description"]').val();
        var operation = $form.find('select[name="operation"]').val();
        var balance_type = $form.find('select[name="balance_type"]').val();
        
        if (!amount || !description) {
            alert('Please fill in all required fields.');
            return;
        }
        
        $button.prop('disabled', true).text('Updating...');
        
        $.ajax({
            url: slimwp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'slimwp_update_user_points',
                nonce: slimwp_admin.nonce,
                user_id: user_id,
                amount: amount,
                description: description,
                operation: operation,
                balance_type: balance_type
            },
            success: function(response) {
                if (response.success) {
                    // Update displayed balances
                    $('.slimwp-balance-total').text(response.data.new_balance);
                    $('.slimwp-balance-free').text(response.data.free_balance);
                    $('.slimwp-balance-permanent').text(response.data.permanent_balance);
                    
                    // Clear form
                    $form.find('input[name="amount"]').val('');
                    $form.find('input[name="description"]').val('');
                    
                    // Show success message
                    showNotice('Points updated successfully!', 'success');
                    
                    // Reload page to show updated transaction history
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showNotice('Error: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('AJAX error occurred. Please try again.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Update Points');
            }
        });
    });
    
    // Show admin notices
    function showNotice(message, type) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.slimwp-wrap, .wrap').first().prepend($notice);
        
        // Auto-dismiss after 3 seconds
        setTimeout(function() {
            $notice.fadeOut();
        }, 3000);
    }
    
    // Handle bulk operations confirmation
    $('.slimwp-bulk-form').on('submit', function(e) {
        var user_ids = $(this).find('input[name="user_ids"]').val();
        var operation = $(this).find('select[name="operation"]').val();
        var amount = $(this).find('input[name="amount"]').val();
        
        if (user_ids === 'all') {
            var confirmMessage = 'Are you sure you want to ' + operation + ' ' + amount + ' points for ALL users? This action cannot be undone.';
        } else {
            var userCount = user_ids.split(',').length;
            var confirmMessage = 'Are you sure you want to ' + operation + ' ' + amount + ' points for ' + userCount + ' user(s)? This action cannot be undone.';
        }
        
        if (!confirm(confirmMessage)) {
            e.preventDefault();
            return false;
        }
    });
    
    // Toggle settings sections
    $('.slimwp-settings-toggle').on('change', function() {
        var $toggle = $(this);
        var $section = $toggle.closest('.settings-row').find('.points-input');
        
        if ($toggle.is(':checked')) {
            $section.slideDown();
        } else {
            $section.slideUp();
        }
    });
    
    // Initialize settings toggles
    $('.slimwp-settings-toggle').each(function() {
        var $toggle = $(this);
        var $section = $toggle.closest('.settings-row').find('.points-input');
        
        if (!$toggle.is(':checked')) {
            $section.hide();
        }
    });
    
    // Format number inputs
    $('input[type="number"]').on('input', function() {
        var value = parseFloat($(this).val());
        if (value < 0) {
            $(this).val(0);
        }
    });
    
    // Auto-save settings (optional enhancement)
    $('.slimwp-auto-save').on('change', function() {
        var $form = $(this).closest('form');
        var $status = $('.slimwp-save-status');
        
        $status.text('Saving...').show();
        
        $.ajax({
            url: slimwp_admin.ajax_url,
            type: 'POST',
            data: $form.serialize() + '&action=slimwp_save_settings&nonce=' + slimwp_admin.nonce,
            success: function(response) {
                if (response.success) {
                    $status.text('Saved!').removeClass('error').addClass('success');
                } else {
                    $status.text('Error saving').removeClass('success').addClass('error');
                }
                
                setTimeout(function() {
                    $status.fadeOut();
                }, 2000);
            },
            error: function() {
                $status.text('Error saving').removeClass('success').addClass('error');
                setTimeout(function() {
                    $status.fadeOut();
                }, 2000);
            }
        });
    });
    
});
