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
     * @param string $version
     * @return string[]
     */
    protected function defaultHeader(string $version = ''): array
    {
        $gatewayParams = $this->getModuleConfig();
        $default_header = array(
            'content-type: application/json',
            'x-plugin-name: WHMCS',
            'x-plugin-version: 1.0.1'
        );
        $default_header[] = "authorization-type: ApiKey";
        $default_header[] = 'Authorization: Basic '.base64_encode(
                ($gatewayParams["xenditTestMode"] == "on" ? $gatewayParams["xenditTestSecretKey"] : $gatewayParams["xenditSecretKey"]).':'
            );
        if (!empty($version)) {
            $default_header[] = 'x-api-version: ' . $version;
        }
        if ($this->for_user_id) {
            $default_header[] = 'for-user-id: ' . $this->for_user_id;
        }

        return $default_header;
    }

    /**
     * @param string $body
     * @return false|string
     * @throws \Exception
     */
    protected function processResponse(string $body)
    {
        $response = json_decode($body, true);
        if(isset($response["error_code"]) && !empty($response["error_code"])){
            throw new \Exception(sprintf("Error: %s - Code %s", $response["message"], $response["error_code"]));
        }
        return $response;
    }

    /**
     * @param string $invoice_id
     * @return false|string
     * @throws \Exception
     */
    public function getInvoiceById(string $invoice_id)
    {
        try{
            $response = $this->request(
                "GET",
                '/payment/xendit/invoice/' . $invoice_id, [
                'headers' => $this->defaultHeader()
            ]);
            return $this->processResponse($response);
        }catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param array $param
     * @return false|string
     * @throws \Exception
     */
    public function createInvoice(array $param = [])
    {
        try{
            $response = $this->request("POST", '/payment/xendit/invoice', [
                'headers' => $this->defaultHeader(),
                'body' => json_encode($param)
            ]);
            return $this->processResponse($response);
        }catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param array $param
     * @return false|string
     * @throws \Exception
     */
    public function createHost3DS(array $param = [])
    {
        try {
            $response = $this->request("POST", '/payment/xendit/credit-card/hosted-3ds', [
                'headers' => $this->defaultHeader('2020-02-14'),
                'body' => json_encode($param)
            ]);
            return $this->processResponse($response);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param $payload
     * @return false|string
     * @throws \Exception
     */
    public function createCharge($payload)
    {
        try {
            $response = $this->request("POST", '/payment/xendit/credit-card/charges', [
                'headers' => $this->defaultHeader(),
                'body' => json_encode($payload)
            ]);
            return $this->processResponse($response);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param string $card_charge_id
     * @return false|string
     * @throws \Exception
     */
    public function getCardInfo(string $card_charge_id)
    {
        try {
            $response = $this->request("GET", '/payment/xendit/credit-card/charges/' . $card_charge_id, [
                'headers' => $this->defaultHeader()
            ]);
            return $this->processResponse($response);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param string $card_token
     * @return false|string
     * @throws \Exception
     */
    public function getCardTokenInfo(string $card_token)
    {
        try {
            $response = $this->request("GET", '/payment/xendit/credit-card/token/' . $card_token, [
                'headers' => $this->defaultHeader()
            ]);
            return $this->processResponse($response);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
