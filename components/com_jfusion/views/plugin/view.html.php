<?php
/**
 * @package JFusion
 * @subpackage Views
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * load the JFusion framework
 */
jimport('joomla.application.component.view');
require_once (JPATH_ADMINISTRATOR . '/components/com_jfusion/models/model.frameless.php');

/**
 * Class that handles the frameless integration
 * @package JFusion
 */
class jfusionViewPlugin extends JViewLegacy {
    var $jname;

	/**
	 * @var $params JRegistry
	 */
	var $params;

	/**
	 * @var $jPluginParam JRegistry
	 */
	var $jPluginParam;

	/**
	 * @var stdClass $data
	 */
	var $data;

	/**
	 * @var string $url
	 */
	var $url;

	/**
	 * @var string $type
	 */
	var $type;

    /**
     * @param null $tpl
     * @return bool
     */
    function frameless($tpl = null) {
		$data = JFusionFrameless::initData($this->jname);

		$result = JFusionFrameless::displayContent($data);
		if (!$result) return false;
	    $this->data = $data;
		parent::display($tpl);
        return true;
	}

	/**
	 * @param null $tpl
	 *
	 * @throws RuntimeException
	 */
    function wrapper($tpl = null) {
	    $document = JFactory::getDocument();
	    $document->addScript('components/com_jfusion/views/plugin/tmpl/wrapper.js');

        $data = JFusionFrameless::initData($this->jname);

	    $platform = \JFusion\Factory::getPlayform('Joomla', $data->jname);

	    if (!$platform->isConfigured()) {
		    throw new RuntimeException($data->jname . ' ' . JText::_('NOT_FOUND'));
	    }

        $url = $platform->getWrapperURL($data);

        //set params
        $this->url = $url;

        parent::display($tpl);
    }
}