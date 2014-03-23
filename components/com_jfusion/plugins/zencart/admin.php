<?php namespace JFusion\Plugins\wordpress;

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
use Exception;
use JFusion\Factory;
use JFusion\Framework;
use Joomla\Database\DatabaseFactory;
use Joomla\Language\Text;
use JFusion\Plugin\Plugin_Admin;
use stdClass;

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
class Admin extends Plugin_Admin
{
    /**
     * @return string
     */
    function getTablename()
    {
        return 'customers';
    }

    /**
     * @param string $softwarePath
     *
     * @return array|bool
     */
    function setupFromPath($softwarePath)
    {
        $myfile = $softwarePath . 'includes' . DIRECTORY_SEPARATOR . 'configure.php';
        $enable_ssl = false;

        $params = array();
        $params['database_type'] = 'mysqli';
        $params['source_path'] = $softwarePath;

        if (!file_exists($myfile)) {
            Framework::raiseWarning(Text::_('WIZARD_FAILURE') . ': ' . $myfile . ' ' . Text::_('WIZARD_MANUAL'), $this->getJname());
            return false;
        } else {
            include_once($myfile);
            //save the parameters into array


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
            if (defined('DB_TYPE')) {
                $params['database_type'] = DB_TYPE;
            }
            if  (ENABLE_SSL != 'false'){
                $params['source_url'] = HTTPS_SERVER . DIR_WS_CATALOG;
            } else  {
                $params['source_url'] = HTTP_SERVER . DIR_WS_CATALOG;
            }
            $driver = 'mysql';
            $options = array('driver' => $driver, 'host' => $params['database_host'], 'user' => $params['database_user'],
                'password' => $params['database_password'], 'database' => $params['database_name'],
                'prefix' => $params['database_prefix']);

	        $db = DatabaseFactory::getInstance($options)->getDriver($driver, $options);

            //Get Default Country
            $query = $db->getQuery(true)
                ->select('configuration_value')
                ->from('#__configuration')
                ->where('configuration_key = ' . $db->quote('STORE_COUNTRY'));
            $db->setQuery($query);
            $params['default_country'] = $db->loadResult();
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
    function getUserList($limitstart = 0, $limit = 0)
    {
        try {
            //getting the connection to the db
            $db = Factory::getDatabase($this->getJname());

            $query = $db->getQuery(true)
                ->select('customers_email_address as username, customers_email_address as email')
                ->from('#__customers');

            $db->setQuery($query, $limitstart, $limit);
            //getting the results
            $userlist = $db->loadObjectList();
        } catch (Exception $e) {
            Framework::raiseError($e, $this->getJname());
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
            $db = Factory::getDatabase($this->getJname());

            $query = $db->getQuery(true)
                ->select('count(*)')
                ->from('#__customers');

            $db->setQuery($query);
            //getting the results
            $no_users = $db->loadResult();
        } catch (Exception $e) {
            Framework::raiseError($e, $this->getJname());
            $no_users = 0;
        }
        return $no_users;
    }

    /**
     * @return array|bool
     */
    function getUsergroupList()
    {
	    $db = Factory::getDataBase($this->getJname());

	    $query = $db->getQuery(true)
		    ->select('group_id as id, group_name as name')
		    ->from('#__group_pricing');

	    $db->setQuery($query);
	    //getting the results
	    $result1 = $db->loadObjectList();
	    $result = array();
	    $result[0] = new stdClass;
	    $result[0]->id = '0';
	    $result[0]->name = '-none-';
	    $result = array_merge((array)$result, (array)$result1);
        return $result;
    }

    /**
     * @return bool
     */
    function allowRegistration()
    {
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
    function allowEmptyCookiePath()
    {
        return true;
    }

    /**
     * @return bool
     */
    function allowEmptyCookieDomain()
    {
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