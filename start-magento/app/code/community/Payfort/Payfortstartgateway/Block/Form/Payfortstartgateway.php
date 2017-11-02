<?php

class Payfort_Payfortstartgateway_Block_Form_Payfortstartgateway extends Mage_Payment_Block_Form {

    protected function _construct() {
        parent::_construct();
        $this->setTemplate('payfortstartgateway/form/payfortstartgateway.phtml');
    }

}
