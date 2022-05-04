<?php
/**
 * Register hook function call.
 *
 * @param string $hookPoint The hook point to call.
 * @param integer $priority The priority for the hook function.
 * @param string|function The function name to call or the anonymous function.
 *
 * @return This depends on the hook function point.
 */

if (!defined('WHMCS')) {
    die('You cannot access this file directly.');
}

/**
 * Hook invoice creation
 *
 * @param $vars
 * @return array|void
 */
function hookInvoiceCreation($vars)
{
    if ($vars['status'] !== 'Draft' && class_exists('\Xendit\Lib\Recurring')) {
        $xenditRecurring = new \Xendit\Lib\Recurring();
        $invoice = $xenditRecurring->getInvoice($vars['invoiceid']);

        // if payment method is Xendit
        if ($invoice->paymentmethod != $xenditRecurring->getDomainName()) {
            return;
        }

        // Save xendit transaction
        return $xenditRecurring->storeTransactions($vars['invoiceid']);
    }
}

add_hook('InvoiceCreation', 1, 'hookInvoiceCreation');


/**
 * Hook to show Xendit payment gateway based on currency
 *
 * @param $vars
 * @return array|void
 */
function hookClientAreaPageCart($vars)
{
    if ($vars['templatefile'] == 'viewcart' && class_exists('\Xendit\Lib\ActionBase')) {
        $actionBase = new \Xendit\Lib\ActionBase();
        $activeCurrency = $vars['currency']['code'] ?? $vars['activeCurrency']->code;
        if (!$actionBase->validateCompatibilityVersion()
            || !in_array($activeCurrency, \Xendit\Lib\ActionBase::ALLOW_CURRENCIES)
        ) {
            unset($vars['gateways']["xendit"]);
        }
    }
    return $vars;
}

add_hook("ClientAreaPageCart", 1, 'hookClientAreaPageCart');
