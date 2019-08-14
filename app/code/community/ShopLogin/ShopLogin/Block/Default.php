<?php
/*
 * Log in with ShopLogin for Magento
 * https://www.shoplogin.com/for-merchants/
 * v1.4.1 for Magento
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

    public function WishlistEnabled() {
        return $this->_getHelper()->WishlistEnabled();
    }

    public function RecommendationEnabled() {
        return $this->_getHelper()->RecommendationEnabled();
    }

    public function ShowSeal() {
        return $this->_getHelper()->ShowSeal();
    }

    public function getClientId()
    {
        return $this->_getHelper()->getClientId();
    }

    public function getRecommendationLicenseKey()
    {
        return $this->_getHelper()->getRecommendationLicenseKey();
    }

    public function getClientSecret()
    {
        return $this->_getHelper()->getClientSecret();
    }

    public function getIsUserConnected()
    {
        // is the session-user connected with ShopLogin?
        return $this->_getHelper()->getIsUserConnected();
    }

}
