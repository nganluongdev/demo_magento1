<?php
class Nexttech_Alepay_Model_Resource_Alepaytoken extends Mage_Core_Model_Resource_Db_Abstract{
    protected function _construct()
    {
        $this->_init('alepay/alepaytoken', 'id');
    }
}