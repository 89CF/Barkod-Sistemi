# Barkod Sistemi

**WordPress/WooCommerce plugin: barcode-based customer loyalty & SMS system**

![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-777bb4?logo=php&logoColor=white)
![WordPress](https://img.shields.io/badge/WordPress-Plugin-21759b?logo=wordpress&logoColor=white)
![WooCommerce](https://img.shields.io/badge/WooCommerce-Required-96588a?logo=woocommerce&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-yellow.svg)

> The plugin's admin UI and customer-facing text are in Turkish. This README is in English for accessibility, but expect Turkish labels, messages, and log strings throughout the codebase.

## Features

- **Barcode-based customer lookup** for fast, in-store point-of-sale style checkout on top of WooCommerce.
- **Quick sale mode** (`hızlı satış`) — scan a barcode to automatically add the matching product to the cart/order.
- **Points and loyalty tracking** integrated with WooCommerce customers.
- **Kumbara (piggy bank) point donation** — customers can donate accumulated points to a kumbara; wraps the separate "Woof Kumbara" plugin's data rather than reimplementing it.
- **SMS notifications** via a pluggable SMS service (İletiMerkezi by default), configurable and toggled from the WordPress admin.
- **Telegram bot integration**: test messages, scheduled daily sales reports, and a webhook endpoint for receiving bot updates, secured with a Telegram secret token.
- **Database migrations** handled on plugin activation and update via a dedicated migrator class.
- Modular admin UI (cards, tables, modals, mode selector) and a set of frontend JS modules for the quick-sale, points-donation, and cart-management flows.

## Requirements

- WordPress (recent version recommended)
- WooCommerce (the plugin will not initialize until `woocommerce_loaded` fires)
- PHP >= 7.4 (codebase uses `declare(strict_types=1)` and scalar type hints throughout)

## Installation

1. Copy the plugin folder into `wp-content/plugins/barkod-sistemi`.
2. Activate **Barkod Sistemi** from the WordPress Plugins screen.
3. On activation, database migrations run automatically to create/update the required tables.

## Configuration

All integrations are configured through the WordPress admin — nothing is hardcoded in the plugin files:

- **SMS**: enable the SMS service and enter provider credentials (e.g. İletiMerkezi) from the plugin's admin settings.
- **Telegram bot**: set the bot token and chat ID from the admin Telegram settings screen. A webhook secret token can be set to authenticate incoming requests to `includes/telegram-webhook.php`; daily report scheduling is configurable from the same screen.
- **Kumbara**: requires the separate "Woof Kumbara" plugin to be active, as this plugin wraps its data rather than duplicating it.

## Screenshots

_(placeholder — add screenshots of the admin panel and quick-sale flow here)_

## Contributing

Issues and pull requests are welcome. Please keep the existing code style (strict types, WordPress coding conventions) and avoid introducing hardcoded secrets or credentials.

## License

Released under the [MIT License](LICENSE).
