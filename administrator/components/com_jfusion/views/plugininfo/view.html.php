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
			$public = JFusionFactory::getPublic($jname);
			$forum = JFusionFactory::getForum($jname);
			$user = JFusionFactory::getUser($jname);
			
			$features['ADMIN']['FEATURE_WIZARD'] = $this->outputFeature(JFusionFunctionAdmin::methodDefined($admin,'setupFromPath'));
			$features['ADMIN']['FEATURE_REQUIRE_FILE_ACCESS'] = $this->outputFeature($admin->requireFileAccess());
			$features['ADMIN']['FEATURE_MULTI_USERGROUP'] = $this->outputFeature($admin->isMultiGroup());

            $frameless = JFusionFunctionAdmin::methodDefined($public,'getBuffer');
            if (!$frameless) {
                $frameless = 'CURL_FRAMELESS';
            }
			$features['PUBLIC']['FEATURE_FRAMELESS'] = $this->outputFeature($frameless);
			$features['PUBLIC']['FEATURE_BREADCRUMB'] = $this->outputFeature(JFusionFunctionAdmin::methodDefined($public,'getPathWay'));
			$features['PUBLIC']['FEATURE_SEARCH'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($public,'getSearchQuery')
												||JFusionFunctionAdmin::methodDefined($public,'getSearchResults')));
			$features['PUBLIC']['FEATURE_ONLINE_STATUS'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($public,'getOnlineUserQuery')));
			$features['PUBLIC']['FEATURE_FRONT_END_LANGUAGE'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($public,'setLanguageFrontEnd')));
			
			$features['FORUM']['FEATURE_THREAD_URL'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($forum,'getThreadURL')));
			$features['FORUM']['FEATURE_POST_URL'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($forum,'getPostURL')));
			$features['FORUM']['FEATURE_PROFILE_URL'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($forum,'getProfileURL')));			
			$features['FORUM']['FEATURE_AVATAR_URL'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($forum,'getAvatar')));
			$features['FORUM']['FEATURE_PRIVATE_MESSAGE_URL'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($forum,'getPrivateMessageURL')));
			$features['FORUM']['FEATURE_VIEW_NEW_MESSAGES_URL'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($forum,'getViewNewMessagesURL')));
			$features['FORUM']['FEATURE_PRIVATE_MESSAGE_COUNT'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($forum,'getPrivateMessageCounts')));
			$features['FORUM']['FEATURE_ACTIVITY'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($forum,'getActivityQuery')
												||JFusionFunctionAdmin::methodDefined($forum,'renderActivityModule')));
			$features['FORUM']['FEATURE_DISCUSSION_BOT'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($forum,'createThread')));
			
			$features['USER']['FEATURE_DUAL_LOGIN'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($user,'createSession')));
			$features['USER']['FEATURE_DUAL_LOGOUT'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($user,'destroySession')));
			$features['USER']['FEATURE_UPDATE_PASSWORD'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($user,'updatePassword')));
			$features['USER']['FEATURE_UPDATE_USERNAME'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($user,'updateUsername')));
			$features['USER']['FEATURE_UPDATE_EMAIL'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($user,'updateEmail')));
			$features['USER']['FEATURE_UPDATE_USERGROUP'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($user,'updateUsergroup')));
			$features['USER']['FEATURE_UPDATE_LANGUAGE'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($user,'updateUserLanguage')));
			$features['USER']['FEATURE_SESSION_SYNC'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($user,'syncSessions')));
			$features['USER']['FEATURE_BLOCK_USER'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($user,'blockUser')));			
			$features['USER']['FEATURE_ACTIVATE_USER'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($user,'activateUser')));
			$features['USER']['FEATURE_DELETE_USER'] = $this->outputFeature((JFusionFunctionAdmin::methodDefined($user,'deleteUser')));
			
			
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
