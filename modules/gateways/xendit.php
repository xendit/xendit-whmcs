<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

//autoload gateway functions
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/gatewayfunctions.php';

require __DIR__ . '/xendit/autoload.php';

use WHMCS\Billing\Invoice;
use Xendit\Lib\Model\XenditTransaction;
use Xendit\Lib\Recurring;
use Xendit\Lib\XenditRequest;

/**
 * @return array
 */
function xendit_MetaData()
{
    return array(
        'DisplayName' => 'Xendit Payment Gateway',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => false,
        'TokenisedStorage' => false
    );
}

/**
 * Xendit config
 *
 * @return array|string[][]
 */
function xendit_config()
{
    (new \Xendit\Lib\Migrate())->createTransactionTable();
    return (new \Xendit\Lib\ActionBase())->createConfig();
}

/**
 * Xendit Deactivate module
 *
 * @return string[]
 */
function xendit_deactivate()
{
    try{
        (new \Xendit\Lib\Migrate())->removeTransactionTable();
        return [
            // Supported values here include: success, error or info
            'status' => 'success',
            'description' => 'Drop Xendit data success.'
        ];
    }catch (\Exception $e){
        return [
            // Supported values here include: success, error or info
            "status" => "error",
            "description" => "Unable drop Xendit data: {$e->getMessage()}",
        ];
    }
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
 * The CVV number parameter will only be present for cardholder present
 * transactions and when made against an existing stored payment token
 * where new card data has not been entered.
 *
 * @param $params
 * @return array|string[]
 * @throws Exception
 */
function xendit_capture($params)
{
    $xenditRequest = new XenditRequest();

    // Capture Parameters
    $remoteGatewayToken = $params["gatewayid"];

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
        $xenditRecurring = new Recurring();
        $transactions = $xenditRecurring->getTransactionFromInvoiceId($params["invoiceid"]);
        if (!empty($transactions)) {
            $xenditRecurring->updateTransactions(
                $transactions,
                [
                    "status" => XenditTransaction::STATUS_PAID,
                    "payment_method" => "CREDIT_CARD"
                ]
            );
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
 * @return string
 * @see https://developers.whmcs.com/payment-gateways/remote-input-gateway/
 *
 */
function xendit_remoteinput($params)
{
    // Gateway Configuration Parameters
    $publicKey = $params['xenditTestMode'] == 'on' ? $params['xenditTestPublicKey'] : $params['xenditPublicKey'];
    $secretKey = $params['xenditTestMode'] == 'on' ? $params['xenditTestSecretKey'] : $params['xenditSecretKey'];

    // Client Parameters
    $clientId = $params["clientdetails"]["id"];

    // System Parameters
    $systemUrl = $params['systemurl'];
    $currencyData = getCurrency($clientId);

    // Build a form which can be submitted to an iframe target to render
    // the payment form.
    $formAction = $systemUrl . 'modules/gateways/xendit/handler/updatecc.php';
    $formFields = [
        'public_key' => $publicKey,
        'secret_key' => $secretKey,
        'card_token' => "",
        'card_number' => "",
        'card_expiry_date' => "",
        'action' => 'createcc',
        'invoice_id' => 0,
        'amount' => 1,
        'currency' => $currencyData["code"],
        'customer_id' => $clientId,
        'return_url' => $systemUrl . 'modules/gateways/callback/xendit.php',
        'payment_method_url' => $systemUrl . 'index.php?rp=/account/paymentmethods',
        'verification_hash' => sha1(
            implode('|', [
                $publicKey,
                $clientId,
                0, // Invoice ID - there is no invoice for an update
                1, // Amount - there is no amount when updating
                $currencyData["code"], // Currency Code - there is no currency when updating
                $secretKey
            ])
        ),
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
</div>';
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

    $xenditRequest = new XenditRequest();

    // Gateway Configuration Parameters
    $publicKey = $xenditRequest->getPublicKey();
    $secretKey = $xenditRequest->getSecretKey();
    $remoteStorageToken = $params['gatewayid'];

    // Client Parameters
    $clientId = $params['client_id'];
    $payMethodId = $params['paymethodid'];
    $card_expired_date = (new DateTime($params['payMethod']->payment->expiry_date));
    $currencyData = getCurrency($clientId);

    // System Parameters
    $systemUrl = $params['systemurl'];

    // Build a form which can be submitted to an iframe target to render
    // the payment form.
    $formAction = $systemUrl . 'modules/gateways/xendit/handler/updatecc.php';
    $formFields = [
        'public_key' => $publicKey,
        'secret_key' => $secretKey,
        'card_token' => $remoteStorageToken,
        'card_description' => $params['payMethod']->description,
        'card_number' => sprintf("**** **** **** %s", $params['payMethod']->payment->last_four),
        'card_expiry_date' => sprintf("%s / %s", $card_expired_date->format("m"), substr($card_expired_date->format("Y"), -2)),
        'action' => 'updatecc',
        'invoice_id' => 0,
        'amount' => 1,
        'currency' => $currencyData['code'],
        'customer_id' => $clientId,
        'return_url' => $systemUrl . 'modules/gateways/callback/xendit.php',
        'payment_method_url' => $systemUrl . 'index.php?rp=/account/paymentmethods',
        'verification_hash' => sha1(
            implode('|', [
                $publicKey,
                $clientId,
                0, // Invoice ID - there is no invoice for an update
                1, // Amount - there is no amount when updating
                $currencyData["code"], // Currency Code - there is no currency when updating
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

/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/refunds/
 *
 * @return array Transaction response status
 */
function xendit_refund($params)
{
    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount'];

    // System Parameters
    $companyName = $params['companyname'];

    // perform API call to initiate refund and interpret result
    $xenditRequest = new \Xendit\Lib\XenditRequest();
    try{
        $invoiceResponse = $xenditRequest->getInvoiceById($transactionIdToRefund);
        $chargeId = $invoiceResponse['credit_card_charge_id'];
    }catch (Exception $e){
        if(str_contains($e->getMessage(), "INVOICE_NOT_FOUND_ERROR")){
            // The invoice created via CLI & chargeID saved to transaction
            $chargeId = $transactionIdToRefund;
        }
    }

    if(empty($chargeId)) {
        return array(
            'status'    => 'error',
            'rawdata'   => 'Can not refund the payment because because it is not credit card transaction'
        );
    }

    $body = array(
        'store_name'    => $companyName,
        'external_id'   => 'whmcs-refund-' . uniqid(),
        'amount'        => $refundAmount
    );

    try{
        $refundResponse = $xenditRequest->createRefund($chargeId, $body);
    }catch (Exception $e){
        return array(
            'status' => 'declined',
            'rawdata' => $e->getMessage(),
        );
    }

    if ($refundResponse['status'] === 'FAILED') {
        return array(
            'status' => 'declined',
            'rawdata' => $refundResponse,
            // Unique Transaction ID for the refund transaction
            'transid' => $refundResponse['id'],
        );
    }

    return array(
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => 'success',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $refundResponse,
        // Unique Transaction ID for the refund transaction
        'transid' => $refundResponse['id'],
    );
}
