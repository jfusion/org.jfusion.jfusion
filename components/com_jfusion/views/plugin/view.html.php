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
 * Class that handles the frameless integration
 * @package JFusion
 */
class jfusionViewPlugin extends JView {
    var $jname;

	/**
	 * @var JParameter $params
	 */
	var $params;

    /**
     * @param null $tpl
     * @return bool
     */
    function frameless($tpl = null) {
		$data = JFusionFrameless::initData($this->jname);

        $db = JFactory::getDBO();

        $query = 'SELECT name , original_name from #__jfusion WHERE name = ' . $db->Quote($this->jname);
        $db->setQuery($query);
        $plugin = $db->loadObject();

        if ($plugin) {
            $lang = JFactory::getLanguage();
            $name = $plugin->original_name ? $plugin->original_name : $plugin->name;
            // Language file is loaded in function of the context
            // of the selected language in Joomla
            // and of the JPATH_BASE (in admin = JPATH_ADMINISTRATOR, in site = JPATH_SITE)
            $lang->load('com_jfusion.plg_' . $name,JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion');
        }

		$result = JFusionFrameless::displayContent($data);
		if (!$result) return false;

	    $this->assignRef ( 'data', $data );
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