<?php
/**
 * @package JFusion
 * @author JFusion development team
 * @copyright Copyright (C) 2009 JFusion. All rights reserved.
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Jfusion_Joomla_Helper_Adapter_Curl extends Varien_Http_Adapter_Curl {

	public $data;

	public $error_message;

	public $error_number;

    private $_resource;

    /**
     *
     */
    function __construct() {
		// check if curl extension is loaded
		if (! extension_loaded ( 'curl' )) {
			return Mage::helper('jfusion')->__( 'CURL_LIBRARY_NOT_INSTALLED' );
		}
        return true;
	}

    /**
     * @param string $url
     * @return mixed
     */
    function init($url = '') {
		$this->_resource = curl_init ( $url );
		return $this->_resource;
	}

    /**
     * @param $array
     */
    function setOptions($array) {
		foreach ( $array as $value ) {
			$curlopt = $value ['key'];
			$curlval = $value ['value'];
			$this->setOption ( $curlopt, $curlval );
		}
	}

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    function setOption($key, $value) {
		return curl_setopt ( $this->_resource, $key, $value );
	}
	
	function exec() {
		if ($this->_resource) {
			$this->data = curl_exec ( $this->_resource );
			if (curl_error ( $this->_resource )) {
				$this->error_message = curl_error ( $this->_resource );
				$this->error_number = curl_errno ( $this->_resource );
				Mage::log($this->error_number . ': ' .$this->error_message, 0, Mage::getStoreConfig('dev/log/exception_file'));
			}
			
			// Remove 100 and 101 responses headers
			if (Zend_Http_Response::extractCode ( $this->data ) == 100 || Zend_Http_Response::extractCode ( $this->data ) == 101) {
				$this->data = preg_split ( '/^\r?$/m', $this->data, 2 );
				$this->data = trim ( $this->data [1] );
			}
			
			return $this->data;
		} else {
			return false;
		}
	}
}