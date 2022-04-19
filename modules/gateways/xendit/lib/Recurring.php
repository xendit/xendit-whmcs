<?php

namespace Xendit\Lib;

use \Xendit\Lib\Model\XenditTransaction;

class Recurring extends \Xendit\Lib\ActionBase
{
    const WHMCS_PRODUCTS = ["service", "domain", "addon"];

    /**
     * @param int $invoiceId
     * @return mixed
     */
    public function getRecurringBillingInfo(int $invoiceId)
    {
        return getRecurringBillingValues($invoiceId);
    }

    /**
     * @param int $orderId
     * @param int $invoiceId
     * @param array $relationIds
     * @param array $types
     * @return false
     */
    protected function getPreviousTransaction(
        int $orderId,
        int $invoiceId,
        array $relationIds,
        array $types
    ) {
        $xenditTransaction = XenditTransaction::where("orderid", $orderId)
            ->where("invoiceid", "!=", $invoiceId)
            ->whereIn("relid", $relationIds)
            ->whereIn("type", $types)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!empty($xenditTransaction)) {
            return $xenditTransaction;
        }
        return false;
    }

    /**
     * @param int $invoiceId
     * @return false|mixed
     */
    public function getPreviousInvoice(int $invoiceId)
    {
        $invoice = $this->getInvoice($invoiceId);
        if (empty($invoice)) {
            throw \Exception("Invoice does not exists!");
        }

        $orderIds = [];
        $items = [];

        foreach ($invoice->items()->get() as $item) {
            $items[$item->relid] = $item->type;

            foreach (self::WHMCS_PRODUCTS as $product) {
                foreach ($item->$product()->get() as $service) {
                    $orderIds[] = $service->orderid;
                }
            }
        }

        // If invoice does not have order OR invoice created for multi Order then IGNORE
        if (empty($orderIds) || count($orderIds) > 1) {
            return false;
        }

        // Get previous transaction
        $xenditTransaction = $this->getPreviousTransaction(
            $orderIds[0],
            $invoiceId,
            array_keys($items),
            array_values($items)
        );
        if (empty($xenditTransaction)) {
            return false;
        }

        return $this->getInvoice($xenditTransaction->invoiceid);
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
     * @return void
     */
    public function storeTransactions(int $invoiceid)
    {
        $invoice = $this->getInvoice($invoiceid);
        if (empty($invoice)) {
            throw \Exception("Invoice does not exists!");
        }

        $transactions = [];
        foreach ($invoice->items()->get() as $item) {
            // Custom item
            if ($item->type == "") {
                $transactions[] = $this->storeTransaction(
                    [
                        "invoiceid" => $invoiceid,
                        "type" => $item->type,
                        "external_id" => $this->generateExternalId($invoiceid)
                    ]
                );
            } else {
                // Products
                foreach (self::WHMCS_PRODUCTS as $product) {
                    foreach ($item->$product()->get() as $p) {
                        $transactions[] = $this->storeTransaction(
                            [
                                "invoiceid" => $invoiceid,
                                "orderid" => $p->orderid,
                                "relid" => $p->id,
                                "type" => $item->type,
                                "external_id" => $this->generateExternalId($invoiceid)
                            ]
                        );
                    }
                }
            }
        }
        return $transactions;
    }

    /**
     * @param int $invoiceId
     * @return mixed
     */
    public function capture(int $invoiceId)
    {
        return localAPI("CapturePayment", ["invoiceid" => $invoiceId]);
    }
}
