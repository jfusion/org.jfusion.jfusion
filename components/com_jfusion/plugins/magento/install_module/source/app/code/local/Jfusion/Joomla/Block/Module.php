<?php
/**
 * @package JFusion
 * @author JFusion development team
 * @copyright Copyright (C) 2009 JFusion. All rights reserved.
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Jfusion_Joomla_Block_Module extends Mage_Core_Block_Template {

    private $_data;

	/**
	 * Save the data of each modules before to render them
	 * $type: it should be the name of the type of module loaded (exple: mod_mainmenu -> $type=mainmenu) - required
	 * $title: the title provided in the backend of Joomla for the specified module (exple: Footer) - optionnal
	 * $id: it's the id of the module, you can find it in the module list in your backend, in the right column ID - optionnal
	 * $style: xhtml, none or chrome style of your template
	 * If you don't provide id or title but type, it will generate a dummy module of the module's type
	 *
	 * @param string $type
	 * @param string $title
     * @param mixed $id
	 * @param string $style
	 * @param bool $debug
     *
	 * @return object Jfusion_Joomla_Block_Module
	 */
	public function loadJModule($type = '', $title = '', $id = null, $style = 'none', $debug = false) {
		$key = $title = urlencode($title);
		$this->_data ['modules'] [$type . '/' . $key] ['type'] = $type;
		$this->_data ['modules'] [$type . '/' . $key] ['title'] = $title;
		$this->_data ['modules'] [$type . '/' . $key] ['id'] = $id;
		$this->_data ['modules'] [$type . '/' . $key] ['style'] = $style;
		$this->_data ['modules'] [$type . '/' . $key] ['debug'] = $debug;
		return $this;
	}

	/**
	 * Load the joomla module from the joomla installation
	 *
	 * @return string $output
	 */
	public function getModule() {
		try{
    	    $out = '';
    		if (is_array ( $this->_data ['modules'] )) {
    			foreach ( $this->_data ['modules'] as $module ) {
    				$out .= $this->helper ( 'joomla/module' )->getJoomlaModule ( $module ['type'], $module ['title'], $module ['id'], $module['style'], $module['debug'] );
    			}
    		}
		}catch(Mage_Core_Exception $e){
		    Mage::log($e);
		    Mage::getSingleton('customer/session')->addError($e->getMessage());
		}
		
		if ($this->getPrepareLayoutMenu() == '1' && strlen($out) > 0) {
			if (strpos ( $out, '<ul' ) == 0) {
				$pos = strpos ( $out, '>' );
				 //remove first tag <ul> and </ul>
				$out = substr ( $out, $pos + 1 );
				$out = substr ( $out, 0, (strlen ( $out ) - 5) );
			}
		}
		return $out;
	}
	
	/**
	* This method allows you to get only li tags from a Joomla menu or any module using only <ul><li>...</li></ul>
	* Very usefull to integrate a menu module to Magento for example. You need then to bring the module into the catalog/navigation/top.html for example
	*
    * @param $value
	*/	
	public function setPrepareLayoutMenu($value){
		parent::setPrepareLayoutMenu($value);
		return;
	}

    /**
     * @return string
     */
    protected function _toHtml() {
		if (! $this->_beforeToHtml ()) {
			return '';
		}
		
		return $this->getModule ();
	}
}

