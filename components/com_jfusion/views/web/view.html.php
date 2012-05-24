<?php
/**
 * @package JFusion
 * @subpackage Views
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined ( '_JEXEC' ) or die ( 'Restricted access' );

/**
 * load the JFusion framework
 */
jimport ( 'joomla.application.component.view' );
require_once (JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.frameless.php');

/**
 * Class that handles the framelesss integration
 * @package JFusion
 */
class jfusionViewWeb extends JView {

	function display($tpl = null) {
		$jname = JRequest::getVar ( 'Itemid' );
		$data = JFusionFrameless::initData($jname,false);

		$result = JFusionFrameless::displayContent($data);
		if (!$result) return false;

		if (isset ( $data->style )) {
			$this->assignRef ( 'style', $data->style );
		}

		// Output the body
		if (isset ( $data->body )) {
			$this->assignRef ( 'body', $data->body );
		}
		parent::display ( $tpl );
        return true;
	}
}