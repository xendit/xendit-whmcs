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
     * @param int|null $auth_id
     * @param int|null $cvn
     * @return array
     * @throws \Exception
     */
    public function generateCCPaymentRequest(array $params = [], int $auth_id = null, int $cvn = null): array
    {
        $invoice = $this->getInvoice($params["invoiceid"]);
        if (empty($invoice))
            throw new \Exception("Invoice does not exist");

        $payload = [
            "amount" => $params["amount"],
            "currency" => $params["currency"],
            "token_id" => $params["gatewayid"],
            "external_id" => $this->generateExternalId($params["invoiceid"]),
            "store_name" => "WHMCS Testing",
            "items" => $this->extractItems($invoice),
            "customer" => $this->extractCustomerDetail($params),
            "is_recurring" => true,
            "should_charge_multiple_use_token" => true
        ];

        if (!empty($auth_id)) {
            $payload["authentication_id"] = $auth_id;
        }
        if (!empty($cvn)) {
            $payload["card_cvn"] = $cvn;
        }
        return $payload;
    }

    /**
     * @return false|string
     * @throws \Exception
     */
    public function getCardSetting()
    {
        $ccSettings = $this->xenditRequest->getCCSettings();
        $midSettings = $this->xenditRequest->getMIDSettings();
        $ccSettings['supported_card_brands'] = !empty($midSettings['supported_card_brands']) ? $midSettings['supported_card_brands'] : array();
        return $ccSettings;
    }
}
