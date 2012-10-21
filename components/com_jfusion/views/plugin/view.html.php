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
class jfusionViewPlugin extends JView {
    var $jname;

    /**
     * @param null $tpl
     * @return bool
     */
    function frameless($tpl = null) {
		$data = JFusionFrameless::initData($this->jname);

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

    /**
     * @param null $tpl
     */
    function wrapper($tpl = null) {
        $data = JFusionFrameless::initData($this->jname);

        $JFusionPlugin = JFusionFactory::getPublic( $data->jname );

        $url = $JFusionPlugin->getWrapperURL($data);

        //set params
        $this->assignRef('url', $url);

        parent::display($tpl);
    }
}