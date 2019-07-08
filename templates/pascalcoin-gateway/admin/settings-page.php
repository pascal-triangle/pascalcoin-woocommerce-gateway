<?php foreach($errors as $error): ?>
<div class="error"><p><strong>PascalCoin Payments Error</strong>: <?php echo $error; ?></p></div>
<?php endforeach; ?>

<h1>PascalCoin Payments Settings</h1>

<div style="border:1px solid #ddd;padding:5px 10px;">
    <?php
         echo 'Wallet height: ' . $balance['height'] . '</br>';
         echo 'Your balance is: ' . $balance['balance'] . '</br>';
         ?>
</div>

<table class="form-table">
    <?php echo $settings_html ?>
</table>

<script>
function pascalcoinUpdateFields() {
    var usePascalcoinPrices = jQuery("#woocommerce_pascalcoin_gateway_use_pascalcoin_price").is(":checked");
    if(usePascalcoinPrices) {
        jQuery("#woocommerce_pascalcoin_gateway_use_pascalcoin_price_decimals").closest("tr").show();
    } else {
        jQuery("#woocommerce_pascalcoin_gateway_use_pascalcoin_price_decimals").closest("tr").hide();
    }
}
pascalcoinUpdateFields();
jQuery("#woocommerce_pascalcoin_gateway_use_pascalcoin_price").change(pascalcoinUpdateFields);
</script>

<style>
#woocommerce_pascalcoin_gateway_pascalcoin_address,
#woocommerce_pascalcoin_gateway_viewkey {
    width: 100%;
}
</style>
