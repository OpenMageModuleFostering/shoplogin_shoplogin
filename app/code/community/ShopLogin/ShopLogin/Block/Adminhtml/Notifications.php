<?php
class Shoplogin_Shoplogin_Block_Adminhtml_Notifications extends Mage_Adminhtml_Block_Template
{
    /**
     * (non-PHPdoc)
     * @see Mage_Core_Block_Template::_construct()
     */
    protected function _construct()
    {
        $this->addData(
            array(
                'cache_lifetime'=> null
            )
        );
    }

    public function isInitialized()
    {
        $clientId = Mage::getStoreConfig('shoplogin/settings/clientid');
        if(empty($clientId)) {
          return false;
        }
        return true;
    }

    public function getManageUrl()
    {
        return $this->getUrl('adminhtml/system_config/edit', array('section' => 'shoplogin'));
    }

    protected function _toHtml()
    {
        if (Mage::getSingleton('admin/session')->isAllowed('system/shoplogin')) {
            return parent::_toHtml();
        }
        return '';
    }
}
