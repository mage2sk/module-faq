# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.5]

### Changed
- Code cleanup: removed redundant inline comments and docblocks from the PHP source. No functional changes.

## [1.1.4] - README rewrite

### Changed
- Rewrote README to match the gold template structure: SEO meta comment, badge
  row with live product page link, Quick Answer block, hire/agency promo,
  full configuration table from system.xml, How It Works section, updated FAQ
  entries, Support table with product page row, and Quick Links table.
- Canonical and all product page links updated to kishansavaliya.com/magento-2-faq.html.
- Removed commercemarketplace.adobe.com link; Marketplace text now links to
  the live product URL.
- Added per-store-view content override detail (added in 1.1.0) to Key Features.

## [1.1.3] - Upload extension deny-list (defense-in-depth)

### Added
- `Controller/Adminhtml/Category/Image/Upload` now calls
  `Panth\Core\Security\UploadExtensionPolicy::assertSafeExtension()` before
  `saveFileToTmpDir()` — a hard executable deny-list independent of the
  ImageUploader allowlist. Admin-gated, defense-in-depth. Requires
  `mage2kishan/module-core ^1.0.17`.

## [1.0.0] — Initial release

### Added
- **FAQ Categories** — create and manage FAQ categories with names,
  descriptions, optional images, URL keys, and sort order.
- **FAQ Items** — create FAQ items with rich-text answers (WYSIWYG),
  assign to categories, products, catalog categories, and CMS pages.
- **Multi-level assignment** — assign FAQ items to products, catalog
  categories, and CMS pages from both the FAQ item edit form and the
  entity edit forms (bidirectional assignment grids).
- **Main FAQ page** — dedicated FAQ listing page with category filter
  tabs, live search, and accordion UI.
- **FAQ category pages** — individual category view pages with
  SEO-friendly URLs via URL rewrites.
- **FAQ detail pages** — individual FAQ item view pages with view
  count tracking.
- **Product page integration** — display assigned FAQs on product
  detail pages (configurable position: tab or below content).
- **Category page integration** — display assigned FAQs on catalog
  category pages.
- **CMS page integration** — display assigned FAQs on CMS pages.
- **FAQ widget** — place FAQ blocks anywhere using the Magento widget
  system.
- **Live search** — real-time client-side FAQ search with debouncing.
- **AJAX search** — server-side search endpoint for large FAQ sets.
- **Helpful voting** — visitors can rate FAQ items as helpful or not
  helpful, with localStorage-based duplicate prevention.
- **View count tracking** — automatic view count increment on FAQ
  detail pages.
- **SEO schema markup** — automatic JSON-LD FAQPage structured data
  for Google rich results.
- **URL rewrites** — SEO-friendly URLs for FAQ pages and categories,
  managed via Magento URL rewrite observers.
- **Custom CSS** — admin configuration field for custom CSS overrides.
- **Hyva theme support** — native Alpine.js templates with Tailwind
  CSS utility classes for Hyva storefronts.
- **Luma theme support** — vanilla JavaScript accordion with inline
  CSS for Luma storefronts.
- **Admin mass actions** — mass delete, mass status change, mass
  show/hide from main page for FAQ items and categories.
- **UTF-8mb4 data patch** — setup patch to convert FAQ tables to
  utf8mb4 for full emoji and special character support.

### Compatibility
- Magento Open Source / Commerce / Cloud 2.4.4 - 2.4.8
- PHP 8.1, 8.2, 8.3

---

## Support

For all questions, bug reports, or feature requests:

- **Email:** kishansavaliyakb@gmail.com
- **Website:** https://kishansavaliya.com
- **WhatsApp:** +91 84012 70422
