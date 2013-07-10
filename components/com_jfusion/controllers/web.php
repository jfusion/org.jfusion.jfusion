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

class JFusionControllerWeb extends JControllerLegacy
{
    /**
     * Displays the integrated software inside Joomla without a frame
     */
    function display()
    {
        // Select which layout to use in the view
        JRequest::setVar ( 'layout', 'default' );
        /**
        * @ignore
         * @var $menu JMenu
        */
	    $menu = JMenu::getInstance('site');
        $item = $menu->getActive ();
        if ($item) {
            $params = $menu->getParams ( $item->id );
        } else {
            $params = $menu->getParams ( null );
        }


        // Set the default view name from the Request
        /**
         * @ignore
         * @var $view jfusionViewWeb
         */
        $view = $this->getView ( 'web', 'html' );
        $view->assignRef('params',$params);
        $view->display ();
    }
}
