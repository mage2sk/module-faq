# Panth FAQ — User Guide

This guide is for store administrators who want to set up and manage
FAQs using the Panth_Faq extension.

---

## Table of contents

1. [Installation](#1-installation)
2. [Configuration](#2-configuration)
3. [Managing FAQ categories](#3-managing-faq-categories)
4. [Managing FAQ items](#4-managing-faq-items)
5. [Assigning FAQs to products, categories, and CMS pages](#5-assigning-faqs-to-products-categories-and-cms-pages)
6. [FAQ widget](#6-faq-widget)
7. [SEO schema markup](#7-seo-schema-markup)
8. [Hyva vs Luma theme support](#8-hyva-vs-luma-theme-support)
9. [Troubleshooting](#9-troubleshooting)
10. [CLI reference](#10-cli-reference)

---

## 1. Installation

### Composer (recommended)

```bash
composer require mage2kishan/module-faq
bin/magento module:enable Panth_Faq
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

### Manual zip

1. Download the extension package zip
2. Extract to `app/code/Panth/Faq`
3. Run the same `module:enable ... cache:flush` commands above

---

## 2. Configuration

Navigate to **Stores > Configuration > Panth Extensions > FAQ Settings**.

### General Settings

| Setting | Default | Description |
|---|---|---|
| **Enable FAQ** | Yes | Enable or disable the FAQ module entirely |
| **FAQ Page URL Key** | `faq` | URL path for the FAQ listing page (e.g., `faqs`, `help`, `support`) |

### Display Settings

| Setting | Default | Description |
|---|---|---|
| **Items Per Page** | 20 | Number of FAQ items shown per page |
| **Show Category Description** | Yes | Display category descriptions on category pages |
| **Show Search Bar** | Yes | Enable client-side search on FAQ pages |
| **Show Category Filter** | Yes | Show category filter tabs on the main FAQ page |
| **Default Open FAQ Items** | No | Expand all accordion items by default |
| **Show View Count** | No | Display view count on each FAQ item |
| **Enable Helpful Voting** | Yes | Allow visitors to rate FAQ items as helpful or not |

### Page Integration

| Setting | Default | Description |
|---|---|---|
| **Show on Product Pages** | Yes | Display assigned FAQ items on product detail pages |
| **Show on Category Pages** | Yes | Display assigned FAQ items on catalog category pages |
| **Show on CMS Pages** | Yes | Display assigned FAQ items on CMS pages |

### SEO Settings

| Setting | Default | Description |
|---|---|---|
| **Enable FAQ Schema Markup** | Yes | Output JSON-LD FAQPage schema for Google rich results |

### Custom Styling

| Setting | Default | Description |
|---|---|---|
| **Custom CSS** | (empty) | Add custom CSS rules. Prefix selectors with `.panth-faq-module`. Do not include `<style>` tags. |

---

## 3. Managing FAQ categories

Navigate to **Panth Infotech > FAQ > FAQ Categories**.

### Create a category

1. Click **Add New Category**
2. Fill in the category name, URL key, description, and optional image
3. Set the sort order and status
4. Click **Save**

### Assign FAQ items to a category

From the category edit page, use the **FAQ Items** tab to assign
existing FAQ items to this category.

---

## 4. Managing FAQ items

Navigate to **Panth Infotech > FAQ > FAQ Items**.

### Create a FAQ item

1. Click **Add New FAQ Item**
2. Enter the **Question** (plain text)
3. Enter the **Answer** (WYSIWYG editor — supports HTML, images, links)
4. Select a **FAQ Category** from the tree
5. Set the sort order, status, and "Show on Main Page" flag
6. Use the **Products**, **Catalog Categories**, and **CMS Pages** tabs
   to assign this FAQ to specific entities
7. Click **Save**

### Mass actions

From the FAQ Items grid you can:
- **Mass Delete** — delete selected items
- **Mass Status** — enable/disable selected items
- **Mass Show on Main** / **Mass Hide from Main** — control main page
  visibility

---

## 5. Assigning FAQs to products, categories, and CMS pages

Each FAQ item can be assigned to:

- **Products** — the FAQ appears on those product detail pages
- **Catalog Categories** — the FAQ appears on those category pages
- **CMS Pages** — the FAQ appears on those CMS pages

Assignment is done from the FAQ item edit form using the grid tabs.
You can also assign from the reverse direction: the Product edit form,
Category edit form, and CMS Page edit form each have a "FAQ" tab where
you can assign FAQ items.

---

## 6. FAQ widget

You can place FAQ blocks anywhere in your store using the Magento
widget system:

1. Go to **Content > Widgets > Add Widget**
2. Select **Panth FAQ** as the widget type
3. Configure which FAQ items to display
4. Assign the widget to a layout position

---

## 7. SEO schema markup

When enabled, the module automatically outputs JSON-LD `FAQPage`
structured data on FAQ pages. This helps Google display rich results
(expandable FAQ snippets) in search results.

Verify your schema at https://search.google.com/test/rich-results.

---

## 8. Hyva vs Luma theme support

The module ships with dual template sets:

- **Hyva** — Alpine.js-powered accordion, Tailwind CSS utility classes,
  responsive and accessible.
- **Luma** — vanilla JavaScript accordion with inline CSS, no
  RequireJS dependencies for the accordion itself.

The correct template set is selected automatically based on the active
theme.

---

## 9. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| FAQ page returns 404 | Module disabled or URL key conflict | Check `Stores > Configuration > Panth Extensions > FAQ Settings > General > Enable FAQ` is Yes. Verify URL key is not used by a CMS page. |
| FAQs not showing on product page | Integration disabled or no FAQs assigned | Check `Page Integration > Show on Product Pages` is Yes. Verify FAQ items are assigned to the product. |
| Schema markup not appearing | SEO setting disabled | Check `SEO Settings > Enable FAQ Schema Markup` is Yes. |
| Helpful voting not working | Voting disabled or JS error | Check `Display Settings > Enable Helpful Voting` is Yes. Check browser console for errors. |

---

## 10. CLI reference

```bash
# Verify module status
bin/magento module:status Panth_Faq

# Enable / disable
bin/magento module:enable  Panth_Faq
bin/magento module:disable Panth_Faq

# After any change
bin/magento setup:upgrade
bin/magento cache:flush
```

---

## Support

For all questions, bug reports, or feature requests:

- **Email:** kishansavaliyakb@gmail.com
- **Website:** https://kishansavaliya.com
- **WhatsApp:** +91 84012 70422
