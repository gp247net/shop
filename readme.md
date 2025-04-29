<p align="center">
    <a href="https://gp247.net"><img src="https://static.gp247.net/logo/logo.png" height="100"></a>
    <a href="https://s-cart.org"><img src="https://s-cart.org/logo.png" height="100"><a/>
</p>
<p align="center">Free e-commerce system for businesses<br>
    <code><b>composer require GP247/Shop</b></code></p>

<p align="center">
<a href="https://packagist.org/packages/GP247/Shop"><img src="https://poser.pugx.org/GP247/Shop/d/total.svg" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/GP247/Shop"><img src="https://poser.pugx.org/GP247/Shop/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/GP247/Shop"><img src="https://poser.pugx.org/GP247/Shop/license.svg" alt="License"></a>
</p>

## Introduction

GP247/Shop is one of the core packages in the GP247 ecosystem, transforming GP247 into a powerful online store for businesses. This package provides comprehensive e-commerce functionality while inheriting all the features of the GP247 ecosystem.

## Key Features

### E-commerce Features
- Product Management
  - Product categories and attributes
  - Product variants and options
  - Inventory management
  - Bulk import/export
- Order Management
  - Order processing and tracking
  - Multiple payment gateways
  - Shipping methods integration
  - Order status management
- Customer Management
  - Customer profiles and accounts
  - Address management
  - Order history
  - Customer groups and discounts
- Marketing Tools
  - Promotions and discounts
  - Coupon management
  - Newsletter integration
  - Product reviews and ratings
- Shopping Features
  - Shopping cart
  - Wishlist
  - Product comparison
  - Recently viewed products
- Multi-vendor Support
  - Vendor dashboard
  - Commission management
  - Vendor product management
  - Vendor order tracking

### GP247 Ecosystem Features
- Page Content Management
- Flexible Template System
- Extensible Plugin System
- Navigation & Link Management
- Integrated Contact & Subscription Forms
- Multi-language Support
- SEO Optimization
- Mobile Responsive Design
- Security Features
- Backup and Restore

## Installation

### Option 1: New Installation with GP247 CMS
1. Install gp247/cms (Includes Laravel, GP247/Core, GP247/Front)
```bash
composer create-project gp247/cms
```

2. Install gp247/shop package
```bash
composer require gp247/shop
```

3. Register the service provider in `bootstrap/providers.php` (add to the end of the array)
```php
GP247\Shop\ShopServiceProvider::class,
```

4. Install and create sample data
```bash
php artisan gp247:shop-install
php artisan gp247:shop-sample
```

### Option 2: Using S-Cart Source Code
S-Cart already includes all the necessary components. You can view the full details at [S-Cart GitHub repository](https://github.com/s-cart/s-cart).

1. Install the package
```bash
composer create-project s-cart/s-cart
```

2. Install data
```bash
php artisan sc:install
php artisan sc:sample
```

<img src="https://static.s-cart.org/guide/info/s-cart-content.jpg">
<img src="https://static.s-cart.org/guide/use/common/shop.jpg">
<img src="https://static.s-cart.org/guide/use/common/dashboard.jpg">

## Customization

### Admin Views Customization
To customize admin views, run the following command:
```bash
php artisan vendor:publish --tag=gp247:view-shop-admin
```
The views will be published to `resources/views/vendor/gp247-shop-admin`

### Front Views Customization
To customize and update front views, run:
```bash
php artisan vendor:publish --tag=gp247:view-shop-admin
```
The views will be stored in `app/GP247/Templates/Default`

If you are not using the `Default` template, you need to manually copy the views from `vendor/gp247/shop/Views/front` to your new template directory.

## Documentation
- For complete GP247 system documentation, visit [https://gp247.net](https://gp247.net)
- For specific e-commerce features documentation, visit [https://s-cart.org](https://s-cart.org)

## License
The GP247/Shop is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
