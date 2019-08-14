<?php

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