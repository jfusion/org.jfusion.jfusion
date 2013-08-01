<?php

/**
 * This is view file for synchistory
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Synchistory
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Renders the main admin screen that shows the configuration overview of all integrations
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Synchistory
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class jfusionViewsynchistory extends JViewLegacy
{
    /**
     * displays the view
     *
     * @param string $tpl template name
     *
     * @return mixed html output of view
     */
    function display($tpl = null)
    {
	    $document = JFactory::getDocument();
	    $document->addScript('components/com_jfusion/views/'.$this->getName().'/tmpl/default.js');
        //get the all usersync data
        $db = JFactory::getDBO();
        $query = 'SELECT * from #__jfusion_sync ORDER BY time_end DESC, time_start DESC';
        $db->setQuery($query);
        $rows = $db->loadObjectList();
	    $this->rows = $rows;
        parent::display($tpl);
    }
    
    /**
     * displays the time nicely
     *
     * @param int $then something
     * @param mixed $now  something
     *
     * @return string sorted log
     */
    function getFormattedTimediff($then, $now = false)
    {
    	/**
    	 * Define some standards
    	 */
        $INT_SECOND = 1;
        $INT_MINUTE = 60;
        $INT_HOUR = 3600;
        $INT_DAY = 86400;
        $INT_WEEK = 604800;
    	
    	$now = (!$now) ? time() : $now;
    	$timediff = ($now - $then);
    	$weeks = (int)intval($timediff / $INT_WEEK);
    	$timediff = (int)intval($timediff - ($INT_WEEK * $weeks));
    	$days = (int)intval($timediff / $INT_DAY);
    	$timediff = (int)intval($timediff - ($INT_DAY * $days));
    	$hours = (int)intval($timediff / $INT_HOUR);
    	$timediff = (int)intval($timediff - ($INT_HOUR * $hours));
    	$mins = (int)intval($timediff / $INT_MINUTE);
    	$timediff = (int)intval($timediff - ($INT_MINUTE * $mins));
    	$sec = (int)intval($timediff / $INT_SECOND);
    	$timediff = (int)intval($timediff - ($sec * $INT_SECOND));
    	$str = '';
    	if ($weeks) {
    		$str.= intval($weeks);
    		$str.= ($weeks > 1) ? ' weeks' : ' week';
    	}
    	if ($days) {
    		$str.= ($str) ? ', ' : '';
    		$str.= intval($days);
    		$str.= ($days > 1) ? ' days' : ' day';
    	}
    	if ($hours) {
    		$str.= ($str) ? ', ' : '';
    		$str.= intval($hours);
    		$str.= ($hours > 1) ? ' hours' : ' hour';
    	}
    	if ($mins) {
    		$str.= ($str) ? ', ' : '';
    		$str.= intval($mins);
    		$str.= ($mins > 1) ? ' minutes' : ' minute';
    	}
    	if ($sec) {
    		$str.= ($str) ? ', ' : '';
    		$str.= intval($sec);
    		$str.= ($sec > 1) ? ' seconds' : ' second';
    	}
    	if (!$weeks && !$days && !$hours && !$mins && !$sec) {
    		$str.= '0 seconds ';
    	}
    	return $str;
    }    
}
