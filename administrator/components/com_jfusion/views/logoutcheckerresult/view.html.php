<?php

/**
 * This is view file for logoutcheckerresult
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Logoutcheckerresults
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
 * @subpackage Logoutcheckerresults
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class jfusionViewLogoutCheckerResult extends JViewLegacy
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
        //get the joomla id
	    $joomlaid = JFactory::getApplication()->input->get('joomlaid');
	    $user = (array)JFactory::getUser($joomlaid);
	    $options = array();
	    $options['group'] = 'USERS';
	    if (JFactory::getApplication()->input->get('show_unsensored') == 1) {
	        $options['show_unsensored'] = 1;
	    } else {
		    $options['show_unsensored'] = 0;
	    }
	    //prevent current joomla session from being destroyed
	    global $JFusionActivePlugin, $JFusionLoginCheckActive, $jfusionDebug;
	    $JFusionActivePlugin = 'joomla_int';
	    $JFusionLoginCheckActive = true;
	    $jfusionDebug = array();
	    $JFusionActivePlugin = 'joomla_int';
	    $jfusion_user = array('type' => 'user', 'name' => 'jfusion', 'params' => '');
	    $plugin = (object)$jfusion_user;
	    include_once JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . $plugin->name .  DIRECTORY_SEPARATOR . $plugin->name . '.php';
	    $className = 'plg' . $plugin->type . $plugin->name;
	    if (class_exists($className)) {
	        $plugin = new $className($this, (array)$plugin);
	    }
	    if (method_exists($plugin, 'onUserLogout')) {
	        $response = $plugin->onUserLogout($user, $options);
	    }

	    $this->debug = $jfusionDebug;
        parent::display($tpl);
    }
    
    /**
     * function to override the default attach function
     *
     * @param string $sample sample name
     * 
     * @return string nothing
     */      
    function attach($sample)
    {
    }
}
