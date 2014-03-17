<?php

/**
 * Abstract admin file
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Abstract interface for all JFusion functions that are accessed through the Joomla administrator interface
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionAdmin extends JFusionPlugin
{
	var $helper;

	/**
	 *
	 */
	function __construct()
	{
		parent::__construct();
		//get the helper object
		$this->helper = &JFusionFactory::getHelper($this->getJname());
	}

    /**
     * Returns the a list of users of the integrated software
     * @param int $limitstart optional
     * @param int $limit optional
     * @return array List of usernames/emails
     */
    function getUserList($limitstart = 0, $limit = 0)
    {
        return array();
    }

    /**
     * Returns the the number of users in the integrated software. Allows for fast retrieval total number of users for the usersync
     *
     * @return integer Number of registered users
     */
    function getUserCount()
    {
        return 0;
    }

    /**
     * Returns the a list of usersgroups of the integrated software
     *
     * @return array List of usergroups
     */
    function getUsergroupList()
	{
        return array();
    }


    /**
     * Function used to display the default usergroup in the JFusion plugin overview
     *
     * @return array Default usergroup name
     */
    function getDefaultUsergroup()
    {
        $usergroups = JFusionFunction::getUserGroups($this->getJname(), true);

        $groups = array();
        if ($usergroups !== null) {
	        $list = $this->getUsergroupList();
	        foreach ($list as $group) {
		        if(in_array($group->id, $usergroups)){
			        $groups[] = $group->name;
		        }
	        }
        }
        return $groups;
    }

    /**
     * Checks if the software allows new users to register
     *
     * @return boolean True if new user registration is allowed, otherwise returns false
     */
    function allowRegistration()
    {
        return true;
    }

    /**
     * returns the name of user table of integrated software
     *
     * @return string table name
     */
    function getTablename()
    {
        return '';
    }

    /**
     * Function finds config file of integrated software and automatically configures the JFusion plugin
     *
     * @param string $softwarePath path to root of integrated software
     *
     * @return array array with ne newly found configuration
     */
    function setupFromPath($softwarePath)
    {
        return array();
    }

	/**
	 * Function that checks if the plugin has a valid config
	 *
	 * @throws RuntimeException
	 * @return boolean return true for success false for error, if you want a message to be included you need to use throw.
	 */
    function checkConfig()
    {
        //for joomla_int check to see if the source_url does not equal the default
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());
	    } catch (Exception $e) {
		    throw new RuntimeException(JText::_('NO_DATABASE') . ' : ' . $e->getMessage());
	    }

	    try {
		    $jdb = JFactory::getDBO();
	    } catch (Exception $e) {
		    throw new RuntimeException($this->getJname() . ' -> joomla_int ' . JText::_('NO_DATABASE') . ' : ' . $e->getMessage());
	    }

	    if (!$db->connected()) {
		    throw new RuntimeException(JText::_('NO_DATABASE'));
	    } elseif (!$jdb->connected()) {
		    throw new RuntimeException($this->getJname() . ' -> joomla_int ' . JText::_('NO_DATABASE'));
	    } else {
		    //added check for missing files of copied plugins after upgrade
		    $path = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR;
		    if (!file_exists($path . 'admin.php')) {
			    throw new RuntimeException(JText::_('NO_FILES') . ' admin.php');
		    } else if (!file_exists($path . 'user.php')) {
			    throw new RuntimeException(JText::_('NO_FILES') . ' user.php');
		    } else {
			    $cookie_domain = $this->params->get('cookie_domain');
			    $jfc = JFusionFactory::getCookies();
			    list($url) = $jfc->getApiUrl($cookie_domain);
			    if ($url) {
				    require_once(JPATH_SITE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'jfusionapi.php');

				    $api = new JFusionAPI($url, JFusionFactory::getParams('joomla_int')->get('secret'));
				    if (!$api->ping()) {
					    list ($message) = $api->getError();

					    throw new RuntimeException($api->url . ' ' . $message);
				    }
			    }
			    $source_path = $this->params->get('source_path');
			    if ($source_path && (strpos($source_path, 'http://') === 0 || strpos($source_path, 'https://') === 0)) {
				    throw new RuntimeException(JText::_('ERROR_SOURCE_PATH') . ' : ' . $source_path);
			    } else {
				    //get the user table name
				    $tablename = $this->getTablename();
				    // lets check if the table exists, now using the Joomla API
				    $table_list = $db->getTableList();
				    $table_prefix = $db->getPrefix();
				    if (!is_array($table_list)) {
					    throw new RuntimeException($table_prefix . $tablename . ': ' . JText::_('NO_TABLE'));
				    } else {
					    if (array_search($table_prefix . $tablename, $table_list) === false) {
						    //do a final check for case insensitive windows servers
						    if (array_search(strtolower($table_prefix . $tablename), $table_list) === false) {
							    throw new RuntimeException($table_prefix . $tablename . ': ' . JText::_('NO_TABLE'));
						    }
					    }
				    }
			    }
		    }
	    }
	    $status = array();
	    $status['config'] = 1;
	    $status['message'] = JText::_('GOOD_CONFIG');
        return true;
    }

	/**
	 * @param int $status
	 */
	public final function updateStatus($status = 0) {
		try {
			$db = JFactory::getDBO();
			$query = $db->getQuery(true)
				->update('#__jfusion')
				->set('status = ' . $db->quote($status))
				->where('name = ' . $db->quote($this->getJname()));
			$db->setQuery($query);
			$db->execute();
		} catch (Exception $e) {
			//there was an error saving the parameters
			JFusionFunction::raiseError($e, $this->getJname());
		}
	}

    /**
     * Function that checks if the plugin has a valid config
     * jerror is used for output
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

	    $query = $db->getQuery(true)
		    ->select('*')
		    ->from('#__jfusion')
		    ->where('name = ' . $db->quote($jname));

	    $db->setQuery($query);
	    $plugin = $db->loadObject();
	    //output a warning to the administrator if the allowRegistration setting is wrong
	    if ($new_registration && $plugin->slave == 1) {
		    JFusionFunction::raiseNotice(JText::_('DISABLE_REGISTRATION'), $jname);
	    }
	    if (!$new_registration && $plugin->master == 1) {
		    JFusionFunction::raiseNotice(JText::_('ENABLE_REGISTRATION'), $jname);
	    }
	    //most dual login problems are due to incorrect cookie domain settings
	    //therefore we should check it and output a warning if needed.

	    $cookie_domain = $this->params->get('cookie_domain',-1);
	    if ($cookie_domain!==-1) {
		    $cookie_domain = str_replace(array('http://', 'https://'), array('', ''), $cookie_domain);
		    $correct_array = explode('.', html_entity_decode($_SERVER['SERVER_NAME']));

		    //check for domain names with double extentions
		    if (isset($correct_array[count($correct_array) - 2]) && isset($correct_array[count($correct_array) - 1])) {
			    //domain array
			    $domain_array = array('com', 'net', 'org', 'co', 'me');
			    if (in_array($correct_array[count($correct_array) - 2], $domain_array)) {
				    $correct_domain = '.' . $correct_array[count($correct_array) - 3] . '.' . $correct_array[count($correct_array) - 2] . '.' . $correct_array[count($correct_array) - 1];
			    } else {
				    $correct_domain = '.' . $correct_array[count($correct_array) - 2] . '.' . $correct_array[count($correct_array) - 1];
			    }
			    if ($correct_domain != $cookie_domain && !$this->allowEmptyCookieDomain()) {
				    JFusionFunction::raiseNotice(JText::_('BEST_COOKIE_DOMAIN') . ' ' . $correct_domain, $jname);
			    }
		    }
	    }

	    //also check the cookie path as it can interfere with frameless
	    $cookie_path = $this->params->get('cookie_path',-1);
	    if ($cookie_path!==-1) {
		    if ($cookie_path != '/' && !$this->allowEmptyCookiePath()) {
			    JFusionFunction::raiseNotice(JText::_('BEST_COOKIE_PATH') . ' /', $jname);
		    }
	    }

	    // allow additional checking of the configuration
	    $this->debugConfigExtra();
    }

    /**
     * Function that determines if the empty cookie path is allowed
     *
     * @return bool
     */
    function allowEmptyCookiePath()
    {
        return false;
    }

    /**
     * Function that determines if the empty cookie domain is allowed
     *
     * @return bool
     */
    function allowEmptyCookieDomain()
    {
        return false;
    }

    /**
     * Function to implement any extra debug checks for plugins
     *
     * @return void
     */
    function debugConfigExtra()
    {
    }

    /**
     * Function returns the path to the modfile
     *
     * @param string $filename file name
     * @param int    &$error   error number
     * @param string &$reason  error reason
     *
     * @return string $mod_file path and file of the modfile.
     */
    function getModFile($filename, &$error, &$reason)
    {
        //check to see if a path is defined
        $path = $this->params->get('source_path');
        if (empty($path)) {
            $error = 1;
            $reason = JText::_('SET_PATH_FIRST');
        }
        //check for trailing slash and generate file path
        if (substr($path, -1) == DIRECTORY_SEPARATOR) {
            $mod_file = $path . $filename;
        } else {
            $mod_file = $path . DIRECTORY_SEPARATOR . $filename;
        }
        //see if the file exists
        if (!file_exists($mod_file) && $error == 0) {
            $error = 1;
            $reason = JText::_('NO_FILE_FOUND');
        }
        return $mod_file;
    }

    /**
     * Called when JFusion is uninstalled so that plugins can run uninstall processes such as removing auth mods
     * @return array    [0] boolean true if successful uninstall
     *                  [1] mixed reason(s) why uninstall was unsuccessful
     */
    function uninstall()
    {
        return array(true, '');
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
     * This function is used to display to the user if the software requires file access to work
     *
     * @return string UNKNOWN or JNO or JYES or ??
     */
    function requireFileAccess()
    {
        return 'UNKNOWN';
    }

	/**
	 * This function tells if the software supports more than one instance
	 *
	 * @return bool do the plugin support multi instance
	 */
	function multiInstance()
	{
		return true;
	}

    /**
     * Function to check if a given itemid is configured for the plugin in question.
     *
     * @param int $itemid
     * @return bool
     */
    public final function isValidItemID($itemid)
    {
        $result = false;
        if ($itemid) {
            $app = JFactory::getApplication();
            $menus = $app->getMenu('site');
            $params = $menus->getParams($itemid);
            if ($params) {
                $jPluginParam = unserialize(base64_decode($params->get('JFusionPluginParam')));
                if (is_array($jPluginParam) && $jPluginParam['jfusionplugin'] == $this->getJname()) {
                    $result = true;
                }
            }
        }
        return $result;
    }

	/**
	 * read a given file (use to read config files)
	 *
	 * @param $file
	 *
	 * @return bool|array returns false or file content
	 */
	function readFile($file)
	{
		$fh = @fopen($file, 'r');

		$lines = false;
		if ($fh !== false) {
			$lines = array();
			while (!feof($fh)) {
				$lines[] = fgets($fh);
			}
			fclose($fh);
		}
		return $lines;
	}

	/**
	 * create the render group function
	 *
	 * @return string
	 */
	function getRenderGroup()
	{
		$jname = $this->getJname();
		$js = <<<JS
		JFusion.renderPlugin['{$jname}'] = JFusion.renderDefault;
JS;
		return $js;
	}

	/**
	 * Saves the posted JFusion component variables
	 *
	 * @param array  $post  Array of JFusion plugin parameters posted to the JFusion component
	 * @param boolean $wizard Notes if function was called by wizardresult();
	 *
	 * @return boolean returns true if successful and false if an error occurred
	 */
	final public function saveParameters($post, $wizard = false)
	{
		$jname = $this->getJname();
		$result = false;
		try {
			if (!empty($jname)) {
				$db = JFactory::getDBO();

				if (isset($post['source_url'])) {
					//check for trailing slash in URL, in order for us not to worry about it later
					if (substr($post['source_url'], -1) != '/') {
						$post['source_url'] .= '/';
					}

					//now also check to see that the url starts with http:// or https://
					if (substr($post['source_url'], 0, 7) != 'http://' && substr($post['source_url'], 0, 8) != 'https://') {
						if (substr($post['source_url'], 0, 1) != '/') {
							$post['source_url'] = 'http://' . $post['source_url'];
						}
					}
				}
				if (isset($post['source_path'])) {
					if (!empty($post['source_path'])) {
						if (substr($post['source_path'], -1) != DIRECTORY_SEPARATOR) {
							$post['source_path'] .= DIRECTORY_SEPARATOR;
						}
						if (!is_dir($post['source_path'])) {
							JFusionFunction::raiseWarning(JText::_('SOURCE_PATH_NOT_FOUND'));
						}
					}
				}

				if ($wizard) {
					//data submitted by the wizard so merge the data with existing params if they do indeed exist

					$query = $db->getQuery(true)
						->select('params')
						->from('#__jfusion')
						->where('name = ' . $db->quote($jname));

					$db->setQuery($query);
					$params = $db->loadResult();
					$params = new JRegistry($params);

					$existing_params = $params->toArray();
					if (is_array($existing_params)) {
						$post = array_merge($existing_params, $post);
					}
				}

				$data = new JRegistry($post);
				//set the current parameters in the jfusion table

				$query = $db->getQuery(true)
					->update('#__jfusion')
					->set('params = ' . $db->quote($data->toString()))
					->where('name = ' . $db->quote($jname));

				$db->setQuery($query);

				$db->execute();

				//reset the params instance for this plugin
				JFusionFactory::getParams($jname, true);
				$result = true;
			}
		} catch (Exception $e ) {
			//there was an error saving the parameters
			JFusionFunction::raiseError($e, $jname);
		}
		return $result;
	}
}