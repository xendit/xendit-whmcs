<?php
namespace Xendit\Lib;

use Xendit\Lib\Model\XenditTransaction;

class PaymentLink extends ActionBase
{
    /** @var string $callbackUrl */
    protected $callbackUrl = 'modules/gateways/callback/xendit.php';

    /**
     * @param array $params
     * @param bool $retry
     * @return array
     */
    protected function generateInvoicePayload(array $params, bool $retry = false): array
    {
        $invoice = $this->getInvoice($params["invoiceid"]);
        $customerObject = $this->extractCustomer($params['clientdetails']);
        $whmsInvoiceUrl = $this->invoiceUrl($params['invoiceid'], $params['systemurl']);

        $payload = [
            'external_id' => $this->generateExternalId($params["invoiceid"], $retry),
            'payer_email' => $params['clientdetails']['email'],
            'description' => $params["description"],
            'currency' => $params['currency'],
            'items' => $this->extractItems($invoice),
            'amount' => $this->roundUpTotal($params['amount'] + (float)$params['paymentfee']),
            'client_type' => 'INTEGRATION',
            'platform_callback_url' => $params["systemurl"] . $this->callbackUrl,
            'success_redirect_url' => $whmsInvoiceUrl,
            'failure_redirect_url' => $whmsInvoiceUrl,
            'should_charge_multiple_use_token' => true
        ];

        if (!empty($customerObject)) {
            $payload['customer'] = $customerObject;
        }

        // Only add the payment fee if it's > 0
        if ($params['paymentfee'] > 0) {
            $payload["fees"] = array(['type' => 'Payment Fee', 'value' => (float)$params['paymentfee']]);
        }

        return $payload;
    }

    /**
     * @param $invoiceId
     * @param string $systemurl
     * @return string
     */
    protected function invoiceUrl($invoiceId, string $systemurl): string
    {
        return $systemurl . 'viewinvoice.php?id=' . $invoiceId;
    }

    /**
     * Check if Referer URL from card
     *
     * @return bool
     */
    protected function isRefererUrlFromCart(): bool
    {
        if (!$this->isViewInvoicePage() || empty($_SERVER) || empty($_SERVER["HTTP_REFERER"])) {
            return false;
        }

        $uri = parse_url($_SERVER['HTTP_REFERER']);
        return in_array("cart.php", explode("/", $uri["path"]));
    }

    /**
     * @return bool
     */
    protected function isViewInvoicePage(): bool
    {
        if (empty($_SERVER) || empty($_SERVER["SCRIPT_NAME"])) {
            return false;
        }

        return in_array("viewinvoice.php", explode("/", $_SERVER["SCRIPT_NAME"]));
    }

    /**
     * @param string $systemUrl
     * @return string
     */
    public function getCallbackUrl(string $systemUrl): string
    {
        return sprintf(
            '%s/modules/gateways/callback/%s.php',
            $systemUrl,
            $this->getDomainName()
        );
    }

    /**
     * @param array $params
     * @param string $invoiceUrl
     * @return string
     */
    protected function generateFormParam(array $params, string $invoiceUrl): string
    {
        if ($this->isRefererUrlFromCart()) {
            return $this->redirectUrl($invoiceUrl);
        }

        $htmlOutput = sprintf(
            '<img src="%s" width="120px" />',
            $params['systemurl'] . '/modules/gateways/' . $this->getDomainName() . '/logo.png'
        );

        $htmlOutput .= sprintf('<div><a href="%s" class="btn btn-success" title="Pay via Xendit">Pay via Xendit</a></div>', $invoiceUrl);

        return $htmlOutput;
    }

    /**
     * @param array $params
     * @param $transactions
     * @param bool $isForced
     * @return false|string
     * @throws \Exception
     */
    protected function createXenditInvoice(array $params, $transactions, bool $isForced = false)
    {
        try {
            $payload = $this->generateInvoicePayload($params, $isForced);
            $xenditInvoice = $this->xenditRequest->createInvoice($payload);
            $this->updateTransactions(
                $transactions,
                [
                    'transactionid' => $xenditInvoice["id"],
                    'status' => XenditTransaction::STATUS_PENDING,
                    'external_id' => $payload["external_id"]
                ]
            );
            return $xenditInvoice;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param array $params
     * @param array $xenditInvoice
     * @param $transactions
     * @return string|null
     * @throws \Exception
     */
    protected function updateInvoiceStatus(array $params, array $xenditInvoice, $transactions)
    {
        if (empty($xenditInvoice)) {
            throw new \Exception('Xendit invoice not found.');
        }

        try {
            $xenditInvoiceStatus = $xenditInvoice['status'] ?? '';
            switch ($xenditInvoiceStatus) {
                case 'PAID':
                case 'SETTLED':
                    $webhook = new \Xendit\Lib\Webhook();
                    $confirmed = $webhook->confirmInvoice($params['invoiceid'], $xenditInvoice);
                    if (!$confirmed) {
                        throw new \Exception('Cannot update invoice status.');
                    }
                    return $this->redirectUrl($xenditInvoice['success_redirect_url']);

                case 'EXPIRED':
                    $this->updateTransactions($transactions, ['status' => XenditTransaction::STATUS_EXPIRED]);
                    return $this->generatePaymentLink($params, true);

                default:
                    return $this->generateFormParam($params, $xenditInvoice['invoice_url']);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param array $params
     * @param bool $force
     * @return string
     * @throws \Exception
     */
    public function generatePaymentLink(array $params, bool $force = false): string
    {
        try {
            if (!$this->isViewInvoicePage()) {
                return false;
            }

            // Get transactions by WHMCS invoice
            $transactions = $this->getTransactionFromInvoiceId($params["invoiceid"]);

            // Create a new Xendit invoice in case the previous invoice is EXPIRED
            if ($force) {
                $xenditInvoice = $this->createXenditInvoice($params, $transactions, true);
                return $this->generateFormParam($params, $xenditInvoice['invoice_url']);
            }

            // Get Xendit Invoice by transaction (Xendit invoice_id)
            $xenditInvoice = false;
            if ($transactions->count() && !empty($transactions[0]->transactionid)) {
                $xenditInvoice = $this->xenditRequest->getInvoiceById($transactions[0]->transactionid);
            }

            /*
             * Check if Xendit invoice exists then update WHMCS invoice status
             */
            if (!empty($xenditInvoice)) {
                return $this->updateInvoiceStatus($params, $xenditInvoice, $transactions);
            }

            // If Xendit invoice does not exist, create a new Xendit invoice
            $xenditInvoice = $this->createXenditInvoice($params, $transactions);
            return $this->generateFormParam($params, $xenditInvoice['invoice_url']);
        } catch (\Exception $e) {
            /*
             * If currency is error
             * Show the error with currency in message
             */
            if (strpos($e->getMessage(), 'UNSUPPORTED_CURRENCY') !== false) {
                throw new \Exception(str_replace("{{currency}}", $params['currency'], $e->getMessage()));
            }

            throw new \Exception($e->getMessage());
        }
    }
}
