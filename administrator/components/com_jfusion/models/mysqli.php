<?php

/**
 * Exented mysqli model that supports rollbacks
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
require_once JPATH_LIBRARIES . DS . 'joomla' . DS . 'database' . DS . 'database' . DS . 'mysqli.php';

/**
 * Extention of the mysqli database object to support rollbacks
 * 
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionMySQLi extends JDatabaseMySQLi
{
	/**
	 * @var mysqli $_resource
	 */
	var $_resource=null;
	/**
	 * @var mysqli $connection
	 */
    public $connection=null;
    /**
     * @param array $options
     */
    public function __construct($options){
        parent::__construct($options);
    }

    /**
     * begin transaction function
     * 
     */
    function BeginTrans()
    {
        if ($this->_resource) {
            mysqli_autocommit($this->_resource, false);
        } elseif ($this->connection) {
            mysqli_autocommit($this->connection, false);
        }
    }
    /**
     * commit transaction
     * 
     */
    function CommitTrans()
    {
        if ($this->_resource) {
            mysqli_commit($this->_resource);
        } elseif ($this->connection) {
            mysqli_commit($this->connection);
        }
    }
    /**
     * rollback trasnaction
     * 
     */
    function RollbackTrans()
    {
        if ($this->_resource) {
            mysqli_rollback($this->_resource);
        } elseif ($this->connection) {
            mysqli_rollback($this->connection);
        }
    }
}
