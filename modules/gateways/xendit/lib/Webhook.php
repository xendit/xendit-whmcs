<?php
namespace Xendit\Lib;

use Xendit\Lib\Model\XenditTransaction;

class Webhook extends ActionBase
{
    /**
     * @param string $external_id
     * @return false|string
     */
    public function getInvoiceIdFromExternalId(string $external_id)
    {
        $externalArray = array_map("trim", explode("-", $external_id));
        return end($externalArray);
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
}
