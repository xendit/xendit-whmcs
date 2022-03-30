<?php
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use Xendit\Lib\XenditRequest;

$gatewayModuleName = basename(__FILE__, '.php');
$gatewayParams = getGatewayVariables($gatewayModuleName);
$reqHeaders = getallheaders();

// use for callback
$rawRequestInput = file_get_contents("php://input");
$arrRequestInput = json_decode($rawRequestInput, true);

$success = $arrRequestInput['status'] == 'PAID';
$invoiceId = explode("WHMCS-", $arrRequestInput['external_id'])[1];
$transactionId = $arrRequestInput['id'];
$paymentAmount = $arrRequestInput['paid_amount'];
$paymentFee = $arrRequestInput['fees_paid_amount'];
$transactionStatus = $success ? 'Success' : 'Failure';

$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
checkCbTransID($transactionId);

if ($success) {

    if(isset($arrRequestInput['credit_card_charge_id']) && isset($arrRequestInput['credit_card_token'])){
        $xenditApi = new XenditRequest();
        $cardInfo = $xenditApi->getCardInfo($arrRequestInput['credit_card_charge_id']);
        $cardExpired = $xenditApi->getCardTokenInfo($arrRequestInput['credit_card_token']);
        if(!empty($cardInfo) && !empty($cardExpired)){
            $lastDigit = substr($cardInfo["masked_card_number"], -4);
            $response = invoiceSaveRemoteCard(
                $invoiceId,
                $lastDigit,
                $cardInfo["card_brand"],
                sprintf("%s/%s", $cardExpired["card_expiration_month"], $cardExpired["card_expiration_year"]),
                $arrRequestInput['credit_card_token']
            );
        }
    }

    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $paymentAmount,
        $paymentFee,
        $gatewayModuleName
    );

    logTransaction($gatewayParams['name'], $_POST, $transactionStatus);
}
