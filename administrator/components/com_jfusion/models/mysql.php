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
class JFusionMySQL extends JDatabaseMySQL
{
    public function __construct($options){
        parent::__construct($options);
    }
    
    /**
     * added execute query as Joomla 1.6 has removed functions
     *
     * @param string $query
     *
     * @return string nothing
     */        
    function Execute($query)
    {
    	$this->setQuery($query);
        $this->query();
    }
    
    /**
     * begin transaction
     * 
     * @return string nothing
     */        
    function BeginTrans()
    {
        return $this->Execute('START TRANSACTION');
    }
    /**
     * commit transaction
     * 
     * @return string nothing
     */        
    function CommitTrans()
    {
        return $this->Execute('COMMIT');
    }
    /**
     * rollback transaction
     * 
     * @return string nothing
     */        
    function RollbackTrans()
    {
        return $this->Execute('ROLLBACK');
    }
}