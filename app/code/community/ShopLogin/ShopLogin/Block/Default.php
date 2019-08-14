<?php
/*
 * Log in with ShopLogin for Magento
 * https://www.shoplogin.com/for-merchants/
 * v0.9.1 for Magento
 */

class ShopLogin_ShopLogin_Block_Default extends Mage_Core_Block_Template {
    private $_helper;

    private function _getHelper() {
        if(null===$this->_helper) {
            $this->_helper = Mage::helper('shoplogin');
        }
        return $this->_helper;
    }

    public function isEnabled() {
        return $this->_getHelper()->isEnabled();
    }

    public function getClientId()
    {
        return $this->_getHelper()->getClientId();
    }

    public function getClientSecret()
    {
        return $this->_getHelper()->getClientSecret();
    }

    public function AffiliateisEnabled() {
        return $this->_getHelper()->AffiliateisEnabled();
    }

    public function TrackingPlusisEnabled() {
        return $this->_getHelper()->TrackingPlusisEnabled();
    }

    public function getIsUserConnected()
    {
        // is the session-user connected with ShopLogin?
        return $this->_getHelper()->getIsUserConnected();
    }

}
