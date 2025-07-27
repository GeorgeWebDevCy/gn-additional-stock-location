# GN Additional Stock Location

GN Additional Stock Location extends WooCommerce with a second inventory location for every product. A separate price can be assigned to items stored in that location. Stock quantities and status are calculated from the combined amount and orders automatically deduct from both locations when needed.

## Features

- Second **Stock Location** field on the inventory tab for simple and variable products. The secondary location is called **Golden Sneakers** while the primary WooCommerce location is **Sneakfreaks**.
- **Golden Sneakers Price** field that is used when the primary location runs out of stock.
- **Golden Sneakers Sale Price** to offer discounts when location two is active.
- Stock status and available quantity are based on the sum of both locations.
- Automatic price switching once the primary location is empty.
- Order processing reduces stock from both locations and logs changes on the order.
- Built in update checker pulls new releases from GitHub.

## Installation

1. Copy the `gn-additional-stock-location` directory to your site's `wp-content/plugins` folder.
2. Activate the plugin from the **Plugins** page in the WordPress admin.
3. Edit a product and enter values for the second location stock and price. Variations have the same fields.

## Sale Price for Golden Sneakers

Set **Golden Sneakers Sale Price** to offer a discounted amount when stock from the second location is being sold. The sale price only applies once the primary stock level reaches zero. Regular WooCommerce sale functionality for the main price is unaffected.

## Location Name Meta

Each product stores the name of the secondary location in a read-only meta field. This field also appears on the product page so customers can see which location is active.

## Updates

This plugin integrates the [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) library. Update checks are configured in `gn-additional-stock-location.php` and pull releases from the configured GitHub repository.
