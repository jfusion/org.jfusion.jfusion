<?php

/**
 * Exented mysql model that supports rollbacks
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
 * Make sure the database model is loaded
 * Note: We cannot use jimport here as it does not include the file unless needed (if using php5+)
 * This leads to a fatal error if the plugin uses a different driver than Joomla
 */
require_once JPATH_LIBRARIES . DS . 'joomla' . DS . 'database' . DS . 'database' . DS . 'mysql.php';

/**
 * Extention of the mysql database object to support rollbacks
 * 
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionReconnectMySQL extends JDatabaseMySQL
{

    /**
     * @param array $options
     * @throws JDatabaseException
     */
    public function __construct($options)
    {
        if (JFusionFunction::isJoomlaVersion('1.7')) {
            // Get some basic values from the options.
	        $options['host']     = (isset($options['host'])) ? $options['host'] : 'localhost';
	        $options['user']     = (isset($options['user'])) ? $options['user'] : 'root';
	        $options['password'] = (isset($options['password'])) ? $options['password'] : '';
	        $options['database'] = (isset($options['database'])) ? $options['database'] : '';
	        $options['select']   = (isset($options['select'])) ? (bool) $options['select'] : true;
	
	        // Make sure the MySQL extension for PHP is installed and enabled.
	        if (!function_exists('mysql_connect')) {
	
	            // Legacy error handling switch based on the JError::$legacy switch.
	            // @deprecated  11.3
	            if (JError::$legacy) {
	                $this->errorNum = 1;
	                $this->errorMsg = JText::_('JLIB_DATABASE_ERROR_ADAPTER_MYSQL');
	                return;
	            } else {
	                throw new JDatabaseException(JText::_('JLIB_DATABASE_ERROR_ADAPTER_MYSQL'));
	            }
	        }

	        // Attempt to connect to the server.
	        if (!($this->connection = @ mysql_connect($options['host'], $options['user'], $options['password'], false))) {

	            // Legacy error handling switch based on the JError::$legacy switch.
	            // @deprecated  11.3
	            if (JError::$legacy) {
	                $this->errorNum = 2;
	                $this->errorMsg = JText::_('JLIB_DATABASE_ERROR_CONNECT_MYSQL');
	                return;
	            }
	            else {
	                throw new JDatabaseException(JText::_('JLIB_DATABASE_ERROR_CONNECT_MYSQL'));
	            }
	        }
	
	        // Finalize initialisation
	        parent::__construct($options);
	
	        // Set sql_mode to non_strict mode
	        mysql_query("SET @@SESSION.sql_mode = '';", $this->connection);
	
	        // If auto-select is enabled select the given database.
	        if ($options['select'] && !empty($options['database'])) {
	            $this->select($options['database']);
	        }
        } else {
            $host       = array_key_exists('host', $options)    ? $options['host']      : 'localhost';
	        $user       = array_key_exists('user', $options)    ? $options['user']      : '';
	        $password   = array_key_exists('password',$options) ? $options['password']  : '';
	        $database   = array_key_exists('database',$options) ? $options['database']  : '';
	        $prefix     = array_key_exists('prefix', $options)  ? $options['prefix']    : 'jos_';
	        $select     = array_key_exists('select', $options)  ? $options['select']    : true;
	
	        // perform a number of fatality checks, then return gracefully
	        if (!function_exists( 'mysql_connect' )) {
	            $this->_errorNum = 1;
	            $this->_errorMsg = 'The MySQL adapter "mysql" is not available.';
	            return;
	        }
	
	        // connect to the server
	        if (!($this->_resource = @mysql_connect( $host, $user, $password, false ))) {
	            $this->_errorNum = 2;
	            $this->_errorMsg = 'Could not connect to MySQL';
	            return;
	        }
	
	        // finalize initialization
	        parent::__construct($options);
	
	        // select the database
	        if ( $select ) {
	            $this->select($database);
	        }
        }        
    }
}