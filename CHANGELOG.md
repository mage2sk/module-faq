# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.3.8] - 2026-10-03

### Fixed
- The FAQ listing block's `escapeHtmlAttr()` used an undefined escaper property and failed with a fatal error; it now uses the block's escaper.
- Saving an FAQ item or category no longer fails with a type error when an engine error (for example a `TypeError`) is thrown during save; the admin sees the generic "Something went wrong" message and returns to the edit form.
