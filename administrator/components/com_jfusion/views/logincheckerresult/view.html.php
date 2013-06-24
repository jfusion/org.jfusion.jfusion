<?php

/**
 * This is view file for logincheckerresult
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Logincheckerresults
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Renders the main admin screen that shows the configuration overview of all integrations
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Logincheckerresults
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class jfusionViewLoginCheckerResult extends JViewLegacy
{
	/**
	 * displays the view
	 *
	 * @param string $tpl template name
	 *
	 * @return mixed html output of view
	 */
	function display($tpl = null)
	{
		//get the submitted login details
		$credentials['username'] = JRequest::getVar('check_username');
		$credentials['password'] = JRequest::getVar('check_password', '', 'post', 'string', JREQUEST_ALLOWRAW);
		//setup the options array
		$options = array();
		if (JRequest::getVar('remember') == 1) {
			$options['remember'] = 1;
		} else {
			$options['remember'] = 0;
		}
		if (JRequest::getVar('show_unsensored') == 1) {
			$options['show_unsensored'] = 1;
		} else {
			$options['show_unsensored'] = 0;
		}
		if (JRequest::getVar('skip_password_check') == 1) {
			$options['skip_password_check'] = 1;
		} else {
			$options['skip_password_check'] = 0;
		}
		if (JRequest::getVar('overwrite') == 1) {
			$options['overwrite'] = 1;
		} else {
			$options['overwrite'] = 0;
		}

		//prevent current joomla session from being destroyed
		global $JFusionActivePlugin, $JFusionLoginCheckActive;
		$JFusionActivePlugin = 'joomla_int';
		$JFusionLoginCheckActive = true;

		$this->getPlugin();

		$this->getAuth($credentials, $options);

		$this->assignRef('options', $options);
		parent::display($tpl);
	}

	function getPlugin()
	{
		$plugins = array();
		//output the current configuration
		$db = JFactory::getDBO();
		$query = 'SELECT * from #__jfusion WHERE master = 1 OR slave = 1 or check_encryption = 1 ORDER BY master DESC;';
		$db->setQuery($query);
		$plugin_list = $db->loadObjectList();
		foreach ($plugin_list as $plugin_details) {
			$plugin = new stdClass;
			$plugin->name = $plugin_details->name;
			if ($plugin_details->original_name) {
				$plugin->original_name = $plugin_details->original_name;
			}
			$plugin->configuration = new stdClass;
			$plugin->configuration->master = $plugin_details->master;
			$plugin->configuration->slave = $plugin_details->slave;
			$plugin->configuration->dual_login = $plugin_details->dual_login;
			$plugin->configuration->check_encryption = $plugin_details->check_encryption;
			$plugins[] = $plugin;
		}
		$this->assignRef('plugins', $plugins);
	}

	/**
	 * @param $credentials
	 * @param $options
	 */
	function getAuth($credentials, $options)
	{
		global $jfusionDebug;
		/**
		 * Launch Authentication Plugin Code
		 */
		// Initialize variables
		jimport('joomla.user.authentication');
		$authenticate = JAuthentication::getInstance();
		$auth = false;
		// Get plugins
		$plugins = JPluginHelper::getPlugin('authentication');
		//add Jfusion plugin
		$jfusion_auth = array('type' => 'authentication', 'name' => 'jfusion', 'params' => '');
		$plugins[] = (object)$jfusion_auth;
		//remove joomla plugin and load model
		foreach ($plugins as $key => $value) {
			if ($value->name == 'joomla') {
				unset($plugins[$key]);
			} else {
				include_once JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'authentication' . DIRECTORY_SEPARATOR . $value->name. DIRECTORY_SEPARATOR .$value->name . '.php';
			}
		}
		// Create Authentication response
		$response = new JAuthenticationResponse();
		/**
		 * @ignore
		 * @var $plugin plgAuthenticationjfusion
		 */
		foreach ($plugins as $plugin) {
			$className = 'plg' . $plugin->type . $plugin->name;
			if (class_exists($className)) {
				$plugin = new $className($this, (array)$plugin);
			}
			// Try to authenticate
			$plugin->onUserAuthenticate($credentials, $options, $response);
			// If authentication is successfully break out of the loop
			if ($response->status === JAUTHENTICATE_STATUS_SUCCESS) {
				if (empty($response->type)) {
					$response->type = $plugin->name;
				}
				if (empty($response->username)) {
					$response->username = $credentials['username'];
				}
				if (empty($response->fullname)) {
					$response->fullname = $credentials['username'];
				}
				if (empty($response->password)) {
					$response->password = $credentials['password'];
				}
				break;
			}
		}
		//check to see if JFusion auth plugin was used
		if (isset($response->userinfo)) {
			//hide sensitive information
			$auth_userinfo = clone ($response->userinfo);
		} else {
			//non jfusion auth plugin was used
			$auth_userinfo = clone ($response);
		}

		if (empty($options['show_unsensored'])) {
			//hide sensitive data
			$auth_userinfo = JFusionFunction::anonymizeUserinfo($auth_userinfo);

		}
		if (!empty($response->error_message)) {
			//clean up empty params for easier reading
			unset($auth_userinfo->fullname, $auth_userinfo->birthdate, $auth_userinfo->gender, $auth_userinfo->postcode, $auth_userinfo->country, $auth_userinfo->language, $auth_userinfo->timezone, $auth_userinfo->type);
		}

		$auth_results = array();
		if ($response->status === JAUTHENTICATE_STATUS_SUCCESS) {
			/**
			 * Launch User Plugin Code
			 */
			// Get plugins
			$plugins = JPluginHelper::getPlugin('user');
			$jfusion_user_plugin = 0;
			//remove joomla plugin and load model
			foreach ($plugins as $key => $value) {
				if ($value->name == 'joomla') {
					unset($plugins[$key]);
				}
				if ($value->name == 'jfusion') {
					$jfusion_user_plugin = 1;
				}
			}
			if ($jfusion_user_plugin == 0) {
				//add Jfusion plugin
				$jfusion_user = array('type' => 'user', 'name' => 'jfusion', 'params' => '');
				$plugins[] = (object)$jfusion_user;
			}

			foreach ($plugins as $plugin) {
				include_once JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . $plugin->name .  DIRECTORY_SEPARATOR . $plugin->name . '.php';
				$className = 'plg' . ucfirst($plugin->type) . ucfirst($plugin->name);
				$plugin_name = $plugin->name;
				if (class_exists($className)) {
					$plugin = new $className($this, (array)$plugin);
				}

				$method_name = 'onUserLogin';
				if (method_exists($plugin, $method_name)) {
					// Try to authenticate
					$user_results = (array)$response;
					$results = $plugin->$method_name($user_results, $options);

					$result = new stdClass;
					$result->result = $results;
					$result->debug = $jfusionDebug;
					$auth_results[$plugin_name] = $result;
				}
			}
		}

		$this->assignRef('auth_userinfo', $auth_userinfo);
		$this->assignRef('response', $response);
		$this->assignRef('auth_results', $auth_results);
	}

	/**
	 * function to override the default attach function
	 *
	 * @param string $sample sample name
	 *
	 * @return string nothing
	 */
	function attach($sample)
	{
	}
}
