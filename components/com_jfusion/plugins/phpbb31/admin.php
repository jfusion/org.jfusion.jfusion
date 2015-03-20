<?php

/**
 * file containing administrator function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Admin Class for phpBB3
 * For detailed descriptions on these functions please check JFusionAdmin
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionAdmin_phpbb31 extends JFusionAdmin
{
	/**
	 * returns the name of this JFusion plugin
	 * @return string name of current JFusion plugin
	 */
	function getJname()
	{
		return 'phpbb31';
	}

	/**
	 * @return string
	 */
	function getTablename()
	{
		return 'users';
	}

	/**
	 * @param string $softwarePath
	 *
	 * @return array
	 */
	function setupFromPath($softwarePath)
	{
		$myfile = $softwarePath . 'config.php';

		$params = array();
		$lines = $this->readFile($myfile);
		if ($lines === false)
		{
			JFusionFunction::raiseWarning(JText::_('WIZARD_FAILURE') . ': ' . $myfile . ' ' . JText::_('WIZARD_MANUAL'), $this->getJname());
			return false;
		}
		else
		{
			//parse the file line by line to get only the config variables
			$config = array();
			foreach ($lines as $line)
			{
				if (strpos($line, '$') === 0)
				{
					//extract the name and value, it was coded to avoid the use of eval() function
					$vars = explode("'", $line);
					$name = trim($vars[0], ' $=');
					$value = trim($vars[1], ' $=');
					$config[$name] = $value;
				}
			}

			if (isset($config['dbms']))
			{
				$config['dbms'] = explode('\\', $config['dbms']);
				$config['dbms'] = $config['dbms'][count($config['dbms']) - 1];
			}

			//save the parameters into array
			$params['database_host'] = isset($config['dbhost']) ? $config['dbhost'] : '';
			$params['database_name'] = isset($config['dbname']) ? $config['dbname'] : '';
			$params['database_user'] = isset($config['dbuser']) ? $config['dbuser'] : '';
			$params['database_password'] = isset($config['dbpasswd']) ? $config['dbpasswd'] : '';
			$params['database_prefix'] = isset($config['table_prefix']) ? $config['table_prefix'] : '';
			$params['database_type'] = isset($config['dbms']) ? $config['dbms'] : '';

			//create a connection to the database
			$options = array('driver' => $params['database_type'], 'host' => $params['database_host'], 'user' => $params['database_user'], 'password' => $params['database_password'], 'database' => $params['database_name'], 'prefix' => $params['database_prefix']);
			//Get configuration settings stored in the database
			try
			{
				$db = JDatabaseDriver::getInstance($options);

				if (!$db)
				{
					JFusionFunction::raiseWarning(JText::_('NO_DATABASE'), $this->getJname());
					return false;
				}
				else
				{
					$query = $db->getQuery(true)
						->select('config_name, config_value')
						->from('#__config')
						->where('config_name IN (\'script_path\', \'cookie_path\', \'server_name\', \'cookie_domain\', \'cookie_name\', \'allow_autologin\')');

					$db->setQuery($query);
					$rows = $db->loadObjectList();
					foreach ($rows as $row)
					{
						$config[$row->config_name] = $row->config_value;
					}
					//store the new found parameters
					$params['cookie_path'] = isset($config['cookie_path']) ? $config['cookie_path'] : '';
					$params['cookie_domain'] = isset($config['cookie_domain']) ? $config['cookie_domain'] : '';
					$params['cookie_prefix'] = isset($config['cookie_name']) ? $config['cookie_name'] : '';
					$params['allow_autologin'] = isset($config['allow_autologin']) ? $config['allow_autologin'] : '';
					$params['source_path'] = $softwarePath;
				}
				$params['source_url'] = '';
				if (isset($config['server_name']))
				{
					//check for trailing slash
					if (substr($config['server_name'], -1) == '/' && substr($config['script_path'], 0, 1) == '/')
					{
						//too many slashes, we need to remove one
						$params['source_url'] = $config['server_name'] . substr($config['script_path'], 1);
					}
					else if (substr($config['server_name'], -1) == '/' || substr($config['script_path'], 0, 1) == '/')
					{
						//the correct number of slashes
						$params['source_url'] = $config['server_name'] . $config['script_path'];
					}
					else
					{
						//no slashes found, we need to add one
						$params['source_url'] = $config['server_name'] . '/' . $config['script_path'];
					}
				}
			}
			catch (Exception $e)
			{
				JFusionFunction::raiseWarning(JText::_('NO_DATABASE') . ' ' . $e->getMessage(), $this->getJname());
				return false;
			}
		}
		//return the parameters so it can be saved permanently
		return $params;
	}

	/**
	 * Returns the a list of users of the integrated software
	 *
	 * @param int $limitstart start at
	 * @param int $limit      number of results
	 *
	 * @return array
	 */
	function getUserList($limitstart = 0, $limit = 0)
	{
		try
		{
			//getting the connection to the db
			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('username, user_email as email, user_id as userid')
				->from('#__users')
				->where('user_email NOT LIKE ' . $db->quote(''))
				->where('user_email IS NOT null');

			$db->setQuery($query, $limitstart, $limit);
			//getting the results
			$userlist = $db->loadObjectList();
		}
		catch (Exception $e)
		{
			JFusionFunction::raiseError($e, $this->getJname());
			$userlist = array();
		}
		return $userlist;
	}

	/**
	 * @return int
	 */
	function getUserCount()
	{
		try
		{
			//getting the connection to the db
			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('count(*)')
				->from('#__users')
				->where('user_email NOT LIKE ' . $db->quote(''))
				->where('user_email IS NOT null');

			$db->setQuery($query);
			//getting the results
			$no_users = $db->loadResult();
		}
		catch (Exception $e)
		{
			JFusionFunction::raiseError($e, $this->getJname());
			$no_users = 0;
		}
		return $no_users;
	}

	/**
	 * @return array
	 */
	function getUsergroupList()
	{
		//get the connection to the db
		$db = JFusionFactory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->select('group_id as id, group_name as name')
			->from('#__groups');

		$db->setQuery($query);
		//getting the results
		return $db->loadObjectList();
	}

	/**
	 * @return string|array
	 */
	function getDefaultUsergroup()
	{
		$usergroup = JFusionFunction::getUserGroups($this->getJname(), true);

		$group = array();
		if ($usergroup !== null)
		{
			//we want to output the usergroup name
			$db = JFusionFactory::getDatabase($this->getJname());

			if (!isset($usergroup->groups))
			{
				$usergroup->groups = array($usergroup->defaultgroup);
			}
			else if (!in_array($usergroup->defaultgroup, $usergroup->groups))
			{
				$usergroup->groups[] = $usergroup->defaultgroup;
			}

			foreach ($usergroup->groups as $g)
			{
				$query = $db->getQuery(true)
					->select('group_name')
					->from('#__groups')
					->where('group_id = ' . $db->quote($g));

				$db->setQuery($query);
				$group[] = $db->loadResult();
			}
		}
		return $group;
	}

	/**
	 * @return bool
	 */
	function allowRegistration()
	{
		$result = false;
		try
		{
			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('config_value')
				->from('#__config')
				->where('config_name = ' . $db->quote('require_activation'));

			$db->setQuery($query);
			//getting the results
			$new_registration = $db->loadResult();
			if ($new_registration != 3)
			{
				$result = true;
			}
		}
		catch (Exception $e)
		{
			JFusionFunction::raiseError($e, $this->getJname());
		}
		return $result;
	}

	/**
	 * @param $name
	 * @param $value
	 * @param $node
	 * @param $control_name
	 *
	 * @return mixed|string
	 */
	function showExtention($name, $value, $node, $control_name)
	{
		try {
			//do a database check to avoid fatal error with incorrect database settings
			$db = JFusionFactory::getDatabase($this->getJname());

			$error = 0;
			$reason = '';

			$path = $this->params->get('source_path');

			jimport('joomla.filesystem.folder');

			$extention = $this->getExtention();

			//add the javascript to enable buttons
			if (JFolder::exists($path . 'ext/jfusion') && $extention && $extention->ext_active)
			{
				//return success
				$text = JText::_('EXTENTION_MOD') . ' ' . JText::_('ENABLED');
				$disable = JText::_('DISABLED');
				$update = JText::_('UPDATE');
				$output = <<<HTML
            <img src="components/com_jfusion/images/check_good_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('disableExtention')">{$disable}</a>

            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('updateConfig')">{$update}</a>
HTML;
			}
			else
			{
				$text = JText::_('EXTENTION_MOD') . ' ' . JText::_('DISABLED') . ': ' . $reason;
				$enable = JText::_('ENABLED');
				$output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('enableExtention')">{$enable}</a>
HTML;
			}
			return $output;
		} catch (Exception $e) {
			return $e->getMessage();
		}
	}

	/**
	 * @return stdClass
	 */
	function getExtention()
	{
		$db = JFusionFactory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->select('*')
			->from('#__ext')
			->where('ext_name = ' . $db->quote('jfusion/phpbbext'));

		$db->setQuery($query);
		//getting the results
		return $db->loadObject();
	}

	/**
	 * @return bool|mixed
	 */
	function updateExtention()
	{
		$path = $this->params->get('source_path') . 'ext/';
		jimport('joomla.filesystem.folder');
		if (JFolder::exists($path)) {
			return JFolder::copy(__DIR__ . '/jfusion/', $path . 'jfusion/', false, true);
		}
		return false;
	}

	function updateConfig()
	{
		$db = JFusionFactory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->select('*')
			->from('#__config')
			->where('config_name = ' . $db->quote('jfusion_phpbbext_jname'));

		$db->setQuery($query);
		//getting the results
		$config = $db->loadObject();

		$jname = $this->getJname();

		if ($config) {
			$config->config_value = $jname;
			$db->updateObject('#__config', $config, 'config_name');
		} else {
			$config = new stdClass();
			$config->config_name = 'jfusion_phpbbext_jname';
			$config->config_value = $jname;

			$db->insertObject('#__config', $config);
		}


		$query = $db->getQuery(true)
			->select('*')
			->from('#__config')
			->where('config_name = ' . $db->quote('jfusion_phpbbext_apipath'));

		$db->setQuery($query);
		//getting the results
		$config = $db->loadObject();

		$path = JPATH_SITE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion';

		if ($config) {
			$config->config_value = $path;
			$db->updateObject('#__config', $config, 'config_name');
		} else {
			$config = new stdClass();
			$config->config_name = 'jfusion_phpbbext_apipath';
			$config->config_value = $path;

			$db->insertObject('#__config', $config);
		}
	}

	/**
	 * @return bool
	 */
	function enableExtention()
	{
		if ($this->updateExtention()) {
			$db = JFusionFactory::getDatabase($this->getJname());

			$extention = $this->getExtention();

			if ($extention) {
				$extention->ext_active = 1;
				$db->updateObject('#__ext', $extention, 'ext_name');
			} else {
				$extention = new stdClass();
				$extention->ext_name = 'jfusion/phpbbext';
				$extention->ext_active = 0;
				$extention->ext_state = serialize(false);

				$db->insertObject('#__ext', $extention);
			}
			$this->updateConfig();

			$this->clearConfigCache();
		}
		return true;
	}

	/**
	 * @return bool
	 */
	function disableExtention()
	{
		if ($this->updateExtention()) {
			$extention = $this->getExtention();

			if ($extention) {
				$extention->ext_active = 0;
				$db = JFusionFactory::getDatabase($this->getJname());
				$db->updateObject('#__ext', $extention, 'ext_name');
			}
			$this->clearConfigCache();
		}
		return true;
	}
	/**
	 * @param $name
	 * @param $value
	 * @param $node
	 * @param $control_name
	 *
	 * @return string
	 */
	function showQuickMod($name, $value, $node, $control_name)
	{
		$error = 0;
		$reason = '';
		$mod_file = $this->getModFile('mcp.php', $error, $reason);
		if ($error == 0)
		{
			//get the joomla path from the file
			jimport('joomla.filesystem.file');
			$file_data = file_get_contents($mod_file);
			preg_match_all('/global \$action/', $file_data, $matches);
			//compare it with our joomla path
			if (!isset($matches[0][0]))
			{
				$error = 1;
				$reason = JText::_('MOD') . ' ' . JText::_('DISABLED');
			}
		}
		//add the javascript to enable buttons
		if ($error == 0)
		{
			//return success
			$text = JText::_('QUICKTOOLS') . ' ' . JText::_('ENABLED');
			$disable = JText::_('MOD_DISABLE');
			$output = <<<HTML
            <img src="components/com_jfusion/images/check_good_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('quickMod', 'disable')">{$disable}</a>
HTML;
		}
		else
		{
			$text = JText::_('QUICKTOOLS') . ' ' . JText::_('DISABLED') . ': ' . $reason;
			$enable = JText::_('MOD_ENABLE');
			$output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('quickMod', 'enable')">{$enable}</a>
HTML;
		}
		return $output;
	}

	/**
	 * @param $action
	 *
	 * @return int
	 */
	function quickMod($action)
	{
		$error = 0;
		$reason = '';
		$mod_file = $this->getModFile('mcp.php', $error, $reason);
		switch ($action)
		{
			case 'reenable':
			case 'disable':
				if ($error == 0)
				{
					//get the joomla path from the file
					jimport('joomla.filesystem.file');
					$file_data = file_get_contents($mod_file);
					$search = '/global \$action\;/si';
					$file_data = preg_replace($search, '', $file_data);
					if (!JFile::write($mod_file, $file_data))
					{
						$error = 1;
					}
				}
				if ($action == 'disable')
				{
					break;
				}
			case 'enable':
				if ($error == 0)
				{
					//get the joomla path from the file
					jimport('joomla.filesystem.file');
					$file_data = file_get_contents($mod_file);
					$search = '/\$action \= request_var/si';
					$replace = 'global $action; $action = request_var';
					$file_data = preg_replace($search, $replace, $file_data);
					JFile::write($mod_file, $file_data);
				}
				break;
		}
		return $error;
	}

	/**
	 * @return bool
	 */
	function clearConfigCache()
	{
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');

		$source_path = $this->params->get('source_path') . 'cache' . DIRECTORY_SEPARATOR;

		$cachefiles = JFolder::files($source_path);

		foreach ($cachefiles as $cachefile) {
			if (file_exists($source_path.$cachefile))
			{
				JFile::delete($source_path.$cachefile);
			}
		}
	}

	/**
	 * @return array
	 */
	function uninstall()
	{
		$return = true;
		$reasons = array();

		$error = $this->disableExtention();
		if (!$error)
		{
			$reasons[] = JText::_('AUTH_MOD_UNINSTALL_FAILED');
			$return = false;
		}

		//doesn't really matter if the quick mod is not disabled so don't return an error
		$this->quickMod('disable');

		return array($return, $reasons);
	}

	/**
	 * do plugin support multi usergroups
	 *
	 * @return bool
	 */
	function isMultiGroup()
	{
		return false;
	}

	/**
	 * do plugin support multi usergroups
	 *
	 * @return string UNKNOWN or JNO or JYES or ??
	 */
	function requireFileAccess()
	{
		return 'DEPENDS';
	}

	/**
	 * create the render group function
	 *
	 * @return string
	 */
	function getRenderGroup()
	{
		$jname = $this->getJname();

		JFusionFunction::loadJavascriptLanguage(array('MAIN_USERGROUP', 'MEMBERGROUPS'));
		$js = <<<JS
		JFusion.renderPlugin['{$jname}'] = function(index, plugin, pair) {
			var usergroups = JFusion.usergroups[plugin.name];

			var div = new Element('div');

			// render default group
			div.appendChild(new Element('div', {'html': Joomla.JText._('MAIN_USERGROUP')}));

		    var defaultselect = new Element('select', {
		    	'name': 'usergroups['+plugin.name+']['+index+'][defaultgroup]',
		    	'id': 'usergroups_'+plugin.name+index+'defaultgroup'
		    });

			jQuery(document).on('change', '#usergroups_'+plugin.name+index+'defaultgroup', function() {
                var value = this.get('value');

				jQuery('#'+'usergroups_'+plugin.name+index+'groups'+' option').each(function() {
					if (jQuery(this).attr('value') == value) {
						jQuery(this).prop('selected', false);
						jQuery(this).prop('disabled', true);

						jQuery(this).trigger('chosen:updated').trigger('liszt:updated');
	                } else if (jQuery(this).prop('disabled') === true) {
						jQuery(this).prop('disabled', false);
						jQuery(this).trigger('chosen:updated').trigger('liszt:updated');
					}
				});
			});

		    Array.each(usergroups, function (group) {
			    var options = {'value': group.id,
					            'html': group.name};

		        if (pair && pair.defaultgroup && pair.defaultgroup == group.id) {
					options.selected = 'selected';
		        }

				defaultselect.appendChild(new Element('option', options));
		    });
		    div.appendChild(defaultselect);


			// render default member groups
			div.appendChild(new Element('div', {'html': Joomla.JText._('MEMBERGROUPS')}));


		    var membergroupsselect = new Element('select', {
		    	'name': 'usergroups['+plugin.name+']['+index+'][groups][]',
		    	'multiple': 'multiple',
		    	'id': 'usergroups_'+plugin.name+index+'groups'
		    });


		    Array.each(usergroups, function (group, i) {
			    var options = {'value': group.id,
					            'html': group.name};

		        if (pair && pair.defaultgroup == group.id) {
					options.disabled = 'disabled';
		        } else if (!pair && i === 0) {
		        	options.disabled = 'disabled';
		        } else {
		            if (pair && pair.groups && pair.groups.contains(group.id)) {
						options.selected = 'selected';
		        	}
		        }

				membergroupsselect.appendChild(new Element('option', options));
		    });
		    div.appendChild(membergroupsselect);
		    return div;
		};
JS;
		return $js;
	}
}
