# SlimWP Simple Points

A lightweight dual-balance points system for WordPress with free and permanent points tracking.

## Description

SlimWP Simple Points is a flexible and powerful points management system for WordPress that allows you to implement gamification, loyalty programs, and user engagement features on your website. The plugin features a unique dual-balance system with both free (temporary) and permanent points.

## Features

### Core Features
- **Dual Balance System**: Manage both free/temporary points and permanent points
- **User Dashboard**: Beautiful user-facing dashboard for points management
- **Admin Management**: Comprehensive admin interface for managing user points
- **Transaction History**: Complete audit trail of all point transactions
- **Shortcodes**: Display points and balances anywhere on your site
- **Security**: Built with WordPress security best practices

### Integrations
- **WooCommerce Integration**: Award points for purchases and allow points redemption
- **Paid Memberships Pro Integration**: Points based on membership levels
- **Stripe Integration**: Direct points purchase via Stripe
- **Custom Hooks**: Extensive hooks for developers to extend functionality

### Developer Features
- **REST API**: Full REST API support for points management
- **Hooks & Filters**: Extensive customization options
- **Clean Code**: Following WordPress coding standards
- **Database Optimized**: Efficient database queries with proper indexing

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Installation

### From WordPress Admin
1. Download the plugin zip file
2. Navigate to Plugins > Add New in your WordPress admin
3. Click "Upload Plugin" and select the downloaded file
4. Click "Install Now" and then "Activate"

### Manual Installation
1. Download and unzip the plugin
2. Upload the `slimwp-simple-points` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress

### From GitHub
1. Clone this repository or download as ZIP
2. Place in your `/wp-content/plugins/` directory
3. Activate through WordPress admin

## Configuration

After activation:
1. Navigate to **SlimWP Points** in your WordPress admin menu
2. Configure your point settings in the Settings tab
3. Set up integrations (WooCommerce, PMPro, Stripe) as needed
4. Customize email notifications and messages

## Usage

### Shortcodes

Display user's total balance:
```
[slimwp_points_balance]
```

Display free points only:
```
[slimwp_points_balance type="free"]
```

Display permanent points only:
```
[slimwp_points_balance type="permanent"]
```

Display points dashboard:
```
[slimwp_points_dashboard]
```

### PHP Functions

Get user's total balance:
```php
$balance = slimwp_get_user_balance($user_id);
```

Add points to user:
```php
slimwp_add_points($user_id, $amount, $type, $description);
```

Deduct points from user:
```php
slimwp_deduct_points($user_id, $amount, $type, $description);
```

## Hooks & Filters

### Actions
- `slimwp_after_add_points` - Fired after points are added
- `slimwp_after_deduct_points` - Fired after points are deducted
- `slimwp_after_transfer_points` - Fired after points transfer

### Filters
- `slimwp_points_balance_display` - Modify balance display
- `slimwp_can_add_points` - Control point addition
- `slimwp_can_deduct_points` - Control point deduction

## WooCommerce Integration

When WooCommerce is active:
1. Enable integration in SlimWP Points > Settings > WooCommerce
2. Set points per currency unit
3. Configure redemption settings
4. Points will be automatically awarded on order completion

## Paid Memberships Pro Integration

When PMPro is active:
1. Enable integration in SlimWP Points > Settings > PMPro
2. Configure points per membership level
3. Set renewal point bonuses
4. Points will be awarded on membership activation/renewal

## Support

For support, feature requests, and bug reports, please use the [GitHub Issues](https://github.com/hassancs91/SlimWP-Simple-Points-Plugin/issues) page.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## Security

If you discover any security related issues, please email the author directly instead of using the issue tracker.

## Changelog

### 1.0.7
- Added Paid Memberships Pro integration
- Improved security measures
- Enhanced admin interface
- Bug fixes and performance improvements

### 1.0.0
- Initial release
- Core points system
- WooCommerce integration
- Stripe integration

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## Author

**Hasan Aboul Hasan**
- Website: [https://learnwithhasan.com](https://learnwithhasan.com)
- GitHub: [@hassancs91](https://github.com/hassancs91)

## Acknowledgments

- WordPress Community
- All contributors and testers
- Plugin Update Checker by Yahnis Elsts