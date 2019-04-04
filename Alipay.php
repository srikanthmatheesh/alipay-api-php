<?php
/**
 * Alipay "Cross-border Website Payment"
 *
 * A simplified and secure payment experience that keeps them local to your
 * website throughout the payment process. Once integrated the Alipay online
 * payment service, you should present an Alipay payment button on your website
 * for the consumer to complete the payment and check out.
 *
 * @author  Srikanth Matheesh <phpsri@gmail.com>
 *
 * @version 1.0
 *
 * @since 1.0
 *
 * @link https://github.com/srikanthmatheesh/alipay-api-php
 *
 */
class Alipay

{
    /**
     * Application Partner ID
     *
     * @var string $partner_id
     */
    private $partner_id = "";
    /**
     * Application Partner Secret Key
     *
     * @var string $partner_secret_key
     */
    private $partner_secret_key = "";
    /**
     * Alipay API gateway
     *
     * @var string $gateway
     */
    private $gateway = "";
    /**
     * Alipay API hasg
     *
     * @var string $hash
     */
    private $hash = "MD5";
    /**
     * Constructor method to set initial data
     * @param partner_id
     * @param partner_secret_key
     * @param gateway
     */
    public function __construct($params)
    {
        $this->partner_id = $params['partner_id'];
        $this->partner_secret_key = $params['partner_secret_key'];
        $this->gateway = strtolower($params['environment']) == 'development' ? 'https://openapi.alipaydev.com/gateway.do' : 'https://mapi.alipay.com/gateway.do';
    }

    /**
     * Prepares a request and Encoding the query.
     *
     * @param array $data Array of parameters for request
     * @return array
     */
    private function _prepare_data($data = array())
    {
        $data['sign'] = $this->_sign($data);
        $data['sign_type'] = $this->hash;
        ksort($data);

        return http_build_query($data);
    }

    /**
     * Prepares a request and hash for the query.
     *
     * @param array $data Array of parameters for request
     * @return string
     */
    private function _sign($data = array())
    {
        ksort($data);
        $query = "";
        foreach($data as $k => $v) {
            if ($v == "") {
                continue;
            } //$v == ""
            $query.= "$k=$v" . '&';
        } //$data as $k => $v
        return md5(substr($query, 0, -1) . $this->partner_secret_key);
    }

    /**
     * Create a transaction URL for Alipay.
     *
     * @param string $transaction_id Transaction ID for reference
     * @param decimal $amount Transaction amount charged to the user
     * @param string $currency The currency for the amount
     * @param string $description Description of the transaction
     * @param url $return_url Return URL after payment
     * @param url $notify_url Alipay will ping after payment
     * @param boolean $is_mobile True for mobile view
     * @return string
     */
    public function create_payment($out_trade_no = "", $amount = 0, $currency = "USD", $description = "", $return_url = "", $notify_url = "", $is_mobile = false)
    {
        $data = array(
            'body' => $description,
            'service' => $is_mobile ? 'create_forex_trade_wap' : 'create_forex_trade',
            'out_trade_no' => $out_trade_no,
            'currency' => $currency,
            'total_fee' => $amount,
            'subject' => $description,
            'return_url' => $return_url,
            'notify_url' => $notify_url,
            'partner' => $this->partner_id,
            '_input_charset' => "utf-8"
        );
        return $this->gateway . "?" . $this->_prepare_data($data);
    }

    public function uuid()
    {
        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0010
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data) , 4));
    }

    /**
     * Verify a transaction form Alipay.
     *
     * @throws Exception
     * @param array $data Array of parameters for request
     * @return bool/string
     */
    public function verify_payment($data = array())
    {
        $sign = $data['sign'];
        unset($data['sign'], $data['sign_type']);
        $new_sign = $this->_sign($data);
        if ($sign != $new_sign) {
            throw new Exception("Hashes do not match (Transaction No:{$data['out_trade_no']}).");
        } //$sign != $new_sign
        $request = array(
            'service' => 'notify_verify',
            'partner' => $this->partner_id,
            'notify_id' => $data['notify_id']
        );
        $response = $this->send(http_build_query($request) , "GET");
        if (preg_match("/true$/i", $response)) {
            if ($data['trade_status'] == "TRADE_FINISHED") {
                return true;
            } //$data['trade_status'] == "TRADE_FINISHED"
        } //preg_match("/true$/i", $response)
        else {
            throw new Exception("Invalid Transaction (Transaction id: {$data['out_trade_no']})");
        }

        return false;
    }

    /**
     * Send a request
     *
     * @throws Exception
     * @param array $data The payload
     * @param string $method The request method: POST, GET
     * @return string
     */
    private function send($data = array() , $method = "GET")
    {
        $method = strtoupper($method);
        if ($method == "GET") {
            $curl = curl_init($this->gateway . "?$data");
            curl_setopt($curl, CURLOPT_POST, false);
        } //$method == "GET"
        else {
            $curl = curl_init($this->gateway . "?_input_charset=utf-8");
            curl_setopt($curl, CURLOPT_POST, true);
        }

        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSLVERSION, 3);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_CAINFO, "./alipay_ca.pem");
        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        if ($error) {
            throw new Exception($error);
        } //$error
        return $response;
    }
}
