<?php

namespace Xendit\Lib;

use \Xendit\Lib\Model\XenditTransaction;

class Recurring extends \Xendit\Lib\ActionBase
{
    const WHMCS_PRODUCTS = ["service", "domain", "addon"];

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
