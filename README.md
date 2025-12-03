# Advanced Post Duplicator

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.2%2B-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Duplicate posts, pages, and custom post types with customizable settings. Perfect for WordPress Multisite networks with cross-site duplication.

## Description

Advanced Post Duplicator is a comprehensive solution for duplicating posts, pages, and custom post types in WordPress. Whether you're working on a single site or a WordPress Multisite network, this plugin provides powerful duplication features with complete control over the duplication process.

### Key Features

- **Universal Post Type Support** - Works with posts, pages, and any custom post type
- **Full Elementor Integration** - Duplicates Elementor page builder templates, widgets, and all Elementor data seamlessly
- **Complete WooCommerce Support** - Duplicates products with proper SKU handling, stock management, attributes, galleries, and linked products
- **Bulk Duplication** - Select multiple posts and duplicate them all at once
- **Cross-Site Duplication (Multisite)** - Copy posts between sites in a WordPress Multisite network with media and taxonomy preservation
- **Smart Slug Resolution** - Automatically handles slug conflicts with intelligent naming
- **Media Handling** - Copies media files and updates all references in post content
- **Taxonomy Migration** - Preserves categories, tags, and custom taxonomies with hierarchy
- **Flexible Settings** - Customize post status, dates, and date offsets
- **Editor Integration** - Works in both Classic and Block (Gutenberg) editors

## Features

- **Universal Post Type Support** - Works with all post types including custom post types
- **Full Elementor Integration** - Duplicates Elementor templates, widgets, and all Elementor page builder data
- **Complete WooCommerce Support** - Duplicates products with proper SKU regeneration, stock management, attributes, galleries, and linked products
- **Bulk Duplication** - Select multiple posts and duplicate them all at once with a single click
- **Cross-Site Duplication (Multisite)** - Copy posts from one site to another in WordPress Multisite networks with complete data preservation
- **Media Preservation** - Automatically copies media files and updates all references in post content
- **Taxonomy Migration** - Preserves categories, tags, and custom taxonomies with full hierarchy support
- **Smart Slug Resolution** - Automatically resolves slug conflicts with intelligent numeric suffixes
- **Customizable Settings** - Control post status (Same as original, Draft, Pending, Published), dates, and date offsets
- **Flexible Date Options** - Duplicate timestamps or use current time with customizable offset
- **Date Offset** - Adjust duplicated post dates with days, hours, minutes, and seconds
- **Meta Data Preservation** - Copies all post meta including custom fields and plugin-specific data
- **Featured Image Support** - Preserves featured images and product galleries
- **Editor Integration** - Works seamlessly in both Classic and Block (Gutenberg) editors
- **Multiple Access Points** - Duplicate from list view, editor, or publish metabox
- **Settings Page** - Easy-to-use settings page for global configuration
- **Error Logging** - Comprehensive error tracking and success logging for all operations

## Installation

1. Upload the plugin files to the `/wp-content/plugins/advanced-post-duplicator` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Settings > Post Duplicator to configure your duplication settings.
4. Use the "Duplicate" link in the post list or editor to duplicate posts.

## Frequently Asked Questions

### Does this plugin work with custom post types?

Yes! The plugin works with all post types including custom post types.

### What happens to post meta and taxonomies?

All post meta (custom fields) and taxonomies are copied to the duplicated post.

### Can I duplicate multiple posts at once?

Yes! The plugin includes bulk duplication functionality. Simply select multiple posts from the post list, choose "Duplicate" from the bulk actions dropdown, and click "Apply".

### Does the plugin work with WordPress Multisite?

Yes! The plugin fully supports WordPress Multisite networks. You can duplicate posts between sites, and all media files, taxonomies, and meta data will be properly migrated.

### Does the plugin work with both Classic and Block editors?

Yes, the plugin works seamlessly with both Classic and Block (Gutenberg) editors. Duplicate buttons are available in both editing interfaces.

### Does the plugin work with Elementor?

Yes! The plugin has full Elementor support. All Elementor templates, widgets, and page builder data are properly duplicated and preserved.

### Does the plugin work with WooCommerce?

Yes! The plugin fully supports WooCommerce products. It automatically regenerates SKUs, handles stock management, and preserves all product attributes, galleries, and linked products.

### What happens if a post slug already exists?

The plugin automatically resolves slug conflicts by appending numeric suffixes (e.g., -copy-2, -copy-3) to ensure unique permalinks.

## Changelog

### 1.0.0

- Initial release
- Duplicate posts, pages, and custom post types
- Settings page with customizable post status, date, and offset options
- Bulk duplication support for multiple posts at once
- Classic and Block (Gutenberg) editor integration
- Duplicate links in post list row actions
- Duplicate button in publish metabox
- Full Elementor page builder support
- Complete WooCommerce product duplication
- WordPress Multisite cross-site duplication
- Media file copying with reference updates
- Taxonomy migration with hierarchy preservation
- Automatic slug conflict resolution
- Meta data preservation with plugin-specific support
- Error logging and tracking
- AJAX-powered cross-site duplication interface

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Network activated for Multisite features

## License

This plugin is licensed under the GPLv2 or later.

## Support

For support, feature requests, and bug reports, please visit the [WordPress.org plugin page](https://wordpress.org/plugins/advanced-post-duplicator).

## Credits

**Contributors:** tanjilahmed

**Donate:** [WordPress.org Plugin Page](https://wordpress.org/plugins/advanced-post-duplicator)
