<?php namespace JFusion\Plugins\joomla_ext;

/**
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage JoomlaExt 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
use JFusion\Factory;
use JFusion\Plugin\Platform\Joomla;
use JText;

defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Authentication Class for an external Joomla database
 * For detailed descriptions on these functions please check the model.abstractauth.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Joomla_ext
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Platform_Joomla extends Joomla
{
	/**
	 * @param null $userinfo
	 *
	 * @throws RuntimeException
	 *
	 * @return array|string
	 */
	function setLanguageFrontEnd($userinfo = null) {
		$status = array('error' => '', 'debug' => '');
		// The language is selected by the library magelib when the magento framework is started
		/*if (JPluginHelper::isEnabled('system', 'magelib')) {
			$status['debug'] = Text::_('STEP_SKIPPED_MAGELIB_INSTALLED');
			return $status;
		}*/
		$cookies_to_set = array();
		/**
		 * To store the good information in the cookie concerning the language
		 * We need to get the code of the store view which should correspond to the language selected from Joomla
		 * Example: the store view code for english is 'en' but it could be 'english' or 'eng' (we can't give code
		 * as fr-FR or en-EN, punctuation is not allowed in magento)
		 * so we need to get this information from the admin plugin because from magento it's not
		 * possible to know which store view is for which language
		 * Then in joomla the english code is en-GB but in the store view code of magento it's 'en'
		 **/

		$language_store_view = $this->params->get('language_store_view', '');
		if (strlen($language_store_view) <= 0) {
			$status['debug'] = JText::_('NO_STORE_LANGUAGE_LIST');
		} else {
			require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.curl.php';

			// we define and set the cookie 'store'
			$langs = explode(';', $language_store_view);
			foreach ($langs as $lang) {
				$codes = explode('=', $lang);
				$joomla_code = $codes[0];
				$store_code = $codes[1];
				if ($joomla_code == Factory::getLanguage()->getTag()) {
					$cookies_to_set[0] = array('store=' . $store_code);
					break;
				}
			}
			$curl_options['cookiedomain'] = $this->params->get('cookie_domain');
			$curl_options['cookiepath'] = $this->params->get('cookie_path');
			$curl_options['expires'] = $this->params->get('cookie_expires');
			$curl_options['secure'] = $this->params->get('secure');
			$curl_options['httponly'] = $this->params->get('httponly');
			$status = Curl::setmycookies($status, $cookies_to_set, $curl_options['cookiedomain'], $curl_options['cookiepath'], $curl_options['expires'], $curl_options['secure'], $curl_options['httponly']);
		}
		return $status;
	}
}
