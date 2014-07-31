<?php namespace JFusion\Api;

/**
 * Intended for direct integration with joomla (loading the joomla framework directly in to other software.)
 */
class Platform_Joomla extends Platform {
	/**
	 * @return \JApplication|\JApplicationCms
	 */
	public function getApplication()
	{
		if (!defined('_JEXEC') && !defined('JPATH_PLATFORM')) {
			/**
			 * @TODO determine if we really need session_write_close or if it need to be selectable
			 */
//			session_write_close();
//			session_id(null);

			// trick joomla into thinking we're running through joomla
			define('_JEXEC', true);
			define('JPATH_PLATFORM', dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'libraries');
			define('DS', DIRECTORY_SEPARATOR);
			define('JPATH_BASE', dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');

			// load joomla libraries
			require_once JPATH_BASE . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'defines.php';
			define('_JREQUEST_NO_CLEAN', true); // we don't want to clean variables as it can "corrupt" them for some applications, it also clear any globals used...

			if (!class_exists('JVersion')) {
				include_once(JPATH_LIBRARIES . DIRECTORY_SEPARATOR . 'cms' . DIRECTORY_SEPARATOR . 'version' . DIRECTORY_SEPARATOR . 'version.php');
			}

			include_once JPATH_LIBRARIES . DIRECTORY_SEPARATOR . 'import.php';
			require_once JPATH_LIBRARIES . DIRECTORY_SEPARATOR . 'loader.php';

			$autoloaders = spl_autoload_functions();
			if ($autoloaders && in_array('__autoload', $autoloaders)) {
				spl_autoload_register('__autoload');
			}

			require_once JPATH_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'framework.php';
			jimport('joomla.base.object');
			jimport('joomla.factory');
			jimport('joomla.filter.filterinput');
			jimport('joomla.error.error');
			jimport('joomla.event.dispatcher');
			jimport('joomla.event.plugin');
			jimport('joomla.plugin.helper');
			jimport('joomla.utilities.arrayhelper');
			jimport('joomla.environment.uri');
			jimport('joomla.environment.request');
			jimport('joomla.user.user');
			jimport('joomla.html.parameter');
			// JText cannot be loaded with jimport since it's not in a file called text.php but in methods
			\JLoader::register('JText', JPATH_LIBRARIES . DIRECTORY_SEPARATOR . 'joomla' . DIRECTORY_SEPARATOR . 'methods.php');
			\JLoader::register('JRoute', JPATH_LIBRARIES . DIRECTORY_SEPARATOR . 'joomla' . DIRECTORY_SEPARATOR . 'methods.php');

			//load JFusion's libraries
			require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'import.php';
		} elseif (!defined('IN_JOOMLA')) {
			define('IN_JOOMLA', 1);
			\JFusionFunction::reconnectJoomlaDb();
		}

		$mainframe = \JFactory::getApplication('site');
		$GLOBALS['mainframe'] = $mainframe;
		return $mainframe;
	}
}