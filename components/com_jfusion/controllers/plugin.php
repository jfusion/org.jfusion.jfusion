<?php
/**
 * @package JFusion
 * @subpackage Controller
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

jimport('joomla.application.component.controller');

/**
 * JFusion Component Controller
 * @package JFusion
 */

class JFusionControllerPlugin extends JControllerLegacy
{

	/**
	 * Displays the profile for a user
	 */
	function profile() {
        $jname = JRequest::getVar('jname');
        $userid = JRequest::getVar('userid');
        $username = JRequest::getVar('username');
        require_once(JPATH_ADMINISTRATOR .DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_jfusion'.DIRECTORY_SEPARATOR.'models'.DIRECTORY_SEPARATOR.'model.jfusionpublic.php');
        $user = JFusionFunction::lookupUser($jname, $userid, false, $username);

        die(print_r($user));
	}
	/**
	 * Displays the integrated software inside Joomla without a frame
	 */
	function display() {
		//find out if there is an itemID with the view variable
		$menuitemid = JRequest::getInt('Itemid');
		//we do not want the front page menuitem as it will cause a 500 error in some cases
		$jPluginParam = new JRegistry('');
		//added to prevent a notice of $jview being undefined;
		if ($menuitemid && $menuitemid!=1) {
            $menu = JMenu::getInstance('site');
            $item = $menu->getItem($menuitemid);
            $menu_params = new JRegistry($item->params);
			$jview = $menu_params->get('visual_integration','wrapper');

			//load custom plugin parameter
			$JFusionPluginParam = $menu_params->get('JFusionPluginParam');
			if(empty($JFusionPluginParam)){
				throw new Exception( JText::_ ( 'ERROR_PLUGIN_CONFIG' ) );
			}

			//load custom plugin parameter
			$jPluginParam->loadArray(unserialize(base64_decode($JFusionPluginParam)));
			global $jname;
			$jname = $jPluginParam->get('jfusionplugin');

            if (!empty($jname)) {
                //check to see if the plugin is configured properly
                $db = JFactory::getDBO();
                $query = 'SELECT status from #__jfusion WHERE name = '. $db->Quote($jname);
                $db->setQuery($query );

                if ($db->loadResult() != 1) {
                    //die gracefully as the plugin is not configured properly
	                throw new Exception( JText::_ ( 'ERROR_PLUGIN_CONFIG' ) );
                }
            } else {
	            throw new Exception( JText::_ ( 'NO_VIEW_SELECTED' ) );
            }

            //load the view
            /**
             * @ignore
             * @var $view JView
             */
            $view = $this->getView('plugin', 'html');
            //render the view
            $view->assignRef('jname', $jname);
            $view->assignRef('jPluginParam', $jPluginParam);
            $view->assignRef('params', $menu_params);
            $view->assignRef('type', $jview);
            $view->setLayout('default');
            $view->$jview();
        }
	}
}
