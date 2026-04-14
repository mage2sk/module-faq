# Panth FAQ

[![Magento 2.4.4 - 2.4.8](https://img.shields.io/badge/Magento-2.4.4%20--%202.4.8-orange)]()
[![PHP 8.1 - 8.3](https://img.shields.io/badge/PHP-8.1%20--%208.3-blue)]()
[![Hyva + Luma](https://img.shields.io/badge/Theme-Hyva%20%2B%20Luma-blueviolet)]()

**Advanced FAQ module** for Magento 2 with multi-level assignment
capabilities. Create and manage FAQ items organized by categories,
and assign them to products, catalog categories, and CMS pages.
Full support for both Hyva and Luma themes.

---

## Features

- **FAQ Categories** — organize questions into categories with
  optional descriptions and images.
- **FAQ Items** — rich-text answers with WYSIWYG editor support.
- **Multi-level assignment** — assign FAQ items to products, catalog
  categories, and CMS pages from the FAQ item edit form.
- **Product page FAQs** — display relevant FAQs on product detail
  pages (as a tab or below content).
- **Category page FAQs** — show FAQs on catalog category pages.
- **CMS page FAQs** — embed FAQs on any CMS page.
- **FAQ widget** — place FAQ blocks anywhere via the Magento widget
  system.
- **Live search** — real-time client-side FAQ search with debouncing.
- **AJAX search** — server-side search endpoint for large FAQ sets.
- **Helpful voting** — visitors can rate FAQ items as helpful or not.
- **View count tracking** — track how often each FAQ is viewed.
- **SEO schema markup** — automatic JSON-LD FAQPage schema for
  Google rich results.
- **URL rewrites** — SEO-friendly URLs for FAQ pages and categories.
- **Custom CSS** — inject custom styles from admin configuration.
- **Hyva + Luma** — native Alpine.js templates for Hyva, vanilla JS
  accordion for Luma.

---

## Installation

```bash
composer require mage2kishan/module-faq
bin/magento module:enable Panth_Faq
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

### Verify

```bash
bin/magento module:status Panth_Faq
# Module is enabled
```

---

## Requirements

| | Required |
|---|---|
| Magento | 2.4.4 - 2.4.8 (Open Source / Commerce / Cloud) |
| PHP | 8.1 / 8.2 / 8.3 |
| Panth_Core | ^1.0 (installed automatically via Composer) |

---

## Configuration

Navigate to **Stores > Configuration > Panth Extensions > FAQ Settings**.

| Group | Key Settings |
|---|---|
| **General** | Enable/disable module, FAQ page URL key |
| **Display** | Items per page, search bar, category filter, accordion default state, view count, helpful voting |
| **Page Integration** | Enable/disable FAQs on product pages, category pages, CMS pages |
| **SEO** | Enable JSON-LD FAQPage schema markup |
| **Custom Styling** | Custom CSS field for minor overrides |

---

## Admin Management

- **FAQ Items** — `Panth Infotech > FAQ > FAQ Items` — create, edit,
  mass-delete, mass-enable/disable, mass show/hide from main page.
- **FAQ Categories** — `Panth Infotech > FAQ > FAQ Categories` —
  manage categories with names, descriptions, images, and sort order.
- **Assignment grids** — assign FAQ items to specific products,
  catalog categories, and CMS pages from the FAQ item edit form.

---

## Support

| Channel | Contact |
|---|---|
| Email | kishansavaliyakb@gmail.com |
| Website | https://kishansavaliya.com |
| WhatsApp | +91 84012 70422 |

---

## License

Commercial — see `LICENSE.txt`. Distribution is restricted to the
Adobe Commerce Marketplace.

---

## About the developer

Built and maintained by **Kishan Savaliya** at **Panth Infotech** —
https://kishansavaliya.com. High-quality, security-focused Magento 2
extensions and themes for both Hyva and Luma storefronts.
