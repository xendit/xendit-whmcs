<?php
namespace Xendit\Lib;

class XenditRequest
{
    protected $tpi_server_domain  = "https://tpi.xendit.co";
    protected $for_user_id;

    /**
     * @return mixed
     */
    protected function getModuleConfig()
    {
        return getGatewayVariables('xendit');
    }

    /**
     * @param int $invoiceId
     * @return mixed
     */
    protected function getInvoice(int $invoiceId)
    {
        return localAPI(/**command*/'GetInvoice', /**postData*/['invoiceid' => $invoiceId]);
    }


    /**
     * @param string $method
     * @param string $endpoint
     * @param array $param
     * @return bool|string
     */
    protected function request(string $method = 'GET', string $endpoint, array $param = [])
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => sprintf("%s/%s", $this->tpi_server_domain, $endpoint),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $param["body"] ?? "",
            CURLOPT_HTTPHEADER => $param["headers"]
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;
    }

    /**
     * @param bool $usePublicKey
     * @param string $version
     * @return string[]
     */
    protected function defaultHeader(bool $usePublicKey = false, string $version = ''): array
    {
        $gatewayParams = $this->getModuleConfig();
        $default_header = array(
            'content-type: application/json',
            'x-plugin-name: WHMCS',
            'x-plugin-version: 1.0.1'
        );
        if ($usePublicKey) { // prioritize use of public key than oauth data for CC requests
            $default_header[] = 'Authorization: Basic '.base64_encode($gatewayParams["publicKey"].':');
        }
        else {
            $default_header[] = "authorization-type: ApiKey";
            $default_header[] = 'Authorization: Basic '.base64_encode($gatewayParams["secretKey"].':');
        }
        if (!empty($version)) {
            $default_header[] = 'x-api-version: ' . $version;
        }
        if ($this->for_user_id) {
            $default_header[] = 'for-user-id: ' . $this->for_user_id;
        }
        return $default_header;
    }

    /**
     * @param string $invoice_id
     * @return mixed
     */
    public function getInvoiceById(string $invoice_id)
    {
        try{
            $response = $this->request(
                "GET",
                '/payment/xendit/invoice/' . $invoice_id, [
                'headers' => $this->defaultHeader()
            ]);
            return json_decode($response, true);
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param array $param
     * @return mixed
     */
    public function createInvoice(array $param = [])
    {
        try{
            $response = $this->request("POST", '/payment/xendit/invoice', [
                'headers' => $this->defaultHeader(),
                'body' => json_encode($param)
            ]);
            return json_decode($response, true);
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param array $param
     * @return false|mixed
     */
    public function createHost3DS(array $param = [])
    {
        try {
            $response = $this->request("POST", '/payment/xendit/credit-card/hosted-3ds', [
                'headers' => $this->defaultHeader(true, '2020-02-14'),
                'body' => json_encode($param)
            ]);
            return json_decode($response, true);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
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
            "currency" => $params["currency"],
            "token_id" => $params["gatewayid"],
            "external_id" => sprintf("WHMCS - %s", $params["invoiceid"] .'-'. uniqid()),
            "store_name" => "WHMCS Testing",
            "items" => $this->extractItems($invoice),
            "customer" => $this->extractCustomerDetail($params),
            "is_recurring" => true,
            "should_charge_multiple_use_token" => true
        ];
    }

    /**
     * @param $payload
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createCharge($payload)
    {
        $default_header = $this->defaultHeader();
        try {
            $response = $this->request("POST", 'payment/xendit/credit-card/charges', [
                'headers' => $default_header,
                'body' => json_encode($payload)
            ]);
            return json_decode($response, true);

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param string $card_charge_id
     * @return mixed
     * @throws Exception
     */
    public function getCardInfo(string $card_charge_id)
    {
        $default_header = $this->defaultHeader();
        try {
            $response = $this->request("GET", 'payment/xendit/credit-card/charges/' . $card_charge_id, [
                'headers' => $default_header
            ]);
            return json_decode($response, true);

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param string $card_token
     * @return mixed
     * @throws Exception
     */
    public function getCardTokenInfo(string $card_token)
    {
        $default_header = $this->defaultHeader();
        try {
            $response = $this->request("GET", 'payment/xendit/credit-card/token/' . $card_token, [
                'headers' => $default_header
            ]);
            return json_decode($response, true);

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param array $param
     * @return array[]
     */
    public function extractCustomerDetail(array $param = [])
    {
        $customerDetails = [
            'first_name' => $param['clientdetails']['firstname'],
            'last_name' => $param['clientdetails']['lastname'],
            'email' => $param['clientdetails']['email'],
            'phone_number' => $param['clientdetails']['phonenumber'],
            'address_city' => $param['clientdetails']['city'],
            'address_postal_code' => $param['clientdetails']['postcode'],
            'address_line_1' => $param['clientdetails']['address1'],
            'address_line_2' => $param['clientdetails']['address2'],
            'address_state' => $param['clientdetails']['state'],
            'address_country' => $param['clientdetails']['country'],
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
        foreach ($invoice['items']['item'] as $item) {
            $item_price = (float) $item['amount'];
            $items[] = [
                'quantity' => 1,
                'name' => $item['description'],
                'price' => $item_price,
            ];
        }
        return $items;
    }
}
