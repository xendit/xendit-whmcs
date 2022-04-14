<?php
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../xendit/autoload.php';

use Xendit\Lib\Callback;
use Xendit\lib\CreditCard;
use Xendit\Lib\XenditRequest;

$callback = new Callback();
$creditCard = new CreditCard();
$xenditRequest = new XenditRequest();

$gatewayModuleName = basename(__FILE__, '.php');
$gatewayParams = getGatewayVariables($gatewayModuleName);
$action = $_REQUEST['action'] ?? "";

// Create/Update credit card
if ($action == 'updatecc' || $action == "createcc") {
    // Retrieve data returned in redirect
    $params = [
        "publicKey" => $xenditRequest->getPublicKey(),
        "secretKey" => $xenditRequest->getSecretKey(),
        "gatewayModuleName" => $gatewayParams['paymentmethod'],
        "customerId" => $_REQUEST['customer_id'] ?? '',
        "cardLastFour" => $_REQUEST['xendit_card_number'] ? substr($_REQUEST['xendit_card_number'], -4, 4) : "",
        "cardExpiryDate" => isset($_REQUEST['xendit_card_exp_month']) && isset($_REQUEST['xendit_card_exp_year'])
            ? sprintf("%s%s", $_REQUEST['xendit_card_exp_month'], substr($_REQUEST['xendit_card_exp_year'], -2))
            : "",
        "cardType" => $_REQUEST['xendit_card_type'] ? (
            CreditCard::CARD_LABEL[$_REQUEST['xendit_card_type']] ?? ""
        ) : "",
        "cardToken" => $_REQUEST['xendit_token'] ?? "",
        "cardDescription" => $_REQUEST['card_description'] ?? "",
        "paymentmethod" => $gatewayParams['paymentmethod'],
        "invoiceId" => $_REQUEST['invoice_id'] ?? '',
        "payMethodId" => $_REQUEST['custom_reference'] ?? ''
    ];
    $verificationHash = $_REQUEST['verification_hash'] ?? '';
    $payMethodId = isset($_REQUEST['custom_reference']) ? (int)$_REQUEST['custom_reference'] : 0;

    // validate hash
    if ($creditCard->compareHash($params, $verificationHash)) {
        logTransaction($gatewayParams['paymentmethod'], $_REQUEST, "Invalid Hash");
        die('Invalid hash.');
    }

    // Save credit card if it has card Token
    if (!empty($params["cardToken"])) {
        try {
            $creditCard->saveCreditCardToken($params, $action == "createcc");

            // Show success message.
            echo json_encode(
                [
                    'error' => false,
                    'message' => 'Success'
                ]
            );
            exit;
        } catch (Exception $e) {

            // Log to gateway log as unsuccessful.
            logTransaction($gatewayParams['paymentmethod'], $_REQUEST, $e->getMessage());

            // Show failure message.
            echo json_encode(
                [
                    'error' => true,
                    'message' => $e->getMessage()
                ]
            );
            exit;
        }
    } else {
        // Log to gateway log as unsuccessful.
        logTransaction($gatewayParams['paymentmethod'], $_REQUEST, 'Save credit card failed');

        // Show failure message.
        echo json_encode(
            [
                'error' => true,
                'message' => $action == "createcc" ? "Payment method failed to create successfully. Please try again." : "Payment method failed to save changes. Please try again."
            ]
        );
        exit;
    }
} else {
    // use for callback
    $arrRequestInput = json_decode(file_get_contents("php://input"), true);
    if (
        !empty($arrRequestInput)
        && isset($arrRequestInput['external_id'])
        && !empty($arrRequestInput['external_id'])
    ) {
        $invoiceId = $callback->getInvoiceIdFromExternalId($arrRequestInput['external_id']);
        $transactions = $callback->getTransactionFromInvoiceId($invoiceId);

        try {
            // Get invoice from Xendit
            $xenditInvoice = $xenditRequest->getInvoiceById($arrRequestInput['id']);
            if(isset($arrRequestInput['credit_card_token'])){
                $xenditInvoice['credit_card_token'] = $arrRequestInput['credit_card_token'];
            }
            $result = $callback->confirmInvoice(
                $invoiceId,
                $xenditInvoice,
                $xenditInvoice["status"] == "PAID" || $xenditInvoice["status"] == "SETTLED"
            );
            if ($result) {
                $callback->updateTransactions($transactions);
                echo 'Success';
                exit;
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
