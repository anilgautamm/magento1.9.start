<?php

class Payfort_Payfortstartgateway_Model_Paymentmethod extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'payfortstartgateway';
    protected $_formBlockType = 'payfortstartgateway/form_payfortstartgateway';
    protected $_infoBlockType = 'payfortstartgateway/info_payfortstartgateway';

//    const REQUEST_TYPE_AUTH_CAPTURE = 'AUTH_CAPTURE';
//    const REQUEST_TYPE_AUTH_ONLY = 'AUTH_ONLY';
//    const REQUEST_TYPE_CAPTURE_ONLY = 'CAPTURE_ONLY';
//    const REQUEST_TYPE_PRIOR_AUTH_CAPTURE = 'PRIOR_AUTH_CAPTURE';

    /**
     * Availability options
     */
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_canSaveCc = false;
    protected $_isInitializeNeeded = true;
    protected $_canFetchTransactionInfo = false;

    public function assignData($stateObject) {
        $info = $this->getInfoInstance();
        return $this;
    }

    public function validate() {
        parent::validate();
        $info = $this->getInfoInstance();
        return $this;
    }

    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('payfortstartgateway/payment/redirect', array('_secure' => false));
    }

}
