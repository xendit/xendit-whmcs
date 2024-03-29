<?php

namespace Xendit\Lib;

class CreditCard extends \Xendit\Lib\ActionBase
{
    const CARD_LABEL = [
        'visa' => 'Visa',
        'mastercard' => 'MasterCard'
    ];

    /**
     * @param $expMonth
     * @param $expFullYear
     * @return string
     */
    public function extractExpiredCard($expMonth, $expFullYear): string
    {
        return sprintf("%s%s", $expMonth, substr($expFullYear, -2));
    }

    /**
     * @param string $cartNumber
     * @return string
     */
    public function extractLastFour(string $cartNumber): string
    {
        return substr($cartNumber, -4, 4);
    }

    /**
     * @param string $cardType
     * @return string
     */
    public function extractCardTypeLabel(string $cardType): string
    {
        return self::CARD_LABEL[$cardType] ?? "";
    }

    /**
     * @param array $data
     * @return bool
     */
    public function validateCardInfo(array $data): bool
    {
        if (empty($data['xendit_card_number']) ||
            empty($data['xendit_card_exp_month']) ||
            empty($data['xendit_card_exp_year']) ||
            empty($data['xendit_card_type'])
        ) {
            return false;
        }
        return true;
    }

    /**
     * @param array $data
     * @return array
     */
    public function extractCardData(array $data)
    {
        return [
            "customerId" => $data['customer_id'] ?? '',
            "cardLastFour" => $this->extractLastFour($data['xendit_card_number']),
            "cardExpiryDate" => $this->extractExpiredCard($data['xendit_card_exp_month'], $data['xendit_card_exp_year']),
            "cardType" => $this->extractCardTypeLabel($data['xendit_card_type']),
            "cardToken" => $data['xendit_token'] ?? "",
            "cardDescription" => $data['card_description'] ?? "",
            "invoiceId" => $data['invoice_id'] ?? '',
            "payMethodId" => $data['custom_reference'] ?? ''
        ];
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
        if (empty($invoice)) {
            throw new \Exception("Invoice does not exist");
        }

        $billingDetailObject = $this->extractCustomer($params['clientdetails'], true);
        $payload = [
            "amount" => $this->roundUpTotal($params["amount"]),
            "currency" => $params["currency"],
            "token_id" => $params["gatewayid"],
            "external_id" => $this->generateExternalId($params["invoiceid"], true),
            "store_name" => $params["companyname"],
            "items" => $this->extractItems($invoice),
            "is_recurring" => true,
            "should_charge_multiple_use_token" => true
        ];

        if (!empty($billingDetailObject)) {
            $payload['billing_details'] = $billingDetailObject;
        }
        if (!empty($auth_id)) {
            $payload["authentication_id"] = $auth_id;
        }
        if (!empty($cvn)) {
            $payload["card_cvn"] = $cvn;
        }
        return $payload;
    }

    /**
     * @param string $verificationHash
     * @param array $params
     * @return false|void
     */
    public function compareHash(string $verificationHash, array $params = [])
    {
        $comparisonHash = $this->generateHash(
            implode('|', [
                $params["publicKey"],
                $params["customerId"],
                $params["invoiceId"],
                $params["amount"],
                $params["currencyCode"],
                $params["secretKey"]
            ])
        );

        if ($verificationHash !== $comparisonHash) {
            return false;
        }
    }

    /**
     * @param array $params
     * @param bool $isNew
     * @return bool
     * @throws \Exception
     */
    public function saveCreditCardToken(array $params = [], bool $isNew = true)
    {
        try {
            if ($isNew) {
                createCardPayMethod(
                    $params["customerId"],
                    $params["gatewayModuleName"],
                    $params["cardLastFour"],
                    $params["cardExpiryDate"],
                    $params["cardType"],
                    null, //start date
                    null, //issue number
                    $params["cardToken"],
                    "billing",
                    $params["cardDescription"]
                );
                logTransaction($params["paymentmethod"], $params, 'Create Success');
            } else {
                // Function available in WHMCS 7.9 and later
                updateCardPayMethod(
                    $params["customerId"],
                    $params["payMethodId"],
                    $params["cardExpiryDate"],
                    null, // card start date
                    null, // card issue number
                    $params["cardToken"]
                );

                // Update card detail
                $client = \WHMCS\User\Client::findOrFail($params["customerId"]);
                $payMethod = $client->payMethods()->where("id", $params["payMethodId"])->first();
                if (!$payMethod) {
                    throw new \Exception("PayMethod ID not found");
                }
                $payment = $payMethod->payment;
                if (!$payMethod->isCreditCard()) {
                    throw new \Exception("Invalid PayMethod");
                }
                $payment->setLastFour($params["cardLastFour"]);
                $payment->setCardType($params["cardType"]);
                $payment->validateRequiredValuesForEditPreSave()->save();

                // Save description
                $payMethod->setDescription($params["cardDescription"]);
                $payMethod->save();

                logTransaction($params["paymentmethod"], $params, 'Update Success');
            }

            return true;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param string $str
     * @return false|string
     */
    public function generateHash(string $str)
    {
        return hash('sha512', $str);
    }
}
