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
use JFusion\Debugger\Debugger;
use JFusion\User\Userinfo;

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
	 * @var array $options
	 */
	var $options;

	/**
	 * @var $plugins array
	 */
	var $plugins = array();

	/**
	 * @var $auth_results array
	 */
	var $auth_results = array();

	/**
	 * @var $response JAuthenticationResponse
	 */
	var $response;

	/**
	 * @var $auth_userinfo stdClass
	 */
	var $auth_userinfo;

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
		$credentials['username'] = JFactory::getApplication()->input->get('username', '', 'username');
		$credentials['password'] = JFactory::getApplication()->input->post->get('password', '', 'raw');
		//setup the options array
		$options = array();
		if (JFactory::getApplication()->input->get('remember') == 1) {
			$options['remember'] = 1;
		} else {
			$options['remember'] = 0;
		}
		if (JFactory::getApplication()->input->get('show_unsensored') == 1) {
			$options['show_unsensored'] = 1;
		} else {
			$options['show_unsensored'] = 0;
		}
		if (JFactory::getApplication()->input->get('skip_password_check') == 1) {
			$options['skip_password_check'] = 1;
		} else {
			$options['skip_password_check'] = 0;
		}
		if (JFactory::getApplication()->input->get('overwrite') == 1) {
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

		$this->options = $options;
		parent::display($tpl);
	}

	function getPlugin()
	{
		//output the current configuration
		$db = JFactory::getDBO();

		$plugins = \JFusion\Factory::getPlugins();
		foreach ($plugins as $plug) {
			$plugin = new stdClass;
			$plugin->name = $plug->name;
			if ($plug->original_name) {
				$plugin->original_name = $plug->original_name;
			}
			$plugin->configuration = new stdClass;
			$plugin->configuration->master = $plug->master;
			$plugin->configuration->slave = $plug->slave;
			$plugin->configuration->dual_login = $plug->dual_login;
			$plugin->configuration->check_encryption = $plug->check_encryption;
			$this->plugins[] = $plugin;
		}
	}

	/**
	 * @param $credentials
	 * @param $options
	 */
	function getAuth($credentials, $options)
	{
		/**
		 * Launch Authentication Plugin Code
		 */
		// Initialize variables
		jimport('joomla.user.authentication');
		JAuthentication::getInstance();

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
				include_once JPATH_SITE . '/plugins/authentication/' . $value->name. '/' . $value->name . '.php';
			}
		}
		// Create Authentication response
		$response = new JAuthenticationResponse();
		/**
		 * @var $plugin plgAuthenticationjfusion|plgUserJfusion
		 */
		foreach ($plugins as $plugin) {
			/** @noinspection PhpUndefinedFieldInspection */
			$className = 'plg' . ucfirst($plugin->type) . $plugin->name;
			if (class_exists($className)) {
				$plugin = new $className($this, (array)$plugin);
			}
			// Try to authenticate
			$plugin->onUserAuthenticate($credentials, $options, $response);
			// If authentication is successfully break out of the loop
			if ($response->status === JAuthentication::STATUS_SUCCESS) {
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
		if (isset ($response->userinfo) && $response->userinfo instanceof Userinfo) {
			$auth_userinfo = $response->userinfo->toObject();
		} else {
			//non jfusion auth plugin was used
			$auth_userinfo = clone($response);
		}
		if (empty($options['show_unsensored'])) {
			$auth_userinfo = JFusionFunction::anonymizeUserinfo($auth_userinfo);
		}

		if (!empty($response->error_message)) {
			//clean up empty params for easier reading
			unset($auth_userinfo->fullname, $auth_userinfo->birthdate, $auth_userinfo->gender, $auth_userinfo->postcode, $auth_userinfo->country, $auth_userinfo->language, $auth_userinfo->timezone, $auth_userinfo->type);
		}

		if ($response->status === JAuthentication::STATUS_SUCCESS) {
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
				include_once JPATH_SITE . '/plugins/user/' . $plugin->name .  '/' . $plugin->name . '.php';
				/** @noinspection PhpUndefinedFieldInspection */
				$className = 'plg' . ucfirst($plugin->type) . ucfirst($plugin->name);
				$plugin_name = $plugin->name;
				if (class_exists($className)) {
					$plugin = new $className($this, (array)$plugin);
				}

				if (method_exists($plugin, 'onUserLogin')) {
					// Try to authenticate
					$user_results = (array)$response;
					$results = $plugin->onUserLogin($user_results, $options);

					$result = new stdClass;
					$result->result = $results;
					$result->debug = Debugger::getInstance('jfusion-loginchecker')->get();

					$this->auth_results[$plugin_name] = $result;
				}
			}
		}

		$this->auth_userinfo = $auth_userinfo;
		$this->response = $response;
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
