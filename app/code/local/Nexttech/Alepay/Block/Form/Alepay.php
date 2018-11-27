<?php

class Nexttech_Alepay_Block_Form_Alepay extends Mage_Payment_Block_Form {

    protected function _construct() {
        parent::_construct();
        $this->setTemplate('alepay/form/alepay.phtml');
    }

}
