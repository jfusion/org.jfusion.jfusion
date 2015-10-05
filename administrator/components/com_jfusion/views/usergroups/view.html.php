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
use JFusion\User\Groups;
use Psr\Log\LogLevel;

defined('_JEXEC') or die('Restricted access');

require_once JPATH_COMPONENT_ADMINISTRATOR . '/defines.php';
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
	    JHtml::_('Formbehavior.chosen');

	    $plugins = \JFusion\Factory::getPlugins('all', true);

        if (!empty($plugins)) {
            //pass the data onto the view
	        $this->plugins = $plugins;

	        $document = JFactory::getDocument();
	        $document->addScript('components/com_jfusion/js/File.Upload.js');
	        $document->addScript('components/com_jfusion/views/' . $this->getName() . '/tmpl/default.js');

	        JFusionFunction::loadJavascriptLanguage(array('SELECT_ONE'));

	        $groups = array();

	        $update = Groups::getUpdate();

	        $master = \JFusion\Framework::getMaster();

	        foreach ($this->plugins as $key => $plugin) {
		        $admin = \JFusion\Factory::getAdmin($plugin->name);
		        $this->plugins[$key]->isMultiGroup = $admin->isMultiGroup();
		        $this->plugins[$key]->update = false;

		        if ($master && $master->name == $plugin->name) {
			        $this->plugins[$key]->master = true;
		        } else {
			        $this->plugins[$key]->master = false;
		        }

		        if (isset($update->{$plugin->name})) {
			        $this->plugins[$key]->update = $update->{$plugin->name};
		        }
		        try {
			        $groups[$plugin->name] = $admin->getUsergroupList();
		        } catch (Exception $e) {
			        \JFusion\Framework::raise(LogLevel::ERROR, $e, $admin->getJname());
		        }
	        }

	        $groups = json_encode($groups);
	        $plugins = json_encode($this->plugins);

	        $pairs = Groups::get();
	        if ($pairs === false) {
		        $pairs = new stdClass();
	        }
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
		        $admin = \JFusion\Factory::getAdmin($plugin->name);
		        $document->addScriptDeclaration($admin->getRenderGroup());
	        }

	        parent::display();
        } else {
            \JFusion\Framework::raise(LogLevel::WARNING, JText::_('CONFIG_FIRST'));
        }
    }
}