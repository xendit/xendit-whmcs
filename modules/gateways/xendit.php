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
    // Create new table
    (new \Xendit\Lib\ActionBase())->createTable();

    // Generate config
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
//function xendit_nolocalcc() {}

/**
 *
 * Capture payment.
 *
 * Called when a payment is requested to be processed and captured.
 *
 * The CVV number parameter will only be present for card holder present
 * transactions and when made against an existing stored payment token
 * where new card data has not been entered.
 *
 * @param $params
 * @return array|string[]
 * @throws Exception
 */
function xendit_capture($params)
{
    // Capture Parameters
    $remoteGatewayToken = $params['gatewayid'];

    // A token is required for a remote input gateway capture attempt
    if (!$remoteGatewayToken) {
        return [
            'status' => 'declined',
            'decline_message' => 'No Remote Token',
        ];
    }

    // Generate payload
    $cc = new \Xendit\Lib\CreditCard();
    $payload = $cc->generateCCPaymentRequest($params);

    try{
        $xenditRequest = new \Xendit\Lib\XenditRequest();
        $response = $xenditRequest->createCharge($payload);
    }catch (\Exception $e){
        return [
            // 'success' if successful, otherwise 'declined', 'error' for failure
            'status' => 'declined',
            // For declines, a decline reason can optionally be returned
            'declinereason' => $e->getMessage(),
            // Data to be recorded in the gateway log - can be a string or array
            'rawdata' => $payload,
        ];
    }

    if (!empty($response) && isset($response['status']) && $response['status'] == "CAPTURED") {

        // Save transaction status
        $xenditRecurring = new \Xendit\Lib\Recurring();
        $transactions = $xenditRecurring->getTransactionFromInvoiceId($params["invoiceid"]);
        if(!empty($transactions)){
            foreach ($transactions as $transaction){
                $transaction->setAttribute("status", "PAID");
                $transaction->setAttribute("payment_method", "CREDIT_CARD");
                $transaction->save();
            }
        }

        return [
            // 'success' if successful, otherwise 'declined', 'error' for failure
            'status' => 'success',
            // The unique transaction id for the payment
            'transid' => $response['id'],
            // Optional fee amount for the transaction
            'fee' => 0,
            // Return only if the token has updated or changed
            'gatewayid' => $response['credit_card_token_id'],
            // Data to be recorded in the gateway log - can be a string or array
            'rawdata' => $response,
        ];
    }

    return [
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => 'declined',
        // For declines, a decline reason can optionally be returned
        'declinereason' => $response['message'],
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $payload,
    ];
}

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
