# PSP Marketplace — ThemeForest Clone PHP App

A futuristic multi-vendor digital marketplace inspired by WBThemes, ThemeForest, Framer-style motion, and modern PSP gaming UI aesthetics. The old single-file implementation has been replaced with a modular PHP + SQLite architecture where `index.php` is only a small front controller.

## What the platform sells

- WordPress themes: movie, OTT, magazine, SEO, affiliate, WooCommerce
- Blogger templates: movie, anime, news, minimal, Adsense-ready
- PHP scripts: auto scraper, OTT clone, movie database, download systems, SaaS tools
- Plugins: auto post generator, IMDb fetcher, player plugin, download button generator
- UI kits and SaaS templates: dashboards, app shells, landing pages, creator tools
- Elementor kits: news portals, streaming layouts, product-selling UI
- Website services: cloning, speed optimization, SEO fixing, malware cleanup, redesign

## Architecture

```txt
/app                 PHP bootstrap, controllers, services, repositories
/admin               Admin route placeholder/module folder
/vendor              Vendor route placeholder/module folder
/storage             Cache and local runtime storage
/uploads             Organized upload folders
/themes              Future installable marketplace theme packages
/plugins             Future installable marketplace plugin packages
/public              Public entry assets placeholder
/assets              CSS and JavaScript
/screenshots         Product screenshot storage
/downloads           Protected digital files placeholder
/database/sqlite     SQLite database location
/api                 API module placeholder
/components          UI component module placeholder
/pages               Static page module placeholder
/layouts             Layout module placeholder
/modules             Feature module placeholder
/views               PHP views and layouts
```

## Implemented modules

### Frontend

- PSP-inspired dark neon UI
- Glassmorphism cards
- Floating gradients and particles
- 3D hover product cards
- Animated counters and scroll reveal
- Mobile bottom navigation
- Floating search and live AJAX quick view endpoint
- Homepage sections for hero, featured products, categories, live demo showcase, trending products, reviews-ready blocks, features, and CTA

### Marketplace

- Products
- Vendors
- Orders
- Reviews
- Downloads
- Licenses
- Product features
- Product FAQs
- Changelog
- Wishlists
- Coupons
- Withdrawals
- Support tickets
- Activity/security logs
- WooCommerce imports
- Global settings

### Admin panel

- Product management
- Vendor management and verification
- Product approval queue
- Order/license architecture
- Marketing/coupon module page
- SEO manager module page
- System tools module page
- WooCommerce product extractor

### Vendor dashboard

- Vendor login
- Dashboard stats
- Product upload/edit system
- Product moderation status
- Earnings/sales placeholders
- Support ticket module

## Product meta fields

The product form covers the requested ThemeForest-style metadata:

- Basic: name, slug, short description, full description, product type, category, tags, version, release date, last updated, status
- Pricing: regular price, sale price, extended license price, subscription price, offer expiry, free download toggle
- Demo: live preview URL, admin demo URL, documentation URL, video preview URL, download preview ZIP
- Media: thumbnail, featured banner, product logo, screenshot gallery, mobile screenshots, GIF preview, video showcase
- Compatibility: browsers, CMS, PHP version, framework, responsive, retina, dark mode, RTL, multi-language
- SEO: meta title, meta description, focus keywords, OpenGraph image, structured data JSON, canonical URL, robots control
- Download system: main ZIP, additional files, file size, download limit, license type, support expiry, auto update token
- Marketplace stats: sales, views, wishlist count, rating average, reviews count, trending score

## Login credentials

Admin:

- Email: `admin@psp.local`
- Password: `admin123`

Vendor:

- Email: `vendor@psp.local`
- Password: `vendor123`

## Requirements

- PHP 8.1+
- PDO SQLite extension
- No Composer/npm install required

## Run locally

```bash
php -S 127.0.0.1:8000
```

Open:

- Frontend: `http://127.0.0.1:8000`
- Catalog: `http://127.0.0.1:8000/index.php?route=catalog`
- Admin: `http://127.0.0.1:8000/index.php?area=admin&route=login`
- Vendor: `http://127.0.0.1:8000/index.php?area=vendor&route=login`

## WooCommerce importer

Create read-only WooCommerce REST credentials in WordPress admin under **WooCommerce → Settings → Advanced → REST API**. In the admin panel, open **Woo Import**, select a vendor/category, enter the store URL and credentials, and import products into SQLite.
