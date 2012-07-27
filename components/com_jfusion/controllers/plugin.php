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

class JFusionControllerPlugin extends JController
{

	/**
	 * Displays the profile for a user
	 */
	function profile() {
        $jname = JRequest::getVar('jname');
        $userid = JRequest::getVar('userid');
        $username = JRequest::getVar('username');
        require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.jfusionpublic.php');
        $user = JFusionFunction::lookupUser($jname, $userid, false, $username);

        die(print_r($user));
	}
	/**
	 * Displays the integrated software inside Joomla without a frame
	 */
	function display() {
		//find out if there is an itemID with the view variable
		$menuitemid = JRequest::getInt('Itemid');
		//we do not want the frontpage menuitem as it will cause a 500 error in some cases
		$jPluginParam = new JParameter('');
		//added to prevent a notice of $jview being undefined;
		if ($menuitemid && $menuitemid!=1) {
            $menu = JMenu::getInstance('site');
            $item = $menu->getItem($menuitemid);
            $menu_params = new JParameter($item->params, '');
			$jview = $menu_params->get('visual_integration','wrapper');

			//load custom plugin parameter
			$JFusionPluginParam = $menu_params->get('JFusionPluginParam');
			if(empty($JFusionPluginParam)){
				JError::raiseError ( 404, JText::_ ('ERROR_PLUGIN_CONFIG') );
			}

			//load custom plugin parameter
			$jPluginParam->loadArray(unserialize(base64_decode($JFusionPluginParam)));
			global $jname;
			$jname = $jPluginParam->get('jfusionplugin');

            if (!empty($jname)) {
                //check to see if the plugin is configured properly
                $db =& JFactory::getDBO();
                $query = 'SELECT status from #__jfusion WHERE name = '. $db->Quote($jname);
                $db->setQuery($query );

                if ($db->loadResult() != 1) {
                    //die gracefully as the plugin is not configured properly
                    JError::raiseError ( 500, JText::_ ('ERROR_PLUGIN_CONFIG') );
                }
            } else {
                JError::raiseError ( 500, JText::_ ('NO_VIEW_SELECTED') );
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
