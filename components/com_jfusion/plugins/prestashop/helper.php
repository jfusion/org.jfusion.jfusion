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
		$params = \JFusion\Factory::getParams($this->getJname());
		$source_path = $params->get('source_path');

		require_once($source_path . 'config' . DIRECTORY_SEPARATOR . 'settings.inc.php');
		require_once($source_path . 'config' . DIRECTORY_SEPARATOR . 'alias.php');

		$this->loadClass('Context');

//      $this->loadClass('Blowfish'); Think this is now unneeded.
//      $this->loadClass('Rijndael'); Think this is now unneeded.
//      $this->loadClass('Cookie'); Think this is now unneeded.
		$this->loadClass('Tools');
		require_once($source_path . 'tools' . DIRECTORY_SEPARATOR . 'profiling' . DIRECTORY_SEPARATOR . 'Tools.php');

		$this->loadClass('ObjectModel');
		require_once($source_path . 'tools' . DIRECTORY_SEPARATOR . 'profiling' . DIRECTORY_SEPARATOR . 'ObjectModel.php');

		require_once($source_path . 'classes' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'Db.php');
		require_once($source_path . 'tools' . DIRECTORY_SEPARATOR . 'profiling' . DIRECTORY_SEPARATOR . 'Db.php');

		require_once($source_path . 'classes' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'DbPDO.php');
		require_once(JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'DbPDO.php');

//		require_once($source_path . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'shop' . DIRECTORY_SEPARATOR . 'Shop.php'); Think this is now unneeded.
//		require_once(JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'shop' . DIRECTORY_SEPARATOR . 'Shop.php'); Think this is now unneeded.

		$this->loadClass('Language');
		$this->loadClass('Validate');
//      $this->loadClass('Country'); Think this is now unneeded.
//      $this->loadClass('State'); Think this is now unneeded.
//      $this->loadClass('Customer'); Think this is now unneeded.

		$this->loadClass('Configuration');
	}

	/**
	 * @param $class
	 */
	function loadClass($class) {
		$params = \JFusion\Factory::getParams($this->getJname());
		$source_path = $params->get('source_path');

		require_once($source_path . 'classes' . DIRECTORY_SEPARATOR . $class . '.php');
		if (file_exists(JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . $class . '.php')) {
			require_once(JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . $class . '.php');
		}
	}

	/**
	 * @return mixed
	 */
	function getDefaultLanguage() {
		static $default_language;
		if (!isset($default_language)) {
			$db = \JFusion\Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('value')
				->from('#__configuration')
				->where('name IN (' . $db->quote('PS_LANG_DEFAULT') . ')');

			$db->setQuery($query);
			//getting the default language to load groups
			$default_language = $db->loadResult();
		}
		return $default_language;
	}

	/**
	 * @param $id
	 *
	 * @return mixed
	 */
	function getGroupName($id) {
		static $groupname;
		if (!isset($groupname[$id])) {
			$db = \JFusion\Factory::getDatabase($this->getJname());
			$query = $db->getQuery(true)
				->select('name')
				->from('#__group_lang')
				->where('id_lang =' . $db->quote($this->getDefaultLanguage()))
				->where('id_group =' . $db->quote($id));

			$db->setQuery($query);

			$groupname[$id] = $db->loadResult();
		}
		return $groupname[$id];
	}
}