=== SlimWP Simple Points ===
Contributors: yourname
Tags: points, rewards, gamification, user engagement, dual balance
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight dual-balance points system for WordPress with free and permanent points tracking.

== Description ==

SlimWP Simple Points is a powerful yet lightweight points system plugin that provides dual-balance functionality for WordPress websites. Perfect for gamification, user engagement, and reward systems.

**Key Features:**

* **Dual Balance System**: Separate free and permanent point balances
* **Automatic Rewards**: Registration bonuses, daily login rewards
* **Balance Management**: Daily/monthly balance resets for free points
* **Admin Dashboard**: Beautiful, responsive admin interface
* **User Profiles**: Points display in user profile pages
* **Developer Friendly**: Comprehensive API with hooks and filters
* **Shortcodes**: Display points anywhere on your site
* **Transaction History**: Complete audit trail of all point changes
* **Bulk Operations**: Mass update points for multiple users

**Dual Balance System:**

* **Free Balance**: Resettable points for daily rewards, temporary promotions
* **Permanent Balance**: Never-reset points for purchases, achievements, referrals

**Perfect For:**

* Membership sites
* E-learning platforms
* Community forums
* E-commerce stores
* Gaming websites
* Any site needing user engagement

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/slimwp-simple-points/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to SlimWP Points > Settings to configure automatic rewards
4. Start awarding points to your users!

== Frequently Asked Questions ==

= How does the dual balance system work? =

Users have two separate point balances:
- **Free Balance**: Can be reset daily/monthly, perfect for renewable rewards
- **Permanent Balance**: Never automatically reset, ideal for earned achievements

= Can I award points automatically? =

Yes! The plugin supports:
- Registration bonuses (free or permanent points)
- Daily login rewards (free or permanent points)
- Daily balance resets (free balance only)
- Monthly balance resets (free balance only)

= How do I display points on my site? =

Use these shortcodes:
- `[slimwp_points]` - Total points (free + permanent)
- `[slimwp_points_free]` - Free balance only
- `[slimwp_points_permanent]` - Permanent balance only
- `[slimwp_points type="free" user_id="123"]` - Specific balance for specific user

= Is there an API for developers? =

Yes! Comprehensive API includes:

**Get Points:**
- `slimwp_get_user_points($user_id)` - Total balance
- `slimwp_get_user_free_points($user_id)` - Free balance
- `slimwp_get_user_permanent_points($user_id)` - Permanent balance

**Modify Points:**
- `slimwp_add_user_points($user_id, $amount, $description, $balance_type)`
- `slimwp_subtract_user_points($user_id, $amount, $description)`
- `slimwp_set_user_balance($user_id, $amount, $description, $balance_type)`

**Hooks:**
- `slimwp_points_balance_updated` - Triggered when points change
- `slimwp_points_after_registration` - After user registration

= How do point deductions work? =

When subtracting points, the system automatically:
1. Deducts from free balance first
2. If free balance insufficient, deducts remainder from permanent balance
3. Prevents negative balances

= Can I reset user balances? =

Yes! You can:
- Set up automatic daily/monthly resets for free balance
- Manually reset individual user balances
- Bulk update multiple users at once

== Screenshots ==

1. Admin dashboard with beautiful statistics and transaction management
2. User profile integration showing dual balance system
3. Settings page with automatic reward configuration
4. Bulk operations for mass point updates

== Changelog ==

= 1.0.0 =
* Initial release
* Dual balance system (free + permanent points)
* Automatic registration and login rewards
* Daily/monthly balance reset functionality
* Admin dashboard with statistics
* User profile integration
* Comprehensive developer API
* Shortcode support
* Transaction history tracking
* Bulk operations
* Responsive design

== Upgrade Notice ==

= 1.0.0 =
Initial release of SlimWP Simple Points with dual balance system.

== Developer Documentation ==

**Basic Usage:**

```php
// Get user's total balance
$total = slimwp_get_user_points($user_id);

// Get individual balances
$free = slimwp_get_user_free_points($user_id);
$permanent = slimwp_get_user_permanent_points($user_id);

// Add points to specific balance
slimwp()->add_points($user_id, 50, 'Daily bonus', 'daily_login', 'free');
slimwp()->add_points($user_id, 100, 'Achievement', 'achievement', 'permanent');

// Subtract points (deducts from free first, then permanent)
slimwp_subtract_user_points($user_id, 75, 'Premium feature');

// Set specific balance
slimwp()->set_balance($user_id, 100, 'Daily reset', 'daily_reset', 'free');
```

**Hook into point changes:**

```php
add_action('slimwp_points_balance_updated', function($user_id, $amount, $new_total, $description) {
    // React to point changes
    error_log("User $user_id now has $new_total points");
}, 10, 4);
```

For complete documentation, visit the Settings page in your WordPress admin.
