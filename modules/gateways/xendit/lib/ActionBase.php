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
    const ALLOW_CURRENCIES = ['IDR', 'PHP', 'USD'];
    const WHMCS_MIN_VERSION_SUPPORT = 7.9;

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
        if (!$this->validateCompatibilityVersion()) {
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
                    'Description' => '<div class="alert alert-danger top-margin-5 bottom-margin-5">
Your WHMCS version not compatibility with Xendit Payment Gateway. <a href="https://marketplace.whmcs.com/product/6411-xendit-payment-gateway" target="_blank">See more</a>
</div>',
                ),
            );
        }

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
        return !$retry ? sprintf(
            "%s-%s",
            $externalPrefix,
            $invoiceId
        )
            : sprintf(
                "%s-%s-%s",
                $externalPrefix,
                uniqid(),
                $invoiceId
            );
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
     * @param $xenditTotal
     * @param $whmcsTotal
     * @return float
     */
    public function extractPaidAmount($xenditTotal, $whmcsTotal): float
    {
        $decimalAmount = $xenditTotal - $whmcsTotal ;
        return $decimalAmount > 0 && $decimalAmount < 1 ? (float)$whmcsTotal : (float)$xenditTotal;
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

            /*
             * Verify the invoice need to update is correct
             * Avoid update wrong WHMCS invoice
             */
            if ($invoiceId != $this->getInvoiceIdFromExternalId($xenditInvoiceData['external_id'])) {
                throw new \Exception('Invoice id is incorrect!');
            }

            // Load WHMCS invoice
            $invoice = $this->getInvoice($invoiceId);

            $transactionId = $xenditInvoiceData['id'];
            $paymentAmount = $this->extractPaidAmount($xenditInvoiceData['paid_amount'], $invoice->total);
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

    /**
     * @param int $invoiceId
     * @return mixed
     */
    public function getRecurringBillingInfo(int $invoiceId)
    {
        return getRecurringBillingValues($invoiceId);
    }

    /**
     * @param int $invoiceId
     * @return bool
     */
    public function isRecurring(int $invoiceId): bool
    {
        $recurringData = $this->getRecurringBillingInfo($invoiceId);
        if (!isset($recurringData["firstpaymentamount"]) && !isset($recurringData['firstcycleperiod'])) {
            return true;
        }
        return false;
    }

    /**
     * @param float $total
     * @return float
     */
    public function roundUpTotal(float $total): float
    {
        return ceil($total);
    }

    /***
     * @param string|null $currentVersion
     * @return bool
     */
    public function validateCompatibilityVersion(string $currentVersion = null): bool
    {
        global $CONFIG;
        $version = !empty($currentVersion) ? $currentVersion : $CONFIG['Version'];
        return version_compare($version, self::WHMCS_MIN_VERSION_SUPPORT, ">=");
    }

    /**
     * @param array $data
     * @return void
     */
    public function renderJson(array $data)
    {
        echo json_encode($data);
        exit;
    }

    /**
     * @param string $message
     * @return string
     */
    public function errorMessage(string $message = ''): string
    {
        if (strpos($message, 'INVALID_API_KEY') !== false) {
            $message = 'The API key is invalid.';
        }
        return sprintf('<p class="alert alert-danger">%s</p>', $message);
    }

    /**
     * @param string $header
     * @param string $content
     * @return void
     */
    protected function sendHeader(string $header, string $content)
    {
        if (!headers_sent()) {
            header(sprintf('%s: %s', $header, $content));
        }
    }

    /**
     * @param string $url
     * @return void
     */
    public function redirectUrl(string $url)
    {
        $this->sendHeader("Location", $url);
        exit();
    }

    /**
     * @param \WHMCS\Billing\Invoice $invoice
     * @return array
     */
    public function extractItems(\WHMCS\Billing\Invoice $invoice): array
    {
        $items = array();
        foreach ($invoice->items()->get() as $item) {
            if ($item->amount < 0) {
                continue;
            }

            $items[] = [
                'quantity' => 1,
                'name' => $item->description,
                'price' => (float)$item->amount,
            ];
        }
        return $items;
    }

    /**
     * @param array $params
     * @param bool $isCreditCard
     * @return array
     */
    public function extractCustomer(array $params, bool $isCreditCard = false): array
    {
        $customerObject = [];

        if (!empty($params['firstname']) || !empty($params['lastname'])) {
            $customerObject['given_names'] = trim(sprintf("%s %s", $params['firstname'], $params['lastname']));
        }
        if (!empty($params['phonenumber'])) {
            $customerObject['mobile_number'] = $params['phonenumber'];
        }

        $customerAddressObject = $this->extractCustomerAddress($params);
        if (!empty($customerAddressObject)) {
            if ($isCreditCard) {
                $customerObject['address'] = $customerAddressObject;
            } else {
                $customerObject['addresses'][] = $customerAddressObject;
            }
        }

        return $customerObject;
    }

    /**
     * extract customer address
     *
     * @param array $params
     * @return array
     */
    public function extractCustomerAddress(array $params): array
    {
        $customerAddressObject = [];
        if (empty($params)) {
            return $customerAddressObject;
        }

        // Map Xendit address key with WHMCS address key
        $customerAddressObject = [
            'country' => $params['country'],
            'street_line1' => $params['address1'],
            'street_line2' => $params['address2'],
            'city' => $params['city'],
            'province_state' => $params['state'],
            'postal_code' => $params['postcode']
        ];
        foreach ($customerAddressObject as $key => $value) {
            if (empty($value)) {
                unset($customerAddressObject[$key]);
            }
        }
        return $customerAddressObject;
    }
}
