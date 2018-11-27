<?php

class Nexttech_Alepay_Helper_Data extends Mage_Core_Helper_Abstract {

    function getPaymentGatewayUrl() {
        return Mage::getUrl('alepay/payment/gateway', array('_secure' => false));
    }

}
