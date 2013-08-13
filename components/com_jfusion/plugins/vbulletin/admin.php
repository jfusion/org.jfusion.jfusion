<?php

/**
 * file containing administrator function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Admin Class for vBulletin
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class JFusionAdmin_vbulletin extends JFusionAdmin
{
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'vbulletin';
    }

    /**
     * @return string
     */
    function getTablename()
    {
        return 'user';
    }

    /**
     * @param string $forumPath
     * @return array
     */
    function setupFromPath($forumPath)
    {
        //check for trailing slash and generate file path
        if (substr($forumPath, -1) == DIRECTORY_SEPARATOR) {
            $configfile = $forumPath . 'includes' . DIRECTORY_SEPARATOR . 'config.php';
            $funcfile = $forumPath . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
        } else {
            $configfile = $forumPath . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'config.php';
            $funcfile = $forumPath . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
        }
        //try to open the file
        $params = array();
        if (($file_handle = @fopen($configfile, 'r')) === false) {
            JFusionFunction::raiseWarning(JText::_('WIZARD_FAILURE') . ": $configfile " . JText::_('WIZARD_MANUAL'), $this->getJname());
        } else {
            //parse the file line by line to get only the config variables
            $file_handle = fopen($configfile, 'r');
            $config = array();
            while (!feof($file_handle)) {
                $line = fgets($file_handle);
                if (strpos($line, '$config') === 0) {
                    $vars = explode("'", $line);
                    if (isset($vars[5])) {
                        $name1 = trim($vars[1], ' $=');
                        $name2 = trim($vars[3], ' $=');
                        $value = trim($vars[5], ' $=');
                        $config[$name1][$name2] = $value;
                    }
                }
            }
            fclose($file_handle);

            //save the parameters into the standard JFusion params format
            $params = array();
            $params['database_host'] = $config['MasterServer']['servername'];
            $params['database_type'] = $config['Database']['dbtype'];
            $params['database_name'] = $config['Database']['dbname'];
            $params['database_user'] = $config['MasterServer']['username'];
            $params['database_password'] = $config['MasterServer']['password'];
            $params['database_prefix'] = $config['Database']['tableprefix'];
            $params['cookie_prefix'] = $config['Misc']['cookieprefix'];
            $params['source_path'] = $forumPath;
            //find the path to vbulletin, for this we need a database connection
            $host = $config['MasterServer']['servername'];
            $user = $config['MasterServer']['username'];
            $password = $config['MasterServer']['password'];
            $database = $config['Database']['dbname'];
            $prefix = $config['Database']['tableprefix'];
            $driver = 'mysql';
            $options = array('driver' => $driver, 'host' => $host, 'user' => $user, 'password' => $password, 'database' => $database, 'prefix' => $prefix);
            $vdb = JDatabaseDriver::getInstance($options);
            if (method_exists($vdb, 'setQuery')) {
                //Find the path to vbulletin
                $query = 'SELECT value, varname FROM #__setting WHERE varname IN (\'bburl\',\'cookietimeout\',\'cookiepath\',\'cookiedomain\')';
                $vdb->setQuery($query);
                $settings = $vdb->loadObjectList('varname');
                $params['source_url'] = $settings['bburl']->value;
                $params['cookie_expires'] = $settings['cookietimeout']->value;
                $params['cookie_path'] = $settings['cookiepath']->value;
                $params['cookie_domain'] = $settings['cookiedomain']->value;
            }

            if (($file_handle = @fopen($funcfile, 'r')) !== false) {
                //parse the functions file line by line to get the cookie salt
                $file_handle = fopen($funcfile, 'r');
                $cookie_salt = '';
                while (!feof($file_handle)) {
                    $line = fgets($file_handle);
                    if (strpos($line, 'COOKIE_SALT') !== false) {
                        $vars = explode("'", $line);
                        if (isset($vars[3])) {
                            $cookie_salt = $vars[3];
                        }
                        break;
                    }
                }
                fclose($file_handle);
                $params['cookie_salt'] = $cookie_salt;
            }
        }
        return $params;
    }

    /**
     * @return string
     */
    function getRegistrationURL()
    {
        return 'register.php';
    }

    /**
     * @return string
     */
    function getLostPasswordURL()
    {
        return 'login.php?do=lostpw';
    }

    /**
     * @return string
     */
    function getLostUsernameURL()
    {
        return 'login.php?do=lostpw';
    }

    /**
     * Returns the a list of users of the integrated software
     *
     * @param int $limitstart start at
     * @param int $limit number of results
     *
     * @return array
     */
    function getUserList($limitstart = 0, $limit = 0)
    {
	    try {
		    // initialise some objects
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $query = 'SELECT username, email from #__user';
		    $db->setQuery($query, $limitstart, $limit);
		    //getting the results
		    $userlist = $db->loadObjectList();
	    } catch (Exception $e) {
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
	    try {
		    //getting the connection to the db
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $query = 'SELECT count(*) from #__user';
		    $db->setQuery($query);
		    //getting the results
		    $no_users = $db->loadResult();
	    } catch (Exception $e) {
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
	    try {
		    //get the connection to the db
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $query = 'SELECT usergroupid as id, title as name from #__usergroup';
		    $db->setQuery($query);
		    //getting the results
		    return $db->loadObjectList();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    return array();
	    }
    }

    /**
     * @return string
     */
    function getDefaultUsergroup()
    {
	    try {
		    $params = JFusionFactory::getParams($this->getJname());
		    $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),null);
		    $usergroup_id = null;
		    if(!empty($usergroups)) {
			    $usergroup_id = $usergroups[0];
		    }
		    //we want to output the usergroup name
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $query = 'SELECT title from #__usergroup WHERE usergroupid = ' . $usergroup_id;
		    $db->setQuery($query);
		    return $db->loadResult();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    return '';
	    }
    }

    /**
     * @return bool
     */
    function allowRegistration()
    {
	    $result = false;
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $query = 'SELECT value FROM #__setting WHERE varname = \'allowregistration\'';
		    $db->setQuery($query);
		    //getting the results
		    $new_registration = $db->loadResult();
		    if ($new_registration == 1) {
			    $result = true;
		    }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
	    }
	    return $result;
    }

    /**
     * @param $ignore
     * @param $hook
     * @return mixed|string
     */
    function showHook($ignore, $hook)
    {
        static $jsSet;
        if (empty($jsSet)) {
	        JFusionFunction::loadJavascriptLanguage('VB_REDIRECT_ITEMID_EMPTY');

            $js = <<<JS
            JFusion.Plugin.toggleHook = function(hook, action) {
                var form = $('adminForm');
                var itemid = $('params[plugin_itemid]_id0');

                var a = (action == 'enable' || action == 'reenable');
                var h = (hook == 'frameless' || hook == 'redirect');
                var i = (itemid.get('value') === '' || itemid.get('value') == '0');
                if (a && h && i) {
                	JFusion.confirm(JFusion.JText('VB_REDIRECT_ITEMID_EMPTY'), JFusion.JText('OK'));
                } else {
                    form.customcommand.set('value', 'toggleHook');
                    $('params_hook_name').set('value', hook);
                    $('params_hook_action').set('value', action);
                    form.action.value = 'apply';
                    Joomla.submitform('saveconfig', form);
                }
                return false;
            }
JS;
            $document = JFactory::getDocument();
            $document->addScriptDeclaration($js);

            $jsSet = true;
        }

		try {
			try {
				$db = JFusionFactory::getDatabase($this->getJname());
			} catch (Exception $e) {
				throw new RuntimeException(JText::_('VB_CONFIG_FIRST'));
			}

			if ($hook != 'framelessoptimization') {
				$hookName = null;
				switch ($hook) {
					case 'globalfix':
						$hookName = 'JFusion Global Fix Plugin';
						break;
					case 'frameless':
						$hookName = 'JFusion Frameless Integration Plugin';
						break;
					case 'duallogin':
						$hookName = 'JFusion Dual Login Plugin';
						break;
					case 'redirect':
						$hookName = 'JFusion Redirect Plugin';
						break;
					case 'jfvbtask':
						$hookName = 'JFusion API Plugin - REQUIRED';
						break;
				}
				if ($hookName) {
					$query = 'SELECT COUNT(*) FROM #__plugin WHERE hookname = \'init_startup\' AND title = \''.$hookName.'\' AND active = 1';
					$db->setQuery($query);
					$check = ($db->loadResult() > 0) ? true : false;
				} else {
					$check = false;
				}
				if ($check) {
					//return success
					$enabled = JText::_('ENABLED');
					$disable = JText::_('DISABLE_THIS_PLUGIN');
					$reenable = JText::_('REENABLE_THIS_PLUGIN');
					$output = <<<HTML
		                    <img style="float: left;" src="components/com_jfusion/images/check_good_small.png">
		                    <span style="float: left; margin-left: 5px;">{$enabled}</span>
		                    <a style="margin-left:5px; float: left;" href="javascript:void(0);" onclick="return JFusion.Plugin.toggleHook('{$hook}','disable')">{$disable}</a>
		                    <a style="margin-left:5px; float: left;" href="javascript:void(0);" onclick="return JFusion.Plugin.toggleHook('{$hook}','reenable')">{$reenable}</a>
HTML;
					return $output;
				} else {
					$disabled = JText::_('DISABLED');
					$enable = JText::_('ENABLE_THIS_PLUGIN');
					$output = <<<HTML
		                    <img style="float: left;" src="components/com_jfusion/images/check_bad_small.png">
		                    <span style="float: left; margin-left: 5px;">{$disabled}</span>
		                    <a style="margin-left:5px; float: left;" href="javascript:void(0);" onclick="return JFusion.Plugin.toggleHook('{$hook}','enable')">{$enable}</a>
HTML;
					return $output;
				}

			} else {
				//let's first check the default icon
				$check = true;
				$q = 'SELECT value FROM #__setting WHERE varname = \'showdeficon\'';
				$db->setQuery($q);
				$deficon = $db->loadResult();
				$check = (!empty($deficon) && strpos($deficon, 'http') === false) ? false : true;
				if ($check) {
					//this will perform functions like rewriting image paths to include the full URL to images to save processing time
					$tables = array('smilie' => 'smiliepath', 'avatar' => 'avatarpath', 'icon' => 'iconpath');
					foreach ($tables as $tbl => $col) {
						$q = 'SELECT '.$col.' FROM #__'.$tbl;
						$db->setQuery($q);
						$images = $db->loadRowList();
						if ($images) {
							foreach ($images as $image) {
								$check = (strpos($image[0], 'http') !== false) ? true : false;
								if (!$check) break;
							}
						}
						if (!$check) break;
					}
				}
				if ($check) {
					//return success
					$complete = JText::_('COMPLETE');
					$undo = JText::_('VB_UNDO_OPTIMIZATION');
					$output = <<<HTML
		                    <img style="float: left;" src="components/com_jfusion/images/check_good_small.png">
		                    <span style="float: left; margin-left: 5px;">{$complete}</span>
		                    <a style="margin-left:5px; float: left;" href="javascript:void(0);" onclick="return JFusion.Plugin.toggleHook('{$hook}','disable')">{$undo}</a>
HTML;
					return $output;
				} else {
					$incomplete = JText::_('INCOMPLETE');
					$do = JText::_('VB_DO_OPTIMIZATION');
					$output = <<<HTML
		                    <img style="float: left;" src="components/com_jfusion/images/check_bad_small.png">
		                    <span style="float: left; margin-left: 5px;">{$incomplete}</span>
		                    <a style="margin-left:5px; float: left;" href="javascript:void(0);" onclick="return JFusion.Plugin.toggleHook('{$hook}','enable')">{$do}</a>
HTML;
					return $output;
				}
			}
		} catch (Exception $e) {
			return $e->getMessage();
		}
    }

    /**
     * @return mixed
     */
    function toggleHook()
    {
	    try {
		    $params = JFactory::getApplication()->input->get('params', array(), 'array');
		    $itemid = $params['plugin_itemid'];
		    $hook = $params['hook_name'];
		    $action = $params['hook_action'];
		    $db = JFusionFactory::getDatabase($this->getJname());
		    if ($hook != 'framelessoptimization') {
			    $hookName = null;
			    switch ($hook) {
				    case 'globalfix':
					    $hookName = 'JFusion Global Fix Plugin';
					    break;
				    case 'frameless':
					    $hookName = 'JFusion Frameless Integration Plugin';
					    break;
				    case 'duallogin':
					    $hookName = 'JFusion Dual Login Plugin';
					    break;
				    case 'redirect':
					    $hookName = 'JFusion Redirect Plugin';
					    break;
				    case 'jfvbtask':
					    $hookName = 'JFusion API Plugin - REQUIRED';
					    break;
			    }
			    if ($hookName) {
				    //all three cases, we want to remove the old hook
				    $query = $db->getQuery(true)
					    ->delete('#__plugin')
					    ->where('hookname = ' . $db->quote('init_startup'))
					    ->where('title = ' . $db->quote($hookName));

				    $db->setQuery($query);
				    $db->execute();

				    //enable or re-enable the plugin
				    if ($action != 'disable') {
					    if (($hook == 'redirect' || $hook == 'frameless') && !$this->isValidItemID($itemid)) {
						    JFusionFunction::raiseWarning(JText::_('VB_REDIRECT_HOOK_ITEMID_EMPTY'));
					    } else {
						    //install the hook
						    $php = $this->getHookPHP($hook, $itemid);
						    $query = 'INSERT INTO #__plugin SET
                        title = ' . $db->Quote($hookName) . ',
                        hookname = \'init_startup\',
                        phpcode = ' . $db->Quote($php) . ',
                        product = \'vbulletin\',
                        active = 1,
                        executionorder = 1';
						    $db->setQuery($query);
						    $db->execute();
					    }
				    }
			    }
		    } else {
			    //this will perform functions like rewriting image paths to include the full URL to images to save processing time
			    $params = JFusionFactory::getParams($this->getJname());
			    $source_url = $params->get('source_url');
			    if (substr($source_url, -1) != '/') {
				    $source_url.= '/';
			    }
			    //let's first update all the image paths for database stored images
			    $tables = array('smilie' => 'smiliepath', 'avatar' => 'avatarpath', 'icon' => 'iconpath');
			    foreach ($tables as $tbl => $col) {
				    $criteria = ($action == 'enable') ? 'NOT LIKE \'http%\'' : 'LIKE \'%http%\'';
				    $q = 'SELECT '.$tbl.'id, '.$col.' FROM #__'.$tbl.' WHERE '.$col.' '.$criteria;
				    $db->setQuery($q);
				    $images = $db->loadRowList();
				    foreach ($images as $i) {
					    $q = $db->getQuery(true)
						    ->update('#__'.$tbl);

					    if ($action == 'enable') {
						    $q->set($col.' = '.$q->quote($source_url.$i[1]));
					    } else {
						    $i[1] = str_replace($source_url, '', $i[1]);
						    $q->set($col.' = '.$q->quote($i[1]));
					    }

					    $q->where($tbl.'id = '.$i[0]);

					    $db->setQuery($q);
					    $db->execute();
				    }
			    }
			    //let's update the default icon
			    $q = 'SELECT value FROM #__setting WHERE varname = \'showdeficon\'';
			    $db->setQuery($q);
			    $deficon = $db->loadResult();
			    if (!empty($deficon)) {
				    $q = $db->getQuery(true)
					    ->update('#__setting');

				    if ($action == 'enable' && strpos($deficon, 'http') === false) {
					    $q->set('value = ' . $q->quote($source_url.$deficon));
				    } elseif ($action == 'disable') {
					    $deficon = str_replace($source_url, '', $deficon);
					    $q->set('value = ' . $q->quote($deficon));
				    }
				    $q->where('varname = '.$q->quote('showdeficon'));

				    $db->setQuery($q);
				    $db->execute();
			    }
		    }
	    } catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
	    }
    }

    /**
     * @param $plugin
     * @param $itemid
     * @return string
     */
    function getHookPHP($plugin, $itemid)
    {
        $params = JFusionFactory::getParams($this->getJname());
        $hookFile = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'hooks.php';
        $php = "defined('_VBJNAME') or define('_VBJNAME', '{$this->getJname()}');\n";
        $php.= "defined('JPATH_PATH') or define('JPATH_BASE', '" . (str_replace(DIRECTORY_SEPARATOR.'administrator', '', JPATH_BASE)) . "');\n";
        $php.= "defined('JFUSION_VB_HOOK_FILE') or define('JFUSION_VB_HOOK_FILE', '$hookFile');\n";
        if ($plugin == 'globalfix') {
            $php.= "if (defined('_JEXEC') && empty(\$GLOBALS['vbulletin']) && !empty(\$vbulletin)) {\n";
            $php.= "\$GLOBALS['vbulletin'] = \$vbulletin;\n";
            $php.= "\$GLOBALS['db'] = \$vbulletin->db;\n";
            $php.= '}';
            return $php;
        } elseif ($plugin == 'frameless') {
            //we only want to initiate the frameless if we are inside Joomla or using AJAX
            $php.= "if (defined('_JEXEC') || isset(\$_GET['jfusion'])){\n";
        } elseif ($plugin == 'redirect') {
            $php.= "if (!defined('_JEXEC')){\n";
            $sefmode = $params->get('sefmode', 0);
            $config = JFactory::getConfig();
            $sef = $config->get('sef');
            //get the baseUR
            $app = JApplication::getInstance('site');
            $router = $app->getRouter();
	        /**
	         * @ignore
	         * @var $uri JUri
	         */
	        $uri = $router->build('index.php?option=com_jfusion&Itemid=' . $itemid);
            $baseURL = $uri->toString();
            $joomla_url = JFusionFunction::getJoomlaURL();
            if (!strpos($baseURL, '?')) {
                $baseURL.= '/';
            }
            $juri = new JURI($joomla_url);
            $path = $juri->getPath();
            if ($path != '/') {
                $baseURL = str_replace($path, '', $baseURL);
            }
            if (substr($joomla_url, -1) == '/') {
                if ($baseURL[0] == '/') {
                    $baseURL = substr($joomla_url, 0, -1) . $baseURL;
                } else {
                    $baseURL = $joomla_url . $baseURL;
                }
            } else {
                if ($baseURL[0] == '/') {
                    $baseURL = $joomla_url . $baseURL;
                } else {
                    $baseURL = $joomla_url . '/' . $baseURL;
                }
            }
            //let's clean up the URL here before passing it
            $baseURL = str_replace('&amp;', '&', $baseURL);
            //remove /administrator from path
            $baseURL = str_replace('/administrator', '', $baseURL);
            //set some constants needed to recreate the Joomla URL
            $php.= "define('SEFENABLED','$sef');\n";
            $php.= "define('SEFMODE','$sefmode');\n";
            $php.= "define('JOOMLABASEURL','$baseURL');\n";
            $php.= "define('REDIRECT_IGNORE','" . $params->get('redirect_ignore') . "');\n";
        } elseif ($plugin == 'duallogin') {
            //only login if not logging into the frontend of the forum and if $JFusionActivePlugin is not active for this plugin
            $php.= "global \$JFusionActivePlugin,\$JFusionLoginCheckActive;\n";
            $php.= "if (empty(\$_POST['logintype']) && \$JFusionActivePlugin != '{$this->getJname() }' && empty(\$JFusionLoginCheckActive)) {\n";
            $php.= "\$JFusionActivePlugin = '{$this->getJname() }';\n";
            //set the JPATH_BASE needed to initiate Joomla if no already inside Joomla
            $php.= "defined('JPATH_BASE') or define('JPATH_BASE','" . JPATH_ROOT . "');\n";
        }

        $php.= "if (file_exists(JFUSION_VB_HOOK_FILE)) {\n";
        $php.= "include_once(JFUSION_VB_HOOK_FILE);\n";
        $php.= "\$val = '$plugin';\n";
        $secret = $params->get('vb_secret', JFactory::getConfig()->get('secret'));
        $php.= "\$JFusionHook = new executeJFusionHook('init_startup', \$val, '$secret');\n";
        /**
         * @ignore
         * @var $helper JFusionHelper_vbulletin
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        $version = $helper->getVersion();
        if (substr($version, 0, 1) > 3) {
            $php.= "vBulletinHook::set_pluginlist(\$vbulletin->pluginlist);\n";
        }
        $php.= "}\n";
        if ($plugin != 'jfvbtask') {
            $php.= "}\n";
        }
        return $php;
    }

    function debugConfigExtra()
    {
	    try {
		    //check for usergroups to make sure membergroups do not include default or display group
		    $params = JFusionFactory::getParams($this->getJname());
		    if (JFusionFunction::isAdvancedUsergroupMode($this->getJname())) {
			    $usergroups = unserialize($params->get('usergroup'));
			    $master = JFusionFunction::getMaster();
			    if (!empty($master)) {
				    if ($master->name != $this->getJName()) {
					    $JFusionMaster = JFusionFactory::getAdmin($master->name);
					    $master_usergroups = $JFusionMaster->getUsergroupList();
					    foreach ($master_usergroups as $group) {
						    if (isset($usergroups[$group->id]['membergroups']) && isset($usergroups[$group->id]['defaultgroup'])) {
							    $membergroups = $usergroups[$group->id]['membergroups'];
							    $defaultgroup = $usergroups[$group->id]['defaultgroup'];
							    if ((is_array($membergroups) && in_array($defaultgroup, $membergroups)) || $defaultgroup == $membergroups) {
								    JFusionFunction::raiseWarning(JText::sprintf('VB_GROUP_MISMATCH', $group->name), $this->getJname());
							    }
						    }
					    }
				    } else {
					    JFusionFunction::raiseWarning(JText::_('ADVANCED_GROUPMODE_ONLY_SUPPORTED_FORSLAVES'), $this->getJname());
				    }
			    }
		    }
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $query = 'SELECT COUNT(*) FROM #__plugin WHERE hookname = \'init_startup\' AND title = \'JFusion API Plugin - REQUIRED\' AND active = 1';
		    $db->setQuery($query);
		    if ($db->loadResult() == 0) {
			    JFusionFunction::raiseWarning(JText::_('VB_API_HOOK_NOT_INSTALLED'), $this->getJname());
		    }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
	    }
    }

    /**
     * @param string $name
     * @param string $value
     * @param string $node
     * @param string $control_name
     * @return mixed|string
     */
    function usergroup($name, $value, $node, $control_name)
    {
        //get the master plugin to be throughout
        $master = JFusionFunction::getMaster();
        //detect is value is a serialized array
        $advanced = 0;

	    JHTML::setFormatOptions(array('format.eol' => "", 'format.indent' => ""));

        if (substr($value, 0, 2) == 'a:') {
            $value = unserialize($value);
            if (!empty($master) && $master->name != $this->getJname()) $advanced = 1;
        }
        if (JFusionFunction::validPlugin($this->getJname())) {
            $usergroups = $this->getUsergroupList();
            if (!empty($usergroups)) {
                $simple_value = (empty($value) || is_array($value)) ? 2 : $value;
                $simple_usergroup = '<table style="width:100%; border:0;">';
                $simple_usergroup.= '<tr><td>' . JText::_('DEFAULT_USERGROUP') . '</td><td>' . JHTML::_('select.genericlist', $usergroups, $control_name . '[' . $name . ']', '', 'id', 'name', $simple_value) . '</td></tr>';
                $simple_usergroup.= '</table>';
                //escape single quotes to prevent JS errors
                $simple_usergroup = str_replace("'", "\'", $simple_usergroup);
            } else {
                $simple_usergroup = '';
            }
        } else {
            return JText::_('SAVE_CONFIG_FIRST');
        }
        //check to see if current plugin is a slave
        $db = JFactory::getDBO();
        $query = 'SELECT slave FROM #__jfusion WHERE name = ' . $db->Quote($this->getJname());
        $db->setQuery($query);
        $slave = $db->loadResult();
        $list_box = '<select onchange="JFusion.Plugin.usergroupSelect(this.selectedIndex);">';
        if ($advanced == 1) {
            $list_box.= '<option value="0" selected="selected">Simple</option>';
        } else {
            $list_box.= '<option value="0">Simple</option>';
        }
        $advanced_usergroup = '';
        $jsGroups = array();
        if ($slave == 1 && !empty($master) && JFusionFunction::hasFeature($this->getJname(),'updateusergroup')) {
            //allow usergroup sync
            if ($advanced == 1) {
                $list_box.= '<option selected="selected" value="1">Avanced</option>';
            } else {
                $list_box.= '<option value="1">Avanced</option>';
            }
            //prepare the advanced options
            $JFusionMaster = JFusionFactory::getAdmin($master->name);
            $master_usergroups = $JFusionMaster->getUsergroupList();
            $jsGroup = array();

            //setup display group list
            $default = array(JHTML::_('select.option', '0', JText::_('DEFAULT'), 'id', 'name'));
            $displaygroups = array_merge($default, $usergroups);
            //remove non-applicable usergroups from displaygroup list
            $non_displaygroups = array(1, 3, 4);
            foreach ($displaygroups as $key => $group) {
                if (in_array($group->id, $non_displaygroups)) {
                    unset($displaygroups[$key]);
                }
            }
            //create advanced usergroup html
            $advanced_usergroup = '<table style="width:100%; border:0">';
            //add options to compare display and member groups in addition to default usergroups
            $advanced_usergroup .= '<tr><td colspan=2><b>'.JText::_('OPTIONS').'</b></td></tr>';
            $options = array();
            $options[] = JHTML::_('select.option', '1', JText::_('JYES'), 'id', 'value');
            $options[] = JHTML::_('select.option', '0', JText::_('JNO'), 'id', 'value');
            //option to compare display groups
            $option_value = ($advanced && is_array($value) && isset($value['options']['compare_displaygroups'])) ? $value['options']['compare_displaygroups'] : '';
            $check = ($advanced && !empty($value['option']['compare_displaygroups'])) ? 'checked' : '';
            $advanced_usergroup .= '<tr><td>'.JText::_('COMPARE_DISPLAYGROUPS').'</td><td>' . JHTML::_('select.genericlist', $options, $control_name . '[' . $name . '][options][compare_displaygroups]', 'class="inputbox"', 'id', 'value', $option_value);
            //option to compare member groups
            $option_value = ($advanced && isset($value['options']['compare_membergroups'])) ? $value['options']['compare_membergroups'] : '';
            $check = ($advanced && !empty($value['option']['compares_membergroups'])) ? 'checked' : '';
            $advanced_usergroup .= '<tr><td>'.JText::_('COMPARE_MEMBERGROUPS').'</td><td>' . JHTML::_('select.genericlist', $options, $control_name . '[' . $name . '][options][compare_membergroups]', 'class="inputbox"', 'id', 'value', $option_value);

            foreach ($master_usergroups as $master_usergroup) {
                $defaultgroup = ($advanced && isset($value[$master_usergroup->id]['defaultgroup'])) ? $value[$master_usergroup->id]['defaultgroup'] : '';
                $displaygroup = ($advanced && isset($value[$master_usergroup->id]['displaygroup'])) ? $value[$master_usergroup->id]['displaygroup'] : '';
                $membergroups = ($advanced && isset($value[$master_usergroup->id]['membergroups'])) ? $value[$master_usergroup->id]['membergroups'] : '';
                $advanced_usergroup.= '<tr><td colspan="2"><b>' . $master_usergroup->name . '</b></td></tr>';
                $advanced_usergroup.= '<tr><td>' . JText::_('DEFAULT_USERGROUP') . '</td><td>' . JHTML::_('select.genericlist', $usergroups, $control_name . '[' . $name . '][' . $master_usergroup->id . '][defaultgroup]', 'onclick="JFusion.Plugin.toggleSecondaryGroups(this.value,' . $master_usergroup->id . ');" class="inputbox"', 'id', 'name', $defaultgroup) . '</td></tr>';

                $advanced_usergroup.= '<tr><td>' . JText::_('DEFAULT_DISPLAYGROUP') . '</td><td>' . JHTML::_('select.genericlist', $displaygroups, $control_name . '[' . $name . '][' . $master_usergroup->id . '][displaygroup]', 'class="inputbox"', 'id', 'name', $displaygroup) . '</td></tr>';
                $advanced_usergroup.= '<tr><td>' . JText::_('DEFAULT_MEMBERGROUPS') . '</td><td>';
                foreach ($usergroups as $group) {
                    $check = (((is_array($membergroups) && in_array($group->id, $membergroups)) || $membergroups == $group->id) && $defaultgroup != $group->id) ? 'checked' : '';
                    $disabled = ($defaultgroup == $group->id) ? 'disabled' : '';
                    $advanced_usergroup.= '<input id="vbgroup' . $master_usergroup->id . '-' . $group->id . '" type=checkbox value="' . $group->id . '" name="' . $control_name . '[' . $name . '][' . $master_usergroup->id . '][membergroups][]" ' . $check . ' ' . $disabled . '/>  ' . $group->name . '<br>';
                    $jsGroups[] = $group->id;
                }
                $advanced_usergroup.= '</td></tr>';
            }
            $advanced_usergroup.= '</table>';
            //escape single quotes to prevent JS errors
            $advanced_usergroup = str_replace("'", "\'", $advanced_usergroup);
        }
        $list_box.= '</select>';

	    $jsGroups = implode(',', $jsGroups);
	    $js = <<<JS
        JFusion.Plugin.groupDataArray[0] = '{$simple_usergroup}';
        JFusion.Plugin.groupDataArray[1] = '{$advanced_usergroup}';

        JFusion.Plugin.toggleSecondaryGroups = function(vbid,masterid) {
        	var groups = new Array({$jsGroups});
	        for(var i=0; i<groups.length; i++) {
        		var element = $('vbgroup'+masterid+'-'+groups[i]);
        		if (element.value==vbid) {
        			element.disabled = true;
        			element.checked = false;
        		} else {
        			element.disabled = false;
        		}
        	}
        }
JS;
        $document = JFactory::getDocument();
        $document->addScriptDeclaration($js);
        if ($advanced == 1) {
            return JText::_('USERGROUP') . ' ' . JText::_('MODE') . ': ' . $list_box . '<br/><div id="JFusionUsergroup">' . $advanced_usergroup . '</div>';
        } else {
            return JText::_('USERGROUP') . ' ' . JText::_('MODE') . ': ' . $list_box . '<br/><div id="JFusionUsergroup">' . $simple_usergroup . '</div>';
        }
    }

    /**
     * @param $name
     * @param $value
     * @param $node
     * @param $control_name
     * @return mixed|string
     */
    function name_field($name, $value, $node, $control_name)
    {
	    try {
		    if (JFusionFunction::validPlugin($this->getJname())) {
			    try {
				    $db = JFusionFactory::getDatabase($this->getJname());
			    } catch (Exception $e) {
					throw new RuntimeException(JText::_('SAVE_CONFIG_FIRST'));
			    }

			    //get a list of field names for custom profile fields
			    $custom_fields = $db->getTableColumns('#__userfield');
			    unset($custom_fields['#__userfield']['userid']);
			    unset($custom_fields['#__userfield']['temp']);

			    $vb_options = array();
			    $vb_options = array(JHTML::_('select.option', '', '', 'id', 'name'));
			    foreach($custom_fields['#__userfield'] as $field  => $type) {
				    $query = 'SELECT text FROM #__phrase WHERE varname = \''.$field.'_title\' AND fieldname = \'cprofilefield\' LIMIT 0,1';
				    $db->setQuery($query);
				    $title = $db->loadResult();
				    $vb_options[] = JHTML::_('select.option', $field, $title, 'id', 'name');
			    }
			    $value = (empty($value)) ? '' : $value;

			    return JHTML::_('select.genericlist', $vb_options, $control_name . '[' . $name . ']', 'class="inputbox"', 'id', 'name', $value);
		    } else {
			    throw new RuntimeException(JText::_('SAVE_CONFIG_FIRST'));
		    }
	    } catch (Exception $e) {
		    return $e->getMessage();
	    }
    }

    /**
     * @return array
     */
    function uninstall()
    {
	    $return = false;
	    $reasons = array();
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $hookNames = array();
		    $hookNames[] = $db->quote('JFusion Global Fix Plugin');
		    $hookNames[] = $db->quote('JFusion Frameless Integration Plugin');
		    $hookNames[] = $db->quote('JFusion Dual Login Plugin');
		    $hookNames[] = $db->quote('JFusion Redirect Plugin');
		    $hookNames[] = $db->quote('JFusion API Plugin - REQUIRED');

		    $query = $db->getQuery(true)
			    ->delete('#__plugin')
			    ->where('hookname = ' . $db->quote('init_startup'))
			    ->where('title IN (' . implode(', ', $hookNames) . ')');

		    $db->setQuery($query);
		    $db->execute();

		    $return = true;
	    } catch (Exception $e) {
		    $reasons[] = $e->getMessage();
	    }

        return array($return, $reasons);
    }
    
	/*
	 * do plugin support multi usergroups
	 * return UNKNOWN for unknown
	 * return JNO for NO
	 * return JYES for YES
	 * return ... ??
	 */
    /**
     * @return string
     */
    function requireFileAccess()
	{
		return 'JYES';
	}
}