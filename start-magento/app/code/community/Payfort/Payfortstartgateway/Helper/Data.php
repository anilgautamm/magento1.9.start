<?php

class Payfort_Payfortstartgateway_Helper_Data extends Mage_Core_Helper_Abstract {

    function getPaymentGatewayUrl() {
        return Mage::getUrl('payfortstartgateway/payment/gateway', array('_secure' => false));
    }

}
