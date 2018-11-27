<?php

class Nexttech_Alepay_PaymentController extends Mage_Core_Controller_Front_Action {

    public $language;
    public $currency;

    protected $_alepay;
    protected $_payment_methods;
    
    protected function _construct() {
        $paymentmethodObj = Mage::getModel('alepay/paymentmethod'); 
        $this->_payment_methods = array(
            '1' => $paymentmethodObj->getConfigData('payment_normal_label'),
            '2' => $paymentmethodObj->getConfigData('payment_installment_label'),
            '3' => $paymentmethodObj->getConfigData('payment_token_label'),
            //'4' => 'Thanh toán qua Ngân Lượng',
        );
        $this->_alepay = Mage::helper('alepay/alepay')->get_instance(array(
            "apiKey" => $paymentmethodObj->getConfigData('public_key'),
            "encryptKey" => $paymentmethodObj->getConfigData('encrypt_key'),
            "checksumKey" => $paymentmethodObj->getConfigData('checksum_key'),
            'env' => $paymentmethodObj->getConfigData('env') === '1' ? 'live' : 'test',
        ));
        $this->language = $paymentmethodObj->getConfigData('language');
        $this->currency = $paymentmethodObj->getConfigData('currency');
    }
    
    public function gatewayAction() {
        if ($this->getRequest()->get("orderId")) {
            $arr_querystring = array(
                'flag' => 1,
                'orderId' => $this->getRequest()->get("orderId")
            );

            Mage_Core_Controller_Varien_Action::_redirect('alepay/payment/response', array('_secure' => false, '_query' => $arr_querystring));
        }
    }

    public function redirectAction() {
//        $this->loadLayout();
//        $block = $this->getLayout()->createBlock('Mage_Core_Block_Template', 'alepay', array('template' => 'alepay/redirect.phtml'));
//        $this->getLayout()->getBlock('content')->append($block);
//        $this->renderLayout();
        
        // Get order info
        //$order = new Mage_Sales_Model_Order();
        $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $order = Mage::getSingleton('sales/order')->loadByIncrementId($orderId);  //var_dump($order->getData()); die;
        $billingAddr = $order->getBillingAddress();
        
        $log_content = '-----------------------------------'; 
        $log_content .= PHP_EOL.'- Order id: '.$orderId;
        
        // Initalize properties 
        $return_url = Mage::getUrl('alepay/payment/response', array('_secure' => false, '_query' => array(
            'flag' => 1,
            'orderId' => $orderId
        )));
        $cancel_url = Mage::getUrl('alepay/payment/response', array('_secure' => false));
        
        $payment_methods = $this->_payment_methods;
        $alepay = $this->_alepay;

        // Get checkout info
        $user_id = $order->getCustomerId();
        $hoten = $order->getCustomerFirstname().' '.$order->getCustomerMiddlename().' '.$order->getCustomerLastname();
        $dienthoai = $billingAddr->getTelephone();  
        $email = $order->getCustomerEmail();
        $diachi = $billingAddr->getStreet(1);
        $city = $billingAddr->getCity();
        $countryCode = $billingAddr->getCountry();
        $countryName = Mage::getModel('directory/country')->load($countryCode)->getName();
        // Lay thong tin region
        $regionName = $billingAddr->getRegion();
        if (!$regionName) {
            $regionId = $billingAddr->getRegionId();
            if ($regionId) {
                $region = Mage::getModel('directory/region')->load($regionId);
                $regionName = $region->getName();
            } else {
                $regionName = 'ha noi';
            }
        }
        // Xong region
        $postal_code = $billingAddr->getPostcode();
        $tongtien = (int)$order->getGrandTotal();
        $payment_method = $payment_methods[$this->getRequest()->get('payment_method')];
        $totalItem = $order->getTotalItemCount();
        //$currency = $order->getOrderCurrencyCode();
        $currency = $this->currency ? $this->currency : $order->getOrderCurrencyCode();
        $orderCode = $order->getIncrementId();
        $orderDesc = 'Customer note: '.$order->getCustomerNote().' | Shipping description: '.$order->getShippingDescription().' | Discount description: '.$order->getDiscountDescription();
        //var_dump($diachi); die;
   
        $log_content .= PHP_EOL.'- Customer id: '.$user_id;
        $log_content .= PHP_EOL.'- Phuong thuc thanh toan: '.$payment_method;
        
        // check connection card
        $tokenObj = Mage::getModel('alepay/alepaytoken')->load($user_id, 'user_id');
        $token = $tokenObj->getToken();
        if (!empty($token) && $payment_method !== $payment_methods['3']) { // Cancel link card if user dont select token method
            $alepay->cancelCardLink($token);
            $tokenObj->delete();
            $log_content .= PHP_EOL.'- Da lien ket nhung huy (token: '.$token.')';
            $token = null;
        }

        if (!empty($token) && $payment_method === $payment_methods['3']) { // connected card and continue select token method
            $data = $alepay->createTokenizationPaymentData($token);
            $data['orderCode'] = $orderCode;
            $data['amount'] = $tongtien;
            $data['currency'] = $currency;
            $data['orderDescription'] = $orderDesc;
            $data['returnUrl'] = $return_url;
            $data['cancelUrl'] = $cancel_url;
            $url = $alepay->baseURL[$alepay->env].$alepay->URI['tokenizationPayment'];
            $log_content .= PHP_EOL.'- Da lien ket va tiep tuc thanh toan qua token (token: '.$token.')';
        } else { // not connect card
            $data = $alepay->createCheckoutData();
            $data['checkoutType'] = array_search($payment_method, $payment_methods);
            $data['amount'] = $tongtien;
            $data['buyerAddress'] = $diachi;
            $data['buyerCity'] = $city;
            $data['buyerCountry'] = $countryName;
            $data['buyerEmail'] = $email;
            $data['buyerName'] = $hoten;
            $data['buyerPhone'] = $dienthoai;
            $data['currency'] = $currency;
            $data['orderCode'] = $orderCode;
            $data['orderDescription'] = $orderDesc;
            $data['totalItem'] = $totalItem;
            $data['returnUrl'] = $return_url;
            $data['cancelUrl'] = $cancel_url;

            if ($payment_method === $payment_methods['3']) { // add 4 fields to use token method
                $data['checkoutType'] = '1';
                $data['merchantSideUserId'] = $user_id;
                $data['buyerPostalCode'] = $postal_code;
                $data['buyerState'] = $regionName;
                $data['isCardLink'] = true;
            }
            $url = $alepay->baseURL[$alepay->env].$alepay->URI['requestPayment'];
            $log_content .= PHP_EOL.'- Chua lien ket the';
        }
        $data['language'] = $this->language ? $this->language : 'vi';
        
        $result = $alepay->sendRequestToAlepay($data, $url);          //var_dump($result); die; 
        if ($result->errorCode === '000') { // success
            $res = json_decode($alepay->decryptData($result->data, $alepay->publicKey));  
            if ($res->checkoutUrl) {
                $log_content .= PHP_EOL.'- Alepay tra ve thanh cong voi checkoutUrl: '.$res->checkoutUrl;
                Mage::helper('alepay/alepay')->log($log_content);
                $this->_redirectUrl($res->checkoutUrl);
            } else {
                $log_content .= PHP_EOL.'- Alepay tra ve thanh cong nhung ko co checkoutUrl';
                Mage::helper('alepay/alepay')->log($log_content);
                $this->_redirectUrl($return_url);
            }
        } else { // fail
            $log_content .= PHP_EOL.'- Alepay tra ve loi (errorCode: '.$result->errorCode.')';//$result->errorDescription
            Mage::helper('alepay/alepay')->log($log_content);
            $this->_redirectUrl($cancel_url);
        }
    }

    public function responseAction() {
        $flag = $this->getRequest()->get('flag');
        $orderId = $this->getRequest()->get('orderId');
        $data = $this->getRequest()->get('data');
        $log_content = PHP_EOL.'- Alepay chuyen huong ve merchant';
        $log_content .= PHP_EOL.'- Order id: '.$orderId;
        
        if ($flag === '1' && $orderId) {
            // cap nhat token
            $data = json_decode($this->_alepay->decryptCallbackData($data, $this->_alepay->publicKey));
            if ($data->errorCode === '000') { // no error
                //cap nhat trang thai hoa don
                $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
                $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true, 'Payment Success.');
                $order->save();
                $log_content .= PHP_EOL.'- Cap nhat trang thai hoa don sang: '.Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;
                // update token
                if (!empty($data->data->alepayToken)) {
                    $token = $data->data->alepayToken;
                    $tokenObj = Mage::getModel('alepay/alepaytoken');
                    if (!$tokenObj->load($token, 'token')->getData()) {
                        $tokenObj->setData(array(
                            'token' => $token,
                            'user_id' => $order->getCustomerId(),
                            'time' => date('Y-m-d H:i:s', Mage::getModel('core/date')->timestamp())
                        ))->save();
                        $log_content .= PHP_EOL.'- Cap nhat token: '.$token;
                    }
                }
            } else { // has error
                $log_content .= PHP_EOL.'- Alepay tra ve loi (errorCode: '.$data->errorCode.')';
            }
            Mage::helper('alepay/alepay')->log($log_content);
            Mage::getSingleton('checkout/session')->unsQuoteId();
            Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure' => false));
        } else {
            Mage::helper('alepay/alepay')->log($log_content);
            Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure', array('_secure' => false));
        }
    }

}
