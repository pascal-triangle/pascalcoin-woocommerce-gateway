<?php

defined( 'ABSPATH' ) || exit;

return array(
    'enabled' => array(
        'title' => __('Enable / Disable', 'pascalcoin_gateway'),
        'label' => __('Enable this payment gateway', 'pascalcoin_gateway'),
        'type' => 'checkbox',
        'default' => 'no'
    ),
    'title' => array(
        'title' => __('Title', 'pascalcoin_gateway'),
        'type' => 'text',
        'desc_tip' => __('Payment title the customer will see during the checkout process.', 'pascalcoin_gateway'),
        'default' => __('PascalCoin Payments', 'pascalcoin_gateway')
    ),
    'description' => array(
        'title' => __('Description', 'pascalcoin_gateway'),
        'type' => 'textarea',
        'desc_tip' => __('Payment description the customer will see during the checkout process.', 'pascalcoin_gateway'),
        'default' => __('Pay securely using PascalCoin. You will be provided payment details after checkout.', 'pascalcoin_gateway')
    ),
    'discount' => array(
        'title' => __('Discount for using PascalCoin', 'pascalcoin_gateway'),
        'desc_tip' => __('Provide a discount to your customers for making a payment with PascalCoin', 'pascalcoin_gateway'),
        'description' => __('Enter a percentage discount (i.e. 5 for 5%) or leave this empty if you do not wish to provide a discount', 'pascalcoin_gateway'),
        'type' => __('number'),
        'default' => '0'
    ),
    'valid_time' => array(
        'title' => __('Order valid time', 'pascalcoin_gateway'),
        'desc_tip' => __('Amount of time order is valid before expiring', 'pascalcoin_gateway'),
        'description' => __('Enter the number of seconds that the funds must be received in after order is placed. 3600 seconds = 1 hour', 'pascalcoin_gateway'),
        'type' => __('number'),
        'default' => '3600'
    ),
    'confirms' => array(
        'title' => __('Number of confirmations', 'pascalcoin_gateway'),
        'desc_tip' => __('Number of confirms a transaction must have to be valid', 'pascalcoin_gateway'),
        'description' => __('Enter the number of confirms that transactions must have. Enter 0 to zero-confim. Each confirm will take approximately five minutes', 'pascalcoin_gateway'),
        'type' => __('number'),
        'default' => '0'
    ),
    'pascalcoin_address' => array(
        'title' => __('PascalCoin Account', 'pascalcoin_gateway'),
        'label' => __('Account number with or without checksum'),
        'type' => 'text',
        'desc_tip' => __('Pascalcoin Account', 'pascalcoin_gateway')
    ),
    'daemon_host' => array(
        'title' => __('PascalCoin RPC Host/IP', 'pascalcoin_gateway'),
        'type' => 'text',
        'desc_tip' => __('This is the Daemon Host/IP to authorize the payment with', 'pascalcoin_gateway'),
        'default' => '127.0.0.1',
    ),
    'daemon_port' => array(
        'title' => __('PascalCoin RPC port', 'pascalcoin_gateway'),
        'type' => __('number'),
        'desc_tip' => __('This is the RPC port to authorize the payment with', 'pascalcoin_gateway'),
        'default' => '4003',
    ),
    'show_qr' => array(
        'title' => __('Show QR Code', 'pascalcoin_gateway'),
        'label' => __('Show QR Code', 'pascalcoin_gateway'),
        'type' => 'checkbox',
        'description' => __('Enable this to show a QR code after checkout with payment details.'),
        'default' => 'no'
    ),
    'use_pascalcoin_price' => array(
        'title' => __('Show Prices in Pascalcoin', 'pascalcoin_gateway'),
        'label' => __('Show Prices in Pascalcoin', 'pascalcoin_gateway'),
        'type' => 'checkbox',
        'description' => __('Enable this to convert ALL prices on the frontend to PascalCoin (experimental)'),
        'default' => 'no'
    ),
    'use_pascalcoin_price_decimals' => array(
        'title' => __('Display Decimals', 'pascalcoin_gateway'),
        'type' => __('number'),
        'description' => __('Number of decimal places to display on frontend. Upon checkout exact price will be displayed.'),
        'default' => 4,
    ),
);
