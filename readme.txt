=== GN Additional Stock Location ===
Contributors: yourname
Tags: woocommerce, inventory, stock
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.5.0
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
= 1.5.0 =
* Location Name field is now a dropdown that lets you choose between Sneakfreaks and Golden Sneakers.
= 1.4.0 =
* Location name now defaults to Sneak Freaks and switches to Golden Sneakers only when the second stock and price are set.
= 1.3.0 =
* Added sale price support for the second location.
= 1.2.0 =
* Initial release
