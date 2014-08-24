<?php
/**
 * @package JFusion
 * @subpackage Controller
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
use JFusion\Factory;
use Joomla\Registry\Registry;

defined('_JEXEC' ) or die('Restricted access' );

jimport('joomla.application.component.controller');

/**
 * JFusion Component Controller
 * @package JFusion
 */

class JFusionControllerPlugin extends JControllerLegacy
{
	/**
	 * Displays the profile for a user
	 */
	function profile() {
        $jname = JFactory::getApplication()->input->get('jname');
        $userid = JFactory::getApplication()->input->get('userid');
        $username = JFactory::getApplication()->input->get('username');
		$email = JFactory::getApplication()->input->get('email');

		$userlookup = null;
		if ($jname && ($userid || $username || $email)) {
			$userlookup = new \JFusion\User\Userinfo($jname);
			if ($userid) {
				$userlookup->userid = $userid;
			}
			if ($username) {
				$userlookup->username = $username;
			}
			if ($email) {
				$userlookup->email = $email;
			}

			$PluginUser = Factory::getUser($jname);
			$userlookup = $PluginUser->lookupUser($userlookup);
		}

        die(print_r($userlookup));
	}

	/**
	 * @param   boolean $cachable   If true, the view output will be cached
	 * @param   array   $urlparams  An array of safe url parameters and their variable types, for valid values see {@link JFilterInput::clean()}.
	 *
	 * @throws RuntimeException
	 * @return $this|\JControllerLegacy
	 */
	public function display($cachable = false, $urlparams = array()) {
		//find out if there is an itemID with the view variable
		$menuitemid = JFactory::getApplication()->input->getInt('Itemid');
		//we do not want the front page menuitem as it will cause a 500 error in some cases
		$jPluginParam = new Registry('');
		//added to prevent a notice of $jview being undefined;
		if ($menuitemid && $menuitemid != 1) {
            $menu = JMenu::getInstance('site');
            $item = $menu->getItem($menuitemid);
			$jview = $item->params->get('visual_integration', 'wrapper');

			//load custom plugin parameter
			$JFusionPluginParam = $item->params->get('JFusionPluginParam');
			if(empty($JFusionPluginParam)){
				throw new RuntimeException(JText::_('ERROR_PLUGIN_CONFIG'));
			}

			//load custom plugin parameter
			$jPluginParam->loadArray(unserialize(base64_decode($JFusionPluginParam)));
			global $jname;
			$jname = $jPluginParam->get('jfusionplugin');

            if (!empty($jname)) {
                //check to see if the plugin is configured properly
                $db = JFactory::getDBO();

	            $query = $db->getQuery(true)
		            ->select('status')
		            ->from('#__jfusion')
		            ->where('name = ' . $db->quote($jname));

                $db->setQuery($query);

                if ($db->loadResult() != 1) {
                    //die gracefully as the plugin is not configured properly
	                throw new RuntimeException(JText::_('ERROR_PLUGIN_CONFIG'));
                }
            } else {
	            throw new RuntimeException(JText::_('NO_VIEW_SELECTED'));
            }

            //load the view
			/**
			 * @var $view jfusionViewPlugin
			 */
            $view = $this->getView('plugin', 'html');
            //render the view
            $view->jname = $jname;
			$view->jPluginParam = $jPluginParam;
			$view->params = $item->params;
			$view->type = $jview;
            $view->setLayout('default');
            $view->$jview();
        }
		return $this;
	}
}
