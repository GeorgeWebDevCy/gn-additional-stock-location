=== GN Additional Stock Location ===
Contributors: yourname
Tags: woocommerce, inventory, stock
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.9.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a second stock location called **Golden Sneakers** to WooCommerce products and manages pricing and stock levels automatically. The default WooCommerce location is referred to as **Sneakfreaks**.
Each product stores the active location name in a dropdown field displayed on the edit screen and the product page so you can select between your predefined locations.

== Description ==
This plugin lets you track inventory in a secondary location for every WooCommerce product. Each item also gets an optional price that is used once the primary location runs out of stock. Stock quantities and status are calculated based on the combined total and orders automatically reduce quantities in both locations. The plugin also ships with an update checker that can pull new versions from GitHub.

When the primary location is empty you can also specify a **Golden Sneakers Sale Price** to offer a discount on items stored in the secondary location.

== Installation ==
1. Upload the `gn-additional-stock-location` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin from **Plugins** in the WordPress admin.
3. Edit a product to enter values for **Golden Sneakers Stock** and **Golden Sneakers Price**. Variations have their own fields as well.

== Changelog ==
= 1.9.9 =
* Bump version to 1.9.9.

= 1.9.8 =
* Improve import logs to show received values and whether meta fields were updated.
= 1.9.7 =
* Log SKU matches with detailed product and variation titles.
= 1.9.6 =
* Log whether a matched SKU belongs to a parent product or variation.
= 1.9.5 =
* Restore compatibility with WP All Import by making the `pmxi_saved_post` hook's import ID parameter optional.
= 1.9.4 =
* Ensure GN ASL module loads after WooCommerce so the Import Log menu is always available.
= 1.9.3 =
* Move Import Log under its own top-level GN ASL menu.
= 1.9.2 =
* Boot Import Log module so the admin page is registered.

= 1.9.1 =
* Allow administrators without WooCommerce management capability to access the Import Log page.

= 1.9.0 =
* Moved import log to its own top-level menu.

= 1.8.1 =
* Fixed WP All Import logger not loading and ensured module inclusion.

= 1.8.0 =
* Location names are now filterable via `gn_asl_primary_location_name` and `gn_asl_secondary_location_name`.
* Fixed double-counting of secondary stock in stock status filter.
* Centralized total stock calculation.
= 1.7.0 =
* Golden Sneakers Stock and Location Name fields display side by side.
= 1.6.0 =
* Golden Sneakers price fields display side by side.
= 1.5.1 =
* Updater fix.
= 1.5.0 =
* Location Name field is now a dropdown that lets you choose between Sneakfreaks and Golden Sneakers.
= 1.4.0 =
* Location name now defaults to Sneak Freaks and switches to Golden Sneakers only when the second stock and price are set.
= 1.3.0 =
* Added sale price support for the second location.
= 1.2.0 =
* Initial release
