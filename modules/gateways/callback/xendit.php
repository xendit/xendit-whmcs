<?php
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

require_once __DIR__ . '/../xendit/autoload.php';

$callback = new \Xendit\Lib\Callback();
$gatewayModuleName = basename(__FILE__, '.php');

// use for callback
$rawRequestInput = file_get_contents("php://input");
$arrRequestInput = json_decode($rawRequestInput, true);

$externalId = explode("-", $arrRequestInput['external_id']);
$invoiceId = trim(end($externalId));
$transactions = $callback->getTransactionFromInvoiceId($invoiceId);

try {
    $result = $callback->confirmInvoice(
        $invoiceId,
        $arrRequestInput,
        $arrRequestInput["status"] == "PAID"
    );
    if($result){
        $callback->updateTransactions($transactions);
    }
} catch (\Exception $e) {
    throw new \Exception($e->getMessage());
}
