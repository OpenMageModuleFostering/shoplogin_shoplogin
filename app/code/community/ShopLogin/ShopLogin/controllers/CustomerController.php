<?php
/*
 * Log in with ShopLogin for Magento
 * https://www.shoplogin.com/for-merchants/
 * v1.4.0 for Magento
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
        // Hauptklasse, diese wird beim Aufrufen des ShopLogin-Logins aufgerufen und übernimmt die Steuerung

        // wenn ShopLogin nicht aktiviert oder nicht die AppID und AppSecret in den Einstellungen eingegeben wurde, dann den User sofort weiterleiten
        $this->helper = Mage::helper('shoplogin');
        if(! ($this->helper->isEnabled() && $this->helper->getClientId() && $this->helper->getClientSecret()) )
        {
            $this->user_redirect(); return;
        }

        // (optional) die vom Shopsystem erforderlichen Adress-Datenfelder heraussuchen, diese werden der ShopLogin-Api übergeben, damit diese die notwendigen Felder zurückgeben kann
        $attributes = Mage::getModel('customer/entity_address_attribute_collection');
        foreach ($attributes as $attribute)
        {
            if($attribute->getIsRequired())
            {
                $this->required[] = $attribute->getAttributeCode();
            }
        }

        // ShopLogin-Klasse initieren (findet sich hier in Magento unter Helper/Data.php)
        $this->helper->init($this->helper->getClientId(),$this->helper->getClientSecret());

        // Basisdaten des User abfragen (eMail, Vorname, Nachname, Token)
        $this->user_basic = $this->helper->get_user_isauthorized();

        // wenn die Basisdaten nicht abgefragt werden konnten, User zurückleiten und Fehlermeldung (Fehler ist höchstwahrscheinlich inkorrekte AppID und AppSecret in den Shop-Einstellungen)
        if(!$this->user_basic)
        {
            $message = $this->__('Error: Please verify the ShopLogin configuration settings of this store.');
            Mage::getSingleton('core/session')->addError($message);
            $this->user_redirect(); return;
        }

        // Adressdaten des Users abfragen
        $this->user_address = $this->helper->get_address_fromuser("&required_fields=".implode(",", $this->required));

        // wenn keine Adressdaten übermittel, User zurückleiten und Fehlermeldung
        if(!$this->user_address->authorized)
        {
            $message = $this->__('Error: You could not be logged in with ShopLogin, please try again.');
            Mage::getSingleton('core/session')->addError($message);
            $this->user_redirect(); return;
        }
        $this->shoplogin_id = $this->user_basic->uid;

        // wenn User bereits angemeldet mit ShopLogin, dann einloggen, Adressen etc. updaten und weiterleiten
        if($this->user_already_registered_with_shoplogin())
        {
            $this->user_update_connect_with_shoplogin();
            $this->user_do_login();
            $this->user_insert_address();
            $this->user_redirect(); return;
        }

        // wenn es bereits einen User im Shop mit der angegeben eMail-Adresse gibt
        if($this->user_already_registered_with_email())
        {
            // wenn von der ShopLogin-Api das Flag email_confirmed auf true steht, hat der User seine eMail bereits bestätigt und kann verknüpft und eingeloggt werden, vorher Adresse etc. updaten
            if($this->user_address->details_history->email_confirmed)
            {
                $this->user_connect_with_shoplogin();
                $this->user_do_login();
                $this->user_insert_address();
            }else
            {
                // wenn der User seine eMail-Adresse bei ShopLogin nicht bestätigt hat, aber im Shop bereits vorhanden ist,
                // darf der User nicht eingeloggt werden, sondern es soll ein Hinweis kommen, dass er bitte seine eMailadresse bei ShopLogin bestätigen soll ums ich anmelden zu können
                $message = $this->__('Error: You could not be logged in with ShopLogin, because your ShopLogin account is not confirmed. ShopLogin sent you an activiation link to your email address. Please click on the included link to activate your account.');
                $message .= "&nbsp; <font style='cursor:pointer; text-decoration:underline;' onclick='shoplogin.resend_activation()'>".$this->__('Resend&nbsp;Activation&nbsp;Email')."</font>";
                Mage::getSingleton('core/session')->addError($message);
                $this->user_redirect(); return;
                die("ShopLogin-eMail not confirmed - User with address already exists");
            }
            $this->user_redirect(); return;
        }

        // Neuen User-Account anlegen, mit ShopLogin verknüpfen, Adressen einfügen etc. und dann weiterleiten
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
        // Anlegen des User-Accounts mit zufälligem Passwort

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
        // Helper-Klasse zum Adresse hinzufügen, wenn der Flag billing_same_as_shipping gesetzt ist von ShopLogin, ist Liefer- und Rechnungsadresse die selbe und soll bei beidem als neuer Standardwert gelten

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
        // Hier wird eine neue Adresse dem Userkonto hinzugefügt und optional als Standardwert für Liefer- oder Rechnungsadresse gesetzt

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
        // Hier wird geprüft ob eine bestimmte von ShopLogin übermittelte Adresse bereits vorhanden ist im Userkonto
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
        // Hier wird in die ShopLogin-Tabelle die interne UserID sowie die ShopLogin-UserID und der Token geschrieben und somit verknüpft
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $write->insert(Mage::getSingleton('core/resource')->getTableName('shoplogin_customer'), array("customer_id" => (int)$this->customer_id, "shoplogin_id" => (int)$this->shoplogin_id, "data_token" => $this->user_basic->data_token));
    }

    private function user_update_connect_with_shoplogin()
    {
        // Hier wird in die ShopLogin-Tabelle im Grunde nur der Token upgedatet
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $write->update(Mage::getSingleton('core/resource')->getTableName('shoplogin_customer'), array("customer_id" => (int)$this->customer_id, "shoplogin_id" => (int)$this->shoplogin_id, "data_token" => $this->user_basic->data_token), "customer_id='".(int)$this->customer_id."' and shoplogin_id='".(int)$this->shoplogin_id."'");
    }

    private function user_already_registered_with_email()
    {
        // Hier wird abgefragt ob bereits ein Kunde mit der bei ShopLogin angegeben eMail-Adresse im Shop existiert
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
        // Hier wird abgefragt ob sich in der ShopLogin-Tabelle bereits ein Eintrag mit der ShopLogin-ID befindet und der User sich somit schonmal über ShopLogin im Shop angemeldet hat
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

    private function user_do_login()
    {
        // Hier wird der User eingeloggt in den Shop
        $session = Mage::getSingleton('customer/session');
        $temp = Mage::getModel('customer/customer')->load($this->customer_id);
        if($temp->getId())
        {
            $session->setCustomerAsLoggedIn($temp);
        }
    }

    private function user_redirect()
    {
        // Hier wird der User weitergeleitet an seine ursprüngliche URL (die URL wurde GET Get-Parameter verschlüsselt an die ShopLogin-Login-Url mitübergeben). Fall keine URL mitübergeben wurde, wird er einfach zu seiner Kundenkonto-Startseite weitergeleitet.
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