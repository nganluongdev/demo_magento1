<?php
/*
 * Alepay class
 * Implement with Alepay service
 */
require_once(Mage::getModuleDir('Helper', 'Nexttech_Alepay').DS.'Helper'.DS.'Crypt'.DS.'RSA.php');

class Nexttech_Alepay_Helper_Alepay extends Mage_Core_Helper_Abstract {
    
    private static $instance;
    private $checksumKey = "";
    private $apiKey = "";
    private $callbackUrl = "";
    
    public $env = "test";
    public $publicKey = "";
    public $alepayUtils;
    
    public $baseURL = array(
        'dev' => 'localhost:8080',
        'test' => 'http://test.alepay.vn',
        //'test' => 'http://192.168.11.40:8081',
        'live' => 'https://alepay.vn'
    );
    
    public $URI = array(
        'requestPayment' => '/checkout/v1/request-order',
        'calculateFee' => '/checkout/v1/calculate-fee',
        'getTransactionInfo' => '/checkout/v1/get-transaction-info',
        'requestCardLink' => '/checkout/v1/request-profile',
        'tokenizationPayment' => '/checkout/v1/request-tokenization-payment',
        'cancelCardLink' => '/checkout/v1/cancel-profile'
    );
    
    public static function get_instance($opts) {
        if (null === self::$instance) {
            self::$instance = new self($opts);
            
            // set KEY
            if (isset($opts) && !empty($opts["apiKey"])) {
                self::$instance->apiKey = $opts["apiKey"];
            } /*else {
                throw new Exception("API key is required !");
            }*/
            if (isset($opts) && !empty($opts["encryptKey"])) {
                self::$instance->publicKey = $opts["encryptKey"];
            } /*else {
                throw new Exception("Encrypt key is required !");
            }*/
            if (isset($opts) && !empty($opts["checksumKey"])) {
                self::$instance->checksumKey = $opts["checksumKey"];
            } /*else {
                throw new Exception("Checksum key is required !");
            }*/
            if (isset($opts) && !empty($opts["callbackUrl"])) {
                self::$instance->callbackUrl = $opts["callbackUrl"];
            }
            if (isset($opts) && !empty($opts["env"])) {
                self::$instance->env = $opts["env"];
            }
        }
        return self::$instance;
    }

    public static function log($message) {
        Mage::log($message, null, 'alepay.log', true);
    }
    
    public function encryptData($data, $publicKey) { 
        $rsa = new Crypt_RSA();
        $rsa->loadKey($publicKey); // public key
        $rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
        $output = $rsa->encrypt($data);
        return base64_encode($output);
    }


    public function decryptData($data, $publicKey) {
        $rsa = new Crypt_RSA();
        $rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
        $ciphertext = base64_decode($data);
        $rsa->loadKey($publicKey); // public key
        $output = $rsa->decrypt($ciphertext);
        // $output = $rsa->decrypt($data);
        return $output;
    }

    public function decryptCallbackData($data, $publicKey){
        $decoded = base64_decode($data);
        return $this->decryptData($decoded, $publicKey);
    }

    public function __construct() {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Credentials: true");
        header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
        header('Access-Control-Max-Age: 1000');
        header('Access-Control-Allow-Headers: Content-Type, Content-Range, Content-Disposition, Content-Description');

        /*
         * Require curl and json extension
         */
        if (!function_exists('curl_init')) {
            throw new Exception('Alepay needs the CURL PHP extension.');
        }
        if (!function_exists('json_decode')) {
            throw new Exception('Alepay needs the JSON PHP extension.');
        }
        
        $this->alepayUtils = $this;
    }

    /*
     * Generate data checkout demo
     */

    public function createCheckoutData() {
        $params = array(
            'amount' => '5000000',
            'buyerAddress' => '12 đường 18, quận 1',
            'buyerCity' => 'TP. Hồ Chí Minh',
            'buyerCountry' => 'Việt Nam',
            'buyerEmail' => 'testalepay@yopmail.com',
            'buyerName' => 'Nguyễn Văn Bê',
            'buyerPhone' => '0987654321',
            'cancelUrl' => 'http://' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . '/demo-beta',
            'currency' => 'VND',
            'orderCode' => 'Order-123',
            'orderDescription' => 'Mua ai phôn 8',
            'paymentHours' => '5',
            'returnUrl' => $this->callbackUrl,
            'totalItem' => '1',
            'checkoutType' => 0,
                // 'installment' => 'true',
                // 'month' => '3',
                // 'bankCode' => 'Sacombank',
                // 'paymentMethod' => 'VISA'
        );

        return $params;
    }

    private function createRequestCardLinkData() {
        $params = array(
            'id' => 'acb-123',
            'firstName' => 'Nguyễn',
            'lastName' => 'Văn Bê',
            'street' => 'Nguyễn Trãi',
            'city' => 'TP. Hồ Chí Minh',
            'state' => 'Quận 1',
            'postalCode' => '100000',
            'country' => 'Việt nam',
            'email' => 'testalepay@yopmail.com',
            'phoneNumber' => '0987654321',
            'callback' => $this->callbackUrl
        );
        return $params;
    }

    public function createTokenizationPaymentData($tokenization) {
        $params = array(
            'customerToken' => $tokenization, // put customer's token
            'orderCode' => 'order-123',
            'amount' => '1000000',
            'currency' => 'VND',
            'orderDescription' => 'Mua ai phôn 8',
            'returnUrl' => $this->callbackUrl,
            'cancelUrl' => 'http://' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . '/demo-beta',
            'paymentHours' => 5
        );
        return $params;
    }

    /*
     * sendOrder - Send order information to Alepay service
     * @param array|null $data
     */

    public function sendOrderToAlepay($data) {
        // get demo data
        $data = $this->createCheckoutData();
        $data['returnUrl'] = $this->callbackUrl;
        $data['cancelUrl'] = 'http://' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . '/demo-beta';
        $url = $this->baseURL[$this->env] . $this->URI['requestPayment'];
        $result = $this->sendRequestToAlepay($data, $url);
        if ($result->errorCode == '000') {
            $dataDecrypted = $this->alepayUtils->decryptData($result->data, $this->publicKey);
            echo $dataDecrypted;
        } else {
            echo json_encode($result);
        }
    }

    /*
     * get transaction info from Alepay
     * @param array|null $data
     */

    public function getTransactionInfo($transactionCode) {

        // demo data
        $data = array('transactionCode' => $transactionCode);
        $url = $this->baseURL[$this->env] . $this->URI['getTransactionInfo'];
        $result = $this->sendRequestToAlepay($data, $url);
        if ($result->errorCode == '000') {
            $dataDecrypted = $this->alepayUtils->decryptData($result->data, $this->publicKey);
            echo $dataDecrypted;
        } else {
            echo json_encode($result);
        }
    }

    /*
     * sendCardLinkRequest - Send user's profile info to Alepay service
     * return: cardlink url
     * @param array|null $data
     */

    public function sendCardLinkRequest($data) {
        // get demo data
        $data = $this->createRequestCardLinkData();
        $url = $this->baseURL[$this->env] . $this->URI['requestCardLink'];
        $result = $this->sendRequestToAlepay($data, $url);

        if ($result->errorCode == '000') {
            $dataDecrypted = $this->alepayUtils->decryptData($result->data, $this->publicKey);
            echo json_encode($dataDecrypted);
        } else {
            return $result;
        }
    }

    public function sendTokenizationPayment($tokenization) {

        $data = $this->createTokenizationPaymentData($tokenization);
        $url = $this->baseURL[$this->env] . $this->URI['tokenizationPayment'];
        $result = $this->sendRequestToAlepay($data, $url);
        if ($result->errorCode == '000') {
            $dataDecrypted = $this->alepayUtils->decryptData($result->data, $this->publicKey);
            return json_encode($dataDecrypted);
        } else {
            return $result;
        }
    }

    public function cancelCardLink($alepayToken) {
        $params = array('alepayToken' => $alepayToken);
        $url = $this->baseURL[$this->env] . $this->URI['cancelCardLink'];
        $result = $this->sendRequestToAlepay($params, $url);
        echo json_encode($result);
        if ($result->errorCode == '000') {
            $dataDecrypted = $this->alepayUtils->decryptData($result->data, $this->publicKey);
            echo $dataDecrypted;
        }
    }

    public function sendRequestToAlepay($data, $url) {
        $dataEncrypt = $this->alepayUtils->encryptData(json_encode($data), $this->publicKey);
        $checksum = md5($dataEncrypt . $this->checksumKey);
        $items = array(
            'token' => $this->apiKey,
            'data' => $dataEncrypt,
            'checksum' => $checksum
        );
        $data_string = json_encode($items);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
        );
        $result = curl_exec($ch); //var_dump($result); die;
        return json_decode($result);
    }

    public function return_json($error, $message = "", $data = array()) {
        header('Content-Type: application/json');
        echo json_encode(array(
            "error" => $error,
            "message" => $message,
            "data" => $data
        ));
    }

}

?>