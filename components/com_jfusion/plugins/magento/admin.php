<?php namespace JFusion\Plugins\magento;

/**
 * file containing administrator function for the jfusion plugin
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Magento 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
use Exception;
use JFusion\Factory;
use JFusion\Framework;
use Joomla\Language\Text;
use JFusion\Plugin\Plugin_Admin;
use Soapfault;

defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Admin class for Magento 1.1
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Magento 
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
    function getTablename() {
        return 'admin_user';
    }

	/**
	 * @param $version
	 *
	 * @return string
	 */
	public function normalize_version($version)
	{
    	/// 1.9 Beta 2 should be read 1.9 , not 1.9.2
    	/// we can discard everything after the first space
    	$version = trim($version);
    	$versionarr = explode(' ', $version);
    	if (!empty($versionarr)) {
    		$version = $versionarr[0];
    	}
    	/// Replace everything but numbers and dots by dots
    	$version = preg_replace('/[^\.\d]/', '.', $version);
    	/// Combine multiple dots in one
    	$version = preg_replace('/(\.{2,})/', '.', $version);
    	/// Trim possible leading and trailing dots
    	$version = trim($version, '.');
    	return $version;
    }
    
    
    // get the Magento version number
	/**
	 * @param $forumPath
	 *
	 * @return string
	 */
	function getMagentoVersion($forumPath)
	{
    	$file = file_get_contents(rtrim($forumPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Mage.php');

		$pstart = strpos($file, 'function getVersionInfo()');
		$pstart = strpos($file, 'return', $pstart);
		$pend = strpos($file, ');', $pstart);
		$version = eval(substr($file, $pstart, $pend-$pstart+2));

    	return $version['major'] . '.' . $version['minor'] . '.' . $version['revision'];
    }
    
    

    /**
     * @param string $softwarePath
     * @return array
     */
    function setupFromPath($softwarePath)
    {
        $xmlfile = $softwarePath . 'app' . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'local.xml';
        $params = array();
        if (file_exists($xmlfile)) {
	        $xml = Framework::getXml($xmlfile);
            if (!$xml) {
                Framework::raiseWarning(Text::_('WIZARD_FAILURE') . ': ' . $xmlfile . ' ' . Text::_('WIZARD_MANUAL'), $this->getJname());
	            return false;
            } else {
                //save the parameters into array
                $params['database_host'] = (string)$xml->global->resources->default_setup->connection->host;
                $params['database_name'] = (string)$xml->global->resources->default_setup->connection->dbname;
                $params['database_user'] = (string)$xml->global->resources->default_setup->connection->username;
                $params['database_password'] = (string)$xml->global->resources->default_setup->connection->password;
                $params['database_prefix'] = (string)$xml->global->resources->db->table_prefix;
                $params['database_type'] = 'mysql';
                $params['source_path'] = $softwarePath;
            }
            unset($xml);
        } else {
            Framework::raiseWarning(Text::_('WIZARD_FAILURE') . ': ' . $xmlfile . ' ' . Text::_('WIZARD_MANUAL'), $this->getJname());
	        return false;
        }
        
        $params['magento_version'] = $this->normalize_version($this->getMagentoVersion($softwarePath));
        
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
        //getting the connection to the db
        $db = Factory::getDataBase($this->getJname());

	    $query = $db->getQuery(true)
		    ->select('email as username, email')
		    ->from('#__customer_entity');

        $db->setQuery($query, $limitstart, $limit);
        //getting the results
        $userlist = $db->loadObjectList();
        return $userlist;
    }
    /**
     * @return int
     */
    function getUserCount()
    {
        //getting the connection to the db
        $db = Factory::getDataBase($this->getJname());

	    $query = $db->getQuery(true)
		    ->select('count(*)')
		    ->from('#__customer_entity');

        $db->setQuery($query);
        //getting the results
        $no_users = $db->loadResult();
        return $no_users;
    }

    /**
     * @return array
     */
    function getUsergroupList()
    {
        //get the connection to the db
        $db = Factory::getDataBase($this->getJname());

	    $query = $db->getQuery(true)
		    ->select('customer_group_id as id, customer_group_code as name')
		    ->from('#__customer_group');

        $db->setQuery($query);
        //getting the results
        return $db->loadObjectList();
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

    function debugConfigExtra()
    {
	    // see if we have an api user in Magento
	    $db = Factory::getDataBase($this->getJname());

	    $query = $db->getQuery(true)
		    ->select('count(*)')
		    ->from('#__api_user');

	    $db->setQuery($query);
	    $no_users = $db->loadResult();
	    if ($no_users <= 0) {
		    Framework::raiseWarning(Text::_('MAGENTO_NEED_API_USER'), $this->getJname());
	    } else {
		    // check if we have valid parameters  for apiuser and api key
		    $apipath = $this->params->get('source_url') . 'index.php/api/?wsdl';
		    $apiuser = $this->params->get('apiuser');
		    $apikey = $this->params->get('apikey');
		    if (!$apiuser || !$apikey) {
			    Framework::raiseWarning(Text::_('MAGENTO_NO_API_DATA'), $this->getJname());
		    } else {
			    //finally check if the apiuser and apikey are valid
			    try {
				    require_once JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'soapclient.php';

				    $proxi = new Soapclient($apipath);
				    if($proxi->login($apiuser, $apikey)) {
					    // all ok
					    try {
						    $proxi->endSession();
					    } catch (Soapfault $fault) {
						    /** @noinspection PhpUndefinedFieldInspection */
						    $status['error'][] = 'Magento API: Could not end this session, message: ' . $fault->faultstring;
					    }

				    }
			    } catch (Soapfault $fault) {
				    /** @noinspection PhpUndefinedFieldInspection */
				    Framework::raiseWarning(Text::_('MAGENTO_WRONG_APIUSER_APIKEY_COMBINATION'), $this->getJname());
			    }
			    /*
				$query = $db->getQuery(true)
					->select('api_key')
					->from('#__api_user')
					->where('username = ' . $db->Quote($apiuser));

				$db->setQuery($query);
				$api_key = $db->loadResult();
				$hashArr = explode(':', $api_key);
				$api_key = $hashArr[0];
				$api_salt = $hashArr[1];
				if ($api_salt) {
					$params_hash_md5 = md5($api_salt . $apikey);
					$params_hash_sha256 = hash('sha256', $api_salt . $apikey);
				} else {
					$params_hash_md5 = md5($apikey);
					$params_hash_sha256 = hash('sha256', $apikey);
				}
				if ($params_hash_md5 != $api_key && $params_hash_sha256 != $api_key) {
					\JFusion\Framework::raiseWarning(Text::_('MAGENTO_WRONG_APIUSER_APIKEY_COMBINATION'), $this->getJname());
				}
			*/
		    }
	    }
	    try {
		    // check the user_remote_addr security settings
		    $query = $db->getQuery(true)
			    ->select('value')
			    ->from('#__core_config_data')
			    ->where('path = ' . $db->quote('web/session/use_remote_addr'));

		    $db->setQuery($query);
		    $value = $db->loadResult();
		    if ($value) {
			    Framework::raiseWarning(Text::_('MAGENTO_USE_REMOTE_ADDRESS_NOT_DISABLED'), $this->getJname());
		    }
		    // we need to have the curl library installed
		    if (!extension_loaded('curl')) {
			    Framework::raiseWarning(Text::_('CURL_NOTINSTALLED'), $this->getJname());
		    }
	    } catch (Exception $e) {

	    }
    }

    /**
     * @return bool
     */
    function allowRegistration()
    {
        $result = true;
        $registration_disabled = $this->params->get('disabled_registration');
		if ($registration_disabled){$result = false;}
		return $result;
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
		return 'JNO';
	}	
}