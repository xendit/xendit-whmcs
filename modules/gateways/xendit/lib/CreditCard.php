<?php

namespace Xendit\lib;

class CreditCard extends \Xendit\Lib\ActionBase
{
    /**
     * @param array $params
     * @return array[]
     */
    public function extractCustomerDetail(array $params = [])
    {
        $customerDetails = [
            'first_name' => $params['clientdetails']['firstname'],
            'last_name' => $params['clientdetails']['lastname'],
            'email' => $params['clientdetails']['email'],
            'phone_number' => $params['clientdetails']['phonenumber'],
            'address_city' => $params['clientdetails']['city'],
            'address_postal_code' => $params['clientdetails']['postcode'],
            'address_line_1' => $params['clientdetails']['address1'],
            'address_line_2' => $params['clientdetails']['address2'],
            'address_state' => $params['clientdetails']['state'],
            'address_country' => $params['clientdetails']['country'],
        ];
        return [
            "billing_details" => $customerDetails,
            "shipping_details" => $customerDetails
        ];
    }

    /**
     * @param $invoice
     * @return array
     */
    public function extractItems($invoice): array
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
     * @throws \Exception
     */
    public function generateCCPaymentRequest(array $params = [])
    {
        $invoice = $this->getInvoice($params["invoiceid"]);
        if(empty($invoice))
            throw new \Exception("Invoice does not exist");

        return [
            "amount" => $params["amount"],
            "currency" => "IDR",//$params["currency"],
            "token_id" => $params["gatewayid"],
            "external_id" => $this->generateExternalId($params["invoiceid"]),
            "store_name" => "WHMCS Testing",
            "items" => $this->extractItems($invoice),
            "customer" => $this->extractCustomerDetail($params),
            "is_recurring" => true,
            "should_charge_multiple_use_token" => true
        ];
    }
}
