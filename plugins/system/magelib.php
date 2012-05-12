<?php
/**
* @package JFusion
* @subpackage System_Plugin
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
defined ( '_JEXEC' ) or trigger_error ( 'Restricted access' );

$jfusion_component = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.factory.php';
if(file_exists ( $jfusion_component )):
	require_once $jfusion_component;
else:
	JError::raiseWarning(0, 'MageLib: The file '.$jfusion_component.' doesn\'t exists. Please install JFusion component or update it.');
endif;
		
class plgSystemMagelib {
	
	private $_OldSessionId;
	private $_OldSessionName;
	private $_OldCookie;
	private $_OldSessionData;
	
	public $params;
	public $mage_path;
	public $mage_url;
	
	function __construct() {
		$plugin = & JPluginHelper::getPlugin ( 'system', 'magelib' );
		$this->params = new JParameter ( $plugin->params );
		
		$mage_plugin = $this->params->get ( 'mage_plugin', 'magento' );
		$mage_path = JFusionFactory::getParams ( $mage_plugin )->get ( 'source_path', false );
		$this->mage_url = JFusionFactory::getParams ( $mage_plugin )->get ( 'source_url', false );
		
		if (! $mage_path) {
			$this->mage_path = $this->params->get ( 'mage_path' );
		}else{
			$this->mage_path = $mage_path;
		}		
	}
	
	/**
	 * Joomla - destroy session to manage to get the Magento session - Both mechanism are different
	 * Need to be used if session of Magento needed
	 *
	 * @access public
	 * @param none
	 * @return void
	 *
	 */
	function destroyTemporaryJoomlaSession() {
		$this->_OldSessionId = session_id ();
		$this->_OldSessionName = session_name ();
		$this->_OldCookie = session_get_cookie_params ();
		$this->_OldSessionData = $_SESSION;
		session_write_close ();
		// Necessary to unset $_SESSION to allow Magento core to set his own session
		// $_SESSION must be restored after the Magento process
		// @see plgSystemMagelib::restartJoomlaSession();
		unset ( $_SESSION );
		return;
	}
	
	/**
	 * Load the bootstrap of Magento and start it
	 *
	 * @return boolean
	 */
	function loadAndStartMagentoBootstrap() {
		/**
		 * If joomla is called from a curl call and the curl is dealing with magento session, a problem will come with this plugin
		 * The session of the user will be destroyed and a new one will be created. We don't want that. So the curl must provide the jnodeid to fix it.
		 * I think it's a temporar solution, the JFusion curl must know how to deal better with session cookies
		 */
		//@todo perform for a plugin name different of 'magento' in the getPluginNodeId()
		if (JRequest::getVar ( 'jnodeid', null ) != JFusionFactory::getPluginNodeId ( 'magento' )) {
			
			$defaultStore = null;
			
				$mage_plugin = $this->params->get ( 'mage_plugin', 'magento' );
				$language_store_view = JFusionFactory::getParams ( $mage_plugin )->get ( 'language_store_view', '' );
				
				if (strlen ( $language_store_view ) > 0) {
					// we define and set the default store (and language if set correctly by the administrator)
					$JLang = &JFactory::getLanguage ();
					$langs = explode ( ";", $language_store_view );
					foreach ( $langs as $lang ) {
						$codes = explode ( "=", $lang );
						if ($codes [0] == $JLang->_lang) {
							$defaultStore = $codes [1];
							break;
						}
					}
				}
			
			$bootstrap = $this->mage_path . 'app/Mage.php';
			
			if (! file_exists ( $bootstrap )) {
				$error_message = JText::sprintf ( 'The file %s doesn\'t exists', $bootstrap );
				$error = JError::raiseWarning ( 0, $error_message );
				$error->message = get_class ( $this ) . '::loadAndStartMagentoBootstrap - ' . $error_message;
				JError::handleLog ( $error, null );
				return false;
			}
			
			if (! isset ( $defaultStore )) {
				$defaultStore = $this->params->get ( 'mage_store', '' );
			}
			
			/**
			 * Hack for language selection through the view store - The cookie 'store' is not send to the client
			 * if in Magento 'Add Store Code to Urls' is set to 'Yes'. So when the language is changed from Joomla and you use the jfusion plugin system
			 * this one set the language frontend thanks to a cookie, not with an url. So you need here to force the Magento core to get this new cookie value
			 * otherwise he won't take in consideration the $defaultStore
			 */
			if (isset($_COOKIE ['store']) && $_COOKIE['store'] != $defaultStore) {
				$_COOKIE ['store'] = $defaultStore;
			}
			// Hack for Joomla to force it to use an autoload method via SPL
			// Though need to comment in Magento the __autoload function (deprecated) at /app/code/Mage/Core/functions.php
			spl_autoload_register ( '__autoload' );
			require_once $bootstrap;
			// DO NOT DELETE - it registers the autoload of Magento when more than one times the magelib is called
			Varien_Autoload::register ();
			static $app = false;
			if(!$app){
				umask ( 0 );
				$app = Mage::app ( $defaultStore );
				// Necessary to load the correct language files in the store view. Maybe others loadAreaPart will be necessary to load in future
				$app->loadAreaPart ( Mage_Core_Model_App_Area::AREA_FRONTEND, Mage_Core_Model_App_Area::PART_TRANSLATE );
				$app->loadAreaPart ( Mage_Core_Model_App_Area::AREA_FRONTEND, Mage_Core_Model_App_Area::PART_EVENTS );
			}
			return $app;
		} else {
			return false;
		}
	}
	
	/**
	 * Start a Magento Session. Necessary if you want to share information between
	 * Magento and Joomla modules through the session
	 *
	 * @param none
	 * @return void
	 */
	function startMagentoSession() {
		ob_start ();
		if (array_key_exists ( 'frontend', $_COOKIE )) {
			session_id ( $_COOKIE ['frontend'] );
		} else {
			$id = JSession::_createId ();
			$_COOKIE ['frontend'] = $id;
			session_id ( $id );
		}
		
		//force to use the frontend session name because it seems not correctly defined when two instance use this plugin
		session_name ( 'frontend' );
		Mage::getSingleton ( 'core/session', array ('name' => 'frontend' ) );
		ob_end_clean ();
		return;
	}
	
	/**
	 * Before to go on to use the framework of Joomla,
	 * The autoload MUST be removed to allow to use the one of Joomla
	 *
	 * @param none
	 * @return void
	 */
	function stopMagentoSession() {
		ob_start ();
		session_write_close ();
		unset ( $_SESSION );
		self::unregisterMagentoAutoload ();
		ob_end_clean ();
		return;
	}
	
	/**
	 * Conflict with Joomla autoload if Varien instance not unregister
	 *
	 * @param none
	 * @return void
	 */
	function unregisterMagentoAutoload() {
		$Varien_instance = Varien_Autoload::instance ();
		spl_autoload_unregister ( array ($Varien_instance, 'autoload' ) );
	}
	
	/**
	 * Restart the Joomla session with current values
	 *
	 * @param none
	 * @return void
	 */
	function restartJoomlaSession() {
		// Restart Joomla session
		session_id ( $this->_OldSessionId );
		session_name ( $this->_OldSessionName );
		ini_restore ( 'session.save_path' );
		ini_set ( 'session.save_handler','files');
		ini_set ( 'session.use_trans_sid', '0' );
		session_set_cookie_params ( $this->_OldCookie ['lifetime'], $this->_OldCookie ['path'], $this->_OldCookie ['domain'], $this->_OldCookie ['secure'] );
		session_start ();
		// reload the data created before the destruction of the session
		$_SESSION = $this->_OldSessionData;
		return;
	}
	
	function getMagePath(){
		return $this->mage_path;
	}
	
	function getMageUrl(){
		return $this->mage_url;
	}
}	