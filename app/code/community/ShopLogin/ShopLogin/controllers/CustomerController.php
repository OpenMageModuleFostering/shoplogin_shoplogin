<?php
/*
 * Log in with ShopLogin for Magento
 * https://www.shoplogin.com/for-merchants/
 * v0.9.4 for Magento
 */

class ShopLogin_ShopLogin_CustomerController extends Mage_Core_Controller_Front_Action
{

    protected $helper;
    protected $user_basic;
    protected $user_address;
    protected $shoplogin_id;
    protected $customer_id;
    protected $customer;
    protected $addresses = array();
    protected $required = array();

    public function LoginAction()
    {
        $this->helper = Mage::helper('shoplogin');
        if(! ($this->helper->isEnabled() && $this->helper->getClientId() && $this->helper->getClientSecret()) )
        {
            $this->user_redirect(); return;
        }

        $attributes = Mage::getModel('customer/entity_address_attribute_collection');
        foreach ($attributes as $attribute)
        {
            if($attribute->getIsRequired())
            {
                $this->required[] = $attribute->getAttributeCode();
            }
        }

        $this->helper->init($this->helper->getClientId(),$this->helper->getClientSecret());
        $this->user_basic = $this->helper->get_user_isauthorized();
        if(!$this->user_basic)
        {
            $message = $this->__('Error: Please verify the ShopLogin configuration settings of this store.');
            Mage::getSingleton('core/session')->addError($message);
            $this->user_redirect(); return;
        }
        $this->user_address = $this->helper->get_address_fromuser("&required_fields=".implode(",", $this->required));
        if(!$this->user_address->authorized)
        {
            $message = $this->__('Error: You could not be logged in with ShopLogin, please try again.');
            Mage::getSingleton('core/session')->addError($message);
            $this->user_redirect(); return;
        }
        $this->shoplogin_id = $this->user_basic->uid;


                if($this->user_already_registered_with_shoplogin())
                {
                    $this->user_update_connect_with_shoplogin();
                    $this->user_do_login();
                    $this->user_insert_address();
                    $this->user_redirect(); return;
                }

                if($this->user_already_registered_with_email())
                {
                    if($this->user_address->details_history->email_confirmed)
                    {
                        $this->user_connect_with_shoplogin();
                        $this->user_do_login();
                        $this->user_insert_address();
                    }else
                    {
                        $message = $this->__('Error: You could not be logged in with ShopLogin, because your ShopLogin account is not confirmed. ShopLogin sent you an activiation link to your email address. Please click on the included link to activate your account.');
                        $message .= "&nbsp; <font style='cursor:pointer; text-decoration:underline;' onclick='shoplogin.resend_activation()'>".$this->__('Resend&nbsp;Activation&nbsp;Email')."</font>";
                        Mage::getSingleton('core/session')->addError($message);
                        $this->user_redirect(); return;
                        die("ShopLogin-eMail not confirmed - User with address already exists");
                    }
                    $this->user_redirect(); return;
                }

        // Register new User
        if($this->user_create_account())
        {
            $this->user_connect_with_shoplogin();
            $this->user_do_login();
            $this->user_insert_address();
        }
        $this->user_redirect(); return;

    }

    private function user_create_account()
    {
            $temp = Mage::getModel('customer/customer');
            $temp->setFirstname($this->user_basic->first_name);
            $temp->setLastname($this->user_basic->last_name);
            $temp->setEmail($this->user_basic->email);
            $temp->setPassword(md5(microtime().'#'.$this->user_basic->data_token));
            $temp->setIsActive(1);
            $temp->setWebsiteId(Mage::app()->getWebsite()->getId());
            $temp->setConfirmation(null);
            $temp->save();
            if($temp->getId())
            {
                $this->customer = $temp;
                $this->customer_id = $temp->getId();
                return true;
            }
            return false;
    }

    private function user_insert_address()
    {
        // todo
        // check ob aktuelle adresse eine andere?
        // wenn ja, dann adresse eingeben und als neue Standardadressen eingeben

        if($this->user_address->details_history->billing_same_as_shipping)
        {
            $this->user_add_address($this->user_address->shipping_address, true, true);
        }else
        {
            $this->user_add_address($this->user_address->shipping_address, true, false);
            $this->user_add_address($this->user_address->billing_address, false, true);
        }

    }

    private function user_add_address($address, $shipping, $billing)
    {

        $addr = array (
            'firstname' => $address->first_name,
            'lastname' => $address->last_name,
            'street' => array (
                '0' => $address->line1,
                '1' => $address->line2,
            ),
            'city' => $address->city,
            'region_id' => $address->state_county->mag_region_id,
            'region' => $address->state_county->name,
            'postcode' => $address->zip,
            'country_id' => $address->country->code,
            'telephone' => $address->phone
        );


        foreach($this->required as $v)
        {
            if(!isset($addr[$v]) or !(trim($addr[$v]) or is_array($addr[$v])) or $addr[$v] == "0")
            {
                // nicht ausgefüllte Pflichtfelder automatisch mit Dummy-Wert ausfüllen
                $addr[$v] = "1";
            }
        }

        $already_added = $this->address_already_exists($addr);
        if($already_added)
        {
            if($shipping)
            {
                $already_added  ->setIsDefaultShipping('1');
            }
            if($billing)
            {
                $already_added->setIsDefaultBilling('1');
            }
            $already_added->save();
            return;
        }

        $temp = Mage::getModel('customer/address');
        $temp->setData($addr);
        $temp->setCustomerId($this->customer_id);
        $temp->setSaveInAddressBook('1');
        if($shipping)
        {
            $temp->setIsDefaultShipping('1');
        }
        if($billing)
        {
            $temp->setIsDefaultBilling('1');
        }
        $temp->save();
    }

    private function address_already_exists($addr)
    {
        $tempaddr = Mage::getSingleton('customer/session')->getCustomer();
        foreach ($tempaddr->getAddresses() as $a)
        {
            $tempstr = array('', '');
            $tempstr2 = explode("\n", $a['street']);
            $tempstr[0] = $tempstr2[0];
            if(count($tempstr2) > 1)
            {
                $tempstr[1] = $tempstr2[1];
            }

            $tempar = array (
            'firstname' => $a['firstname'],
            'lastname' => $a['lastname'],
            'street' => array (
                '0' => $tempstr[0],
                '1' => $tempstr[1],
            ),
            'city' => $a['city'],
            'region_id' => $a['region_id'],
            'region' => $a['region'],
            'postcode' => $a['postcode'],
            'country_id' => $a['country_id'],
            'telephone' => $a['telephone']
            );

            if($addr == $tempar)
            {
                 return $a;
            }
        }
        return false;
    }

    private function user_connect_with_shoplogin()
    {
            $write = Mage::getSingleton('core/resource')->getConnection('core_write');
            $write->insert(Mage::getSingleton('core/resource')->getTableName('shoplogin_customer'), array("customer_id" => (int)$this->customer_id, "shoplogin_id" => (int)$this->shoplogin_id, "data_token" => $this->user_basic->data_token));
    }

    private function user_update_connect_with_shoplogin()
    {
            $write = Mage::getSingleton('core/resource')->getConnection('core_write');
            $write->update(Mage::getSingleton('core/resource')->getTableName('shoplogin_customer'), array("customer_id" => (int)$this->customer_id, "shoplogin_id" => (int)$this->shoplogin_id, "data_token" => $this->user_basic->data_token), "customer_id='".(int)$this->customer_id."' and shoplogin_id='".(int)$this->shoplogin_id."'");
    }

    private function user_already_registered_with_email()
    {
        if($temp = Mage::getModel('customer/customer')->setWebsiteId(Mage::app()->getWebsite()->getId())->loadByEmail($this->user_basic->email))
        {
            if($temp->getId())
            {
                $this->customer = $temp;
                $this->customer_id = $temp->getId();
                return true;
            }
        }
        return false;
    }

    public function user_already_registered_with_shoplogin()
    {
        if($temp = Mage::getModel('shoplogin/shoplogin')->load($this->shoplogin_id, 'shoplogin_id'))
        {
            if($temp->getCustomerId())
            {
                $this->customer = $temp;
                $this->customer_id = $temp->getCustomerId();
                return true;
            }
        }
        return false;
    }

    private function user_do_login() {
        $session = Mage::getSingleton('customer/session');
        $temp = Mage::getModel('customer/customer')->load($this->customer_id);
        if($temp->getId())
        {
            $session->setCustomerAsLoggedIn($temp);
        }
    }

    private function user_redirect()
    {
        $session = Mage::getSingleton('customer/session');

        if (isset($_GET['redirect'])) {
            $redirect = base64_decode(trim($_GET['redirect']));
            if ( substr_count($redirect, str_replace('https://', '', str_replace('http://', '', Mage::app()->getStore()->getBaseUrl()))) )
            {
                $session->setBeforeAuthUrl($redirect);
            } else 
            {
                $session->setBeforeAuthUrl(Mage::helper('customer')->getDashboardUrl());
            }
        } 
        else 
        {
            $session->setBeforeAuthUrl(Mage::helper('customer')->getDashboardUrl()); die(Mage::helper('customer')->getDashboardUrl());
        }
        $this->_redirectUrl($session->getBeforeAuthUrl(true));
    }
}
?>