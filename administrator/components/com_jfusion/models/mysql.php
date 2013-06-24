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
require_once JPATH_LIBRARIES . DIRECTORY_SEPARATOR . 'joomla' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'mysql.php';

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

    /**
     * @param array $options
     */
    public function __construct($options){
        parent::__construct($options);
    }

    /**
     * begin transaction
     */        
    function BeginTrans()
    {
        $this->setQuery('START TRANSACTION');
        return $this->execute();
    }
    /**
     * commit transaction
     */        
    function CommitTrans()
    {
        $this->setQuery('COMMIT');
        return $this->execute();
    }
    /**
     * rollback transaction
     */        
    function RollbackTrans()
    {
        $this->setQuery('ROLLBACK');
        return $this->execute();
    }
}