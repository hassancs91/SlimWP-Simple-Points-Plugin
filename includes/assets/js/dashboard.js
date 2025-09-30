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

    // Announcement Slider Functionality
    let currentSlide = 0;
    const slides = $('.announcement-slide');
    const totalSlides = slides.length;
    const dots = $('.ann-dot');
    const prevBtn = $('.ann-nav-prev');
    const nextBtn = $('.ann-nav-next');
    let autoSlideInterval = null;

    // Function to show specific slide
    function showSlide(index) {
        if (index < 0) index = totalSlides - 1;
        if (index >= totalSlides) index = 0;

        currentSlide = index;

        // Update slides
        slides.removeClass('active');
        slides.eq(index).addClass('active');

        // Update dots
        dots.removeClass('active');
        dots.eq(index).addClass('active');

        // Update button states
        prevBtn.prop('disabled', false);
        nextBtn.prop('disabled', false);

        if (totalSlides <= 1) {
            prevBtn.prop('disabled', true);
            nextBtn.prop('disabled', true);
        }
    }

    // Navigate to previous slide
    prevBtn.on('click', function() {
        if (!$(this).prop('disabled')) {
            showSlide(currentSlide - 1);
            resetAutoSlide();
        }
    });

    // Navigate to next slide
    nextBtn.on('click', function() {
        if (!$(this).prop('disabled')) {
            showSlide(currentSlide + 1);
            resetAutoSlide();
        }
    });

    // Click on dots to navigate
    dots.on('click', function() {
        const slideIndex = parseInt($(this).data('slide'));
        showSlide(slideIndex);
        resetAutoSlide();
    });

    // Auto-slide functionality
    function startAutoSlide() {
        if (totalSlides > 1) {
            autoSlideInterval = setInterval(function() {
                showSlide(currentSlide + 1);
            }, 5000); // Change slide every 5 seconds
        }
    }

    // Reset auto-slide when user interacts
    function resetAutoSlide() {
        if (autoSlideInterval) {
            clearInterval(autoSlideInterval);
            startAutoSlide();
        }
    }

    // Keyboard navigation for slider
    $(document).on('keydown', function(e) {
        if ($('.slimwp-announcements-slider').is(':visible')) {
            if (e.keyCode === 37) { // Left arrow
                showSlide(currentSlide - 1);
                resetAutoSlide();
            } else if (e.keyCode === 39) { // Right arrow
                showSlide(currentSlide + 1);
                resetAutoSlide();
            }
        }
    });

    // Initialize slider
    if (totalSlides > 0) {
        showSlide(0);
        startAutoSlide();
    }

    // Pause on hover
    $('.slimwp-announcements-slider').on('mouseenter', function() {
        if (autoSlideInterval) {
            clearInterval(autoSlideInterval);
        }
    }).on('mouseleave', function() {
        startAutoSlide();
    });

    // Promotion Slider Functionality
    let currentPromoSlide = 0;
    const promoSlides = $('.promotion-slide');
    const totalPromoSlides = promoSlides.length;
    const promoDots = $('.promo-dot');
    const promoPrevBtn = $('.promo-nav-prev');
    const promoNextBtn = $('.promo-nav-next');
    let promoAutoSlideInterval = null;

    // Function to show specific promotion slide
    function showPromoSlide(index) {
        if (index < 0) index = totalPromoSlides - 1;
        if (index >= totalPromoSlides) index = 0;

        currentPromoSlide = index;

        // Update slides
        promoSlides.removeClass('active');
        promoSlides.eq(index).addClass('active');

        // Update dots
        promoDots.removeClass('active');
        promoDots.eq(index).addClass('active');

        // Update button states
        promoPrevBtn.prop('disabled', false);
        promoNextBtn.prop('disabled', false);

        if (totalPromoSlides <= 1) {
            promoPrevBtn.prop('disabled', true);
            promoNextBtn.prop('disabled', true);
        }
    }

    // Navigate to previous promotion
    promoPrevBtn.on('click', function() {
        if (!$(this).prop('disabled')) {
            showPromoSlide(currentPromoSlide - 1);
            resetPromoAutoSlide();
        }
    });

    // Navigate to next promotion
    promoNextBtn.on('click', function() {
        if (!$(this).prop('disabled')) {
            showPromoSlide(currentPromoSlide + 1);
            resetPromoAutoSlide();
        }
    });

    // Click on dots to navigate promotions
    promoDots.on('click', function() {
        const slideIndex = parseInt($(this).data('slide'));
        showPromoSlide(slideIndex);
        resetPromoAutoSlide();
    });

    // Auto-slide functionality for promotions
    function startPromoAutoSlide() {
        if (totalPromoSlides > 1) {
            promoAutoSlideInterval = setInterval(function() {
                showPromoSlide(currentPromoSlide + 1);
            }, 7000); // Change slide every 7 seconds (different from announcements)
        }
    }

    // Reset auto-slide when user interacts
    function resetPromoAutoSlide() {
        if (promoAutoSlideInterval) {
            clearInterval(promoAutoSlideInterval);
            startPromoAutoSlide();
        }
    }

    // Initialize promotion slider
    if (totalPromoSlides > 0) {
        showPromoSlide(0);
        startPromoAutoSlide();
    }

    // Pause promotion slider on hover
    $('.slimwp-promotions-slider').on('mouseenter', function() {
        if (promoAutoSlideInterval) {
            clearInterval(promoAutoSlideInterval);
        }
    }).on('mouseleave', function() {
        startPromoAutoSlide();
    });

    // Promotion button click tracking
    $('.promotion-slide').on('click', 'a.promotion-button', function(e) {
        const slide = $(this).closest('.promotion-slide');
        const promotionId = slide.data('id');
        const isRemote = $(this).data('remote') === '1' || $(this).data('remote') === 1;

        // Track click
        $.post(slimwp_dashboard.ajax_url, {
            action: 'slimwp_track_promotion_click',
            promotion_id: promotionId,
            is_remote: isRemote ? 1 : 0,
            nonce: slimwp_dashboard.nonce
        });

        // Don't prevent default - let the link work normally
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