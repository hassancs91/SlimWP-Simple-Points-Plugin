jQuery(document).ready(function($) {
    'use strict';

    // Auto-refresh functionality
    let refreshInterval = null;
    const refreshButton = $('#slimwp-refresh-dashboard');

    // Manual refresh
    refreshButton.on('click', function() {
        refreshDashboard();
    });

    // Auto-refresh setup
    if (slimwp_dashboard.refresh_interval) {
        refreshInterval = setInterval(function() {
            refreshDashboard(true); // Silent refresh
        }, slimwp_dashboard.refresh_interval);
    }

    // Refresh dashboard function
    function refreshDashboard(silent = false) {
        if (!silent) {
            refreshButton.prop('disabled', true).addClass('updating-message');
        }

        $.ajax({
            url: slimwp_dashboard.ajax_url,
            type: 'POST',
            data: {
                action: 'slimwp_dashboard_refresh',
                nonce: slimwp_dashboard.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateDashboardStats(response.data.stats);
                    updateActivityFeed(response.data.activity);

                    if (!silent) {
                        showNotification('Dashboard refreshed successfully', 'success');
                    }
                }
            },
            error: function() {
                if (!silent) {
                    showNotification('Failed to refresh dashboard', 'error');
                }
            },
            complete: function() {
                if (!silent) {
                    refreshButton.prop('disabled', false).removeClass('updating-message');
                }
            }
        });
    }

    // Update dashboard statistics
    function updateDashboardStats(stats) {
        // Update stat cards with animation
        $('.slimwp-stat-card').each(function(index) {
            const card = $(this);
            const valueElement = card.find('.stat-value');
            const oldValue = parseInt(valueElement.text().replace(/,/g, ''));
            let newValue = 0;

            // Map stats to cards
            switch(index) {
                case 0: newValue = stats.total_users; break;
                case 1: newValue = stats.total_points; break;
                case 2: newValue = stats.transactions_today; break;
                case 3: newValue = stats.avg_balance; break;
            }

            if (oldValue !== newValue) {
                animateNumber(valueElement, oldValue, newValue);
            }
        });
    }

    // Animate number change
    function animateNumber(element, from, to) {
        const duration = 500;
        const steps = 20;
        const increment = (to - from) / steps;
        let current = from;
        let step = 0;

        const timer = setInterval(function() {
            current += increment;
            step++;

            if (step >= steps) {
                current = to;
                clearInterval(timer);
            }

            element.text(numberWithCommas(Math.round(current)));
        }, duration / steps);
    }

    // Format number with commas
    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // Update activity feed
    function updateActivityFeed(activities) {
        const feed = $('.activity-feed');

        if (!activities || activities.length === 0) {
            return;
        }

        // Create new activity HTML
        let newHtml = '';
        activities.forEach(function(activity) {
            const isPositive = parseFloat(activity.amount) >= 0;
            const timeAgo = getTimeAgo(new Date(activity.created_at));

            newHtml += `
                <div class="activity-item" style="display: none;">
                    <div class="activity-avatar">
                        <img src="${getGravatarUrl(activity.user_id)}" alt="">
                    </div>
                    <div class="activity-details">
                        <div class="activity-main">
                            <strong>User #${activity.user_id}</strong>
                            ${escapeHtml(activity.description)}
                        </div>
                        <div class="activity-meta">
                            <span class="activity-amount ${isPositive ? 'positive' : 'negative'}">
                                ${isPositive ? '+' : ''}${parseFloat(activity.amount).toFixed(2)} points
                            </span>
                            <span class="activity-time">${timeAgo}</span>
                        </div>
                    </div>
                </div>
            `;
        });

        // Replace feed content with fade effect
        feed.fadeOut(200, function() {
            feed.html(newHtml);
            feed.children().fadeIn(300);
            feed.fadeIn(200);
        });
    }

    // Dismiss content (announcements/promotions)
    $('.dismiss-btn').on('click', function(e) {
        e.preventDefault();
        const button = $(this);
        const contentId = button.data('content-id');
        const isRemote = button.data('remote') === '1' || button.data('remote') === 1;
        const item = button.closest('.announcement-item');

        $.ajax({
            url: slimwp_dashboard.ajax_url,
            type: 'POST',
            data: {
                action: 'slimwp_dismiss_content',
                content_id: contentId,
                is_remote: isRemote ? 1 : 0,
                nonce: slimwp_dashboard.nonce
            },
            success: function(response) {
                if (response.success) {
                    item.slideUp(300, function() {
                        item.remove();

                        // Update count badge
                        const badge = $('.widget-badge');
                        const count = parseInt(badge.text()) - 1;
                        badge.text(count);

                        if (count === 0) {
                            badge.hide();
                        }

                        // If no announcements left, hide the section
                        if ($('.announcement-item').length === 0) {
                            $('.slimwp-dashboard-widget:has(.slimwp-announcements)').fadeOut();
                        }
                    });
                }
            }
        });
    });

    // Promotion card click tracking
    $('.promotion-card').on('click', 'a.promotion-button', function() {
        const card = $(this).closest('.promotion-card');
        const promotionId = card.data('id');

        // Track click (you could send this to server)
        $.post(slimwp_dashboard.ajax_url, {
            action: 'slimwp_track_promotion_click',
            promotion_id: promotionId,
            nonce: slimwp_dashboard.nonce
        });
    });

    // Show notification
    function showNotification(message, type = 'info') {
        const notification = $('<div class="slimwp-notification"></div>')
            .addClass('notice-' + type)
            .text(message)
            .css({
                position: 'fixed',
                top: '32px',
                right: '20px',
                background: type === 'success' ? '#00a32a' : '#d63638',
                color: '#fff',
                padding: '12px 20px',
                borderRadius: '4px',
                boxShadow: '0 2px 8px rgba(0, 0, 0, 0.2)',
                zIndex: 9999,
                opacity: 0,
                transform: 'translateX(100px)'
            });

        $('body').append(notification);

        notification.animate({
            opacity: 1,
            right: '20px'
        }, 300);

        setTimeout(function() {
            notification.animate({
                opacity: 0,
                right: '-100px'
            }, 300, function() {
                notification.remove();
            });
        }, 3000);
    }

    // Get time ago string
    function getTimeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);

        let interval = seconds / 31536000;
        if (interval > 1) {
            return Math.floor(interval) + " years ago";
        }

        interval = seconds / 2592000;
        if (interval > 1) {
            return Math.floor(interval) + " months ago";
        }

        interval = seconds / 86400;
        if (interval > 1) {
            return Math.floor(interval) + " days ago";
        }

        interval = seconds / 3600;
        if (interval > 1) {
            return Math.floor(interval) + " hours ago";
        }

        interval = seconds / 60;
        if (interval > 1) {
            return Math.floor(interval) + " minutes ago";
        }

        return Math.floor(seconds) + " seconds ago";
    }

    // Get Gravatar URL
    function getGravatarUrl(userId) {
        // This is a placeholder - in production, you'd get the actual email hash
        return 'https://www.gravatar.com/avatar/?d=mp&s=32';
    }

    // Escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Chart initialization (if you want to add charts later)
    function initializeCharts() {
        // Placeholder for chart initialization
        // You can use Chart.js or any other library here
    }

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Alt + R for refresh
        if (e.altKey && e.keyCode === 82) {
            e.preventDefault();
            refreshDashboard();
        }

        // Alt + N for new content
        if (e.altKey && e.keyCode === 78) {
            e.preventDefault();
            window.location.href = slimwp_dashboard.admin_url + 'admin.php?page=slimwp-content-manager&action=new';
        }
    });

    // Tooltips
    $('.slimwp-dashboard [title]').each(function() {
        const element = $(this);
        const title = element.attr('title');

        element.removeAttr('title');
        element.on('mouseenter', function() {
            $('<div class="slimwp-tooltip">' + title + '</div>')
                .appendTo('body')
                .css({
                    position: 'absolute',
                    background: '#1d2327',
                    color: '#fff',
                    padding: '5px 10px',
                    borderRadius: '4px',
                    fontSize: '12px',
                    zIndex: 10000,
                    top: element.offset().top - 30,
                    left: element.offset().left + (element.outerWidth() / 2),
                    transform: 'translateX(-50%)'
                })
                .fadeIn(200);
        }).on('mouseleave', function() {
            $('.slimwp-tooltip').remove();
        });
    });

    // Handle window resize
    let resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            // Adjust layouts if needed
            adjustDashboardLayout();
        }, 250);
    });

    // Adjust dashboard layout
    function adjustDashboardLayout() {
        const width = $(window).width();

        if (width < 768) {
            $('.slimwp-dashboard-grid').addClass('mobile-view');
        } else {
            $('.slimwp-dashboard-grid').removeClass('mobile-view');
        }
    }

    // Initial layout adjustment
    adjustDashboardLayout();

    // Clean up on page unload
    $(window).on('beforeunload', function() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    });
});