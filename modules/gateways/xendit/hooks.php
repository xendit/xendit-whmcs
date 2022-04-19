<?php

use Xendit\Lib\Recurring;

/**
 * Hook invoice created
 *
 * @param $vars
 * @return void
 */
add_hook('InvoiceCreation', 1, function ($vars) {
    $xenditRecurring = new Recurring();
    $invoice = $xenditRecurring->getInvoice($vars['invoiceid']);

    // if payment method is Xendit
    if ($invoice->paymentmethod != $xenditRecurring->getDomainName()) {
        return;
    }

    // Save xendit transaction
    $xenditRecurring->storeTransactions($vars['invoiceid']);
});
