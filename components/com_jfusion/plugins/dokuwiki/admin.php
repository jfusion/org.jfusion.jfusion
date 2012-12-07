<?php

/**
 * file containing administrator function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage DokuWiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');


/**
 * JFusion admin class for DokuWiki
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage DokuWiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class JFusionAdmin_dokuwiki extends JFusionAdmin
{
    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'dokuwiki';
    }

    /**
     * check config
     *
     * @return array status message
     */
    function checkConfig()
    {
        $status = array();
        $params = JFusionFactory::getParams($this->getJname());
        /**
         * @ignore
         * @var $helper JFusionHelper_dokuwiki
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        $source_path = $params->get('source_path');
        $config = $helper->getConf($source_path);
        if (is_array($config)) {
            $status['config'] = 1;
            $status['message'] = JText::_('GOOD_CONFIG');
        } else {
            $status['config'] = 0;
            $status['message'] = JText::_('WIZARD_FAILURE');
        }
        return $status;
    }

    /**
     * setup plugin from path
     *
     * @param string $Path Source path user to find config files
     *
     * @return array return array
     */
    function setupFromPath($Path)
    {
        /**
         * @ignore
         * @var $helper JFusionHelper_dokuwiki
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        //try to open the file
        $config = $helper->getConf($Path);
        $params = array();
        if ($config === false) {
            JError::raiseWarning(500, JText::_('WIZARD_FAILURE') . ": $Path " . JText::_('WIZARD_MANUAL'));
        } else {
            if (isset($config['auth']['mysql']) && isset($config['authtype']) && $config['authtype'] == 'mysql') {
	            $params['database_type'] = 'mysql';
	            $params['database_host'] = $config['auth']['mysql']['server'];
	            $params['database_name'] = $config['auth']['mysql']['database'];
	            $params['database_user'] = $config['auth']['mysql']['user'];
	            $params['database_password'] = $config['auth']['mysql']['password'];
            	if (isset($config['auth']['mysql']['charset'])) {
            		$params['database_charset'] = $config['auth']['mysql']['charset'];
            	} else {
            		$params['database_charset'] = 'utf8';
            	}
	        } else {
	        	$params['database_type'] = $params['database_host'] = $params['database_name'] = $params['database_user'] = $params['database_password'] = $params['database_charset'] = '';
	        }
            if (isset($config['cookie_name'])) {
            	$params['cookie_name'] = $config['cookie_name'];
            }
            if (isset($config['cookie_path'])) {
            	$params['cookie_path'] = $config['cookie_path'];
            }
            if (isset($config['cookie_domain'])) {
            	$params['cookie_domain'] = $config['cookie_domain'];
            }
            if (isset($config['cookie_seed'])) {
            	$params['cookie_seed'] = $config['cookie_seed'];
            }
            if (isset($config['cookie_secure'])) {
            	$params['cookie_secure'] = $config['cookie_secure'];
            }
            $params['source_path'] = $Path;
        }
        return $params;
    }

    /**
     * returns avetar
     *
     * @param string $userid userd used to find avatar
     *
     * @return string
     */
    function getAvatar($userid)
    {
        return 0;
    }

    /**
     * Get a list of users
     *
     * @param int $limitstart
     * @param int $limit
     *
     * @return array array with object with list of users
     */
    function getUserList($limitstart = 0, $limit = 0)
    {
        /**
         * @ignore
         * @var $helper JFusionHelper_dokuwiki
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        $list = $helper->auth->retrieveUsers($limitstart,$limit);
        $userlist = array();
        foreach ($list as $value) {
            $user = new stdClass;
            $user->email = isset($value['mail']) ? $value['mail'] : null;
            $user->username = isset($value['username']) ? $value['username'] : null;
            $userlist[] = $user;
        }
        return $userlist;
    }

    /**
     * returns user count
     *
     * @return int user count
     */
    function getUserCount()
    {
        /**
         * @ignore
         * @var $helper JFusionHelper_dokuwiki
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        return $helper->auth->getUserCount();
    }

    /**
     * get default user group list
     *
     * @return array with default user group list
     */
    function getUsergroupList()
    {
		$params = JFusionFactory::getParams($this->getJname());
		$usergroupmap = $params->get('usergroupmap','user,admin');
		
		$usergroupmap = explode (',', $usergroupmap);
        $usergrouplist = array();
		if ( is_array($usergroupmap) ) {
			foreach ($usergroupmap as $key => $value) {
	         	//append the default usergroup
	         	$default_group = new stdClass;
	            $default_group->id = trim($value);
	            $default_group->name = trim($value);
	            $usergrouplist[] = $default_group;
      		}
    	} else {    	
	        $default_group = new stdClass;
	        $default_group->name = $default_group->id = $this->getDefaultUsergroup();
	        $usergrouplist[] = $default_group;
    	}
        return $usergrouplist;
    }

    /**
     * get default user group
     *
     * @return string with default user group
     */
    function getDefaultUsergroup()
    {
        /**
         * @ignore
         * @var $helper JFusionHelper_dokuwiki
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        return $helper->getDefaultUsergroup();
    }

    /**
     * function  return if user can register or not
     *
     * @return boolean true can register
     */
    function allowRegistration()
    {
        /**
         * @ignore
         * @var $helper JFusionHelper_dokuwiki
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        $conf = $helper->getConf();
        if (strpos($conf['disableactions'], 'register') !== false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * debug configure
     * asdasd asdas
     *
     * @return void
     */
    function debugConfig()
    {
        $jname = $this->getJname();
        //get registration status
        $new_registration = $this->allowRegistration();
        //get the data about the JFusion plugins
        $db = JFactory::getDBO();
        $query = 'SELECT * from #__jfusion WHERE name = ' . $db->Quote($jname);
        $db->setQuery($query);
        $plugin = $db->loadObject();
        //output a warning to the administrator if the allowRegistration setting is wrong
        if ($new_registration && $plugin->slave == '1') {
            JError::raiseNotice(0, $jname . ': ' . JText::_('DISABLE_REGISTRATION'));
        }
        if (!$new_registration && $plugin->master == '1') {
            JError::raiseNotice(0, $jname . ': ' . JText::_('ENABLE_REGISTRATION'));
        }
    }

    /**
     * renerate redirect code
     *
     * @param string $url
     * @param int $itemid
     *
     * @return string output php redirect code
     */
    function generateRedirectCode($url, $itemid)
    {
        //create the new redirection code
        $redirect_code = '
//JFUSION REDIRECT START
//SET SOME VARS
$joomla_url = \'' . $url . '\';
$joomla_itemid = ' . $itemid . ';
    ';
        $redirect_code.= '
if (!defined(\'_JEXEC\'))';
        $redirect_code.= '
{
    $QUERY_STRING = array_merge( $_GET,$_POST );
    if (!isset($QUERY_STRING[\'id\'])) $QUERY_STRING[\'id\'] = $ID;
    $QUERY_STRING = http_build_query($QUERY_STRING);
    $order = array(\'%3A\', \':\', \'/\');
    $QUERY_STRING = str_replace($order,\';\',$QUERY_STRING);
    $pattern = \'#do=(admin|login|logout)#\';
    if ( !preg_match( $pattern , $QUERY_STRING )) {
        $file = $_SERVER["SCRIPT_NAME"];
        $break = explode(\'/\', $file);
        $pfile = $break[count($break) - 1];
        $jfusion_url = $joomla_url . \'index.php?option=com_jfusion&Itemid=\' . $joomla_itemid . \'&jfile=\'.$pfile. \'&\' . $QUERY_STRING;
        header(\'Location: \' . $jfusion_url);
        exit;
    }
}
//JFUSION REDIRECT END';
        return $redirect_code;
    }

    /**
     * Enable redirect mod
     *
     * @return void
     */
    function enableRedirectMod() {
        $params = JFusionFactory::getParams($this->getJname());
        $joomla_params = JFusionFactory::getParams('joomla_int');
        $joomla_url = $joomla_params->get('source_url');
        $joomla_itemid = $params->get('redirect_itemid');

        //check to see if all vars are set
        if (empty($joomla_url)) {
            JError::raiseWarning(0, JText::_('MISSING') . ' Joomla URL');
        } else if (empty($joomla_itemid) || !is_numeric($joomla_itemid)) {
            JError::raiseWarning(0, JText::_('MISSING') . ' ItemID');
        } else if (!$this->isValidItemID($joomla_itemid)) {
            JError::raiseWarning(0, JText::_('MISSING') . ' ItemID '. JText::_('MUST BE'). ' ' . $this->getJname());
        } else {
            $error = $this->disableRedirectMod();
            $reason = '';
            $mod_file = $this->getModFile('doku.php', $error, $reason);
            if ($error == 0) {
                //get the joomla path from the file
                jimport('joomla.filesystem.file');
                $file_data = JFile::read($mod_file);
                $redirect_code = $this->generateRedirectCode($joomla_url,$joomla_itemid);

//                $search = '/getID\(\)\;/si';
//                $replace = 'getID();' . $redirect_code;
                $search = '/\<\?php/si';
                $replace = '<?php' . $redirect_code;

                $file_data = preg_replace($search, $replace, $file_data);
                JFile::write($mod_file, $file_data);
            }
        }
    }

    /**
     * Disable redirect mod
     *
     * @return string
     */
    function disableRedirectMod()
    {
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('doku.php', $error, $reason);
        if ($error == 0) {
            //get the joomla path from the file
            jimport('joomla.filesystem.file');
            $file_data = JFile::read($mod_file);
            $search = '/\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END(\r?\n)/si';
            preg_match_all($search, $file_data, $matches);
            //remove any old code
            if (!empty($matches[1][0])) {
                $file_data = preg_replace($search, '', $file_data);
                if (!JFile::write($mod_file, $file_data)) {
                    $error = 1;
                }
            }
        }
        return $error;
    }

    /**
     * Used to display and conofigure the redirect mod
     *
     * @param string $name         name of element
     * @param string $value        value of element
     * @param string $node         node
     * @param string $control_name name of controler
     *
     * @return string html
     */
    function showRedirectMod($name, $value, $node, $control_name)
    {
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('doku.php', $error, $reason);
        if ($error == 0) {
            //get the joomla path from the file
            jimport('joomla.filesystem.file');
            $file_data = JFile::read($mod_file);
            preg_match_all('/\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/ms', $file_data, $matches);
            //compare it with our joomla path
            if (empty($matches[1][0])) {
                $error = 1;
                $reason = JText::_('MOD_NOT_ENABLED');
            }
        }
        //add the javascript to enable buttons
        if ($error == 0) {
            //return success
            $text = JText::_('REDIRECTION_MOD') . ' ' . JText::_('ENABLED');
            $disable = JText::_('MOD_DISABLE');
            $update = JText::_('MOD_UPDATE');
            $output = <<<HTML
            <img src="components/com_jfusion/images/check_good_small.png">{$text}
            <a href="javascript:void(0);" onclick="return module('disableRedirectMod')">{$disable}</a>
            <a href="javascript:void(0);" onclick="return module('enableRedirectMod')">{$update}</a>
HTML;
            return $output;
        } else {
            $text = JText::_('REDIRECTION_MOD') . ' ' . JText::_('DISABLED') . ': ' . $reason;
            $enable = JText::_('MOD_ENABLE');
            $output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return module('enableRedirectMod')">{$enable}</a>
HTML;
            return $output;
        }
    }

    /**
     * Used to display and conofigure the Auth mod
     *
     * @param string $name         name of element
     * @param string $value        value of element
     * @param string $node         node
     * @param string $control_name name of controler
     *
     * @return string html
     */
    function showAuthMod($name, $value, $node, $control_name)
    {
        $error = 0;
        $reason = '';
        /**
         * @ignore
         * @var $helper JFusionHelper_dokuwiki
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        $conf = $helper->getConf();
        $params = JFusionFactory::getParams($this->getJname());
        $source_path = $params->get('source_path');
        $plugindir = $source_path . DS . 'lib' . DS . 'plugins';

        //check to see if plugin installed and config options available
        jimport('joomla.filesystem.folder');
        if (!JFolder::exists($plugindir . DS . 'jfusion') || empty($conf['jfusion'])) {
            $error = 1;
            $reason = JText::_('MOD_NOT_ENABLED');
        }

        //add the javascript to enable buttons
        if ($error == 0) {
            //return success
            $text = JText::_('AUTHENTICATION_MOD') . ' ' . JText::_('ENABLED');
            $disable = JText::_('MOD_DISABLE');
            $update = JText::_('MOD_UPDATE');

            $output = <<<HTML
            <img src="components/com_jfusion/images/check_good_small.png">{$text}
            <a href="javascript:void(0);" onclick="return module('disableAuthMod')">{$disable}</a>';
            <a href="javascript:void(0);" onclick="return module('enableAuthMod')">{$update}</a>';
HTML;
            return $output;
        } else {
            $text = JText::_('AUTHENTICATION_MOD') . ' ' . JText::_('DISABLED') . ': ' . $reason;
            $enable = JText::_('MOD_ENABLE');
            $output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return module('enableAuthMod')">{$enable}</a>
HTML;
            return $output;
        }
    }

    /**
     * Enable auth mod
     *
     * @return void
     */
    function enableAuthMod()
    {
        $params = JFusionFactory::getParams($this->getJname());
        $source_path = $params->get('source_path');
        $plugindir = $source_path . DS . 'lib' . DS . 'plugins' . DS . 'jfusion';
        $pluginsource = JFUSION_PLUGIN_PATH . DS . 'dokuwiki' . DS . 'jfusion';

        //copy the jfusion plugin to Dokuwiki's plugin directory
        jimport('joomla.filesystem.folder');
        jimport('joomla.filesystem.file');

        if (JFolder::copy($pluginsource, $plugindir, '', true)) {
            //update the config file
            $cookie_domain = $params->get('cookie_domain');
            $cookie_path = $params->get('cookie_path');
            /**
             * @ignore
             * @var $helper JFusionHelper_dokuwiki
             */
            $helper = JFusionFactory::getHelper($this->getJname());
            $config_path = $helper->getConfigPath();

            if (JFolder::exists($config_path)) {
                $config_file = $config_path . 'local.php';
                if (!JFile::exists($config_file)) {
                    if (!JFile::create($config_file)) {
                       //config file couldn't be installed so abort
                       return;
                    }
                }

                $file_data = JFile::read($config_file);
                preg_match_all('/\/\/JFUSION AUTOGENERATED CONFIG START(.*)\/\/JFUSION AUTOGENERATED CONFIG END/ms', $file_data, $matches);
                //remove any old code
                if (!empty($matches[1][0])) {
                    $search = '/\/\/JFUSION AUTOGENERATED CONFIG START(.*)\/\/JFUSION AUTOGENERATED CONFIG END/ms';
                    $file_data = preg_replace($search, '', $file_data);
                }
                $joomla_basepath = JPATH_SITE;
                $config_code = <<<PHP
//JFUSION AUTOGENERATED CONFIG START
\$conf['jfusion']['cookie_path'] = '{$cookie_path}';
\$conf['jfusion']['cookie_domain'] = '{$cookie_domain}';
\$conf['jfusion']['joomla'] = 1;
\$conf['jfusion']['joomla_basepath'] = '{$joomla_basepath}';
\$conf['jfusion']['jfusion_plugin_name'] = '{$this->getJname()}';
//JFUSION AUTOGENERATED CONFIG END
PHP;
                $file_data .= $config_code;
                JFile::write($config_file, $file_data);
            }
        }
    }

    /**
     * Disable redirect mod
     *
     * @return string
     */
    function disableAuthMod()
    {
        $params = JFusionFactory::getParams($this->getJname());
        $source_path = $params->get('source_path');
        $plugindir = $source_path . DS . 'lib' . DS . 'plugins' . DS . 'jfusion';

        jimport('joomla.filesystem.folder');
        jimport('joomla.filesystem.file');

        //delete the jfusion plugin from Dokuwiki's plugin directory
        if (JFolder::exists($plugindir) && !JFolder::delete($plugindir)) {
            return false;
        }

        //update the config file
        /**
         * @ignore
         * @var $helper JFusionHelper_dokuwiki
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        $config_path = $helper->getConfigPath();

        if (JFolder::exists($config_path)) {
            $config_file = $config_path . 'local.php';
            if (JFile::exists($config_file)) {
                $file_data = JFile::read($config_file);
                preg_match_all('/\/\/JFUSION AUTOGENERATED CONFIG START(.*)\/\/JFUSION AUTOGENERATED CONFIG END/ms', $file_data, $matches);
                //remove any old code
                if (!empty($matches[1][0])) {
                    $search = '/\/\/JFUSION AUTOGENERATED CONFIG START(.*)\/\/JFUSION AUTOGENERATED CONFIG END/ms';
                    $file_data = preg_replace($search, '', $file_data);
                }

                JFile::write($config_file, $file_data);
            }
        }
        return true;
    }

    /**
     * uninstall function is to disable verious mods
     *
     * @return array
     */
    function uninstall()
    {
        $return = true;
        $reasons = array();

    	$error = $this->disableRedirectMod();
    	if (!empty($error)) {
           $reasons[] = JText::_('REDIRECT_MOD_UNINSTALL_FAILED');
           $return = false;
        }

        $error = $this->disableAuthMod();
        if (!$error) {
            $reasons[] = JText::_('AUTH_MOD_UNINSTALL_FAILED');
            $return = false;
        }

        return array($return, $reasons);
    }

    /**
     * do plugin support multi usergroups
     *
     * @return string UNKNOWN or JNO or JYES or ??
     */
    function requireFileAccess()
	{
		return 'JYES';
	}
}
