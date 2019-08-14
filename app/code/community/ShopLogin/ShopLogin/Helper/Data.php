<?php
/*
 * Log in with ShopLogin for Magento
 * https://www.shoplogin.com/for-merchants/
 * v1.4.0 for Magento
 */

class ShopLogin_ShopLogin_Helper_Data extends Mage_Core_Helper_Abstract  {

    protected $APP_ID;
    protected $APP_SECRET;
    protected $data_url = 'https://data.shoplogin.com/v1/';

    public function isEnabled()
    {
        if(Mage::getStoreConfig('shoplogin/settings_loginwith/enabled'))
        {
            return true;
        }
        return false;
    }

    public function WishlistEnabled()
    {
        if(Mage::getStoreConfig('shoplogin/settings_wishlist/enabled'))
        {
            return true;
        }
        return false;
    }

    public function ShowSeal()
    {
        if(Mage::getStoreConfig('shoplogin/settings/show_seal'))
        {
            return true;
        }
        return false;
    }

    public function RecommendationEnabled($what = false)
    {
        if(!$what && Mage::getStoreConfig('shoplogin/settings_recommendation/enabled'))
        {
            return true;
        }
        if($what == "product_viewed" && Mage::getStoreConfig('shoplogin/settings_recommendation/product_viewed') && Mage::getStoreConfig('shoplogin/settings_recommendation/enabled'))
        {
            return true;
        }
        if($what == "rightbar_interesting" && Mage::getStoreConfig('shoplogin/settings_recommendation/rightbar_interesting') && Mage::getStoreConfig('shoplogin/settings_recommendation/enabled'))
        {
            return true;
        }
        if($what == "homepage_popular" && Mage::getStoreConfig('shoplogin/settings_recommendation/homepage_popular') && Mage::getStoreConfig('shoplogin/settings_recommendation/enabled'))
        {
            return true;
        }
        if($what == "homepage_interesting" && Mage::getStoreConfig('shoplogin/settings_recommendation/homepage_interesting') && Mage::getStoreConfig('shoplogin/settings_recommendation/enabled'))
        {
            return true;
        }
        return false;
    }

    public function getClientId()
    {
        return Mage::getStoreConfig('shoplogin/settings/clientid');
    }

    public function getRecommendationLicenseKey()
    {
        return Mage::getStoreConfig('shoplogin/settings_recommendation/licensekey');
    }

    public function getClientSecret()
    {
        return Mage::getStoreConfig('shoplogin/settings/clientsecret');
    }

    public function getIsUserConnected()
    {
     // hier wird abgefragt ob der aktuelle User bereits mit ShopLogin über die Tabelle verknüpft ist.

     if(Mage::getSingleton('customer/session')->isLoggedIn())
     {

        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = $read->select()->from(Mage::getSingleton('core/resource')->getTableName('shoplogin_customer')) ->where("customer_id=?", $customer->getId());
        $results = $read->fetchAll($query);

        if (count($results) && isset($results[0]['shoplogin_id']))
        {
            return $results[0]['shoplogin_id'];
        }
     }
     return false;
    }

    public function init($APP_ID = false, $APP_SECRET = false)
    {

        if ( !function_exists('json_encode') ) {
          throw new Exception('Configuration error: ShopLogin needs the JSON extension');
        }
        if ( !function_exists('curl_init') ) {
          throw new Exception('Configuration error: ShopLogin needs the CURL extension');
        }

        if(!$APP_ID || !$APP_SECRET)
        {
            die('Configuration error: ShopLogin needs your APP_ID and APP_SECRET');
        }
        $this->appid = $APP_ID;
        $this->secret = $APP_SECRET;
    }

    public function get_user_isauthorized($signed_request = false)
    {
        // hier werden die Basisdaten des Users (eMail, Vorname, Nachame, Token) mit Hilfe des AppSecret aus dem verschlüsselten Cookie herausgelesen

        if(isset($_COOKIE['shoplogin_'.$this->appid]) && $_COOKIE['shoplogin_'.$this->appid])
        {
            $result = $this->parse_signed_request($_COOKIE['shoplogin_'.$this->appid], $this->secret);
            if(is_array($result) and isset($result['uid']))
            {
                return json_decode(json_encode($result));
            }
        }
        if($signed_request)
        {
            $result = $this->parse_signed_request($signed_request, $this->secret);
            if(is_array($result) and isset($result['uid']))
            {
                return json_decode(json_encode($result));
            }
        }
        return false;
    }

    public function get_address_fromuser($addon='')
    {
        // Helper-Klasse, wenn User per Cookie authentifiziert, dann Adresse per API abfragen

        $user = $this->get_user_isauthorized();
        if($user)
        {
            return $this->get_address_fromtoken($user->data_token, $addon);
        }else
        {
            return json_decode(json_encode(array('authorized'=>false, 'error'=>'user_not_logined')));
        }
    }

    public function get_address_fromtoken($data_token = '', $addon='')
    {
        // hier wird die API mit dem Token aus dem Cookie abgefragt, bei Erfolg liefert diese die Adressdaten des Users zurück

        if(strlen($data_token) != 74)
        {
            return json_decode(json_encode(array('authorized'=>false, 'error'=>'data_token_invalid')));
        }
        $url = $this->data_url.'public_connect_getaddressfromtoken/?appid='.$this->appid.'&data_token='.rawurlencode($data_token).$addon;
        $temp = json_decode($this->do_curl($url));
        if( !isset($temp->authorized))
        {
            $temp = json_decode(json_encode(array('authorized'=>false, 'error'=>'connection_error')));
        }
        return $temp;
    }

    protected function do_curl($url)
    {
        // Abfrage der Api mit CURL mit einigen zusätzlichen Parametern wie Version etc.
        // sowie einer Checksumme bestehend aus der "komplettenUrl#AppSecret"

        $url = $url."&version=magento-".Mage::getConfig()->getModuleConfig("ShopLogin_ShopLogin")->version."&shop_system=magento-".Mage::getVersion();
        $url = $url.'&checksum='.md5($url.'#'.$this->secret);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'sl-magento-1.4.0');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    protected function parse_signed_request($signed_request, $APP_SECRET)
    {
        $temp = explode('.', $signed_request);
        if(!is_array($temp) || count($temp) != 2)
        {
            return null;
        }

        $data = json_decode($this->base64_url_decode($temp[1]), true);

        if(is_array($data) and $this->base64_url_decode($temp[0]) == hash_hmac('sha256', json_encode($data), $APP_SECRET, true))
        {
            return $data;
        }
        return null;
    }

    protected function base64_url_decode($input)
    {
        return base64_decode(strtr($input, '-_', '+/'));
    }

}

?>
