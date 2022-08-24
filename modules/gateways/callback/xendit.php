<?php
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../xendit/autoload.php';

use Xendit\Lib\Webhook;
use Xendit\Lib\CreditCard;
use Xendit\Lib\XenditRequest;

$webhook = new Webhook();
$creditCard = new CreditCard();
$xenditRequest = new XenditRequest();

$gatewayModuleName = basename(__FILE__, '.php');
$gatewayParams = getGatewayVariables($gatewayModuleName);
$postData = $_REQUEST;
$action = $postData['action'] ?? "";

// Create/Update credit card
if ($action == 'updatecc' || $action == "createcc") {
    /*
     * Make sure the CC authentication status = 1
     * That mean the CC token is valid to create the charge
     */
    if (!isset($postData['xendit_cc_authentication_status']) || $postData['xendit_cc_authentication_status'] == 0) {
        logTransaction($gatewayParams['paymentmethod'], $postData, "CC authentication failed");
        $creditCard->renderJson(
            [
                'error' => true,
                'message' => 'CC authentication failed.',
            ]
        );
    }

    /*
     * Make sure the credit card info has value
     * We extract card data to save card token
     */
    if (!$creditCard->validateCardInfo($postData)) {
        logTransaction($gatewayParams['paymentmethod'], $postData, "Missing cared information.");
        $creditCard->renderJson(
            [
                'error' => true,
                'message' => 'Missing card information.',
            ]
        );
    }

    /*
     * Extract CC data from REQUEST
     */
    $params = array_merge(
        $creditCard->extractCardData($postData),
        [
            "publicKey" => $xenditRequest->getPublicKey(),
            "secretKey" => $xenditRequest->getSecretKey(),
            "gatewayModuleName" => $gatewayParams['paymentmethod'],
            "paymentmethod" => $gatewayParams['paymentmethod'],
        ]
    );

    /*
     * Verification hash data is correct
     * We generate the hash on the create/update CC form which used to validate again before save CC
     */
    $verificationHash = $postData['verification_hash'] ?? '';
    if ($creditCard->compareHash($verificationHash, $params)) {
        logTransaction($gatewayParams['paymentmethod'], $postData, "Invalid Hash");
        $creditCard->renderJson(
            [
                'error' => true,
                'message' => 'Invalid.',
            ]
        );
    }

    // Save credit card if it has card Token
    if (!empty($params["cardToken"])) {
        try {
            $creditCard->saveCreditCardToken($params, $action == "createcc");
            $creditCard->renderJson(
                [
                    'error' => false,
                    'message' => 'Success',
                ]
            );
        } catch (Exception $e) {
            // Log to gateway log as unsuccessful.
            logTransaction($gatewayParams['paymentmethod'], $postData, $e->getMessage());
            $creditCard->renderJson(
                [
                    'error' => true,
                    'message' => $e->getMessage(),
                ]
            );
        }
    } else {
        // Log to gateway log as unsuccessful.
        logTransaction($gatewayParams['paymentmethod'], $postData, 'Save credit card failed');
        $creditCard->renderJson(
            [
                'error' => true,
                'message' => $action == "createcc" ? "Payment method failed to create successfully. Please try again." : "Payment method failed to save changes. Please try again.",
            ]
        );
    }
} else {
    // use for webhook
    $arrRequestInput = json_decode(file_get_contents("php://input"), true);
    if (!empty($arrRequestInput) && isset($arrRequestInput['external_id']) && !empty($arrRequestInput['external_id'])) {
        $invoiceId = $webhook->getInvoiceIdFromExternalId($arrRequestInput['external_id']);
        $transactions = $webhook->getTransactionFromInvoiceId($invoiceId);

        try {
            // Get invoice from Xendit
            $xenditInvoice = $xenditRequest->getInvoiceById($arrRequestInput['id']);
            if (isset($arrRequestInput['credit_card_token'])) {
                $xenditInvoice['credit_card_token'] = $arrRequestInput['credit_card_token'];
            }
            $result = $webhook->confirmInvoice(
                $invoiceId,
                $xenditInvoice,
                $xenditInvoice["status"] == "PAID" || $xenditInvoice["status"] == "SETTLED"
            );
            if ($result) {
                $webhook->updateTransactions($transactions);
                echo 'Success';
                exit;
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
