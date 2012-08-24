<?php

/**
 * file containing administrator function for the jfusion plugin
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage osCommerce
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage osCommerce
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionAdmin_oscommerce extends JFusionAdmin 
{
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'oscommerce';
    }

    /**
     * @return string
     */
    function getTablename() {
        return 'customers';
    }

    /**
     * @param string $forumPath
     * @return array|bool
     */
    function setupFromPath($forumPath) {
        //check for trailing slash and generate file path
        if (substr($forumPath, -1) == DS) {
            $myfile = $forumPath . 'includes' . DS . 'configure.php';
        } else {
            $myfile = $forumPath . DS . 'includes' . DS . 'configure.php';
        }
        $params = array();
        if (($file_handle = @fopen($myfile, 'r')) === false) {
            JError::raiseWarning(500, JText::_('WIZARD_FAILURE') . ": $myfile " . JText::_('WIZARD_MANUAL'));
        } else {
            //parse the file line by line to get only the config variables
            fclose($file_handle);
            include_once ($myfile);
            //save the parameters into array
            $params = array();
            $params['database_host'] = DB_SERVER;
            $params['database_name'] = DB_DATABASE;
            $params['database_user'] = DB_SERVER_USERNAME;
            $params['database_password'] = DB_SERVER_PASSWORD;
            if (DB_TABLE_PREFIX !== 'DB_TABLE_PREFIX') {
                $params['database_prefix'] = DB_TABLE_PREFIX;
            }
            if (DB_PREFIX !== 'DB_PREFIX') {
                $params['database_prefix'] = DB_PREFIX;
            }
            $params['database_type'] = 'mysqli';
            if (DB_DATABASE_CLASS !== 'DB_DATABASE_CLASS') {
                $params['database_type'] = DB_DATABASE_CLASS;
            }
            if (DB_TYPE !== 'DB_TYPE') {
                $params['database_type'] = DB_TYPE;
            }
            $params['source_path'] = $forumPath;
            $params['usergroup'] = '0'; #make sure we do not assign roles with more capabilities automatically
            // handle ssl enabling
            $enable_ssl = false;
            if (ENABLE_SSL !== 'ENABLE_SSL') {
                if (ENABLE_SLL !== false) {
                    $enable_ssl = true;
                }
            }
            if (ENABLE_SSL_CATALOG !== 'ENABLE_SSL_CATALOG') {
                if (ENABLE_SLL_CATALOG !== false) {
                    $enable_ssl = true;
                }
            }
            if ($enable_ssl) {
                if (HTTPS_SERVER !== 'HTTPS_SERVER') {
                    $params['source_url'] = $params['source_url'] . HTTPS_SERVER;
                }
                if (HTTPS_CATALOG_SERVER !== 'HTTPS_CATALOG_SERVER') {
                    $params['source_url'] = $params['source_url'] . HTTPS_CATALOG_SERVER;
                }
                if (DIR_WS_HTTPS_CATALOG !== 'DIR_WS_HTTPS_CATALOG') {
                    $params['source_url'] = $params['source_url'] . DIR_WS_HTTPS_CATALOG;
                }
                if (HTTPS_COOKIE_PATH !== 'HTTPS_COOKIE_PATH') {
                    $params['cookie_path'] = HTTPS_COOKIE_PATH;
                }
                if (HTTPS_COOKIE_DOMAIN !== 'HTTPS_COOKIE_DOMAIN') {
                    $params['cookie_domain'] = HTTPS_COOKIE_DOMAIN;
                }
            } else {
                if (HTTP_SERVER !== 'HTTP_SERVER') {
                    $params['source_url'] = $params['source_url'] . HTTP_SERVER;
                }
                if (HTTP_CATALOG_SERVER !== 'HTTP_CATALOG_SERVER') {
                    $params['source_url'] = $params['source_url'] . HTTP_CATALOG_SERVER;
                }
                if (DIR_WS_HTTP_CATALOG !== 'DIR_WS_HTTP_CATALOG') {
                    $params['source_url'] = $params['source_url'] . DIR_WS_HTTP_CATALOG;
                }
                if (DIR_WS_CATALOG !== 'DIR_WS_CATALOG') {
                    $params['source_url'] = $params['source_url'] . DIR_WS_CATALOG;
                }
                if (HTTP_COOKIE_PATH !== 'HTTP_COOKIE_PATH') {
                    $params['cookie_path'] = HTTP_COOKIE_PATH;
                }
                if (HTTP_COOKIE_DOMAIN !== 'HTTP_COOKIE_DOMAIN') {
                    $params['cookie_domain'] = HTTP_COOKIE_DOMAIN;
                }
            }
        }
        return $params;
    }

    /**
     * @return array
     */
    function getUserList() {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT customers_email_address as username, customers_email_address as email from #__customers';
        $db->setQuery($query);
        //getting the results
        $userlist = $db->loadObjectList();
        return $userlist;
    }

    /**
     * @return int
     */
    function getUserCount() {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT count(*) from #__customers';
        $db->setQuery($query);
        //getting the results
        $no_users = $db->loadResult();
        return $no_users;
    }

    /**
     * @return array|bool
     */
    function getUsergroupList() {
        $params = JFusionFactory::getParams($this->getJname());
        $osCversion = $params->get('osCversion');
        switch ($osCversion) {
            case 'osc2':
            case 'osc3':
                $result = array();
                $result[0]['id'] = 0;
                $result[0]['name'] = '-none-';
                return $result;
            case 'osczen':
                $db = JFusionFactory::getDataBase($this->getJname());
                $query = 'SELECT group_id as id, group_name as name from #__group_pricing;';
                $db->setQuery($query);
                //getting the results
                $result1 = $db->loadObjectList();
                $result = array();
                $result[0]['id'] = 0;
                $result[0]['name'] = '-none-';
                $result = array_merge((array)$result, (array)$result1);
                return $result;
            case 'oscxt':
            case 'oscseo':
                // get default language
                $db = JFusionFactory::getDataBase($this->getJname());
                $query = 'SELECT configuration_value from #__configuration WHERE configuration_key = \'DEFAULT_LANGUAGE\'';
                $db->setQuery($query);
                $default_language = $db->loadResult();
                $query = 'SELECT languages_id from #__languages WHERE code =' . $db->Quote($default_language);
                $db->setQuery($query);
                $default_language_id = $db->loadResult();
                $query = 'SELECT customers_status_id as id, customers_status_name as name from #__customers_status WHERE language_id =' . $default_language_id;
                $db->setQuery($query);
                //getting the results
                $result = $db->loadObjectList();
                return $result;
            case 'oscmax':
                $db = JFusionFactory::getDataBase($this->getJname());
                $query = 'SELECT customers_group_id as id, customers_group_name as name from #__customers_groups;';
                $db->setQuery($query);
                //getting the results
                $result = $db->loadObjectList();
                return $result;
        }
        return false;
    }

    /**
     * @return bool|string
     */
    function getDefaultUsergroup() {
        $params = JFusionFactory::getParams($this->getJname());
        $osCversion = $params->get('osCversion');
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),null);
        $usergroup_id = null;
        if(!empty($usergroups)) {
            $usergroup_id = $usergroups[0];
        }
        switch ($osCversion) {
            case 'osc2':
            case 'osc3':
                return '-none-';
            case 'osczen':
                //we want to output the usergroup name
                $db = JFusionFactory::getDatabase($this->getJname());
                $query = 'SELECT group_name from #__group_pricing WHERE group_id = ' . $usergroup_id;
                $db->setQuery($query);
                return $db->loadResult();
            case 'oscxt':
            case 'oscseo':
                $db = JFusionFactory::getDataBase($this->getJname());
                $query = 'SELECT configuration_value from #__configuration WHERE configuration_key = \'DEFAULT_LANGUAGE\'';
                $db->setQuery($query);
                $default_language = $db->loadResult();
                $query = 'SELECT languages_id from #__languages WHERE code =' . $db->Quote($default_language);
                $db->setQuery($query);
                $default_language_id = $db->loadResult();
                //we want to output the usergroup name
                $query = 'SELECT customers_status_name from #__customers_status WHERE customers_status_id = ' . $usergroup_id . ' AND language_id = ' . $default_language_id;
                $db->setQuery($query);
                return $db->loadResult();
            case 'oscmax':
                //we want to output the usergroup name
                $db = JFusionFactory::getDatabase($this->getJname());
                $query = 'SELECT customers_group_name from #__customers_groups WHERE customers_group_id = ' . $usergroup_id;
                $db->setQuery($query);
                return $db->loadResult();
        }
        return false;
    }

    /**
     * @return bool
     */
    function allowRegistration() {
        $result = true;
        $params = JFusionFactory::getParams($this->getJname());
        $registration_disabled = $params->get('disabled_registration');
        if ($registration_disabled) {
            $result = false;
        }
        return $result;
    }

    /**
     * @return bool
     */
    function allowEmptyCookiePath() {
        return true;
    }

    /**
     * @return bool
     */
    function allowEmptyCookieDomain() {
        return true;
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
     * @return string UNKNOWN or JNO or JYES or ???
     */
    function requireFileAccess()
	{
		return 'JNO';
	}    
}