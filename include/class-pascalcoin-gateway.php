<?php
/*
 * Main Gateway of Pascalcoin using json rpc
 * Authors: mosu-forge
 */

defined( 'ABSPATH' ) || exit;

require_once('class-pascalcoin-rpc.php');

class Pascalcoin_Gateway extends WC_Payment_Gateway
{
    private static $_id = 'pascalcoin_gateway';
    private static $_title = 'PascalCoin Payments';
    private static $_method_title = 'PascalCoin Payments';
    private static $_method_description = 'PascalCoin Payments Gateway Plug-in for WooCommerce.';
    private static $_errors = [];

    private static $discount = false;
    private static $valid_time = null;
    private static $confirms = null;
    private static $address = null;
    private static $host = null;
    private static $port = null;
    private static $show_qr = false;
    private static $use_pascalcoin_price = false;
    private static $use_pascalcoin_price_decimals = PASCALCOIN_GATEWAY_ATOMIC_UNITS;

    private static $pascalcoin_rpc;
    private static $log;

    private static $currencies = array('BTC','USD','EUR','CAD','INR','GBP','COP','SGD','JPY');
    private static $rates = array();

    private static $payment_details = array();

    public function get_icon()
    {
        return apply_filters('woocommerce_gateway_icon', '<img src="'.PASCALCOIN_GATEWAY_PLUGIN_URL.'assets/images/pascalcoin-icon.png"/>', $this->id);
    }

    function __construct($add_action=true)
    {
        $this->id = self::$_id;
        $this->method_title = __(self::$_method_title, 'pascalcoin_gateway');
        $this->method_description = __(self::$_method_description, 'pascalcoin_gateway');
        $this->has_fields = false;
        $this->supports = array(
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change'
        );

        $this->enabled = $this->get_option('enabled') == 'yes';

        $this->init_form_fields();
        $this->init_settings();

        self::$_title = $this->settings['title'];
        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        self::$discount = $this->settings['discount'];
        self::$valid_time = $this->settings['valid_time'];
        self::$confirms = $this->settings['confirms'];
        self::$address = $this->settings['pascalcoin_address'];
        self::$host = $this->settings['daemon_host'];
        self::$port = $this->settings['daemon_port'];
        self::$show_qr = $this->settings['show_qr'] == 'yes';
        self::$use_pascalcoin_price = $this->settings['use_pascalcoin_price'] == 'yes';
        self::$use_pascalcoin_price_decimals = $this->settings['use_pascalcoin_price_decimals'];

        if($add_action)
            add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));

        // Initialize helper classes
        self::$pascalcoin_rpc = new Pascalcoin_Rpc(self::$host, self::$port);
        self::$log = new WC_Logger();
    }

    public function init_form_fields()
    {
        $this->form_fields = include 'admin/pascalcoin-gateway-admin-settings.php';
    }

    public function validate_pascalcoin_address_field($key,$address)
    {
        // only allow numbers and hyphen
        if(preg_match('/^[0-9\-]+$/', $address)) {
            if(substr_count($address, '-') === 0) {
                // no hyphen, assume address is okay
                return $address;
            } else if(substr_count($address, '-') === 1) {
                // we have a checksum
                $parts = explode('-', $address);
                $checksum = (($parts[0]*101) % 89) + 10;
                if($checksum == $parts[1]) {
                    // checksum matches
                    return $address;
                }
            }
        }
        self::$_errors[] = 'Pascalcoin address is invalid';
    }

    public function validate_confirms_field($key,$confirms)
    {
        if($confirms >= 0 && $confirms <= 100)
            return $confirms;
        self::$_errors[] = 'Number of confirms must be between 0 and 100';
    }

    public function validate_valid_time_field($key,$valid_time)
    {
        if($valid_time >= 600 && $valid_time < 86400*7)
            return $valid_time;
        self::$_errors[] = 'Order valid time must be between 600 (10 minutes) and 604800 (1 week)';
    }

    public function admin_options()
    {
        $balance = self::admin_balance_info();
        $settings_html = $this->generate_settings_html(array(), false);
        $errors = array_merge(self::$_errors, $this->admin_php_module_check());
        include PASCALCOIN_GATEWAY_PLUGIN_DIR . '/templates/pascalcoin-gateway/admin/settings-page.php';
    }

    public static function admin_balance_info()
    {
        if(!is_admin()) {
            return array(
                'height' => 'Not Available',
                'balance' => 'Not Available',
            );
        }
        $account = strpos(self::$address,'-') ? substr(self::$address, 0, -3) : self::$address;
        $wallet_amount = self::$pascalcoin_rpc->getaccount($account);
        $height = self::$pascalcoin_rpc->getblockcount();

        if (!isset($wallet_amount->result) || !isset($height->result)) {
            self::$_errors[] = 'Cannot connect to pascalcoin-rpc';
            self::$log->add('Pascalcoin_Payments', '[ERROR] Cannot connect to pascalcoin-rpc');
            return array(
                'height' => 'Not Available',
                'balance' => 'Not Available',
            );
        } else {
            return array(
                'height' => $height->result,
                'balance' => $wallet_amount->result->balance.' PASC',
            );
        }
    }

    protected function admin_php_module_check()
    {
        $errors = array();
        if(!extension_loaded('bcmath'))
            $errors[] = 'PHP extension bcmath must be installed';
        return $errors;
    }

    public function process_payment($order_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix.'pascalcoin_gateway_quotes';

        $order = wc_get_order($order_id);

        // Generate a unique payment id
        do {
            $payment_id = bin2hex(openssl_random_pseudo_bytes(8));
            $query = $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE payment_id=%s", array($payment_id));
            $payment_id_used = $wpdb->get_var($query);
        } while ($payment_id_used);

        $currency = $order->get_currency();
        $rate = self::get_live_rate($currency);
        $fiat_amount = $order->get_total('');
        $pascalcoin_amount = 1e8 * $fiat_amount / $rate;

        if(self::$discount)
            $pascalcoin_amount = $pascalcoin_amount - $pascalcoin_amount * self::$discount / 100;

        $pascalcoin_amount = intval($pascalcoin_amount * PASCALCOIN_GATEWAY_ATOMIC_UNITS_POW);

        $query = $wpdb->prepare("INSERT INTO $table_name (order_id, payment_id, currency, rate, amount) VALUES (%d, %s, %s, %d, %d)", array($order_id, $payment_id, $currency, $rate, $pascalcoin_amount));
        $wpdb->query($query);

        $order->update_status('on-hold', __('Awaiting offline payment', 'pascalcoin_gateway'));
        $order->reduce_order_stock(); // Reduce stock levels
        WC()->cart->empty_cart(); // Remove cart

        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }

    /*
     * function for verifying payments
     * This cron runs every 60 seconds
     */
    public static function do_update_event()
    {
        global $wpdb;

        // Get Live Price
        $currencies = implode(',', self::$currencies);
        $api_link = 'https://min-api.cryptocompare.com/data/price?fsym=PASC&tsyms='.$currencies.'&extraParams=pascalcoin_woocommerce';
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $api_link,
        ));
        $resp = curl_exec($curl);
        curl_close($curl);
        $price = json_decode($resp, true);

        if(!isset($price['Response']) || $price['Response'] != 'Error') {
            $table_name = $wpdb->prefix.'pascalcoin_gateway_live_rates';
            foreach($price as $currency=>$rate) {
                // shift decimal eight places for precise int storage
                $rate = intval($rate * 1e8);
                $query = $wpdb->prepare("INSERT INTO $table_name (currency, rate, updated) VALUES (%s, %d, NOW()) ON DUPLICATE KEY UPDATE rate=%d, updated=NOW()", array($currency, $rate, $rate));
                $wpdb->query($query);
            }
        }

        // Get current network/wallet height
        $height = self::$pascalcoin_rpc->getblockcount();
        if(!isset($height->result)) {
            $height = get_transient('pascalcoin_gateway_network_height');
        } else {
            set_transient('pascalcoin_gateway_network_height', $height->result);
        }

        // Get pending payments
        $table_name_1 = $wpdb->prefix.'pascalcoin_gateway_quotes';
        $table_name_2 = $wpdb->prefix.'pascalcoin_gateway_quotes_txids';

        $query = $wpdb->prepare("SELECT *, $table_name_1.payment_id AS payment_id, $table_name_1.amount AS amount_total, $table_name_2.amount AS amount_paid, NOW() as now FROM $table_name_1 LEFT JOIN $table_name_2 ON $table_name_1.payment_id = $table_name_2.payment_id WHERE pending=1", array());
        $rows = $wpdb->get_results($query);

        $pending_payments = array();

        // Group the query into distinct orders by payment_id
        foreach($rows as $row) {
            if(!isset($pending_payments[$row->payment_id]))
                $pending_payments[$row->payment_id] = array(
                    'quote' => null,
                    'txs' => array()
                );
            $pending_payments[$row->payment_id]['quote'] = $row;
            if($row->txid)
                $pending_payments[$row->payment_id]['txs'][] = $row;
        }

        // Loop through each pending payment and check status
        foreach($pending_payments as $pending) {
            $quote = $pending['quote'];
            $old_txs = $pending['txs'];
            $order_id = $quote->order_id;
            $order = wc_get_order($order_id);
            $payment_id = self::sanatize_id($quote->payment_id);
            $amount_pascalcoin = $quote->amount_total;

            $new_txs = self::check_payment_rpc($payment_id);

            foreach($new_txs as $new_tx) {
                $is_new_tx = true;
                foreach($old_txs as $old_tx) {
                    if($new_tx['txid'] == $old_tx->txid && $new_tx['amount'] == $old_tx->amount_paid) {
                        $is_new_tx = false;
                        break;
                    }
                }
                if($is_new_tx) {
                    $old_txs[] = (object) $new_tx;
                }

                $query = $wpdb->prepare("INSERT INTO $table_name_2 (payment_id, txid, amount, height) VALUES (%s, %s, %d, %d) ON DUPLICATE KEY UPDATE height=%d", array($payment_id, $new_tx['txid'], $new_tx['amount'], $new_tx['height'], $new_tx['height']));
                $wpdb->query($query);
            }

            $txs = $old_txs;
            $heights = array();
            $amount_paid = 0;
            foreach($txs as $tx) {
                $amount_paid += $tx->amount;
                $heights[] = $tx->height;
            }

            $paid = $amount_paid > $amount_pascalcoin - PASCALCOIN_GATEWAY_ATOMIC_UNIT_THRESHOLD;

            if($paid) {
                if(self::$confirms == 0) {
                    $confirmed = true;
                } else {
                    $highest_block = max($heights);
                    if($height - $highest_block >= self::$confirms && !in_array(0, $heights)) {
                        $confirmed = true;
                    } else {
                        $confirmed = false;
                    }
                }
            } else {
                $confirmed = false;
            }

            if($paid && $confirmed) {
                self::$log->add('Pascalcoin_Payments', "[SUCCESS] Payment has been confirmed for order id $order_id and payment id $payment_id");
                $query = $wpdb->prepare("UPDATE $table_name_1 SET confirmed=1,paid=1,pending=0 WHERE payment_id=%s", array($payment_id));
                $wpdb->query($query);

                unset(self::$payment_details[$order_id]);

                if(self::is_virtual_in_cart($order_id) == true){
                    $order->update_status('completed', __('Payment has been received.', 'pascalcoin_gateway'));
                } else {
                    $order->update_status('processing', __('Payment has been received.', 'pascalcoin_gateway'));
                }

            } else if($paid) {
                self::$log->add('Pascalcoin_Payments', "[SUCCESS] Payment has been received for order id $order_id and payment id $payment_id");
                $query = $wpdb->prepare("UPDATE $table_name_1 SET paid=1 WHERE payment_id=%s", array($payment_id));
                $wpdb->query($query);

                unset(self::$payment_details[$order_id]);

            } else {
                $timestamp_created = new DateTime($quote->created);
                $timestamp_now = new DateTime($quote->now);
                $order_age_seconds = $timestamp_now->getTimestamp() - $timestamp_created->getTimestamp();
                if($order_age_seconds > self::$valid_time) {
                    self::$log->add('Pascalcoin_Payments', "[FAILED] Payment has expired for order id $order_id and payment id $payment_id");
                    $query = $wpdb->prepare("UPDATE $table_name_1 SET pending=0 WHERE payment_id=%s", array($payment_id));
                    $wpdb->query($query);

                    unset(self::$payment_details[$order_id]);

                    $order->update_status('cancelled', __('Payment has expired.', 'pascalcoin_gateway'));
                }
            }
        }
    }

    protected static function check_payment_rpc($payment_id)
    {
        $txs = array();
        $account = strpos(self::$address,'-') ? substr(self::$address, 0, -3) : self::$address;
        $payments = self::$pascalcoin_rpc->get_all_payments($account, $payment_id);
        foreach($payments as $payment) {
            $txs[] = array(
                'amount' => $payment['amount'],
                'txid' => $payment['tx_hash'],
                'height' => $payment['block_height']
            );
        }
        return $txs;
    }

    protected static function get_payment_details($order_id)
    {
        if(!is_integer($order_id))
            $order_id = $order_id->get_id();

        if(isset(self::$payment_details[$order_id]))
            return self::$payment_details[$order_id];

        global $wpdb;
        $table_name_1 = $wpdb->prefix.'pascalcoin_gateway_quotes';
        $table_name_2 = $wpdb->prefix.'pascalcoin_gateway_quotes_txids';
        $query = $wpdb->prepare("SELECT *, $table_name_1.payment_id AS payment_id, $table_name_1.amount AS amount_total, $table_name_2.amount AS amount_paid, NOW() as now FROM $table_name_1 LEFT JOIN $table_name_2 ON $table_name_1.payment_id = $table_name_2.payment_id WHERE order_id=%d", array($order_id));
        $details = $wpdb->get_results($query);
        if (count($details)) {
            $txs = array();
            $heights = array();
            $amount_paid = 0;
            foreach($details as $tx) {
                if(!isset($tx->txid))
                    continue;
                $txs[] = array(
                    'txid' => $tx->txid,
                    'height' => $tx->height,
                    'amount' => $tx->amount_paid,
                    'amount_formatted' => self::format_pascalcoin($tx->amount_paid)
                );
                $amount_paid += $tx->amount_paid;
                $heights[] = $tx->height;
            }

            usort($txs, function($a, $b) {
                if($a['height'] == 0) return -1;
                return $b['height'] - $a['height'];
            });

            if(count($heights) && !in_array(0, $heights)) {
                $height = get_transient('pascalcoin_gateway_network_height');
                $highest_block = max($heights);
                $confirms = $height - $highest_block;
                $blocks_to_confirm = self::$confirms - $confirms;
            } else {
                $blocks_to_confirm = self::$confirms;
            }
            $time_to_confirm = self::format_seconds_to_time($blocks_to_confirm * PASCALCOIN_GATEWAY_DIFFICULTY_TARGET);

            $amount_total = $details[0]->amount_total;
            $amount_due = max(0, $amount_total - $amount_paid);

            $timestamp_created = new DateTime($details[0]->created);
            $timestamp_now = new DateTime($details[0]->now);

            $order_age_seconds = $timestamp_now->getTimestamp() - $timestamp_created->getTimestamp();
            $order_expires_seconds = self::$valid_time - $order_age_seconds;

            $address = self::$address;
            $payment_id = self::sanatize_id($details[0]->payment_id);

            $status = '';
            $paid = $details[0]->paid == 1;
            $confirmed = $details[0]->confirmed == 1;
            $pending = $details[0]->pending == 1;

            if($confirmed) {
                $status = 'confirmed';
            } else if($paid) {
                $status = 'paid';
            } else if($pending && $order_expires_seconds > 0) {
                if(count($txs)) {
                    $status = 'partial';
                } else {
                    $status = 'unpaid';
                }
            } else {
                if(count($txs)) {
                    $status = 'expired_partial';
                } else {
                    $status = 'expired';
                }
            }

            //$qrcode_uri = 'pascalcoin:'.$address.'?tx_amount='.$amount_due.'&tx_payment_id='.$payment_id;
            $qrcode_uri = $address;
            $my_order_url = wc_get_endpoint_url('view-order', $order_id, wc_get_page_permalink('myaccount'));

            $payment_details = array(
                'order_id' => $order_id,
                'address' => $address,
                'payment_id' => $payment_id,
                'qrcode_uri' => $qrcode_uri,
                'my_order_url' => $my_order_url,
                'rate' => $details[0]->rate,
                'rate_formatted' => sprintf('%.8f', $details[0]->rate / 1e8),
                'currency' => $details[0]->currency,
                'amount_total' => $amount_total,
                'amount_paid' => $amount_paid,
                'amount_due' => $amount_due,
                'amount_total_formatted' => self::format_pascalcoin($amount_total),
                'amount_paid_formatted' => self::format_pascalcoin($amount_paid),
                'amount_due_formatted' => self::format_pascalcoin($amount_due),
                'status' => $status,
                'created' => $details[0]->created,
                'order_age' => $order_age_seconds,
                'order_expires' => self::format_seconds_to_time($order_expires_seconds),
                'blocks_to_confirm' => $blocks_to_confirm,
                'time_to_confirm' => $time_to_confirm,
                'txs' => $txs
            );
            self::$payment_details[$order_id] = $payment_details;
            return $payment_details;
        } else {
            return '[ERROR] Quote not found';
        }

    }

    public static function get_payment_details_ajax() {

        $user = wp_get_current_user();
        if($user === 0)
            self::ajax_output(array('error' => '[ERROR] User not logged in'));

        $order_id = preg_replace("/[^0-9]+/", "", $_GET['order_id']);
        $order = wc_get_order( $order_id );

        if($order->user_id != $user->ID)
            self::ajax_output(array('error' => '[ERROR] Order does not belong to this user'));

        if($order->get_payment_method() != self::$_id)
            self::ajax_output(array('error' => '[ERROR] Order not paid for with Pascalcoin'));

        $details = self::get_payment_details($order);
        if(!is_array($details))
            self::ajax_output(array('error' => $details));

        self::ajax_output($details);

    }
    public static function ajax_output($response) {
        ob_clean();
        header('Content-type: application/json');
        echo json_encode($response);
        wp_die();
    }

    public static function admin_order_page($post)
    {
        $order = wc_get_order($post->ID);
        if($order->get_payment_method() != self::$_id)
            return;

        $method_title = self::$_title;
        $details = self::get_payment_details($order);
        if(!is_array($details)) {
            $error = $details;
            include PASCALCOIN_GATEWAY_PLUGIN_DIR . '/templates/pascalcoin-gateway/admin/order-history-error-page.php';
            return;
        }
        include PASCALCOIN_GATEWAY_PLUGIN_DIR . '/templates/pascalcoin-gateway/admin/order-history-page.php';
    }

    public static function customer_order_page($order)
    {
        if(is_integer($order)) {
            $order_id = $order;
            $order = wc_get_order($order_id);
        } else {
            $order_id = $order->get_id();
        }

        if($order->get_payment_method() != self::$_id)
            return;

        $method_title = self::$_title;
        $details = self::get_payment_details($order_id);
        if(!is_array($details)) {
            $error = $details;
            include PASCALCOIN_GATEWAY_PLUGIN_DIR . '/templates/pascalcoin-gateway/customer/order-error-page.php';
            return;
        }
        $show_qr = self::$show_qr;
        $details_json = json_encode($details);
        $ajax_url = WC_AJAX::get_endpoint('pascalcoin_gateway_payment_details');
        include PASCALCOIN_GATEWAY_PLUGIN_DIR . '/templates/pascalcoin-gateway/customer/order-page.php';
    }

    public static function customer_order_email($order)
    {
        if(is_integer($order)) {
            $order_id = $order;
            $order = wc_get_order($order_id);
        } else {
            $order_id = $order->get_id();
        }

        if($order->get_payment_method() != self::$_id)
            return;

        $method_title = self::$_title;
        $details = self::get_payment_details($order_id);
        if(!is_array($details)) {
            include PASCALCOIN_GATEWAY_PLUGIN_DIR . '/templates/pascalcoin-gateway/customer/order-email-error-block.php';
            return;
        }
        include PASCALCOIN_GATEWAY_PLUGIN_DIR . '/templates/pascalcoin-gateway/customer/order-email-block.php';
    }

    public static function get_id()
    {
        return self::$_id;
    }

    public static function use_qr_code()
    {
        return self::$show_qr;
    }

    public static function use_pascalcoin_price()
    {
        return self::$use_pascalcoin_price;
    }


    public static function convert_wc_price($price, $currency)
    {
        $rate = self::get_live_rate($currency);
        $pascalcoin_amount = intval(PASCALCOIN_GATEWAY_ATOMIC_UNITS_POW * 1e8 * $price / $rate) / PASCALCOIN_GATEWAY_ATOMIC_UNITS_POW;
        $pascalcoin_amount_formatted = sprintf('%.'.self::$use_pascalcoin_price_decimals.'f', $pascalcoin_amount);

        return <<<HTML
            <span class="woocommerce-Price-amount amount" data-price="$price" data-currency="$currency"
        data-rate="$rate" data-rate-type="live">
            $pascalcoin_amount_formatted
            <span class="woocommerce-Price-currencySymbol">PASC</span>
        </span>

HTML;
    }

    public static function convert_wc_price_order($price_html, $order)
    {
        if($order->get_payment_method() != self::$_id)
            return $price_html;

        $order_id = $order->get_id();
        $payment_details = self::get_payment_details($order_id);
        if(!is_array($payment_details))
            return $price_html;

        // Experimental regex, may fail with other custom price formatters
        $match_ok = preg_match('/data-price="([^"]*)"/', $price_html, $matches);
        if($match_ok !== 1) // regex failed
            return $price_html;

        $price = array_pop($matches);
        $currency = $payment_details['currency'];
        $rate = $payment_details['rate'];
        $pascalcoin_amount = intval(PASCALCOIN_GATEWAY_ATOMIC_UNITS_POW * 1e8 * $price / $rate) / PASCALCOIN_GATEWAY_ATOMIC_UNITS_POW;
        $pascalcoin_amount_formatted = sprintf('%.'.PASCALCOIN_GATEWAY_ATOMIC_UNITS.'f', $pascalcoin_amount);

        return <<<HTML
            <span class="woocommerce-Price-amount amount" data-price="$price" data-currency="$currency"
        data-rate="$rate" data-rate-type="fixed">
            $pascalcoin_amount_formatted
            <span class="woocommerce-Price-currencySymbol">PASC</span>
        </span>

HTML;
    }

    public static function get_live_rate($currency)
    {
        if(isset(self::$rates[$currency]))
            return self::$rates[$currency];

        global $wpdb;
        $table_name = $wpdb->prefix.'pascalcoin_gateway_live_rates';
        $query = $wpdb->prepare("SELECT rate FROM $table_name WHERE currency=%s", array($currency));

        $rate = $wpdb->get_row($query)->rate;
        self::$rates[$currency] = $rate;

        return $rate;
    }

    protected static function sanatize_id($payment_id)
    {
        // Limit payment id to alphanumeric characters
        $sanatized_id = preg_replace("/[^a-zA-Z0-9]+/", "", $payment_id);
        return $sanatized_id;
    }

    protected static function is_virtual_in_cart($order_id)
    {
        $order = wc_get_order($order_id);
        $items = $order->get_items();
        $cart_size = count($items);
        $virtual_items = 0;

        foreach ( $items as $item ) {
            $product = new WC_Product( $item['product_id'] );
            if ($product->is_virtual()) {
                $virtual_items += 1;
            }
        }
        return $virtual_items == $cart_size;
    }

    public static function format_pascalcoin($atomic_units) {
        return sprintf(PASCALCOIN_GATEWAY_ATOMIC_UNITS_SPRINTF, $atomic_units / PASCALCOIN_GATEWAY_ATOMIC_UNITS_POW);
    }

    public static function format_seconds_to_time($seconds)
    {
        $units = array();

        $dtF = new \DateTime('@0');
        $dtT = new \DateTime("@$seconds");
        $diff = $dtF->diff($dtT);

        $d = $diff->format('%a');
        $h = $diff->format('%h');
        $m = $diff->format('%i');

        if($d == 1)
            $units[] = "$d day";
        else if($d > 1)
            $units[] = "$d days";

        if($h == 0 && $d != 0)
            $units[] = "$h hours";
        else if($h == 1)
            $units[] = "$h hour";
        else if($h > 0)
            $units[] = "$h hours";

        if($m == 1)
            $units[] = "$m minute";
        else
            $units[] = "$m minutes";

        return implode(', ', $units) . ($seconds < 0 ? ' ago' : '');
    }

}
