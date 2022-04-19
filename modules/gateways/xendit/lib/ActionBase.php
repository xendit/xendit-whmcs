<?php

namespace Xendit\Lib;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Xendit\Lib\Model\XenditTransaction;
use Xendit\Lib\XenditRequest;

use WHMCS\Billing\Invoice;
use WHMCS\Billing\Invoice\Item;

class ActionBase
{
    protected $moduleDomain = 'xendit';
    protected $xenditRequest;
    protected $xenditTransaction;

    public function __construct()
    {
        $this->xenditRequest = new XenditRequest();
    }

    /**
     * @return string
     */
    public function getDomainName()
    {
        return $this->moduleDomain;
    }

    /**
     * @return mixed
     */
    public function getXenditConfig()
    {
        return getGatewayVariables($this->moduleDomain);
    }

    /**
     * @return array
     */
    public function createConfig()
    {
        return array(
            'FriendlyName' => array(
                'Type' => 'System',
                'Value' => 'Xendit Payment Gateway',
            ),
            'description' => array(
                'FriendlyName' => '',
                'Type' => 'hidden',
                'Size' => '72',
                'Default' => '',
                'Description' => '<div class="alert alert-info top-margin-5 bottom-margin-5">
<img src="../modules/gateways/xendit/logo.png" width="70" align="left" style="padding-right:12px;" />
<span>Xendit is a leading payment gateway for Indonesia, the Philippines and Southeast Asia</span>
</div>',
            ),
            'xenditTestMode' => array(
                'FriendlyName' => 'Test Mode',
                'Type' => 'yesno',
                'Description' => 'Enable test mode
<script type="text/javascript">
const testModeCheckbox = $("[type=checkbox][name=\'field[xenditTestMode]\']");
const showTestModeFields = function(testMode){
    $("[name=\'field[xenditTestSecretKey]\']").parent().parent().prop("hidden", testMode);
    $("[name=\'field[xenditTestPublicKey]\']").parent().parent().prop("hidden", testMode);
    $("[name=\'field[xenditSecretKey]\']").parent().parent().prop("hidden", !testMode);
    $("[name=\'field[xenditPublicKey]\']").parent().parent().prop("hidden", !testMode);
}
$(document).ready(function (){
    showTestModeFields(!testModeCheckbox.is(":checked"));
    testModeCheckbox.on("change", function(){
        showTestModeFields(!$(this).is(":checked"));
    });
});
</script>',
            ),
            'xenditTestPublicKey' => array(
                'FriendlyName' => 'Test Public Key',
                'Type' => 'password',
                'Size' => '25',
                'Default' => '',
                'Description' => 'Enter test public key here',
            ),
            'xenditTestSecretKey' => array(
                'FriendlyName' => 'Test Secret Key',
                'Type' => 'password',
                'Size' => '25',
                'Default' => '',
                'Description' => 'Enter test secret key here',
            ),
            'xenditPublicKey' => array(
                'FriendlyName' => 'Public Key',
                'Type' => 'password',
                'Size' => '25',
                'Default' => '',
                'Description' => 'Enter public key here',
            ),
            'xenditSecretKey' => array(
                'FriendlyName' => 'Secret Key',
                'Type' => 'password',
                'Size' => '25',
                'Default' => '',
                'Description' => 'Enter secret key here',
            ),
            'xenditExternalPrefix' => array(
                'FriendlyName' => 'External ID Prefix',
                'Type' => 'text',
                'Size' => '25',
                'Default' => 'WHMCS-Xendit',
                'Description' => '<div>
Format: <b>{Prefix}-{Invoice ID}</b> . Example: <b>WHMCS-Xendit-123</b>
</div>',
            ),
        );
    }

    /**
     * @param int $invoiceId
     * @param bool $retry
     * @return string
     */
    protected function generateExternalId(int $invoiceId, bool $retry = false): string
    {
        $config = $this->getXenditConfig();
        $externalPrefix = !empty($config["xenditExternalPrefix"]) ? $config["xenditExternalPrefix"] : "WHMCS-Xendit";
        return !$retry ? sprintf("%s-%s", $externalPrefix, $invoiceId) : sprintf("%s-%s-%s", $externalPrefix, uniqid(), $invoiceId);
    }

    /**
     * @param int $invoiceId
     * @return mixed
     */
    public function getInvoice(int $invoiceId)
    {
        return Invoice::find($invoiceId);
    }

    /**
     * @return XenditTransaction
     */
    public function storeTransaction(array $params = [])
    {
        return XenditTransaction::create($params);
    }

    /**
     * @param int $invoiceId
     * @return mixed
     */
    public function getTransactionFromInvoiceId(int $invoiceId)
    {
        return XenditTransaction::where("invoiceid", $invoiceId)->get();
    }

    /**
     * @param $transactions
     * @param array $attributes
     * @return bool
     * @throws \Exception
     */
    public function updateTransactions($transactions, array $attributes = []): bool
    {
        try {
            /** @var XenditTransaction $transaction */
            foreach ($transactions as $transaction) {
                foreach ($attributes as $attribute => $value) {
                    $transaction->setAttribute($attribute, $value);
                }
                $transaction->save();
            }
            return true;
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

    /**
     * @param int $invoiceId
     * @return bool
     */
    public function isInvoiceUsedCreditCard(int $invoiceId): bool
    {
        $transaction = XenditTransaction::where("invoiceid", $invoiceId)
            ->where("payment_method", "CREDIT_CARD")
            ->get();
        return $transaction->count() > 0;
    }

    /**
     * @param int $invoiceId
     * @param array $xenditInvoiceData
     * @param bool $success
     * @return bool
     * @throws \Exception
     */
    public function confirmInvoice(int $invoiceId, array $xenditInvoiceData, bool $success = true): bool
    {
        try {
            if (!$success) {
                return false;
            }

            $transactionId = $xenditInvoiceData['id'];
            $paymentAmount = $xenditInvoiceData['paid_amount'];
            $paymentFee = $xenditInvoiceData['fees'][0]["value"];
            $transactionStatus = 'Success';

            // Save credit card token
            if (isset($xenditInvoiceData['credit_card_charge_id']) && isset($xenditInvoiceData['credit_card_token'])) {
                $cardInfo = $this->xenditRequest->getCardInfo($xenditInvoiceData['credit_card_charge_id']);
                $cardExpired = $this->xenditRequest->getCardTokenInfo($xenditInvoiceData['credit_card_token']);

                if (!empty($cardInfo) && !empty($cardExpired)) {
                    $lastDigit = substr($cardInfo["masked_card_number"], -4);
                    invoiceSaveRemoteCard(
                        $invoiceId,
                        $lastDigit,
                        $cardInfo["card_brand"],
                        sprintf("%s/%s", $cardExpired["card_expiration_month"], $cardExpired["card_expiration_year"]),
                        $xenditInvoiceData['credit_card_token']
                    );
                }
            }

            $invoiceId = checkCbInvoiceID($invoiceId, $this->getDomainName());
            checkCbTransID($transactionId);

            addInvoicePayment(
                $invoiceId,
                $transactionId,
                $paymentAmount,
                $paymentFee,
                $this->getDomainName()
            );

            // Save payment method
            $transactions = $this->getTransactionFromInvoiceId($invoiceId);
            if (!empty($transactions)) {
                $this->updateTransactions(
                    $transactions,
                    [
                        "status" => XenditTransaction::STATUS_PAID,
                        "payment_method" => $xenditInvoiceData["payment_method"]
                    ]
                );
            }

            logTransaction($this->getDomainName(), $_POST, $transactionStatus);
            return true;
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }
}
