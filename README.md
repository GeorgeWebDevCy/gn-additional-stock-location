# GN Additional Stock Location

This WordPress plugin adds a second stock location field to WooCommerce products. It also lets you set a different price for items coming from that location. When an order is processed the stock is reduced from both locations accordingly and the price from the active location is used.

## Installation

1. Copy the `gn-additional-stock-location` directory to your site's `wp-content/plugins` folder.
2. Activate the plugin from the **Plugins** page in the WordPress admin.

After activation, a **2nd Stock Location** field will appear in the product inventory tab.
A **2nd Location Price** field is also added in the pricing section. For variable products each variation gets its own pair of fields for stock and price.
When the primary location runs out of stock the plugin automatically uses the price from the 2nd location.

## Updates

This plugin integrates the [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) library. Update checks are configured in `gn-additional-stock-location.php` and pull releases from the configured GitHub repository.
