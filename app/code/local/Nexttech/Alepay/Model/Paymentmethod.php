<?php

class Nexttech_Alepay_Model_Paymentmethod extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'alepay';
    protected $_formBlockType = 'alepay/form_alepay';
    protected $_infoBlockType = 'alepay/info_alepay';

    public function assignData($data) {
        $info = $this->getInfoInstance();
        //var_dump($data->getAlepayMethod());
        if ($data->getAlepayMethod()) {
            $info->setAlepayMethod($data->getAlepayMethod());
        }
        return $this;
    }

    public function validate() {
        parent::validate();
        $errorMsg = '';
        $info = $this->getInfoInstance();   
        $payment_method = $info->getAlepayMethod();
        $payment_methods = array(
            '1' => $this->getConfigData('payment_normal_label'),
            '2' => $this->getConfigData('payment_installment_label'),
            '3' => $this->getConfigData('payment_token_label'),
            //'4' => 'Thanh toán qua Ngân Lượng',
        );

        if (!$info->getAlepayMethod()) { // Neu khong chon phuong thuc thanh toan nao
            $errorCode = 'invalid_data';
            $errorMsg = $this->_getHelper()->__("Please select one payment method.\n");
        }
        
        if ($payment_method === $payment_methods['3'] && !Mage::getSingleton('customer/session')->isLoggedIn()) { // neu chon thanh toan token ma chua login
            $errorCode = 'invalid_data';
            $errorMsg = $this->_getHelper()->__("Please login to checkout.\n");
        }

        if ($errorMsg) {
            Mage::throwException($errorMsg);
        }

        return $this;
    }

    public function getOrderPlaceRedirectUrl() {
        $info = $this->getInfoInstance();
        $payment_method = $info->getAlepayMethod();
        $payment_methods = array(
            '1' => $this->getConfigData('payment_normal_label'),
            '2' => $this->getConfigData('payment_installment_label'),
            '3' => $this->getConfigData('payment_token_label'),
            //'4' => 'Thanh toán qua Ngân Lượng',
        );
        
        return Mage::getUrl('alepay/payment/redirect', array('_secure' => false, '_query' => array(
            'payment_method' => array_search($payment_method, $payment_methods)
        )));
    }

}
