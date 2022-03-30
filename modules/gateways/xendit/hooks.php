<?php

use Xendit\Lib\Recurring;

/**
 * Hook invoice created
 *
 * @param $vars
 * @return void
 */
function hookInvoiceCreated($vars)
{
    $xenditRecurring = new Recurring();
    $invoice = $xenditRecurring->getInvoice($vars['invoiceid']);

    // if payment method is Xendit
    if($invoice->paymentmethod != $xenditRecurring->getDomainName()){
        return;
    }

    // Set paymethodid is null
    $invoice->setAttribute("paymethodid", null)->save();

    // Save xendit transaction
    $xenditRecurring->storeTransactions($vars['invoiceid']);

    // Check if is recurring payment
    if($xenditRecurring->isRecurring($vars['invoiceid'])){
        $previousInvoice = $xenditRecurring->getPreviousInvoice($vars['invoiceid']);
        if(!empty($previousInvoice) && !empty($previousInvoice->paymethodid))
        {
            $invoice->setAttribute("paymethodid", $previousInvoice->paymethodid);
            $invoice->save();

            // Capture invoice payment
            $xenditRecurring->capture($invoice->id);
        }
    }
}
add_hook('InvoiceCreation', 1, 'hookInvoiceCreated');
