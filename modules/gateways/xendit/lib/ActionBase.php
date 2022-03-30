<?php

namespace Xendit\Lib;

use WHMCS\Database\Capsule;
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
     * @return void
     */
    public function createTable()
    {
        // Create database.
        if (!Capsule::schema()->hasTable('xendit_transactions')) {
            Capsule::schema()->create('xendit_transactions', function ($table) {
                $table->increments('id');
                $table->integer('invoiceid')->unsigned();
                $table->integer('orderid')->unsigned();
                $table->integer('relid')->unsigned();
                $table->string('type', 100);
                $table->string('external_id', 255);
                $table->string('transactionid', 255);
                $table->string('status', 100);
                $table->timestamps();
            });
        }
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
                'Description' => '<div class="alert alert-info top-margin-5 bottom-margin-5"><img src="../modules/gateways/xendit/logo.png" width="70" align="left" style="padding-right:12px;" /> Xendit is a leading payment gateway for Indonesia, the Philippines and Southeast Asia</span></div>',
            ),
            'testMode' => array(
                'FriendlyName' => 'Test Mode',
                'Type' => 'yesno',
                'Description' => 'Enable test mode',
            ),
            'publicKey' => array(
                'FriendlyName' => 'Public Key',
                'Type' => 'password',
                'Size' => '25',
                'Default' => '',
                'Description' => 'Enter secret key here',
            ),
            'secretKey' => array(
                'FriendlyName' => 'Secret Key',
                'Type' => 'password',
                'Size' => '25',
                'Default' => '',
                'Description' => 'Enter secret key here',
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
        return !$retry ? sprintf("WHMCS-%s", $invoiceId) : sprintf("WHMCS-%s-%s", $invoiceId, microtime(true));
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
        return XenditTransaction::where("invoiceid", $invoiceId)
            ->whereNotIn("status", ["EXPIRED"])
            ->get();
    }

    /**
     * @param $transactions
     * @param string $transactionid
     * @param string $status
     * @return bool
     * @throws \Exception
     */
    public function updateTransactions($transactions, string $transactionid = "", string $status = "PAID")
    {
        try{
            foreach ($transactions as $transaction){
                $transaction->setAttribute("status", $status);
                if(!empty($transactionid)){
                    $transaction->setAttribute("transactionid", $transactionid);
                }
                $transaction->save();
            }
            return true;
        }catch (\Exception $exception){
            throw new \Exception($exception->getMessage());
        }
    }

    /**
     * @param int $invoiceId
     * @param array $xenditInvoiceData
     * @param bool $success
     * @return void
     */
    public function confirmInvoice(int $invoiceId, array $xenditInvoiceData, bool $success)
    {
        $transactionId = $xenditInvoiceData['id'];
        $paymentAmount = $xenditInvoiceData['paid_amount'];
        $paymentFee = $xenditInvoiceData['fees_paid_amount'];
        $transactionStatus = $success ? 'Success' : 'Failure';

        $invoiceId = checkCbInvoiceID($invoiceId, $this->getDomainName());
        checkCbTransID($transactionId);

        if(isset($xenditInvoiceData['credit_card_charge_id']) && isset($xenditInvoiceData['credit_card_token'])){
            $cardInfo = $this->xenditRequest->getCardInfo($xenditInvoiceData['credit_card_charge_id']);
            $cardExpired = $this->xenditRequest->getCardTokenInfo($xenditInvoiceData['credit_card_token']);
            if(!empty($cardInfo) && !empty($cardExpired)){
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

        addInvoicePayment(
            $invoiceId,
            $transactionId,
            $paymentAmount,
            $paymentFee,
            $this->getDomainName()
        );

        logTransaction($this->getDomainName(), $_POST, $transactionStatus);
    }
}
