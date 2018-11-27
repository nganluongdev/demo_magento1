<?php

class Nexttech_Alepay_Block_Info_Alepay extends Mage_Payment_Block_Info {

    protected function _prepareSpecificInformation($transport = null) {
        if (null !== $this->_paymentSpecificInformation) {
            return $this->_paymentSpecificInformation;
        }

        $data = array();        //var_dump($this->getInfo()->getAlepayMethod());
        if ($this->getInfo()->getAlepayMethod()) {
            $data[Mage::helper('payment')->__('Alepay method')] = $this->getInfo()->getAlepayMethod();
        }

        $transport = parent::_prepareSpecificInformation($transport);

        return $transport->setData(array_merge($data, $transport->getData()));
    }

}
