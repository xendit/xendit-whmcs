<?php

//autoload gateway functions
require_once __DIR__ . '/../../includes/gatewayfunctions.php';

require __DIR__ . '/xendit/autoload.php';
require __DIR__ . '/xendit/hooks.php';

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

function xendit_storeremote($params)
{
}

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

    try {
        $xenditRequest = new \Xendit\Lib\XenditRequest();
        $response = $xenditRequest->createCharge($payload);
    } catch (\Exception $e) {
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
        if (!empty($transactions)) {
            foreach ($transactions as $transaction) {
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
 * @return array
 * @see https://developers.whmcs.com/payment-gateways/remote-input-gateway/
 *
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
 * @return array
 * @throws Exception
 * @see https://developers.whmcs.com/payment-gateways/remote-input-gateway/
 *
 */
function xendit_remoteupdate($params)
{
    if (strpos($_REQUEST["rp"], "/admin/") !== FALSE) {
        return <<<HTML
<div class="alert alert-info text-center">
    Updating your card/bank is not possible. Please create a new Pay Method to make changes.
</div>
HTML;
    }

    // Gateway Configuration Parameters
    $publicKey = $params['xenditTestMode'] == 'on' ? $params['xenditTestPublicKey'] : $params['xenditPublicKey'];
    $secretKey = $params['xenditTestMode'] == 'on' ? $params['xenditTestSecretKey'] : $params['xenditSecretKey'];
    $remoteStorageToken = $params['gatewayid'];

    // Client Parameters
    $clientId = $params['client_id'];
    $payMethodId = $params['paymethodid'];
    $card_expired_date = (new DateTime($params['payMethod']->payment->expiry_date));

    // System Parameters
    $systemUrl = $params['systemurl'];

    // Build a form which can be submitted to an iframe target to render
    // the payment form.
    $formAction = $systemUrl . 'modules/gateways/xendit/handler/updatecc.php';
    $formFields = [
        'public_key' => $publicKey,
        'secret_key' => $secretKey,
        'card_token' => $remoteStorageToken,
        'card_number' => sprintf("**** **** **** %s", $params['payMethod']->payment->last_four),
        'card_expiry_date' => sprintf("%s / %s", $card_expired_date->format("m"), substr($card_expired_date->format("Y"), -2)),
        'action' => 'updatecc',
        'invoice_id' => 0,
        'amount' => 1,
        'currency' => 'IDR',
        'customer_id' => $clientId,
        'return_url' => $systemUrl . 'modules/gateways/callback/xendit.php',
        'verification_hash' => sha1(
            implode('|', [
                $publicKey,
                $clientId,
                0, // Invoice ID - there is no invoice for an update
                1, // Amount - there is no amount when updating
                'IDR', // Currency Code - there is no currency when updating
                $secretKey
            ])
        ),
        // The PayMethod ID will need to be available in the callback file after
        // update. We will pass a custom variable here to enable that.
        'custom_reference' => $payMethodId,
    ];

    $formOutput = '';
    foreach ($formFields as $key => $value) {
        $formOutput .= '<input type="hidden" name="' . $key . '" value="' . $value . '">' . PHP_EOL;
    }

    // This is a working example which posts to the file: demo/remote-iframe-demo.php
    return '<div id="frmRemoteCardProcess" class="text-center">
    <form method="post" action="' . $formAction . '" target="remoteUpdateIFrame">
        ' . $formOutput . '
        <noscript>
            <input type="submit" value="Click here to continue &raquo;">
        </noscript>
    </form>
    <iframe name="remoteUpdateIFrame" class="auth3d-area" width="90%" height="600" scrolling="auto" src="about:blank"></iframe>
</div>
<script>
    setTimeout("autoSubmitFormByContainer(\'frmRemoteCardProcess\')", 1000);
</script>';
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
