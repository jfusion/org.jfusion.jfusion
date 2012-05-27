<?php
/**
 * @package JFusion
 * @subpackage Controller
 * @author JFusion development team
 * @copyright Copyright (C) 2009 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined ( '_JEXEC' ) or die ( 'Restricted access' );

jimport ( 'joomla.application.component.controller' );
jimport ( 'joomla.application.module.helper' );
require_once (JPATH_SITE . DS . 'components' . DS . 'com_jfusion' . DS . 'helpers' . DS . 'helper.php');
/**
 * JFusionControllerConnect class
 *
 * @category   JFusion
 * @package    Model
 * @subpackage JFusionControllerConnect
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionControllerConnect extends JController {
	
	public function module() {
		
		global $Itemid;
		if ($Itemid == 0) {
			/* unset the ItemId to allow the display of every modules as whished from external software */
			/* Could be a problem in future if we need of this value later in the code */
			unset ( $GLOBALS['Itemid'] );
		}
		
		//perform a secret key check
		$secret = JRequest::getVar ( 'secret' );
		$params = JFusionFactory::getParams ( 'joomla_int' );
		$module = new stdClass ( );
		
		if (! $params->get ( 'allow_connections', 0 )) {
			die(JText::_('Connections are not allowed' ));
		}
		
		$correct_secret = $params->get ( 'secret' );
		if ($secret != $correct_secret) {
			die(JText::_('Incorrect secret key' ));
		}
		
		/* Maybe don't need htmlspecialchars and strip_tags functions, JRequest may do the filter */
		/* Priority to the id of a module - More precise to retreive the module informations */
		if (isset($_REQUEST ['id'])) {
			$id = htmlspecialchars ( strip_tags ( JRequest::getVar ( 'id' ) ) );
			$module = JFusionHelper::getModuleById ( $id );
		} else if (isset($_REQUEST ['title'])) {
			$module->title = htmlspecialchars ( strip_tags ( JRequest::getVar ( 'title' ) ) );
			$module = JFusionHelper::getModuleByTitle($module->title);
		}
		
		if (isset($_REQUEST ['modulename']) && empty($module->name)) {
			$module->name = htmlspecialchars ( strip_tags ( JRequest::getVar ( 'modulename' ) ) );
		}
		
		if (empty ( $module )) {
			echo JText::_ ( 'No module found!' );
			exit(0);
		}
		
		if (isset($_REQUEST ['style'])) {
			$module->style = htmlspecialchars ( strip_tags ( JRequest::getVar ( 'style' ) ) );
		}else{
			$module->style = 'none';
		}
		
		echo JModuleHelper::renderModule ( JModuleHelper::getModule ( $module->name, $module->title ), array ('style' => $module->style ) );
		
		// To update language of integrated software automatically (maybe could be improved in an other way)
		if (JPluginHelper::importPlugin ( 'system', 'jfusion' )) {
			plgSystemJfusion::setLanguagePluginsFrontend();
		}
		
		exit ( 0 );
	}
	
	public function component() {
	
	}
	
}
