<?php

/**
* @package JFusion_mediawiki
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

/**
 * JFusion User Class for mediawiki 1.1.x
 * For detailed descriptions on these functions please check the model.abstractuser.php
 * @package JFusion_mediawiki
 */
//require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.jfusion.php');
//require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.abstractuser.php');
require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.jplugin.php');
/**
 * JFusionUser_mediawiki class
 *
 * @category   JFusion
 * @package    Plugin
 * @subpackage JFusionUser_mediawiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionUser_mediawiki extends JFusionUser {

    /**
     * @param object $userinfo
     * @return null|object
     */
    function getUser($userinfo)
    {
		// get the username
		if (is_object($userinfo)){
			$username = $userinfo->username;
		} else {
			$username = $userinfo;
		}
		$username = ucfirst($username);
        // initialise some objects
        $db = JFusionFactory::getDatabase($this->getJname());

        $query = 'SELECT user_id as userid, user_name as username, user_token, user_real_name as name, user_email as email, user_password as password, NULL as password_salt, NULL as activation, TRUE as is_activated, NULL as reason, user_touched as lastvisit '.
        		'FROM #__user '.
        		'WHERE user_name=' . $db->Quote($username);
        $db->setQuery($query );
        $result = $db->loadObject();

        if ($result) {
        	$query = 'SELECT ug_group '.
        			'FROM #__user_groups '.
        			'WHERE ug_user=' . $db->Quote($result->userid);
        	$db->setQuery( $query );
        	$grouplist = $db->loadObjectList();
			$groups = array();
			foreach($grouplist as $group) {
				$groups[] = $group->ug_group;
			}
			$result->group_id = implode( ',' , $groups );
            $result->groups = $groups;

        	$query = 'SELECT ipb_user, ipb_expiry '.
        			'FROM #__ipblocks '.
        			'WHERE ipb_user=' . $db->Quote($result->userid);
        	$db->setQuery( $query );
        	$block = $db->loadObject();

            if (isset($block->ipb_user)) {
            	if ($block->ipb_expiry ) {
                	$result->block = 1;
            	} else {
                	$result->block = 0;
            	}
            } else {
                $result->block = 0;
            }

			$result->activation = '';
        }
        return $result;
    }

    /**
     * @return string
     */
    function getJname()
    {
        return 'mediawiki';
    }

    /**
     * @param object $userinfo
     * @return array
     */
    function deleteUser($userinfo)
    {
    	//setup status array to hold debug info and errors
        $status = array('error' => array(),'debug' => array());

        $db = JFusionFactory::getDatabase($this->getJname());

		$query = 'DELETE FROM #__user WHERE user_name = '.$db->quote($userinfo->username);
		$db->setQuery($query);
        if (!$db->query()) {
       		$status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' .  $db->stderr();
        } else {
			$query = 'DELETE FROM #__user_groups WHERE ug_user = '.$db->quote($userinfo->userid);
			$db->setQuery($query);
			$db->query();
			$status['debug'][] = JText::_('USER_DELETION'). ' ' . $userinfo->username;
		}

		return $status;
    }

    /**
     * @param object $userinfo
     * @param array $options
     *
     * @return array
     */
    function destroySession($userinfo, $options){
        $params = JFusionFactory::getParams($this->getJname());
        /**
         * @ignore
         * @var $helper JFusionHelper_mediawiki
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        $cookie_path = $params->get('cookie_path');
        $cookie_domain = $params->get('cookie_domain');
        $cookie_secure = $params->get('secure');
        $cookie_httponly = $params->get('httponly');
        $cookie_name = $helper->getCookieName();
        $expires = -3600;

        $helper->startSession($options);
   		$_SESSION['wsUserID'] = 0;
   		$_SESSION['wsUserName'] = '';
   		$_SESSION['wsToken'] = '';
        $helper->closeSession();

        $status['debug'][] = JFusionFunction::addCookie($cookie_name  . 'UserName', '', $expires, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
        $status['debug'][] = JFusionFunction::addCookie($cookie_name  . 'UserID', '', $expires, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
        $status['debug'][] = JFusionFunction::addCookie($cookie_name  . 'Token', '', $expires, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);

   		$now = time();
        $expiration = 86400;

        $status['debug'][] = JFusionFunction::addCookie('LoggedOut', $now, $expiration, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
		return $status;
     }

    /**
     * @param object $userinfo
     * @param array $options
     * @return array
     */
    function createSession($userinfo, $options){
        $status = array('error' => array(),'debug' => array());

		//do not create sessions for blocked users
		if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status['error'][] = JText::_('FUSION_BLOCKED_USER');
		} else {
            //$status = JFusionJplugin::createSession($userinfo, $options,$this->getJname());

            $params = JFusionFactory::getParams($this->getJname());
            /**
             * @ignore
             * @var $helper JFusionHelper_mediawiki
             */
            $helper = JFusionFactory::getHelper($this->getJname());

            $cookie_path = $params->get('cookie_path');
            $cookie_domain = $params->get('cookie_domain');
            $cookie_secure = $params->get('secure');
            $cookie_httponly = $params->get('httponly');
			$expires = $params->get('cookie_expires', 3100);
            $cookie_name = $helper->getCookieName();
            $helper->startSession($options);

			$status['debug'][] = JFusionFunction::addCookie($cookie_name  . 'UserName', $userinfo->username, $expires, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
            $_SESSION['wsUserName'] = $userinfo->username;

			$status['debug'][] = JFusionFunction::addCookie($cookie_name  . 'UserID', $userinfo->userid, $expires, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
            $_SESSION['wsUserID'] = $userinfo->userid;

            $_SESSION[ 'wsToken'] = $userinfo->user_token;
            if (!empty($options['remember'])) {
	            $status['debug'][] = JFusionFunction::addCookie($cookie_name  . 'Token', $userinfo->user_token, $expires, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
            }

            $helper->closeSession();
        }
		return $status;
	}


    /**
     * @param string $username
     *
     * @return string
     */
    function filterUsername($username)
    {
        //no username filtering implemented yet
        return $username;
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function updatePassword($userinfo, &$existinguser, &$status)
    {
        $existinguser->password = ':A:' . md5( $userinfo->password_clear);
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__user SET user_password = ' . $db->quote($existinguser->password). ' WHERE user_id  = ' . $existinguser->userid;
        $db->setQuery($query );
        if (!$db->query()) {
            $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR')  . $db->stderr();
        } else {
	        $status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password,0,6) . '********';
        }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function updateUsername($userinfo, &$existinguser, &$status)
    {

    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function updateEmail($userinfo, &$existinguser, &$status)
    {
        //we need to update the email
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__user SET user_email ='.$db->quote($userinfo->email) .' WHERE user_id =' . $existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $db->stderr();
        } else {
	        $status['debug'][] = JText::_('EMAIL_UPDATE'). ': ' . $existinguser->email . ' -> ' . $userinfo->email;
        }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function updateUsergroup($userinfo, &$existinguser, &$status)
	{
        $params = JFusionFactory::getParams($this->getJname());
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
        if (empty($usergroups)) {
            $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ": " . JText::_('USERGROUP_MISSING');
        } else {
            $db = JFusionFactory::getDatabase($this->getJname());
            $query = 'DELETE FROM #__user_groups WHERE ug_user = '.$db->quote($existinguser->userid);
            $db->setQuery($query);
            $db->query();
            foreach($usergroups as $usergroup) {
                //prepare the user variables
                $ug = new stdClass;
                $ug->ug_user = $existinguser->userid;
                $ug->ug_group = $usergroup;

                if (!$db->insertObject('#__user_groups', $ug, 'ug_user' )) {
                    $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
                } else {
                    $status['debug'][] = JText::_('GROUP_UPDATE'). ': ' . implode (' , ', $existinguser->groups) . ' -> ' . $usergroup;
                }
            }
        }
	}

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function blockUser($userinfo, &$existinguser, &$status)
    {
        $db = JFusionFactory::getDatabase($this->getJname());
        $ban = new stdClass;
        $ban->ipb_id = NULL;
        $ban->ipb_address = NULL;
        $ban->ipb_user = $existinguser->userid;
        $ban->ipb_by = $existinguser->userid;
        $ban->ipb_by_text = $existinguser->username;

        $ban->ipb_reason = 'You have been banned from this software. Please contact your site admin for more details';
        $ban->ipb_timestamp = gmdate( 'YmdHis', time() );

		$ban->ipb_auto = 0;
		$ban->ipb_anon_only = 0;
		$ban->ipb_create_account = 1;
		$ban->ipb_enable_autoblock = 1;
		$ban->ipb_expiry = 'infinity';
		$ban->ipb_range_start = NULL;
		$ban->ipb_range_end = NULL;
		$ban->ipb_deleted = 0;
		$ban->ipb_block_email = 0;
		$ban->ipb_allow_usertalk = 0;

        //now append the new user data
        if (!$db->insertObject('#__ipblocks', $ban, 'ipb_id' )) {
     	   $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
        } else {
	        $status['debug'][] = JText::_('BLOCK_UPDATE'). ': ' . $existinguser->block . ' -> ' . $userinfo->block;
    	}
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function unblockUser($userinfo, &$existinguser, &$status)
    {
    	$db = JFusionFactory::getDatabase($this->getJname());
        $query = 'DELETE FROM #__ipblocks WHERE ipb_user = ' . $db->quote($existinguser->userid);
        $db->setQuery($query);
	    if (!$db->query()) {
    	    $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
    	} else {
        	$status['debug'][] = JText::_('BLOCK_UPDATE'). ': ' . $existinguser->block . ' -> ' . $userinfo->block;
    	}
    }
/*
    function activateUser($userinfo, &$existinguser, &$status)
    {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__user SET is_activated = 1, validation_code = \'\' WHERE user_id  = ' . $existinguser->userid;
        $db->setQuery($query );
        if (!$db->query()) {
            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
        } else {
	        $status['debug'][] = JText::_('ACTIVATION_UPDATE'). ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
        }
    }

    function inactivateUser($userinfo, &$existinguser, &$status)
    {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__user SET is_activated = 0, validation_code = '.$db->Quote($userinfo->activation).' WHERE user_id  = ' . $existinguser->userid;
        $db->setQuery($query );
        if (!$db->query()) {
            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
        } else {
	        $status['debug'][] = JText::_('ACTIVATION_UPDATE'). ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
        }
    }
*/

    /**
     * @param object $userinfo
     * @param array $status
     *
     * @return void
     */
    function createUser($userinfo, &$status)
    {
        //we need to create a new SMF user
        $db = JFusionFactory::getDatabase($this->getJname());
        $params = JFusionFactory::getParams($this->getJname());
        $source_path = $params->get('source_path');
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
        if (empty($usergroups)) {
            $status['error'][] = JText::_('ERROR_CREATE_USER') . ' ' . JText::_('USERGROUP_MISSING');
        } else {
            //prepare the user variables
            $user = new stdClass;
            $user->user_id = NULL;
            $user->user_name = ucfirst($userinfo->username);
            $user->user_real_name = $userinfo->name;
            $user->user_email = $userinfo->email;
            $user->user_email_token_expires = null;
            $user->user_email_token = '';

            if (isset($userinfo->password_clear)) {
                $user->user_password = ':A:' . md5( $userinfo->password_clear);
            } else {
                $user->user_password = ':A:' . $userinfo->password;
            }
            $user->user_newpass_time = $user->user_newpassword = null;

            $db->setQuery("SHOW COLUMNS FROM #__user LIKE 'user_options'");
            if ($db->query() && $db->getNumRows() ) {
                $user->user_options = ' ';
            }

            $user->user_email_authenticated = $user->user_registration = $user->user_touched = gmdate( 'YmdHis', time() );
            $user->user_editcount = 0;
            /*
                    if ($userinfo->activation){
                        $user->is_activated = 0;
                        $user->validation_code = $userinfo->activation;
                    } else {
                        $user->is_activated = 1;
                        $user->validation_code = '';
                    }
            */
            //now append the new user data
            if (!$db->insertObject('#__user', $user, 'user_id' )) {
                //return the error
                $status['error'] = JText::_('USER_CREATION_ERROR'). ': ' . $db->stderr();
            } else {
                $wgDBprefix = $params->get('database_prefix');
                $wgDBname = $params->get('database_name');

                if ( $wgDBprefix ) {
                    $wfWikiID = $wgDBname.'-'.$wgDBprefix;
                } else {
                    $wfWikiID = $wgDBname;
                }
                /**
                 * @ignore
                 * @var $helper JFusionHelper_mediawiki
                 */
                $helper = JFusionFactory::getHelper($this->getJname());
                $wgSecretKey = $helper->getConfig('wgSecretKey');
                $wgProxyKey = $helper->getConfig('wgProxyKey');

                if ( $wgSecretKey ) {
                    $key = $wgSecretKey;
                } elseif ( $wgProxyKey ) {
                    $key = $wgProxyKey;
                } else {
                    $key = microtime();
                }
                //update the stats
                $mToken = md5( $key . mt_rand( 0, 0x7fffffff ) . $wfWikiID . $user->user_id );

                $query = 'UPDATE #__user SET user_token = '.$db->Quote($mToken).' WHERE user_id = '.$db->Quote($user->user_id);
                $db->setQuery($query);
                if (!$db->query()) {
                    //return the error
                    $status['error'][] = JText::_('USER_CREATION_ERROR')  . ' ' .  $db->stderr();
                } else {
                    //prepare the user variables
                    foreach($usergroups as $usergroup) {
                        //prepare the user variables
                        $ug = new stdClass;
                        $ug->ug_user = $user->user_id;
                        $ug->ug_group = $usergroup;

                        if (!$db->insertObject('#__user_groups', $ug, 'ug_user' )) {
                            $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
                        }
                    }
                    //return the good news
                    $status['debug'][] = JText::_('USER_CREATION');
                    $status['userinfo'] = $this->getUser($userinfo);
                }
            }
        }
    }
}
