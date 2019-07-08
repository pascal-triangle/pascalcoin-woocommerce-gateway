<?php

php_sapi_name() === 'cli' || exit;

define('PASCALCOIN_GATEWAY_ATOMIC_UNITS', 4);
define('PASCALCOIN_GATEWAY_ATOMIC_UNITS_POW', pow(10, PASCALCOIN_GATEWAY_ATOMIC_UNITS));

include "class-pascalcoin-rpc.php";

$rpc = new Pascalcoin_Rpc();

// Get daemon height
print_r($rpc->getblockcount());

// Get account info for 0-10
print_r($rpc->getaccount(0));

// Get payments with non encrypted payload equal to 'd0ad7e50eefb8394'
// For account 0-10 there will not be any such payments, but this
// woocommerce plugin will instruct customers to pay with a unique
// 16 character hex string per each order
print_r($rpc->get_all_payments(0, 'd0ad7e50eefb8394'));
