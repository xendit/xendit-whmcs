<?php

use Xendit\Lib\Recurring;

/**
 * Hook invoice created
 *
 * @param $vars
 * @return void
 */
add_hook('InvoiceCreation', 1, function ($vars){
    $xenditRecurring = new Recurring();
    $invoice = $xenditRecurring->getInvoice($vars['invoiceid']);

    // if payment method is Xendit
    if ($invoice->paymentmethod != $xenditRecurring->getDomainName()) {
        return;
    }

    // Save xendit transaction
    $xenditRecurring->storeTransactions($vars['invoiceid']);

    // Check if it is recurring payment
    if ($xenditRecurring->isRecurring($vars['invoiceid'])) {
        $previousInvoice = $xenditRecurring->getPreviousInvoice($vars['invoiceid']);
        if (!empty($previousInvoice) && $xenditRecurring->isInvoiceUsedCreditCard($previousInvoice->id))
        {
            $invoice->setAttribute("paymethodid", $previousInvoice->paymethodid);
            $invoice->save();

            // Capture invoice payment
            $xenditRecurring->capture($invoice->id);
        }
    }
});
