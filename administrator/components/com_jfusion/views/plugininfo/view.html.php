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
class jfusionViewplugininfo extends JView
{
    /**
     * displays the view
     *
     * @param string $tpl template name
     *
     * @return mixed html output of view
     */
    function display($tpl = null)
    {
        //set jname as a global variable in order for elements to access it.
        global $jname;
        //find out the submitted name of the JFusion module
        $jname = JRequest::getVar('jname');
        if ($jname) {
			$features = array();
			 
			$admin = JFusionFactory::getAdmin($jname);

			$features['ADMIN']['FEATURE_WIZARD'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'wizard'));
			$features['ADMIN']['FEATURE_REQUIRE_FILE_ACCESS'] = $this->outputFeature($admin->requireFileAccess());
			$features['ADMIN']['FEATURE_MULTI_USERGROUP'] = $this->outputFeature($admin->isMultiGroup());

            $frameless = JFusionFunction::hasFeature($jname,'frameless');
	        if ($jname=='joomla_int') {
				$frameless = 'JNO';
	        } else {
		        $frameless = $frameless ? 'NATIVE_FRAMELESS' : 'CURL_FRAMELESS';
	        }

			$features['PUBLIC']['FEATURE_FRAMELESS'] = $this->outputFeature($frameless);
			$features['PUBLIC']['FEATURE_BREADCRUMB'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'breadcrumb'));
			$features['PUBLIC']['FEATURE_SEARCH'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'search'));
			$features['PUBLIC']['FEATURE_ONLINE_STATUS'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'whosonline'));
			$features['PUBLIC']['FEATURE_FRONT_END_LANGUAGE'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'frontendlanguage'));
			
			$features['FORUM']['FEATURE_THREAD_URL'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'threadurl'));
			$features['FORUM']['FEATURE_POST_URL'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'posturl'));
			$features['FORUM']['FEATURE_PROFILE_URL'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'profileurl'));
			$features['FORUM']['FEATURE_AVATAR_URL'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'avatarurl'));
			$features['FORUM']['FEATURE_PRIVATE_MESSAGE_URL'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'privatemessageurl'));
			$features['FORUM']['FEATURE_VIEW_NEW_MESSAGES_URL'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'viewnewmessagesurl'));
			$features['FORUM']['FEATURE_PRIVATE_MESSAGE_COUNT'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'privatemessagecounts'));
			$features['FORUM']['FEATURE_ACTIVITY'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'activity'));
			$features['FORUM']['FEATURE_DISCUSSION_BOT'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'discussion'));
			
			$features['USER']['FEATURE_DUAL_LOGIN'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'duallogin'));
			$features['USER']['FEATURE_DUAL_LOGOUT'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'duallogout'));
			$features['USER']['FEATURE_UPDATE_PASSWORD'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'updatepassword'));
			$features['USER']['FEATURE_UPDATE_USERNAME'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'updateusername'));
			$features['USER']['FEATURE_UPDATE_EMAIL'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'updateemail'));
			$features['USER']['FEATURE_UPDATE_USERGROUP'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'updateusergroup'));
			$features['USER']['FEATURE_UPDATE_LANGUAGE'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'updateuserlanguage'));
			$features['USER']['FEATURE_SESSION_SYNC'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'syncsessions'));
			$features['USER']['FEATURE_BLOCK_USER'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'blockuser'));
			$features['USER']['FEATURE_ACTIVATE_USER'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'activateuser'));
			$features['USER']['FEATURE_DELETE_USER'] = $this->outputFeature(JFusionFunction::hasFeature($jname,'deleteuser'));
			
			
        	$this->assignRef('features', $features);
            $this->assignRef('jname', $jname);
            //render view
            parent::display($tpl);
        } else {
            //report error
            JError::raiseWarning(500, JText::_('NONE_SELECTED'));
        }
    }

    /**
     * @param $feature
     * @return string
     */
    function outputFeature($feature)
    {
    	if ($feature===true) {
    		$feature = 'JYES';
    	} else if ($feature===false) {
    		$feature = 'JNO';
    	}
	    switch ($feature) {
		    case 'JNO':
		    	$images = 'cross.png';
		        break;
		    case 'JYES':
		    	$images = 'tick.png';
		        break;
		    default:
		    	$images = 'system-help.png';
		        break;
		}
		return '<img src="components/com_jfusion/images/'.$images.'"/> '.JText::_($feature);
    }
}
