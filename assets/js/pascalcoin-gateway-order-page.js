/*
 * Copyright (c) 2018, PascalCoin
 * Copyright (c) 2018, Ryo Currency Project
*/
function pascalcoin_showNotification(message, type='success') {
    var toast = jQuery('<div class="' + type + '"><span>' + message + '</span></div>');
    jQuery('#pascalcoin_toast').append(toast);
    toast.animate({ "right": "12px" }, "fast");
    setInterval(function() {
        toast.animate({ "right": "-400px" }, "fast", function() {
            toast.remove();
        });
    }, 2500)
}
function pascalcoin_showQR(show=true) {
    jQuery('#pascalcoin_qr_code_container').toggle(show);
}
function pascalcoin_fetchDetails() {
    var data = {
        '_': jQuery.now(),
        'order_id': pascalcoin_details.order_id
    };
    jQuery.get(pascalcoin_ajax_url, data, function(response) {
        if (typeof response.error !== 'undefined') {
            console.log(response.error);
        } else {
            pascalcoin_details = response;
            pascalcoin_updateDetails();
        }
    });
}

function pascalcoin_updateDetails() {

    var details = pascalcoin_details;

    jQuery('#pascalcoin_payment_messages').children().hide();
    switch(details.status) {
        case 'unpaid':
            jQuery('.pascalcoin_payment_unpaid').show();
            jQuery('.pascalcoin_payment_expire_time').html(details.order_expires);
            break;
        case 'partial':
            jQuery('.pascalcoin_payment_partial').show();
            jQuery('.pascalcoin_payment_expire_time').html(details.order_expires);
            break;
        case 'paid':
            jQuery('.pascalcoin_payment_paid').show();
            jQuery('.pascalcoin_confirm_time').html(details.time_to_confirm);
            jQuery('.button-row button').prop("disabled",true);
            break;
        case 'confirmed':
            jQuery('.pascalcoin_payment_confirmed').show();
            jQuery('.button-row button').prop("disabled",true);
            break;
        case 'expired':
            jQuery('.pascalcoin_payment_expired').show();
            jQuery('.button-row button').prop("disabled",true);
            break;
        case 'expired_partial':
            jQuery('.pascalcoin_payment_expired_partial').show();
            jQuery('.button-row button').prop("disabled",true);
            break;
    }

    jQuery('#pascalcoin_exchange_rate').html('1 PASC = '+details.rate_formatted+' '+details.currency);
    jQuery('#pascalcoin_total_amount').html(details.amount_total_formatted);
    jQuery('#pascalcoin_total_paid').html(details.amount_paid_formatted);
    jQuery('#pascalcoin_total_due').html(details.amount_due_formatted);

    jQuery('#pascalcoin_account').html(details.address);
    jQuery('#pascalcoin_payment_id').html(details.payment_id);

    if(pascalcoin_show_qr) {
        var qr = jQuery('#pascalcoin_qr_code').html('');
        new QRCode(qr.get(0), details.qrcode_uri);
    }

    if(details.txs.length) {
        jQuery('#pascalcoin_tx_table').show();
        jQuery('#pascalcoin_tx_none').hide();
        jQuery('#pascalcoin_tx_table tbody').html('');
        for(var i=0; i < details.txs.length; i++) {
            var tx = details.txs[i];
            var height = tx.height == 0 ? 'N/A' : tx.height;
            var row = ''+
                '<tr>'+
                '<td style="word-break: break-all">'+
                '<a href="'+pascalcoin_explorer_url+'/findoperation.php?ophash='+tx.txid+'" target="_blank">'+tx.txid+'</a>'+
                '</td>'+
                '<td>'+height+'</td>'+
                '<td>'+tx.amount_formatted+' PASC</td>'+
                '</tr>';

            jQuery('#pascalcoin_tx_table tbody').append(row);
        }
    } else {
        jQuery('#pascalcoin_tx_table').hide();
        jQuery('#pascalcoin_tx_none').show();
    }

    // Show state change notifications
    var new_txs = details.txs;
    var old_txs = pascalcoin_order_state.txs;
    if(new_txs.length != old_txs.length) {
        for(var i = 0; i < new_txs.length; i++) {
            var is_new_tx = true;
            for(var j = 0; j < old_txs.length; j++) {
                if(new_txs[i].txid == old_txs[j].txid && new_txs[i].amount == old_txs[j].amount) {
                    is_new_tx = false;
                    break;
                }
            }
            if(is_new_tx) {
                pascalcoin_showNotification('Transaction received for '+new_txs[i].amount_formatted+' PASC');
            }
        }
    }

    if(details.status != pascalcoin_order_state.status) {
        switch(details.status) {
            case 'paid':
                pascalcoin_showNotification('Your order has been paid in full');
                break;
            case 'confirmed':
                pascalcoin_showNotification('Your order has been confirmed');
                break;
            case 'expired':
            case 'expired_partial':
                pascalcoin_showNotification('Your order has expired', 'error');
                break;
        }
    }

    pascalcoin_order_state = {
        status: pascalcoin_details.status,
        txs: pascalcoin_details.txs
    };

}
jQuery(document).ready(function($) {
    if (typeof pascalcoin_details !== 'undefined') {
        pascalcoin_order_state = {
            status: pascalcoin_details.status,
            txs: pascalcoin_details.txs
        };
        setInterval(pascalcoin_fetchDetails, 30000);
        pascalcoin_updateDetails();
        new ClipboardJS('.clipboard').on('success', function(e) {
            e.clearSelection();
            if(e.trigger.disabled) return;
            switch(e.trigger.getAttribute('data-clipboard-target')) {
                case '#pascalcoin_account':
                    pascalcoin_showNotification('Copied destination account!');
                    break;
                case '#pascalcoin_payment_id':
                    pascalcoin_showNotification('Copied payload id!');
                    break;
                case '#pascalcoin_total_due':
                    pascalcoin_showNotification('Copied total amount due!');
                    break;
            }
            e.clearSelection();
        });
    }
});
