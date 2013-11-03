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
     * @param string $softwarePath
     *
     * @return array|bool
     */
    function setupFromPath($softwarePath) {
	    $myfile = $softwarePath . 'includes' . DIRECTORY_SEPARATOR . 'configure.php';

        $params = array();
        if (!file_exists($myfile)) {
            JFusionFunction::raiseWarning(JText::_('WIZARD_FAILURE') . ': '.$myfile.' '. JText::_('WIZARD_MANUAL'), $this->getJname());
	        return false;
        } else {
            include_once ($myfile);
            //save the parameters into array
            $params = array();
            $params['database_host'] = DB_SERVER;
            $params['database_name'] = DB_DATABASE;
            $params['database_user'] = DB_SERVER_USERNAME;
            $params['database_password'] = DB_SERVER_PASSWORD;

            if (defined('DB_TABLE_PREFIX')) {
                $params['database_prefix'] = DB_TABLE_PREFIX;
            }
            if (defined('DB_PREFIX')) {
                $params['database_prefix'] = DB_PREFIX;
            }
            $params['database_type'] = 'mysqli';
            if (defined('DB_DATABASE_CLASS')) {
                $params['database_type'] = DB_DATABASE_CLASS;
            }
            if (defined('DB_TYPE')) {
                $params['database_type'] = DB_TYPE;
            }
            $params['source_path'] = $softwarePath;
            // handle ssl enabling
            $enable_ssl = false;
            if (defined('ENABLE_SLL')) {
                if (ENABLE_SLL !== false) {
                    $enable_ssl = true;
                }
            }
            if (defined('ENABLE_SLL_CATALOG')) {
                if (ENABLE_SLL_CATALOG !== false) {
                    $enable_ssl = true;
                }
            }
            if ($enable_ssl) {
                if (defined('HTTPS_SERVER')) {
                    $params['source_url'] = $params['source_url'] . HTTPS_SERVER;
                }
                if (defined('HTTPS_CATALOG_SERVER')) {
                    $params['source_url'] = $params['source_url'] . HTTPS_CATALOG_SERVER;
                }
                if (defined('DIR_WS_HTTPS_CATALOG')) {
                    $params['source_url'] = $params['source_url'] . DIR_WS_HTTPS_CATALOG;
                }
                if (defined('HTTPS_COOKIE_PATH')) {
                    $params['cookie_path'] = HTTPS_COOKIE_PATH;
                }
                if (defined('HTTPS_COOKIE_DOMAIN')) {
                    $params['cookie_domain'] = HTTPS_COOKIE_DOMAIN;
                }
            } else {
                if (defined('HTTP_SERVER')) {
                    $params['source_url'] = $params['source_url'] . HTTP_SERVER;
                }
                if (defined('HTTP_CATALOG_SERVER')) {
                    $params['source_url'] = $params['source_url'] . HTTP_CATALOG_SERVER;
                }
                if (defined('DIR_WS_HTTP_CATALOG')) {
                    $params['source_url'] = $params['source_url'] . DIR_WS_HTTP_CATALOG;
                }
                if (defined('DIR_WS_CATALOG')) {
                    $params['source_url'] = $params['source_url'] . DIR_WS_CATALOG;
                }
                if (defined('HTTP_COOKIE_PATH')) {
                    $params['cookie_path'] = HTTP_COOKIE_PATH;
                }
                if (defined('HTTP_COOKIE_DOMAIN')) {
                    $params['cookie_domain'] = HTTP_COOKIE_DOMAIN;
                }
            }
        }
        return $params;
    }

    /**
     * Returns the a list of users of the integrated software
     *
     * @param int $limitstart start at
     * @param int $limit number of results
     *
     * @return array
     */
    function getUserList($limitstart = 0, $limit = 0) {
	    try {
		    //getting the connection to the db
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('customers_email_address as username, customers_email_address as email')
			    ->from('#__customers');

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
    function getUserCount() {
	    try {
		    //getting the connection to the db
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('count(*)')
			    ->from('#__customers');

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
     * @return array|bool
     */
    function getUsergroupList() {
	    $result = array();
	    try {
	        $osCversion = $this->params->get('osCversion');
	        switch ($osCversion) {
	            case 'osc2':
	            case 'osc3':
	                $result = array();
			        $result[0] = new stdClass;
			        $result[0]->id ='0';
			        $result[0]->name ='-none-';
	                break;
	            case 'osczen':
	                $db = JFusionFactory::getDataBase($this->getJname());

		            $query = $db->getQuery(true)
			            ->select('group_id as id, group_name as name')
			            ->from('#__group_pricing');

	                $db->setQuery($query);
	                //getting the results
	                $result1 = $db->loadObjectList();
	                $result = array();
		            $result[0] = new stdClass;
		            $result[0]->id ='0';
		            $result[0]->name ='-none-';
	                $result = array_merge((array)$result, (array)$result1);
		            break;
	            case 'oscxt':
	            case 'oscseo':
	                // get default language
	                $db = JFusionFactory::getDataBase($this->getJname());

			        $query = $db->getQuery(true)
				        ->select('configuration_value')
				        ->from('#__configuration')
			            ->where('configuration_key = ' . $db->quote('DEFAULT_LANGUAGE'));

	                $db->setQuery($query);
	                $default_language = $db->loadResult();

			        $query = $db->getQuery(true)
				        ->select('languages_id')
				        ->from('#__languages')
				        ->where('code = ' . $db->Quote($default_language));

	                $db->setQuery($query);
	                $default_language_id = $db->loadResult();

			        $query = $db->getQuery(true)
				        ->select('customers_status_id as id, customers_status_name as name')
				        ->from('#__customers_status')
				        ->where('language_id = ' . $default_language_id);

	                $db->setQuery($query);
	                //getting the results
	                $result = $db->loadObjectList();
		            break;
	            case 'oscmax':
	                $db = JFusionFactory::getDataBase($this->getJname());

		            $query = $db->getQuery(true)
			            ->select('customers_group_id as id, customers_group_name as name')
			            ->from('#__customers_groups');

	                $db->setQuery($query);
	                //getting the results
	                $result = $db->loadObjectList();
		            break;
	        }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
	    }
        return $result;
    }

    /**
     * @return string|array
     */
    function getDefaultUsergroup() {

	    try {
		    $osCversion = $this->params->get('osCversion');
		    $usergroups = JFusionFunction::getUserGroups($this->getJname(), true);

		    if ($usergroups !== null) {
			    $group = '';
			    switch ($osCversion) {
				    case 'osc2':
				    case 'osc3':
					    $group = '-none-';
					    break;
				    case 'osczen':
					    //we want to output the usergroup name
					    $db = JFusionFactory::getDatabase($this->getJname());

					    $query = $db->getQuery(true)
						    ->select('group_name')
						    ->from('#__group_pricing')
						    ->where('group_id = ' . $usergroups);

					    $db->setQuery($query);
					    $group = $db->loadResult();
					    break;
				    case 'oscxt':
				    case 'oscseo':
					    $db = JFusionFactory::getDataBase($this->getJname());

					    $query = $db->getQuery(true)
						    ->select('configuration_value')
						    ->from('#__configuration')
						    ->where('configuration_key = ' . $db->quote('DEFAULT_LANGUAGE'));

					    $db->setQuery($query);
					    $default_language = $db->loadResult();

					    $query = $db->getQuery(true)
						    ->select('languages_id')
						    ->from('#__languages')
						    ->where('code = ' . $db->Quote($default_language));

					    $db->setQuery($query);
					    $default_language_id = $db->loadResult();
					    //we want to output the usergroup name
					    $query = $db->getQuery(true)
						    ->select('customers_status_name')
						    ->from('#__customers_status')
						    ->where('customers_status_id = ' . $usergroups)
						    ->where('language_id = ' . $default_language_id);

					    $db->setQuery($query);
					    $group = $db->loadResult();
					    break;
				    case 'oscmax':
					    //we want to output the usergroup name
					    $db = JFusionFactory::getDatabase($this->getJname());

					    $query = $db->getQuery(true)
						    ->select('customers_group_name')
						    ->from('#__customers_groups')
						    ->where('customers_group_id = ' . $usergroups);

					    $db->setQuery($query);
					    $group = $db->loadResult();
					    break;
			    }
		    } else {
			    $group = '';
		    }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $group = '';
	    }
        return $group;
    }

    /**
     * @return bool
     */
    function allowRegistration() {
        $result = true;
        $registration_disabled = $this->params->get('disabled_registration');
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