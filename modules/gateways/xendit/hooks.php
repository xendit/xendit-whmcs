<?php

use Xendit\Lib\ActionBase;
use Xendit\Lib\Recurring;

/**
 * Hook invoice created
 *
 * @param $vars
 * @return void
 */
add_hook('InvoiceCreation', 1, function ($vars) {
    if ($vars['status'] == 'Draft') {
        return;
    }

    $xenditRecurring = new Recurring();
    $invoice = $xenditRecurring->getInvoice($vars['invoiceid']);

    // if payment method is Xendit
    if ($invoice->paymentmethod != $xenditRecurring->getDomainName()) {
        return;
    }

    // Save xendit transaction
    $xenditRecurring->storeTransactions($vars['invoiceid']);
});

/**
 * Hook to show Xendit payment gateway based on currency
 *
 * @param $vars
 * @return array|void
 */
add_hook("ClientAreaPageCart", 1, function ($vars) {
    if ($vars['templatefile'] == 'viewcart') {
        $activeCurrency = $vars['currency']['code'] ?? $vars['activeCurrency']->code;
        if (!in_array($activeCurrency, ActionBase::ALLOW_CURRENCIES)) {
            unset($vars['gateways']["xendit"]);
        }
    }
    return $vars;
});
