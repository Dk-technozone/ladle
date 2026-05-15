# Ladle Themes PHP Marketplace

A premium, mobile-first PHP marketplace script for selling WordPress themes, Blogger templates, Elementor kits, SEO themes, and digital products. It uses SQLite for storage, Tailwind CSS for the UI, Boxicons for icons, and includes a WooCommerce product extractor.

## Features

- Dark premium landing page inspired by modern theme marketplaces
- Sticky navbar, cinematic hero, featured product grid, categories, testimonials, CTA banner, and footer
- Mobile-first responsive layout with a sticky bottom menu
- SQLite database auto-created at `data/marketplace.sqlite`
- Seeded sample themes for first launch
- WooCommerce REST product importer for published products
- SEO-friendly meta tags and Product schema microdata
- Dark/light mode toggle, scroll reveal animations, counters, gradients, and glassmorphism cards

## Requirements

- PHP 8.1+
- SQLite PDO extension
- A web server that can run PHP scripts

## Run locally

```bash
php -S 127.0.0.1:8000
```

Open `http://127.0.0.1:8000` in your browser.

## WooCommerce importer

Create REST API credentials in WordPress admin under **WooCommerce → Settings → Advanced → REST API**. Use read access credentials, then submit:

- Store URL, for example `https://example.com`
- Consumer key beginning with `ck_`
- Consumer secret beginning with `cs_`
- Import limit between 1 and 50

The importer reads `/wp-json/wc/v3/products`, normalizes product data, and stores it in SQLite so imported products appear in the marketplace grid.
