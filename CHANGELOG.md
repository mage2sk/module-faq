# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.3.13] - 2026-11-22

### Fixed
- Saving an item or category that was loaded on the storefront wrote a store view row with every field copied from the default values. Those copies hid later edits of the default values on that store view. A storefront save is now a default scope save: it never writes a store view row, a field changed in code is saved to the default values, and store view text shown on the storefront is never copied into them. Store view values are only written when `store_scope_id` is set explicitly, as the admin form does.
- `use_default` in the keyed form that a store view load produces (`['question' => 1, ...]`) was not recognised, so saving such a model treated every field as an override. Both the list form and the keyed form are now accepted. In the keyed form, a field changed after loading counts as an override.
- A store view value equal to the default value is now stored as "use default", and the store view row is deleted when every field inherits. The same applies to FAQ categories.

### Added
- `bin/magento panth:faq:clean-store-values [--dry-run]` removes store view values that only repeat the default value, for items and categories, and deletes rows that end up empty. Values that differ from the default are kept, because they cannot be told apart from an intended override. Run `bin/magento cache:clean full_page` afterwards.

### Upgrade notes
- Run `bin/magento setup:upgrade` and `bin/magento cache:flush`. There are no database changes. To remove copies written by earlier versions, run `bin/magento panth:faq:clean-store-values --dry-run`, check the counts, then run it without `--dry-run`.

## [1.3.12] - 2026-10-30

### Changed
- Released under the MIT License: this module is free and open-source software.

## [1.3.11] - 2026-10-06

### Changed
- The FAQ page and FAQ category pages use a 760px text column on both stores. Before, the Hyva FAQ page was 1232px wide at 1440px.
- Heading sizes: the page title is 36px (28px on phones), section headings are 28px (24px on phones) and questions are 18px semibold (16px on phones). Before, section headings were 24px and questions were 14px to 16px.
- Answers are 16px with a 1.5 line height on both stores. Before, they were 14px on Luma (13px on phones) and on Hyva category pages.
- Sections are 64px apart (40px on phones).
- The Search button uses 15px semibold text.

### Added
- Unit tests for the reading styles.
