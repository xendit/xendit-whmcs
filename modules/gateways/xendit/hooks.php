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
    $xenditRecurring->storeTransactions($vars['invoiceid']);

    if($xenditRecurring->isRecurring($vars['invoiceid'])){
        $previousInvoice = $xenditRecurring->getPreviousInvoice($vars['invoiceid']);
        if(!empty($previousInvoice) && !empty($previousInvoice->paymethodid))
        {
            $invoice = $xenditRecurring->getInvoice($vars['invoiceid']);
            $invoice->setAttribute("paymethodid", $previousInvoice->paymethodid);
            $invoice->save();

            // Capture invoice payment
            $xenditRecurring->capture($invoice->id);
        }
    }
}
add_hook('InvoiceCreated', 1, 'hookInvoiceCreated');
