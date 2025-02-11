<?php

namespace Xendit\Lib;

if (!defined('XENDIT_PAYMENT_GATEWAY_VERSION')) {
    define('XENDIT_PAYMENT_GATEWAY_VERSION', '1.3.2'); // or any default value
}

class XenditRequest
{
    protected $tpi_server_domain = "https://tpi-gateway.xendit.co";

    /**
     * @return mixed
     */
    protected function getModuleConfig()
    {
        return getGatewayVariables('xendit');
    }

    /**
     * @return bool
     */
    public function isTestMode(): bool
    {
        $gatewayParams = $this->getModuleConfig();
        return $gatewayParams['xenditTestMode'] == "on";
    }

    /**
     * @return mixed
     */
    public function getPublicKey()
    {
        $gatewayParams = $this->getModuleConfig();
        return $this->isTestMode() ? $gatewayParams['xenditTestPublicKey'] : $gatewayParams['xenditPublicKey'];
    }

    /**
     * @return mixed
     */
    public function getSecretKey()
    {
        $gatewayParams = $this->getModuleConfig();
        return $this->isTestMode() ? $gatewayParams['xenditTestSecretKey'] : $gatewayParams['xenditSecretKey'];
    }

    /**
     * @param string $endpoint
     * @param array $param
     * @param string $method
     * @return bool|string
     */
    protected function request(string $endpoint, array $param = [], string $method = 'GET')
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => sprintf("%s%s", $this->tpi_server_domain, $endpoint),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $param["body"] ?? "",
            CURLOPT_HTTPHEADER => $param["headers"]
        ));

        // Handle with retry mechanism
        $retry = 3;
        $response = false;

        while ($retry > 0) {
            $response = curl_exec($curl);

            // Check for cURL errors
            if (curl_errno($curl)) {
                $error_msg = curl_error($curl);
                if (strpos($error_msg, 'Could not resolve host') !== false) {
                    $retry--;
                    sleep(1 * $retry); // Wait for 1 second before retrying
                    continue;
                }
            }
            break;
        }

        curl_close($curl);
        return $response;
    }

    /**
     * @param string $version
     * @return string[]
     */
    protected function defaultHeader(string $version = ''): array
    {
        $default_header = array(
            'content-type: application/json',
            'x-plugin-name: WHMCS',
            'x-plugin-version: ' . XENDIT_PAYMENT_GATEWAY_VERSION
        );
        $default_header[] = 'Authorization: Basic ' . base64_encode(sprintf("%s:", $this->getSecretKey()));
        if (!empty($version)) {
            $default_header[] = 'x-api-version: ' . $version;
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
        if (isset($response["error_code"]) && !empty($response["error_code"])) {
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
        try {
            $response = $this->request(
                '/tpi/payment/xendit/invoice/' . $invoice_id,
                [
                    'headers' => $this->defaultHeader()
                ]
            );
            return $this->processResponse($response);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param string $chargeId
     * @param array $payload
     * @return object json
     * @throws \Exception
     */
    public function createRefund(string $chargeId, array $payload = [])
    {
        try {
            $response = $this->request(
                '/tpi/payment/xendit/credit-card/charges/' . $chargeId . '/refund',
                [
                    'headers' => $this->defaultHeader(),
                    'body' => json_encode($payload)
                ],
                "POST"
            );
            return $this->processResponse($response);
        } catch (\Exception $e) {
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
        try {
            $response = $this->request(
                '/tpi/payment/xendit/invoice',
                [
                    'headers' => $this->defaultHeader(),
                    'body' => json_encode($param)
                ],
                "POST"
            );
            return $this->processResponse($response);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param array $param
     * @return false|string
     * @throws \Exception
     */
    public function expire(string $invoice_id)
    {
        try {
            $response = $this->request(
                '/tpi/payment/xendit/invoice/' . $invoice_id . '/expire',
                [
                    'headers' => $this->defaultHeader()
                ],
                "POST"
            );
            return $this->processResponse($response);
        } catch (\Exception $e) {
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
            $response = $this->request(
                '/tpi/payment/xendit/credit-card/hosted-3ds',
                [
                    'headers' => $this->defaultHeader('2020-02-14'),
                    'body' => json_encode($param)
                ],
                "POST"
            );
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
            $response = $this->request(
                '/tpi/payment/xendit/credit-card/charges',
                [
                    'headers' => $this->defaultHeader(),
                    'body' => json_encode($payload)
                ],
                "POST"
            );
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
            $response = $this->request('/tpi/payment/xendit/credit-card/charges/' . $card_charge_id, [
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
            $response = $this->request('/tpi/payment/xendit/credit-card/token/' . $card_token, [
                'headers' => $this->defaultHeader()
            ]);
            return $this->processResponse($response);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Get MID settings
     *
     * @return false|string
     * @throws \Exception
     */
    public function getCardSettings()
    {
        try {
            $response = $this->request('/tpi/payment/xendit/settings/credit-card', [
                'headers' => $this->defaultHeader()
            ]);
            return $this->processResponse($response);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param array $payload
     * @return bool
     */
    public function trackMetricCount(array $payload = [])
    {
        try {
            $this->request(
                '/tpi/log/metrics/count',
                [
                    'headers' => $this->defaultHeader(),
                    'body' => json_encode($payload)
                ],
                "POST"
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param string $name
     * @param array $additional_tags
     * @param string $error_code
     * @return array
     */
    public function constructMetricPayload(
        string $name,
        array $additional_tags,
        string $error_code = ''
    ): array {
        return array(
            'name'              => $name,
            'additional_tags'   => array_merge(
                array(
                    'version' => XENDIT_PAYMENT_GATEWAY_VERSION,
                    'is_live' => $this->isTestMode()
                ),
                $additional_tags
            )
        );
    }
}
