<?php

class Payfort_Payfortstartgateway_PaymentController extends Mage_Core_Controller_Front_Action {

    const ACTION_AUTHORIZE_CAPTURE = 'authorize_capture';
    const PLUGIN_VERSION = '1.9.2.0';

    public function gatewayAction() {
        require_once(MAGENTO_ROOT . '/lib/Start/autoload.php');
        $order = new Mage_Sales_Model_Order();
        $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $order->loadByIncrementId($orderId);
        $quoteId = $order->getQuoteId();
        $quote = Mage::getModel('sales/quote')->load($quoteId);
        $totals = $quote->getTotals();
        foreach ($totals as $_total) {
            if ($_total->getCode() == 'grand_total') {
                $amount = $_total->getValue();
            }
        }

        if ($amount <= 0) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid amount for capture.'));
        }
        $payment = $order->getPayment();
        $method = $payment->getMethodInstance();
        $capture = false;
        if ($method->getConfigData('payment_action') == self::ACTION_AUTHORIZE_CAPTURE)
            $capture = true;

        $Currency = Mage::app()->getStore()->getBaseCurrencyCode();

        $token = isset($_POST['startToken']) ? $_POST['startToken'] : false;
        $email = isset($_POST['startEmail']) ? $_POST['startEmail'] : false;
        if (!$token || !$email) {
            Mage::throwException('Invalid Token');
        }
        $currency = !isset($Currency) ? 'AED' : $Currency;
        if (file_exists(MAGENTO_ROOT . '/data/currencies.json')) {
            $currency_json_data = json_decode(file_get_contents(MAGENTO_ROOT . '/data/currencies.json'), 1);
            $currency_multiplier = $currency_json_data[$currency];
        } else {
            $currency_multiplier = 100;
        }
        $amount_in_cents = $amount * $currency_multiplier;
        $order_items_array_full = array();
        foreach ($order->getAllVisibleItems() as $value) {
            $order_items_array['title'] = $value->getName();
            $order_items_array['amount'] = round($value->getPrice(), 2) * $currency_multiplier;
            $order_items_array['quantity'] = $value->getQtyOrdered();
            array_push($order_items_array_full, $order_items_array);
        }
        $shipping_amount = $order->getShippingAmount();
        $shipping_amount = $shipping_amount * $currency_multiplier;
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $username = $customer->getName();
            $registered_at = date(DATE_ISO8601, strtotime($customer->getCreatedAt()));
        } else {
            $username = "Guest";
            $registered_at = date(DATE_ISO8601, strtotime(date("Y-m-d H:i:s")));
        }
        $billing_data = $order->getBillingAddress()->getData();
        if (is_object($order->getShippingAddress())) {
            $shipping_data = $order->getShippingAddress()->getData();
            $shipping_address = array(
                "first_name" => $shipping_data['firstname'],
                "last_name" => $shipping_data['lastname'],
                "country" => $shipping_data['country_id'],
                "city" => $shipping_data['city'],
                "address" => $shipping_data['street'],
                "phone" => $shipping_data['telephone'],
                "postcode" => $shipping_data['postcode']
            );
        } else {
            $shipping_address = array();
        }

        $billing_address = array(
            "first_name" => $billing_data['firstname'],
            "last_name" => $billing_data['lastname'],
            "country" => $billing_data['country_id'],
            "city" => $billing_data['city'],
            "address" => $billing_data['street'],
            "phone" => $billing_data['telephone'],
            "postcode" => $billing_data['postcode']
        );

        $shopping_cart_array = array(
            'user_name' => $username,
            'registered_at' => $registered_at,
            'items' => $order_items_array_full,
            'billing_address' => $billing_address,
            'shipping_address' => $shipping_address
        );

        $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $charge_args = array(
            'description' => "Magento charge for " . $email,
            'card' => $token,
            'currency' => $currency,
            'email' => $email,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'amount' => $amount_in_cents,
            'capture' => $capture,
            'shipping_amount' => $shipping_amount,
            'shopping_cart' => $shopping_cart_array,
            'metadata' => array('reference_id' => $orderId)
        );
        $ver = new Mage;
        $version = $ver->getVersion();
        $userAgent = 'Magento ' . $version . ' / Start Plugin ' . self::PLUGIN_VERSION;
        Start::setUserAgent($userAgent);

        if ($method->getConfigData('test_mode') == 1) {
            Start::setApiKey($method->getConfigData('test_secret_key'));
        } else {
            Start::setApiKey($method->getConfigData('live_secret_key'));
        }

        try {
            // Charge the token
            $charge = Start_Charge::create($charge_args);
            //need to process charge as success or failed
            $payment->setTransactionId($charge["id"]);
            if ($capture) {
                $payment->setIsTransactionClosed(1);
            } else {
                $payment->setIsTransactionClosed(0);
            }
            $arr_querystring = array(
                'flag' => 1,
                'orderId' => $orderId
            );
            Mage_Core_Controller_Varien_Action::_redirect('payfortstartgateway/payment/response', array('_secure' => false, '_query' => $arr_querystring));
        } catch (Start_Error $e) {
            $error_code = $e->getErrorCode();
            if ($error_code === "card_declined") {
                $errorMsg = 'Charge was declined. Please, contact you bank for more information or use a different card.';
            } else {
                $errorMsg = $e->getMessage();
            }

            throw new Mage_Payment_Model_Info_Exception($errorMsg);
        }
//need to process charge as success or failed
    }

    public function redirectAction() {
        $this->loadLayout();
        $block = $this->getLayout()->createBlock('Mage_Core_Block_Template', 'payfortstartgateway', array('template' => 'payfortstartgateway/redirect.phtml'));
        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
    }

    public function responseAction() {
        if ($this->getRequest()->get("flag") == "1" && $this->getRequest()->get("orderId")) {
            $orderId = $this->getRequest()->get("orderId");
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true, 'Payment Success.');
            $order->save();

            Mage::getSingleton('checkout/session')->unsQuoteId();
            Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure' => false));
        } else {
            Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/error', array('_secure' => false));
        }
    }

}
