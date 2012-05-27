<?php
/**
 * @package JFusion
 * @author JFusion development team
 * @copyright Copyright (C) 2009 JFusion. All rights reserved.
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Jfusion_Joomla_Helper_Module extends Mage_Core_Helper_Abstract {
	
	const CACHE_TAG = 'joomla_module';
	const CACHE_LIFETIME = false;

    /**
     * @param string $type
     * @param string $title
     * @param null $id
     * @param string $style
     * @param bool $debug
     * @return bool|mixed
     */
    function getJoomlaModule($type = '', $title = '', $id = null, $style = 'none', $debug = false) {
		
		$storeCacheData = false;
		if (Mage::helper ( 'joomla/data' )->isCacheActivated ()) {
			if (! $data = $this->_loadCache ( 'joomla_module_' . Mage::app ()->getStore ()->getCode () . $type . $id . $id )) {
				$storeCacheData = true;
			} else {
				return $data;
			}
		}
		
		static $count = 0;
		Varien_Profiler::start ( 'JFUSION JOOMLA MODULE: moduletitle_' . $type );
		
		$configData = Mage::helper ( 'joomla/data' );
		$cookiesHelper = Mage::helper ( 'joomla/cookies' );
		
		$store_code = Mage::app ()->getStore ()->getCode ();
		
		//set ONLY once the current language of Magento to Joomla cookie
		if (array_key_exists ( 'jfcookie', $_COOKIE ) && $_COOKIE ['jfcookie'] ['lang'] != $store_code && $count == 0) :
			$cookie_path = Mage::app ()->getStore ()->getConfig ( 'web/cookie/cookie_path' );
			$cookie_domain = Mage::app ()->getStore ()->getConfig ( 'web/cookie/cookie_domain' );
			$cookie_lifetime = Mage::app ()->getStore ()->getConfig ( 'web/cookie/cookie_lifetime' );
			
			$cookiesHelper->setCookies ( array (array ('jfcookie[lang]=' . $store_code ) ), null, $cookie_path, $cookie_lifetime, 0, 0 );
			$cookiesHelper->setCookies ( array (array ('store=' . $store_code ) ), $cookie_domain, $cookie_path, $cookie_lifetime, 0, 0 );
		
		endif;
		$cookies = $cookiesHelper->getCookies ( 'string' );
		
		/* Construct the url */
		$url_params = '';
		if(isset($title) && $title != '')	$url_params .= '&title='.$title;
		if(isset($id) && $id != '')			$url_params .= '&id='.$id;
		if(isset($style) && $style != '')	$url_params .= '&style='.$style;
		if(isset($type) && $type != '')		$url_params .= "&modulename=".$type;
		
		$url = $configData->getJSecureBaseUrl() . 'index.php?option=com_jfusion&controller=connect&task=module&lang=' . $store_code . '&secret=' . $configData->getSecretKey() . $url_params;
		
		$curl = Mage::helper ( 'joomla/adapter_curl' );
		$curl->init ();
		$curlopt = array ();
		$curlopt [] = array ('key' => CURLOPT_URL, 'value' => $url );
		//$curlopt [] = array ('key' => CURLOPT_URL, 'value' => $configData->getBaseUrl () . 'index.php?tmpl=' . $configData->getTemplateModule () . '&lang=' . $store_code . '&secret=' . $configData->getSecretKey () . "&modulename=$type&title=$title&style=$style" );
		$curlopt [] = array ('key' => CURLOPT_USERAGENT, 'value' => 'PHP_CURL' ); // Provide a user agent 'CURL' to identify who try to connect to the jfusion controller for statistics by example.
		$curlopt [] = array ('key' => CURLOPT_COOKIE, 'value' => $cookies );
		$curlopt [] = array ('key' => CURLOPT_SSL_VERIFYPEER, 'value' => 0 );
		$curlopt [] = array ('key' => CURLOPT_SSL_VERIFYHOST, 'value' => 1 );
		$curlopt [] = array ('key' => CURLOPT_FAILONERROR, 'value' => true );
		$curlopt [] = array ('key' => CURLOPT_MAXREDIRS, 'value' => 4 );
		$curlopt [] = array ('key' => CURLOPT_RETURNTRANSFER, 'value' => true );
		if ((ini_get ( 'open_basedir' ) == '') && (ini_get ( 'safe_mode' ) == '')) {
			$curlopt [] = array ('key' => CURLOPT_FOLLOWLOCATION, 'value' => true );
		}
		
		$count ++;
		
		$curl->setOptions ( $curlopt );
		$data = $curl->exec ();
		Varien_Profiler::stop ( 'JFUSION JOOMLA MODULE: moduletitle_' . $type );
		
		if ($curl->getErrno()) {
			Mage::log($curl->getError() . ': ' . $curl->getError(), 0, Mage::getStoreConfig('dev/log/exception_file'));
			return false;
		}
		
		if ($debug) {
			print_r ( $curl->getInfo () );
		}
		
		if(Mage::helper('joomla/data')->isCacheActivated() && $storeCacheData){
			$this->_saveCache($data, 'joomla_module_' . Mage::app()->getStore()->getCode () . $type . $id . $id, array(self::CACHE_TAG), self::CACHE_LIFETIME);
		}
		//$cookieHelper = $cookiesHelper->instance ( $curl->ch );
		//$cookieHelper->setCookies ( $cookieHelper->cookies_to_set );
		$curl->close ();
		
		return $this->_prepareUrlRewrite($data);
	}

    /**
     * @param $data
     * @return bool|mixed
     */
    protected function _prepareUrlRewrite($data){
		if($data){
    	    $configData = Mage::helper ( 'joomla/data' );
    		// Base Url from Config - $configData->getJSecureBaseUrl() = Base url from config + secure or not (depending on current statement)
    		$baseUrl = str_replace('/','\/',rtrim($configData->getData('baseurl'), '/') . '/');
    		
    		$search = array(
    			'/\s(href=\"(index.php|images|templates))(\S+)/',
    			'/\s(src=\"(index.php|images|templates))(\S+)/',
    			'/\s(src=\"'.$baseUrl.'(index.php|images|templates))(\S+)/'
    		);
    		$replace = array(
    			' href="'.$configData->getJSecureBaseUrl().'\\2\\3',
    			' src="'.$configData->getJSecureBaseUrl().'\\2\\3',
    			' src="'.$configData->getJSecureBaseUrl().'\\2\\3'
    		);
    		return preg_replace($search, $replace, $data);
		}
		return false;
	}
	
}