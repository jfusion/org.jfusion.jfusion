<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpbb
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Helper Class for Dokuwiki
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpbb
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionHelper_prestashop extends JFusionPlugin
{
    /**
     * Returns the name for this plugin
     *
     * @return string
     */
    function getJname() {
        return 'prestashop';
    }

	/**
	 * Load Framework
	 */
	function loadFramework()
	{
		$params = JFusionFactory::getParams($this->getJname());
		$source_path = $params->get('source_path');

		require_once($source_path . DS . 'config' . DS . 'settings.inc.php');
		require_once($source_path . DS . 'config' . DS . 'alias.php');

		$this->loadClass('Context');

//      $this->loadClass('Blowfish'); Think this is now unneeded.
//      $this->loadClass('Rijndael'); Think this is now unneeded.
//      $this->loadClass('Cookie'); Think this is now unneeded.
		$this->loadClass('Tools');
		require_once($source_path . DS . 'tools' . DS . 'profiling' . DS . 'Tools.php');

		$this->loadClass('ObjectModel');
		require_once($source_path . DS . 'tools' . DS . 'profiling' . DS . 'ObjectModel.php');

		require_once($source_path . DS . 'classes' . DS . 'db' . DS . 'Db.php');
		require_once($source_path . DS . 'tools' . DS . 'profiling' . DS . 'Db.php');

		require_once($source_path . DS . 'classes' . DS . 'db' . DS . 'DbPDO.php');
		require_once(JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'classes' . DS . 'db' . DS . 'DbPDO.php');

		require_once($source_path . DS . 'classes' . DS . 'shop' . DS . 'Shop.php');
		require_once(JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'classes' . DS . 'shop' . DS . 'Shop.php');

		$this->loadClass('Language');
		$this->loadClass('Validate');
//      $this->loadClass('Country');
//      $this->loadClass('State'); Think this is now unneeded.
//      $this->loadClass('Customer'); Think this is now unneeded.

		$this->loadClass('Configuration');
	}

	function loadClass($class) {
		$params = JFusionFactory::getParams($this->getJname());
		$source_path = $params->get('source_path');

		require_once($source_path . DS . 'classes' . DS . $class.'.php');
		if (file_exists(JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'classes' . DS . $class.'.php')) {
			require_once(JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'classes' . DS . $class.'.php');
		}
	}

	function getDefaultLanguage() {
		static $default_language;
		if (!isset($default_language)) {
			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('value')
				->from('#__configuration')
				->where('name IN (' . $db->Quote('PS_LANG_DEFAULT') . ')');

			$db->setQuery($query);
			//getting the default language to load groups
			$default_language = $db->loadResult();
		}
		return $default_language;
	}

	function getGroupName($id) {
		static $groupname;
		if (!isset($groupname[$id])) {
			$db = JFusionFactory::getDatabase($this->getJname());
			$query = $db->getQuery(true)
				->select('name')
				->from('#__group_lang')
				->where('id_lang =' . $db->Quote($this->getDefaultLanguage()))
				->where('id_group =' . $db->Quote($id));

			$db->setQuery($query);

			$groupname[$id] = $db->loadResult();
		}
		return $groupname[$id];
	}
}