<?php

namespace Xendit\Lib;

class Link extends ActionBase
{
    /**
     * @param \WHMCS\Billing\Invoice $invoice
     * @return array
     */
    protected function extractItems(\WHMCS\Billing\Invoice $invoice): array
    {
        $items = array();
        foreach ($invoice->items()->get() as $item){
            $items[] = [
                'quantity' => 1,
                'name' => $item->description,
                'price' => (float) $item->amount,
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
     * @param array $params
     * @param string $invoiceUrl
     * @return string
     */
    protected function generateFormParam(array $params, string $invoiceUrl = "")
    {
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

        $htmlOutput = '<form method="post" action="' . $invoiceUrl . '">';
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
            if($force){
                $createInvoice = $this->xenditRequest->createInvoice(
                    $this->generateInvoicePayload($params, true)
                );
                $url = $createInvoice['invoice_url'];

                $this->updateTransactions(
                    $transactions,
                    $createInvoice["id"],
                    "PENDING"
                );
                return $this->generateFormParam($params, $url);
            }

            // Get Xendit Invoice by transactionid (Xendit invoice_id)
            $xenditInvoice = false;
            if(!empty($transactions) && !empty($transactions[0]->transactionid)){
                $xenditInvoice = $this->xenditRequest->getInvoiceById($transactions[0]->transactionid);
            }

            // Check xendit invoice status
            if(!empty($xenditInvoice)){
                if($xenditInvoice['status'] == "PAID" || $xenditInvoice['status'] == "SETTLED"){
                    $this->updateTransactions($transactions);
                    $this->confirmInvoice(
                        $params["invoiceid"],
                        $xenditInvoice
                    );

                    // Redirect to success page
                    header('Location:' . sprintf("%sviewinvoice.php?id=%s", $params['systemurl'], $params['invoiceid']));
                    exit;
                }elseif($xenditInvoice['status'] == "EXPIRED"){
                    $this->updateTransactions($transactions, "", "EXPIRED");
                    return $this->generatePaymentLink($params, true);
                }else{
                    $url = $xenditInvoice['invoice_url'];
                }
            }else{
                $createInvoice = $this->xenditRequest->createInvoice(
                    $this->generateInvoicePayload($params)
                );
                $url = $createInvoice['invoice_url'];
                $this->updateTransactions(
                    $transactions,
                    $createInvoice["id"],
                    "PENDING"
                );
            }

            return $this->generateFormParam($params, $url);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
