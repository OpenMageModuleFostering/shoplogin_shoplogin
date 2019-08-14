<?php
/*
 * Log in with ShopLogin for Magento
 * https://www.shoplogin.com/for-merchants/
 * v1.4.1 for Magento
 */

class ShopLogin_ShopLogin_Model_Shoplogin extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('shoplogin/shoplogin');
    }
}

class ShopLogin_ShopLogin_Model_Resource_Shoplogin extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {
        $this->_init('shoplogin/shoplogin', 'id');
    }
}

?>