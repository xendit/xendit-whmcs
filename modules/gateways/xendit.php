<?php

//autoload gateway functions
require_once __DIR__ . '/../../includes/gatewayfunctions.php';

require __DIR__ .'/xendit/autoload.php';
require __DIR__ .'/xendit/hooks.php';

use WHMCS\Billing\Invoice;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * @return array
 */
function xendit_MetaData()
{
    return array(
        'DisplayName' => 'Xendit Payment Gateway',
        'APIVersion' => '1.1'
    );
}

function xendit_storeremote($params){}

/**
 * @return array
 */
function xendit_config()
{
    (new \Xendit\Lib\ActionBase())->createTable();
    return (new \Xendit\Lib\ActionBase())->createConfig();
}

/**
 * @param $params
 * @return string
 * @throws Exception
 */
function xendit_link($params)
{
    return (new \Xendit\Lib\Link())->generatePaymentLink($params);
}

/**
 * No local credit card input.
 *
 * This is a required function declaration. Denotes that the module should
 * not allow local card data input.
 */
function xendit_nolocalcc() {}

/**
 * Remote input.
 *
 * Called when a pay method is requested to be created or a payment is
 * being attempted.
 *
 * New pay methods can be created or added without a payment being due.
 * In these scenarios, the amount parameter will be empty and the workflow
 * should be to create a token without performing a charge.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/remote-input-gateway/
 *
 * @return array
 */
function xendit_remoteinput($params)
{

}

/**
 * Remote update.
 *
 * Called when a pay method is requested to be updated.
 *
 * The expected return of this function is direct HTML output. It provides
 * more flexibility than the remote input function by not restricting the
 * return to a form that is posted into an iframe. We still recommend using
 * an iframe where possible and this sample demonstrates use of an iframe,
 * but the update can sometimes be handled by way of a modal, popup or
 * other such facility.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/remote-input-gateway/
 *
 * @return array
 */
function xendit_remoteupdate($params)
{
    if(strpos($_REQUEST["rp"], "/admin/") !== FALSE){
        return <<<HTML
<div class="alert alert-info text-center">
    Updating your card/bank is not possible. Please create a new Pay Method to make changes.
</div>
HTML;
    }
}

/**
 * Admin status message.
 *
 * Called when an invoice is viewed in the admin area.
 *
 * @param array $params Payment Gateway Module Parameters.
 *
 * @return array
 */
function xendit_adminstatusmsg($params)
{
}
