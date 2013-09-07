<?php

/**
 * This is view file for wizard
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Plugindisplay
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'defines.php';
jimport('joomla.application.component.view');

/**
 * Renders the main admin screen that shows the configuration overview of all integrations
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Plugindisplay
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class jfusionViewusergroups extends JViewLegacy {

	/**
	 * @var $plugins array
	 */
	var $plugins;

	/**
	 * @var $VersionData array
	 */
	var $VersionData;

    /**
     * displays the view
     *
     * @param string $tpl template name
     *
     * @return mixed
     */
    function display($tpl = null)
    {
	    //load mootools
	    JHtml::_('behavior.framework', true);

	    $plugins = JFusionFactory::getPlugins('both', true);

        if (!empty($plugins)) {
            //pass the data onto the view
	        $this->plugins = $plugins;

	        $document = JFactory::getDocument();
	        $document->addScript('components/com_jfusion/js/File.Upload.js');
	        $document->addScript('components/com_jfusion/views/'.$this->getName().'/tmpl/default.js');

	        JFusionFunction::loadJavascriptLanguage(array('COPY_MESSAGE', 'DELETE', 'PLUGIN', 'COPY'));

	        $groups = array();

	        $update = JFusionFunction::getUpdateUserGroups();

	        foreach ($this->plugins as $key => $plugin) {
		        $admin = JFusionFactory::getAdmin($plugin->name);
		        $this->plugins[$key]->isMultiGroup = $admin->isMultiGroup();
		        $this->plugins[$key]->update = false;
		        if (isset($update->{$plugin->name})) {
			        $this->plugins[$key]->update = $update->{$plugin->name};
		        }
		        $groups[$plugin->name] = $admin->getUsergroupList();
	        }

	        $groups = json_encode($groups);
	        $plugins = json_encode($this->plugins);

	        $pairs = JFusionFunction::getUserGroups();

	        $pairs = json_encode($pairs);

	        $js=<<<JS
	        JFusion.renderPlugin = [];
			JFusion.usergroups = {$groups};
			JFusion.plugins = {$plugins};
			JFusion.pairs = {$pairs};
JS;
	        $document = JFactory::getDocument();
	        $document->addScriptDeclaration($js);

	        foreach ($this->plugins as $plugin) {
		        $admin = JFusionFactory::getAdmin($plugin->name);
		        $admin->getRenderGroup();
	        }

	        parent::display();
        } else {
            JFusionFunction::raiseWarning(JText::_('NO_JFUSION_TABLE'));
        }
    }
}