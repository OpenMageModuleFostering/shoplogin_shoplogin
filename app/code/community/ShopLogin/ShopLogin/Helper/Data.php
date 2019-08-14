<?php
/*
 * Log in with ShopLogin for Magento
 * https://www.shoplogin.com/for-merchants/
 * v0.9.4 for Magento
 */

class ShopLogin_ShopLogin_Helper_Data  {

    protected $APP_ID;
    protected $APP_SECRET;
    protected $redirect_url_login;
    protected $redirect_url_logout;
    protected $data_url = 'https://data.shoplogin.com/v001/';

    public function isEnabled()
    {
        if(Mage::getStoreConfig('shoplogin/settings_loginwith/enabled'))
        {
            return true;
        }
        return false;
    }

    public function AffiliateisEnabled()
    {
        if(Mage::getStoreConfig('shoplogin/settings_affiliate/enabled'))
        {
            return true;
        }
        return false;
    }

    public function TrackingPlusisEnabled()
    {
        if(Mage::getStoreConfig('shoplogin/settings_trackingplus/enabled'))
        {
            return true;
        }
        return false;
    }

    public function getClientId()
    {
        return Mage::getStoreConfig('shoplogin/settings/clientid');
    }

    public function getClientSecret()
    {
        return Mage::getStoreConfig('shoplogin/settings/clientsecret');
    }

    public function getIsUserConnected()
    {
     // is the session-user connected with ShopLogin?
     if(Mage::getSingleton('customer/session')->isLoggedIn())
     {
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $results = $read->fetchAll() ->from(Mage::getSingleton('core/resource')->getTableName('shoplogin_customer')) ->where("customer_id='?'", $customer->getId());
        if (count($results) && isset($results[0]['shoplogin_id']))
        {
            return $results[0]['shoplogin_id'];
        }
     }
     return false;
    }

    public function init($APP_ID = false, $APP_SECRET = false, $redirect_url_login = 'auto', $redirect_url_logout = 'auto')
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
        $this->redirect_url_login = $redirect_url_login;
        $this->redirect_url_logout = $redirect_url_logout;
        
        $this->auto_login_logout();
    }

    public function get_user_isauthorized($signed_request = false)
    {
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

    public function do_login($typ = 'address', $state = '', $redirect_url = 'auto')
    {
        header("LOCATION:".$this->get_login_url($typ, $state, $redirect_url));
    }

    public function do_logout($state = '', $redirect_url = 'auto')
    {
        header("LOCATION:".$this->get_logout_url($state, $redirect_url));
    }

    public function get_login_url($typ = 'address', $state = '', $redirect_url = 'auto')
    {
        if(!in_array($typ, array('address', 'login'))) { $typ = 'address'; }
        return 'https://www.shoplogin.com/account/?appid='.$this->appid.'&version=1&callback=redirect&method=connect&action='.$typ.'&redirect='.rawurlencode($redirect_url).'&state='.rawurlencode($state);
    }

    public function get_logout_url($state = '', $redirect_url = 'auto')
    {
        return 'https://www.shoplogin.com/account/?appid='.$this->appid.'&version=1&callback=redirect&method=connect&action=logout&redirect='.rawurlencode($redirect_url).'&state='.rawurlencode($state);
    }

    protected function auto_login_logout()
    {
        $domain = '';
        $accesscode = '';
        $authorized = '';
        $redirect = '';

        if(isset($_GET['sl_domain']))
        {
            $domain = $_GET['sl_domain'];
        }
        if(isset($_GET['sl_access']))
        {
            $accesscode = $_GET['sl_access'];
        }
        if(isset($_GET['sl_authorized']))
        {
            $authorized = $_GET['sl_authorized'];
        }
        if($authorized == 'false' || $authorized == 'true')
        {
            $redirect = $this->redirect_url_logout;
            setcookie('shoplogin_'.$this->appid, '', 0, '/', $domain);
            $_COOKIE['shoplogin_'.$this->appid] = '';
        }
        if($authorized == 'true' and $accesscode)
        {
              $redirect = $this->redirect_url_login;
              if($this->get_user_isauthorized($accesscode))
              {
                  setcookie('shoplogin_'.$this->appid, $accesscode, 0, '/', $domain);
                  $_COOKIE['shoplogin_'.$this->appid] = $accesscode;
              }
        }

        if($domain || $accesscode || $authorized)
        {
            if( ($authorized == 'true' && $this->redirect_url_login == 'auto') )
            {
                $redirect = $this->clean_url(getenv('REQUEST_URI'));
            }
            if( ($authorized == 'false' && $this->redirect_url_logout == 'auto') )
            {
                $redirect = $this->clean_url(getenv('REQUEST_URI'));
            }
            header('LOCATION:'.$redirect);
        }
    }

    protected function clean_url($url)
    {
        foreach(array('sl_access', 'sl_authorized', 'sl_domain') as $key)
        {
            if(isset($_GET[$key]))
            {
                $url = str_replace($key.'='.$_GET[$key], '', $url);
                $url = str_replace('&&', '&', $url);
            }
        }
        $url = str_replace('?&', '?', $url);
        if(substr($url, strlen($url)-1) =='?' || substr($url, strlen($url)-1) == '&') { $url = substr($url,0, strlen($url)-1); }
        return $url;
    }

    protected function do_curl($url)
    {
        $url = $url."&version=magento-".Mage::getConfig()->getModuleConfig("ShopLogin_ShopLogin")->version."&shop_system=magento-".Mage::getVersion();
        $url = $url.'&checksum='.md5($url.'#'.$this->secret);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'sl-php-001');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    protected function parse_signed_request($signed_request, $APP_SECRET) {
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

    protected function base64_url_decode($input) {
        return base64_decode(strtr($input, '-_', '+/'));
    }




}

?>