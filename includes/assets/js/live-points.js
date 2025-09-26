/**
 * SlimWP Live Points - Real-time balance updates
 * 
 * This script handles live updating of points balances in shortcodes
 * and provides a global API for triggering updates from any code.
 */

(function($) {
    'use strict';
    
    // Global object for the live points system
    window.SlimWP_LivePoints = {
        
        // Configuration
        config: {
            defaultRefreshInterval: 30000, // 30 seconds default
            animationDuration: 500,
            retryAttempts: 3,
            retryDelay: 1000
        },
        
        // Active shortcodes tracking
        activeShortcodes: [],
        refreshIntervals: {},
        
        /**
         * Initialize the live points system
         */
        init: function() {
            console.log('SlimWP Live Points: Initializing...');
            
            // Find all live points shortcodes on the page
            this.discoverShortcodes();
            
            // Set up auto-refresh for shortcodes that have it enabled
            this.setupAutoRefresh();
            
            // Listen for custom events
            this.setupEventListeners();
            
            console.log('SlimWP Live Points: Initialized with ' + this.activeShortcodes.length + ' shortcodes');
        },
        
        /**
         * Discover all live points shortcodes on the page
         */
        discoverShortcodes: function() {
            var self = this;
            
            $('.slimwp-points-live').each(function() {
                var $element = $(this);
                var shortcodeData = {
                    element: $element,
                    id: $element.attr('id'),
                    userId: $element.data('user-id'),
                    type: $element.data('type'),
                    refresh: parseInt($element.data('refresh')) || 0,
                    animate: $element.data('animate') === 'true',
                    format: $element.data('format'),
                    decimals: parseInt($element.data('decimals')) || 0,
                    currencySymbol: $element.data('currency-symbol'),
                    lastBalance: parseFloat($element.data('raw-balance')) || 0
                };
                
                self.activeShortcodes.push(shortcodeData);
                console.log('SlimWP Live Points: Discovered shortcode', shortcodeData);
            });
        },
        
        /**
         * Set up auto-refresh intervals for shortcodes that have refresh enabled
         */
        setupAutoRefresh: function() {
            var self = this;
            
            this.activeShortcodes.forEach(function(shortcode) {
                if (shortcode.refresh > 0) {
                    var intervalMs = shortcode.refresh * 1000;
                    
                    self.refreshIntervals[shortcode.id] = setInterval(function() {
                        self.updateShortcode(shortcode);
                    }, intervalMs);
                    
                    console.log('SlimWP Live Points: Auto-refresh enabled for ' + shortcode.id + ' (' + shortcode.refresh + 's)');
                }
            });
        },
        
        /**
         * Set up event listeners for manual updates
         */
        setupEventListeners: function() {
            var self = this;
            
            // Listen for global balance update events
            $(document).on('slimwp:balance:updated', function(event, data) {
                console.log('SlimWP Live Points: Received balance update event', data);
                self.handleBalanceUpdate(data);
            });
            
            // Listen for manual refresh events
            $(document).on('slimwp:refresh:all', function() {
                console.log('SlimWP Live Points: Manual refresh all requested');
                self.refreshAll();
            });
            
            // Listen for specific user refresh events
            $(document).on('slimwp:refresh:user', function(event, userId) {
                console.log('SlimWP Live Points: Manual refresh requested for user ' + userId);
                self.refreshUser(userId);
            });
        },
        
        /**
         * Update a specific shortcode's balance
         */
        updateShortcode: function(shortcode, retryCount) {
            var self = this;
            retryCount = retryCount || 0;
            
            // Show loading state
            if (shortcode.animate) {
                shortcode.element.addClass('slimwp-loading');
            }
            
            // AJAX request to get updated balance
            $.ajax({
                url: slimwp_live.ajax_url,
                type: 'POST',
                data: {
                    action: 'slimwp_get_live_balance',
                    user_id: shortcode.userId,
                    type: shortcode.type,
                    nonce: slimwp_live.nonce
                },
                timeout: 10000,
                success: function(response) {
                    shortcode.element.removeClass('slimwp-loading');
                    
                    if (response.success && response.data) {
                        self.updateShortcodeDisplay(shortcode, response.data.balance);
                    } else {
                        console.error('SlimWP Live Points: Failed to update ' + shortcode.id, response);
                        self.showError(shortcode, response.data || 'Update failed');
                    }
                },
                error: function(xhr, status, error) {
                    shortcode.element.removeClass('slimwp-loading');
                    
                    if (retryCount < self.config.retryAttempts) {
                        console.log('SlimWP Live Points: Retrying update for ' + shortcode.id + ' (attempt ' + (retryCount + 1) + ')');
                        setTimeout(function() {
                            self.updateShortcode(shortcode, retryCount + 1);
                        }, self.config.retryDelay * (retryCount + 1));
                    } else {
                        console.error('SlimWP Live Points: Failed to update ' + shortcode.id + ' after ' + self.config.retryAttempts + ' attempts', error);
                        self.showError(shortcode, 'Network error');
                    }
                }
            });
        },
        
        /**
         * Update the visual display of a shortcode with new balance
         */
        updateShortcodeDisplay: function(shortcode, newBalance) {
            var formattedBalance = this.formatBalance(newBalance, shortcode);
            var $balanceSpan = shortcode.element.find('.slimwp-balance');
            var hasChanged = Math.abs(newBalance - shortcode.lastBalance) > 0.001;
            
            // Update the balance display
            $balanceSpan.text(formattedBalance);
            
            // Update raw balance data
            shortcode.element.attr('data-raw-balance', newBalance);
            shortcode.lastBalance = newBalance;
            
            // Show animation if balance changed and animation is enabled
            if (hasChanged && shortcode.animate) {
                shortcode.element.addClass('slimwp-updated');
                setTimeout(function() {
                    shortcode.element.removeClass('slimwp-updated');
                }, this.config.animationDuration);
            }
            
            // Trigger custom event
            $(document).trigger('slimwp:shortcode:updated', {
                shortcode: shortcode,
                newBalance: newBalance,
                changed: hasChanged
            });
            
            console.log('SlimWP Live Points: Updated ' + shortcode.id + ' to ' + newBalance);
        },
        
        /**
         * Format balance according to shortcode settings
         */
        formatBalance: function(balance, shortcode) {
            var formatted;
            
            if (shortcode.format === 'currency') {
                formatted = shortcode.currencySymbol + this.numberFormat(balance, shortcode.decimals);
            } else {
                formatted = this.numberFormat(balance, shortcode.decimals);
            }
            
            return formatted;
        },
        
        /**
         * Number formatting helper
         */
        numberFormat: function(number, decimals) {
            return parseFloat(number).toLocaleString(undefined, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        },
        
        /**
         * Show error state for a shortcode
         */
        showError: function(shortcode, message) {
            var $balanceSpan = shortcode.element.find('.slimwp-balance');
            var originalText = $balanceSpan.text();
            
            shortcode.element.addClass('slimwp-error');
            $balanceSpan.text('Error');
            
            // Restore original text after 3 seconds
            setTimeout(function() {
                shortcode.element.removeClass('slimwp-error');
                $balanceSpan.text(originalText);
            }, 3000);
        },
        
        /**
         * Handle balance update events from external sources
         */
        handleBalanceUpdate: function(data) {
            var self = this;
            
            // Update relevant shortcodes
            this.activeShortcodes.forEach(function(shortcode) {
                var shouldUpdate = false;
                
                if (data.userId && shortcode.userId == data.userId) {
                    shouldUpdate = true;
                } else if (data.allUsers && shortcode.userId == slimwp_live.current_user_id) {
                    shouldUpdate = true;
                }
                
                if (shouldUpdate) {
                    self.updateShortcode(shortcode);
                }
            });
        },
        
        /**
         * Refresh all shortcodes
         */
        refreshAll: function() {
            var self = this;
            this.activeShortcodes.forEach(function(shortcode) {
                self.updateShortcode(shortcode);
            });
        },
        
        /**
         * Refresh shortcodes for a specific user
         */
        refreshUser: function(userId) {
            var self = this;
            this.activeShortcodes.forEach(function(shortcode) {
                if (shortcode.userId == userId) {
                    self.updateShortcode(shortcode);
                }
            });
        },
        
        /**
         * Stop all auto-refresh intervals
         */
        stopAutoRefresh: function() {
            Object.keys(this.refreshIntervals).forEach(function(id) {
                clearInterval(this.refreshIntervals[id]);
                delete this.refreshIntervals[id];
            }, this);
        },
        
        /**
         * Add a new shortcode dynamically (for AJAX-loaded content)
         */
        addShortcode: function($element) {
            var shortcodeData = {
                element: $element,
                id: $element.attr('id'),
                userId: $element.data('user-id'),
                type: $element.data('type'),
                refresh: parseInt($element.data('refresh')) || 0,
                animate: $element.data('animate') === 'true',
                format: $element.data('format'),
                decimals: parseInt($element.data('decimals')) || 0,
                currencySymbol: $element.data('currency-symbol'),
                lastBalance: parseFloat($element.data('raw-balance')) || 0
            };
            
            this.activeShortcodes.push(shortcodeData);
            
            // Set up auto-refresh if enabled
            if (shortcodeData.refresh > 0) {
                var intervalMs = shortcodeData.refresh * 1000;
                var self = this;
                
                this.refreshIntervals[shortcodeData.id] = setInterval(function() {
                    self.updateShortcode(shortcodeData);
                }, intervalMs);
            }
            
            console.log('SlimWP Live Points: Added dynamic shortcode', shortcodeData);
        }
    };
    
    // Global convenience functions
    window.slimwp_refresh_all = function() {
        SlimWP_LivePoints.refreshAll();
    };
    
    window.slimwp_refresh_user = function(userId) {
        SlimWP_LivePoints.refreshUser(userId);
    };
    
    window.slimwp_trigger_update = function(userId, allUsers) {
        $(document).trigger('slimwp:balance:updated', {
            userId: userId,
            allUsers: allUsers || false
        });
    };
    
    // jQuery plugin for easy integration
    $.fn.slimwp_refresh = function() {
        return this.each(function() {
            var $this = $(this);
            if ($this.hasClass('slimwp-points-live')) {
                var shortcode = SlimWP_LivePoints.activeShortcodes.find(function(s) {
                    return s.element.is($this);
                });
                if (shortcode) {
                    SlimWP_LivePoints.updateShortcode(shortcode);
                }
            }
        });
    };
    
})(jQuery);