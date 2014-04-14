<?php

/**
 * This is the jfusion user plugin file
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    Plugins
 * @subpackage System
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');
/**
 * Load the JFusion framework if installed
 */
jimport('joomla.plugin.plugin');
$factory_file = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'import.php';
if (file_exists($factory_file)) {
    /**
     * require the JFusion libraries
     */
    include_once $factory_file;
}
/**
 * JFusion System Plugin class
 *
 * @category   JFusion
 * @package    Plugins
 * @subpackage System
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class plgSystemJfusion extends JPlugin
{
    /**
     * Constructor
     *
     * For php4 compatibility we must not use the __constructor as a constructor for plugins
     * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
     * This causes problems with cross-referencing necessary for the observer design pattern.
     *
     * @param object &$subject The object to observe
     * @param array  $config   An array that holds the plugin configuration
     *
     * @access protected
     * @since  1.0
     */
    function plgSystemJfusion(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage('plg_system_jfusion', JPATH_ADMINISTRATOR);
    }
    /**
     * onAfterInitialise
     *
     * This function is called by joomla framework
     *
     * @since 1.0
     * @return void
     */
    function onAfterInitialise()
    {
        //\JFusion\Framework::raiseNotice('system plugin called');
        $session = JFactory::getSession();
        //initialise some vars
        ob_start();
        $refresh = false;
        $task = JFactory::getApplication()->input->get('task');
        $debug = $this->params->get('debug', 0);
        if ($debug) {
            define('DEBUG_SYSTEM_PLUGIN', 1);
        }

        //prevent endless loops
        $time = JFactory::getApplication()->input->get('time');
        if (!empty($time)) {
            //restore $_POST, $_FILES, and $_REQUEST data if this was a refresh
            $backup = $session->get('JFusionVarBackup', array());
            if (!empty($backup)) {
                $_POST = $_POST + $backup['post'];
                $_FILES = $_FILES + $backup['files'];
                $_REQUEST = $_REQUEST + $backup['request'];
                $session->clear('JFusionVarBackup');
                if ($debug) {
                    \JFusion\Framework::raiseNotice('Form variables restored.');
                }
            }
        } else {
            //only call keepAlive if in the frontend
            $syncsessions = $this->params->get('syncsessions');
            $keepalive = $this->params->get('keepalive');
            $mainframe = JFactory::getApplication();
            if ($mainframe->isSite() && !empty($syncsessions) && $task != 'logout' && $task != 'user.logout') {
                //for master if not joomla_int
                $master = \JFusion\Framework::getMaster();
                if (!empty($master) && $master->name != 'joomla_int' && $master->dual_login) {
	                /**
	                 * @ignore
	                 * @var $platform \JFusion\Plugin\Platform\Joomla
	                 */
	                $platform = \JFusion\Factory::getPlayform('Joomla', $master->name);
	                try {
		                $changed = $platform->syncSessions($keepalive);
		                if (!empty($changed)) {
			                if ($debug) {
				                \JFusion\Framework::raiseNotice('session changed', $master->name);
			                }
			                $refresh = true;
		                }
	                } catch (Exception $e) {
		                \JFusion\Framework::raiseError($e, $platform->getJname());
	                }
                }
                //slave plugins
                $plugins = \JFusion\Factory::getPlugins('both');
                foreach ($plugins as $plugin) {
                    //only call keepAlive if the plugin is activated for dual login
                    if ($plugin->dual_login) {
	                    /**
	                     * @ignore
	                     * @var $platform \JFusion\Plugin\Platform\Joomla
	                     */
	                    $platform = \JFusion\Factory::getPlayform('Joomla', $plugin->name);
	                    try {
		                    $changed = $platform->syncSessions($keepalive);
		                    if (!empty($changed)) {
			                    if ($debug) {
				                    \JFusion\Framework::raiseNotice('session changed', $plugin->name);
			                    }
			                    $refresh = true;
		                    }
	                    } catch (Exception $e) {
		                    \JFusion\Framework::raiseError($e, $platform->getJname());
	                    }
                    }
                }
            }
            /**
             * Joomla Object language with the current information about the language loaded
             * In the purpose to reduce the load charge of Joomla and the communication with the others
             * integrated software the script is realized once the language is changed
             *
             */
            $synclanguage = $this->params->get('synclanguage');
            if (!empty($synclanguage)) {
                self::setLanguagePluginsFrontend();
            }

            //stop output buffer
            ob_end_clean();

            //check if page refresh is needed
            if ($refresh == true) {
                $backup = array();
                $backup['post'] = $_POST;
                $backup['request'] = $_REQUEST;
                $backup['files'] = $_FILES;
                $session->set('JFusionVarBackup', $backup);
                if ($debug) {
                    \JFusion\Framework::raiseNotice('Refresh is true');
                }
                $uri = JUri::getInstance();
                //add a variable to ensure refresh
                $uri->setVar('time', time());
                $link = $uri->toString();
                $mainframe = JFactory::getApplication();
                $mainframe->redirect($link);
            }
        }
    }
    
    /**
     * Can be invoked from components, modules or else
     */
    public static function setLanguagePluginsFrontend() {
		$lang = JFactory::getLanguage();
		$session = JFactory::getSession();
		$oldlang = $session->get('oldlang');
		if (!isset($oldlang) || $oldlang != $lang->getTag()) {
			$session->set('oldlang', $lang->getTag());
			// The instance of the user is not obligatory. Without to be logged, the user can change the language of the integrated software
			// if those implement it.

			$userinfo = JFusionFunction::getJoomlaUser(JFactory::getUser());

			$master = \JFusion\Framework::getMaster();

			/**
			 * @ignore
			 * @var $platform \JFusion\Plugin\Platform\Joomla
			 */
			$platform = \JFusion\Factory::getPlayform('Joomla', $master->name);
			if (method_exists($platform, 'setLanguageFrontEnd')) {
				try {
					$platform->setLanguageFrontEnd($userinfo);
				} catch (Exception $e) {
					\JFusion\Framework::raiseError($e, $master->name . ' ' . JText::_('SET_LANGUAGEFRONTEND_ERROR'));
				}
			}
			$slaves = \JFusion\Framework::getSlaves();
			foreach($slaves as $slave) {
				/**
				 * @ignore
				 * @var $platform \JFusion\Plugin\Platform\Joomla
				 */
				$platform = \JFusion\Factory::getPlayform('Joomla', $slave->name);
				if (method_exists($platform, 'setLanguageFrontEnd')) {
					try {
						$platform->setLanguageFrontEnd($userinfo);
					} catch (Exception $e) {
						\JFusion\Framework::raiseError($e, $slave->name . ' ' . JText::_('SET_LANGUAGEFRONTEND_ERROR'));
					}
				}
			}
		}
	}
}