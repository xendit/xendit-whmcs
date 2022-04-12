<?php
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../xendit/autoload.php';

$callback = new \Xendit\Lib\Callback();
$gatewayModuleName = basename(__FILE__, '.php');
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Update credit card
if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'updatecc') {

    $publicKey = $gatewayParams['xenditTestMode'] == 'on' ? $gatewayParams['xenditTestPublicKey'] : $gatewayParams['xenditPublicKey'];
    $secretKey = $gatewayParams['xenditTestMode'] == 'on' ? $gatewayParams['xenditTestSecretKey'] : $gatewayParams['xenditSecretKey'];

    // Retrieve data returned in redirect
    $invoiceId = $_REQUEST['invoice_id'] ?? '';
    $customerId = $_REQUEST['customer_id'] ?? '';
    $amount = $_REQUEST['amount'] ?? '';
    $currencyCode = $_REQUEST['currency'] ?? '';
    $cardNumber = $_REQUEST['xendit_card_number'] ?? '';
    $cardType = $_REQUEST['xendit_card_type'] ?? '';
    $cardExpMonth = $_REQUEST['xendit_card_exp_month'] ?? '';
    $cardExpYear = $_REQUEST['xendit_card_exp_year'] ?? '';
    $cardToken = $_REQUEST['card_token'] ?? '';
    $verificationHash = $_REQUEST['verification_hash'] ?? '';
    $payMethodId = isset($_REQUEST['custom_reference']) ? (int)$_REQUEST['custom_reference'] : 0;

    $comparisonHash = sha1(
        implode('|', [
            $publicKey,
            $customerId,
            $invoiceId,
            $amount,
            $currencyCode,
            $secretKey
        ])
    );

    if ($verificationHash !== $comparisonHash) {
        logTransaction($gatewayParams['paymentmethod'], $_REQUEST, "Invalid Hash");
        die('Invalid hash.');
    }

    if (!empty($cardToken)) {
        try {
            // Function available in WHMCS 7.9 and later
            updateCardPayMethod(
                $customerId,
                $payMethodId,
                sprintf("%s%s", $cardExpMonth, substr($cardExpYear, -2)),
                null, // card start date
                null, // card issue number
                $cardToken
            );

            // Update last 4 digits
            \Xendit\Lib\Model\CreditCard::where('pay_method_id', $payMethodId)->update([
                'last_four' => substr($cardNumber, -4, 4),
                'card_type' => CreditCard::CARD_LABEL[$cardType] ?? $cardType
            ]);

            // Log to gateway log as successful.
            logTransaction($gatewayParams['paymentmethod'], $_REQUEST, 'Update Success');

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
        logTransaction($gatewayParams['paymentmethod'], $_REQUEST, 'Update Failed');

        // Show failure message.
        echo json_encode(
            [
                'error' => true,
                'message' => 'Update failed. Please try again.'
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
            $result = $callback->confirmInvoice(
                $invoiceId,
                $arrRequestInput,
                $arrRequestInput["status"] == "PAID"
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
