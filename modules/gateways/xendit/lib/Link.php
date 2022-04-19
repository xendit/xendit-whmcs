<?php

namespace Xendit\Lib;

use Xendit\Lib\Model\XenditTransaction;

class Link extends ActionBase
{
    /** @var string $callbackUrl */
    protected $callbackUrl = 'modules/gateways/callback/xendit.php';

    /**
     * @param \WHMCS\Billing\Invoice $invoice
     * @return array
     */
    protected function extractItems(\WHMCS\Billing\Invoice $invoice): array
    {
        $items = array();
        foreach ($invoice->items()->get() as $item) {
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
     * @return array
     */
    protected function extractCustomer(array $params)
    {
        return [
            'given_names' => $params['clientdetails']['firstname'] . ' ' . $params['clientdetails']['lastname'],
            'mobile_number' => $params['clientdetails']['phonenumber']
        ];
    }

    /**
     * @param array $params
     * @param bool $retry
     * @return array
     */
    protected function generateInvoicePayload(array $params, bool $retry = false): array
    {
        $invoice = $this->getInvoice($params["invoiceid"]);

        return [
            'external_id' => $this->generateExternalId($params["invoiceid"], $retry),
            'payer_email' => $params['clientdetails']['email'],
            'description' => $params["description"],
            'items' => $this->extractItems($invoice),
            'fees' => array(['type' => 'Payment Fee', 'value' => (float)$params['paymentfee']]),
            'amount' => $params['amount'] + (float)$params['paymentfee'],
            'client_type' => 'INTEGRATION',
            'platform_callback_url' => $params["systemurl"] . $this->callbackUrl,
            'success_redirect_url' => $this->invoiceUrl($params['invoiceid'], $params['systemurl']),
            'failure_redirect_url' => $this->invoiceUrl($params['invoiceid'], $params['systemurl']),
            'should_charge_multiple_use_token' => true,
            'customer' => $this->extractCustomer($params)
        ];
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
        if (isset($_SERVER["HTTP_REFERER"]) && ltrim($_SERVER["SCRIPT_NAME"], "/") == "viewinvoice.php") {
            $uri = parse_url($_SERVER['HTTP_REFERER']);
            if (ltrim($uri["path"], "/") == "cart.php") {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array $params
     * @param string $invoiceUrl
     * @return string
     */
    protected function generateFormParam(array $params, string $invoiceUrl = "")
    {
        if ($this->isRefererUrlFromCart()) {
            header("Location: " . $invoiceUrl);
            exit();
        }

        $postfields = array();
        $postfields['invoice_id'] = $params['invoiceid'];
        $postfields['description'] = $params["description"];
        $postfields['amount'] = $params['amount'];
        $postfields['currency'] = $params['currency'];
        $postfields['first_name'] = $params['clientdetails']['firstname'];
        $postfields['last_name'] = $params['clientdetails']['lastname'];
        $postfields['email'] = $params['clientdetails']['email'];
        $postfields['address1'] = $params['clientdetails']['address1'];
        $postfields['address2'] = $params['clientdetails']['address2'];
        $postfields['city'] = $params['clientdetails']['city'];
        $postfields['state'] = $params['clientdetails']['state'];
        $postfields['postcode'] = $params['clientdetails']['postcode'];
        $postfields['country'] = $params['clientdetails']['country'];
        $postfields['phone'] = $params['clientdetails']['phonenumber'];
        $postfields['callback_url'] = $params['systemurl'] . '/modules/gateways/callback/' . $this->getDomainName() . '.php';
        $postfields['return_url'] = $params['returnurl'];

        $htmlOutput = '<form id="frm-xendit" method="post" action="' . $invoiceUrl . '">';
        foreach ($postfields as $k => $v) {
            $htmlOutput .= '<input type="hidden" name="' . $k . '" value="' . urlencode($v) . '" />';
        }

        $htmlOutput .= sprintf(
            '<img src="%s" width="120px" />',
            $params['systemurl'] . '/modules/gateways/' . $this->getDomainName() . '/logo.png'
        );

        $htmlOutput .= '<div><input class="btn btn-success" type="submit" value="Pay via Xendit" /></div>';
        $htmlOutput .= '</form>';

        return $htmlOutput;
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
            // Get transaction
            $transactions = $this->getTransactionFromInvoiceId($params["invoiceid"]);

            // If force create new invoice
            if ($force) {
                $payload = $this->generateInvoicePayload($params, true);
                $createInvoice = $this->xenditRequest->createInvoice($payload);
                $url = $createInvoice['invoice_url'];

                $this->updateTransactions(
                    $transactions,
                    [
                        'transactionid' => $createInvoice["id"],
                        'status' => XenditTransaction::STATUS_PENDING,
                        'external_id' => $payload["external_id"]
                    ]
                );
                return $this->generateFormParam($params, $url);
            }

            // Get Xendit Invoice by transaction (Xendit invoice_id)
            $xenditInvoice = false;
            if ($transactions->count() && !empty($transactions[0]->transactionid)) {
                $xenditInvoice = $this->xenditRequest->getInvoiceById($transactions[0]->transactionid);
            }

            // Check xendit invoice status
            if (!empty($xenditInvoice)) {
                if ($xenditInvoice['status'] == "EXPIRED") {
                    $this->updateTransactions($transactions, ['status' => XenditTransaction::STATUS_EXPIRED]);
                    return $this->generatePaymentLink($params, true);
                } else {
                    $url = $xenditInvoice['invoice_url'];
                }
            } else {
                $createInvoice = $this->xenditRequest->createInvoice(
                    $this->generateInvoicePayload($params)
                );
                $url = $createInvoice['invoice_url'];
                $this->updateTransactions(
                    $transactions,
                    [
                        'transactionid' => $createInvoice["id"],
                        'status' => XenditTransaction::STATUS_PENDING
                    ]
                );
            }
            return $this->generateFormParam($params, $url);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
