# PascalCoin Payments Gateway for WooCommerce

## Features

* Payment validation done through `pascalcoin_daemon` with no private keys needed on server.
* Validates payments with `cron`, so does not require users to stay on the order confirmation page for their order to validate.
* Order status updates are done through AJAX.
* Customers can pay with multiple transactions and are notified as soon as transactions hit the mempool.
* Configurable block confirmations, from `0` for zero confirm to `100` for high ticket purchases.
* Live price updates every minute; total amount due is locked in after the order is placed for a configurable amount of time (default four hours) so the price does not change after order has been made.
* Hooks into emails, order confirmation page, customer order history page, and admin order details page.
* View all payments received to your wallet with links to the blockchain explorer and associated orders.
* Optionally display all prices on your store in terms of PascalCoin.
* Shortcodes! Display exchange rates in numerous currencies.

## Requirements

* PascalCoin account to receive payments
* [BCMath](http://php.net/manual/en/book.bc.php) - A PHP extension used for arbitrary precision maths

## Installing the plugin

* Download the plugin
* Unzip or place the `pascalcoin-payments-woocommerce-gateway` folder in the `wp-content/plugins` directory.
* Activate "PascalCoin Payments Woocommerce Gateway" in your WordPress admin dashboard.
* It is highly recommended that you use native cronjobs instead of WordPress's "Poor Man's Cron" by adding `define('DISABLE_WP_CRON', true);` into your `wp-config.php` file and adding `* * * * * wget -q -O - https://yourstore.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1` to your crontab.

## Set-up PascalCoin daemon

You will need access to a daemon to verify payments. You'll need:

* Root access to your webserver
* Latest [PascalCoin binaries](https://github.com/PascalCoin/PascalCoin/releases)

After downloading (or compiling) the PascalCoin binaries on your server, start the daemon.

## Configuration

* `Enable / Disable` - Turn on or off PascalCoin payments. (Default: Disable)
* `Title` - Name of the payment gateway as displayed to the customer. (Default: PascalCoin Payments)
* `Discount for using PascalCoin` - Percentage discount applied to orders for paying with PascalCoin. Can also be negative to apply a surcharge. (Default: 0)
* `Order valid time` - Number of seconds after order is placed that the transaction must be seen in the mempool. (Default: 3600 [4 hours])
* `Number of confirmations` - Number of confirmations the transaction must recieve before the order is marked as complete. Use `0` for instant confirmation. (Default: 5)
* `PascalCoin Account` - Your PascalCoin account with or without a checksum. (No default)
* `PascalCoin RPC Host/IP` - IP address where the daemon is running. (Default: 127.0.0.1)
* `Pascalcoin RPC port` - Port the daemon is bound to. (Default 4003)
* `Show QR Code` - Show payment QR codes. There is no PascalCoin software that can read QR codes at this time (Default: unchecked)
* `Show Prices in PascalCoin` - Convert all prices on the frontend to PascalCoin. Experimental feature, only use if you do not accept any other payment option. (Default: unchecked)
* `Display Decimals` (if show prices in PascalCoin is enabled) - Number of decimals to round prices to on the frontend. The final order amount will not be rounded. (Default: 4)

## Shortcodes

This plugin makes available two shortcodes that you can use in your theme.

#### Live price shortcode

This will display the price of PascalCoin in the selected currency. If no currency is provided, the store's default currency will be used.

```
[pascalcoin-price]
[pascalcoin-price currency="BTC"]
[pascalcoin-price currency="USD"]
[pascalcoin-price currency="CAD"]
[pascalcoin-price currency="EUR"]
[pascalcoin-price currency="GBP"]
```
Will display:
```
1 PASC = 0.24330 USD
1 PASC = 0.00006999 BTC
1 PASC = 0.24330 USD
1 PASC = 0.34190 CAD
1 PASC = 0.21210 EUR
1 PASC = 0.18900 GBP
```


#### Pascalcoin accepted here badge

This will display a badge showing that you accept PascalCoin.

`[pascalcoin-accepted-here]`

![Pascalcoin Accepted Here](/assets/images/pascalcoin-accepted-here.png?raw=true "PascalCoin Accepted Here")  

## Credits

Based on the [Ryo-currency](https://ryo-currency.com/) plugin. Donate to 573198-21 to support development.
