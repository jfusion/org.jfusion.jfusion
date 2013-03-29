<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
*/

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

/**
 * JFusion Admin Class for vBulletin
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionUser_vbulletin extends JFusionUser
{
    /**
     * @var $params JParameter
     */
    var $params;
    /**
     * @var $helper JFusionHelper_vbulletin
     */
    var $helper;

    /**
     *
     */
    function __construct()
    {
        //get the params object
        $this->params = JFusionFactory::getParams($this->getJname());
        //get the helper object

        $this->helper = JFusionFactory::getHelper($this->getJname());
    }

    /**
     * @param object $userinfo
     * @param string $identifier_type
     * @param int $ignore_id
     * @return null|object
     */
    function getUser($userinfo, $identifier_type = 'auto', $ignore_id = 0)
    {
    	if($identifier_type == 'auto') {
        	//get the identifier
        	list($identifier_type,$identifier) = $this->getUserIdentifier($userinfo,'u.username','u.email');
        	if ($identifier_type == 'u.username') {
        	    //lower the username for case insensitivity purposes
        	    $identifier_type = 'LOWER(u.username)';
        	    $identifier = strtolower($identifier);
        	}
    	} else {
    		$identifier_type = 'u.' . $identifier_type;
    		$identifier = $userinfo;
    	}

        // Get user info from database
        $db = JFusionFactory::getDatabase($this->getJname());

        $name_field = $this->params->get('name_field');

        $query = 'SELECT u.userid, u.username, u.email, u.usergroupid AS group_id, u.membergroupids, u.displaygroupid, u.password, u.salt as password_salt, u.usertitle, u.customtitle, u.posts, u.username as name FROM #__user AS u WHERE ' . $identifier_type . ' = ' . $db->Quote($identifier);
        $query.= ($ignore_id) ? ' AND u.userid != '.$ignore_id : '';

        $db->setQuery($query );
        $result = $db->loadObject();

        if ($result) {
            $query = 'SELECT title FROM #__usergroup WHERE usergroupid = '.$result->group_id;
            $db->setQuery($query);
            $result->group_name = $db->loadResult();

            if (!empty($name_field)) {
                $query = 'SELECT $name_field FROM #__userfield WHERE userid = '.$result->userid;
                $db->setQuery($query);
                $name = $db->loadResult();
                if (!empty($name)) {
                    $result->name = $name;
                }
            }
            //Check to see if they are banned
            $query = 'SELECT userid FROM #__userban WHERE userid='. $result->userid;
            $db->setQuery($query);
            if ($db->loadObject() || ($this->params->get('block_coppa_users', 1) && (int) $result->group_id == 4)) {
                $result->block = 1;
            } else {
                $result->block = 0;
            }

            //check to see if the user is awaiting activation
            $activationgroup = $this->params->get('activationgroup');

            if ($activationgroup == $result->group_id) {
                jimport('joomla.user.helper');
                $result->activation = JUserHelper::genRandomPassword(32);
            } else {
                $result->activation = '';
            }
        }
        return $result;
    }

    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'vbulletin';
    }

    /**
     * @return string
     */
    function getTablename()
    {
        return 'user';
    }

    /**
     * @param object $userinfo
     * @return array
     */
    function deleteUser($userinfo)
    {
        //setup status array to hold debug info and errors
        $status = array();
        $status['debug'] = array();
        $status['error'] = array();

        $apidata = array('userinfo' => $userinfo);
        $response = $this->helper->apiCall('deleteUser', $apidata);

        if (!empty($response['errors'])) {
            foreach ($response['errors'] as $error) {
                $status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' . $error;
            }
        } else {
            $status['debug'][] = JText::_('USER_DELETION'). ' ' . $userinfo->userid;
        }

        if (!empty($response['debug'])) {
		    $status['debug']['api_call'] = $response['debug'];
		}
        return $status;
    }

    /**
     * @param object $userinfo
     * @param array $options
     *
     * @return array
     */
    function destroySession($userinfo, $options)
    {
        $status = array();
        $status['error'] = array();
        $status['debug'] = array();

        $cookie_prefix = $this->params->get('cookie_prefix');
        $vbversion = $this->helper->getVersion();
        if ((int) substr($vbversion, 0, 1) > 3) {
           if (substr($cookie_prefix, -1) !== '_') {
               $cookie_prefix .= '_';
           }
        }
        $cookie_domain = $this->params->get('cookie_domain');
        $cookie_path = $this->params->get('cookie_path');
        $cookie_salt = $this->params->get('cookie_salt');
        $cookie_expires = $this->params->get('cookie_expires', '15') * 60;
        $secure = $this->params->get('secure',false);
        $httponly = $this->params->get('httponly',true);
        $timenow = time();

        $session_user = JRequest::getVar($cookie_prefix . "userid", '', 'cookie');
        if (empty($session_user)) {
            $status['debug'][] = JText::_('VB_COOKIE_USERID_NOT_FOUND');
        }

        $session_hash = JRequest::getVar($cookie_prefix . "sessionhash", '', 'cookie');
        if (empty($session_hash)) {
            $status['debug'][] = JText::_('VB_COOKIE_HASH_NOT_FOUND');
        }

        //If blocking a user in Joomla User Manager, Joomla will initiate a logout.
        //Thus, prevent a logout of the currently logged in user if a user has been blocked:
        if (!defined('VBULLETIN_BLOCKUSER_CALLED')) {
            require_once JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.curl.php';

            //clear out all of vB's cookies
            foreach ($_COOKIE AS $key => $val) {
		        if (strpos($key, $cookie_prefix) !== false) {
                    $status['debug'][] = JFusionCurl::addCookie($key , 0, $timenow - 3600, $cookie_path, $cookie_domain, $secure, $httponly);
		        }
            }

    		$db = JFusionFactory::getDatabase($this->getJname());
    		$queries = array();

    		if ($session_user) {
    			$queries[] = 'UPDATE #__user SET lastvisit = ' . $db->Quote($timenow) . ', lastactivity = ' . $db->Quote($timenow - $cookie_expires) . ' WHERE userid = ' . $db->Quote($session_user);
            	$queries[] = 'DELETE FROM #__session WHERE userid = ' . $db->Quote($session_user);
    		}
        	$queries[] = 'DELETE FROM #__session WHERE sessionhash = ' . $db->Quote($session_hash);

            foreach ($queries as $q) {
                $db->setQuery($q);
                if (!$db->query()) {
                    $status['debug'][] = $db->stderr();
                }
            }
            return $status;
        } else {
            $status = array();
            $status['debug'] = 'Joomla initiated a logout of a blocked user thus skipped vBulletin destroySession() to prevent current user from getting logged out.';
        }
        return $status;
    }

    /**
     * @param object $userinfo
     * @param array $options
     * @return array
     */
    function createSession(&$userinfo, $options)
    {
        $status = array('error' => array(),'debug' => array());
        //do not create sessions for blocked users
        if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status['error'][] = JText::_('FUSION_BLOCKED_USER');
        } else {
            require_once JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.curl.php';
            //first check to see if striking is enabled to prevent further strikes
            $db = JFusionFactory::getDatabase($this->getJname());
            $query = 'SELECT value FROM #__setting WHERE varname = \'usestrikesystem\'';
            $db->setQuery($query);
            $strikeEnabled = $db->loadResult();

            if ($strikeEnabled) {
                $ip = $_SERVER['REMOTE_ADDR'];
                $time = strtotime('-15 minutes');
                $query = 'SELECT COUNT(*) FROM #__strikes WHERE strikeip = '.$db->Quote($ip).' AND striketime >= '.$time;
                $db->setQuery($query);
                $strikes = $db->loadResult();

                if ($strikes >= 5) {
                    $status = array();
                    $status['error'] = JText::_('VB_TOO_MANY_STRIKES');
                    return $status;
                }
            }

            //make sure a session is not already active for this user
            $cookie_prefix = $this->params->get('cookie_prefix');
            $vbversion = $this->helper->getVersion();
            if ((int) substr($vbversion, 0, 1) > 3) {
                if (substr($cookie_prefix, -1) !== '_') {
                    $cookie_prefix .= '_';
                }
            }
            $cookie_salt = $this->params->get('cookie_salt');
            $cookie_domain = $this->params->get('cookie_domain');
            $cookie_path = $this->params->get('cookie_path');
            $cookie_expires  = (!empty($options['remember'])) ? 0 : $this->params->get('cookie_expires');
            if ($cookie_expires == 0) {
                $expires_time = time() + (60 * 60 * 24 * 365);
            } else {
                $expires_time = time() + ( 60 * $cookie_expires );
            }
            $debug_expiration = date('Y-m-d H:i:s', $expires_time);
            $passwordhash = md5($userinfo->password.$cookie_salt);

            $query = 'SELECT sessionhash FROM #__session WHERE userid = ' . $userinfo->userid;
            $db->setQuery($query);
            $sessionhash = $db->loadResult();

            $cookie_sessionhash = JRequest::getVar($cookie_prefix . 'sessionhash', '', 'cookie');
            $cookie_userid = JRequest::getVar($cookie_prefix . 'userid', '', 'cookie');
            $cookie_password = JRequest::getVar($cookie_prefix . 'password', '', 'cookie');

            if (!empty($cookie_userid) && $cookie_userid == $userinfo->userid && !empty($cookie_password) && $cookie_password == $passwordhash) {
                $vbcookieuser = true;
            } else {
                $vbcookieuser = false;
            }

            if (!$vbcookieuser && (empty($cookie_sessionhash) || $sessionhash != $cookie_sessionhash)) {
                $secure = $this->params->get('secure', false);
                $httponly = $this->params->get('httponly', true);

                $status['debug'][] = JFusionCurl::addCookie($cookie_prefix.'userid' , $userinfo->userid, $expires_time,  $cookie_path, $cookie_domain, $secure, $httponly);
                $status['debug'][] = JFusionCurl::addCookie($cookie_prefix.'password' , $passwordhash, $expires_time, $cookie_path, $cookie_domain, $secure, $httponly, true);
            } else {
                $status['debug'][] = JText::_('VB_SESSION_ALREADY_ACTIVE');
	            /*
	             * do not want to output as it indicate the cookies are set when they are not.
                $status['debug'][JText::_('COOKIES')][] = array(JText::_('NAME') => $cookie_prefix.'userid', JText::_('VALUE') => $cookie_userid, JText::_('EXPIRES') => $debug_expiration, JText::_('COOKIE_PATH') => $cookie_path, JText::_('COOKIE_DOMAIN') => $cookie_domain);
                $status['debug'][JText::_('COOKIES')][] = array(JText::_('NAME') => $cookie_prefix.'password', JText::_('VALUE') => substr($cookie_password, 0, 6) . '********, ', JText::_('EXPIRES') => $debug_expiration, JText::_('COOKIE_PATH') => $cookie_path, JText::_('COOKIE_DOMAIN') => $cookie_domain);
                $status['debug'][JText::_('COOKIES')][] = array(JText::_('NAME') => $cookie_prefix.'sessionhash', JText::_('VALUE') => $cookie_sessionhash, JText::_('EXPIRES') => $debug_expiration, JText::_('COOKIE_PATH') => $cookie_path, JText::_('COOKIE_DOMAIN') => $cookie_domain);
	            */
            }
        }
        return $status;
    }

    /**
     * @param string $username
     * @return string
     */
    function filterUsername($username)
    {
        //lower username for case insensitivity purposes
        return strtolower($username);
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
        jimport('joomla.user.helper');
        $existinguser->password_salt = JUserHelper::genRandomPassword(3);
        $existinguser->password = md5(md5($userinfo->password_clear).$existinguser->password_salt);

        $date = date('Y-m-d');

        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__user SET passworddate = ' . $db->Quote($date) . ', password = ' . $db->Quote($existinguser->password). ', salt = ' . $db->Quote($existinguser->password_salt). ' WHERE userid  = ' . $existinguser->userid;
        $db->setQuery($query );
        if (!$db->query()) {
            $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR')  . ': ' . $db->stderr();
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
    function updateEmail($userinfo, &$existinguser, &$status)
    {
        $apidata = array('userinfo' => $userinfo, 'existinguser' => $existinguser);
        $response = $this->helper->apiCall('updateEmail', $apidata);

	    if(!empty($response['errors'])) {
    		foreach ($response['errors'] as $error) {
        		$status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . ' ' . $error;
            }
        } else {
            $status['debug'][] = JText::_('EMAIL_UPDATE'). ': ' . $existinguser->email . ' -> ' . $userinfo->email;
        }

        if (!empty($response['debug'])) {
		    $status['debug']['api_call'] = $response['debug'];
		}
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function blockUser (&$userinfo, &$existinguser, &$status)
    {
        $db = JFusionFactory::getDatabase($this->getJname());

        //get the id of the banned group
        $bannedgroup = $this->params->get('bannedgroup');

        //update the usergroup to banned
        $query = 'UPDATE #__user SET usergroupid = ' . $bannedgroup . ' WHERE userid  = ' . $existinguser->userid;
        $db->setQuery($query);

        if (!$db->query()) {
            $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . ': ' . $db->stderr();
        } else {
            //add a banned user catch to vbulletin database
            $ban = new stdClass;
            $ban->userid = $existinguser->userid;
            $ban->usergroupid = $existinguser->group_id;
            $ban->displaygroupid = $existinguser->displaygroupid;
            $ban->customtitle = $existinguser->customtitle;
            $ban->usertitle = $existinguser->usertitle;
            $ban->adminid = 1;
            $ban->bandate = time();
            $ban->liftdate = 0;
            $ban->reason = (!empty($status['aec'])) ? $status['block_message'] : $this->params->get('blockmessage');

            //now append or update the new user data
            $query = 'SELECT COUNT(*) FROM #__userban WHERE userid = ' . $existinguser->userid;
            $db->setQuery($query);
            $banned = $db->loadResult();

            $result = ($banned) ?  $db->updateObject('#__userban', $ban, 'userid' ) : $db->insertObject('#__userban', $ban, 'userid' );
            if (!$result) {
                $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . ': ' . $db->stderr();
            } else {
                $status['debug'][] = JText::_('BLOCK_UPDATE'). ': ' . $existinguser->block . ' -> ' . $userinfo->block;
            }
        }

        //note that blockUser has been called
        if (empty($status['aec'])) {
            define('VBULLETIN_BLOCKUSER_CALLED',1);
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
        //found out what usergroup should be used
        $usergroups = JFusionFunction::isAdvancedUsergroupMode($this->getJname()) ? unserialize($this->params->get('usergroup')) : $this->params->get('usergroup');
        $bannedgroup = $this->params->get('bannedgroup');

        //first check to see if user is banned and if so, retrieve the prebanned fields
        //must be something other than $db because it conflicts with vbulletin global variables
        $jdb = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT b.*, g.usertitle AS bantitle FROM #__userban AS b INNER JOIN #__user AS u ON b.userid = u.userid INNER JOIN #__usergroup AS g ON u.usergroupid = g.usergroupid WHERE b.userid = ' . $existinguser->userid;
        $jdb->setQuery($query );
        $result = $jdb->loadObject();

        if (is_array($usergroups)) {
            $defaultgroup = $usergroups[$existinguser->group_id]['defaultgroup'];
            $displaygroup = $usergroups[$existinguser->group_id]['displaygroup'];
        } else {
            $defaultgroup = $usergroups;
            $displaygroup = $usergroups;
        }

        $defaulttitle = $this->getDefaultUserTitle($defaultgroup, $existinguser->posts);

        $apidata = array(
        	"userinfo" => $userinfo,
        	"existinguser" => $existinguser,
            "usergroups" => $usergroups,
        	"bannedgroup" => $bannedgroup,
        	"defaultgroup" => $defaultgroup,
        	"displaygroup" => $displaygroup,
        	"defaulttitle" => $defaulttitle,
            "result" => $result
        );
        $response = $this->helper->apiCall('unblockUser', $apidata);

        if ($result) {
            //remove any banned user catches from vbulletin database
            $query = 'DELETE FROM #__userban WHERE userid='. $existinguser->userid;
            $jdb->setQuery($query);
            if (!$jdb->Query()) {
                $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . ': ' . $jdb->stderr();
            }
        }

        if (empty($response['errors'])) {
            $status['debug'][] = JText::_('BLOCK_UPDATE'). ': ' . $existinguser->block . ' -> ' . $userinfo->block;
        } else {
            foreach ($response['errors'] as $error) {
                $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . ' ' . $error;
            }
        }

        if (!empty($response['debug'])) {
		    $status['debug']['api_call'] = $response['debug'];
		}
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function activateUser($userinfo, &$existinguser, &$status)
    {
        //found out what usergroup should be used
        $usergroups = JFusionFunction::isAdvancedUsergroupMode($this->getJname()) ? unserialize($this->params->get('usergroup')) : $this->params->get('usergroup');
        $usergroup = (is_array($usergroups)) ? $usergroups[$userinfo->group_id]['defaultgroup'] : $usergroups;

        //update the usergroup to default group
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__user SET usergroupid = ' . $usergroup . ' WHERE userid  = ' . $existinguser->userid;
        $db->setQuery($query );

        if ($db->query()) {
            //remove any activation catches from vbulletin database
            $query = 'DELETE FROM #__useractivation WHERE userid = ' . $existinguser->userid;
            $db->setQuery($query);

            if (!$db->Query()) {
                $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . ': ' . $db->stderr();
            } else {
                $status['debug'][] = JText::_('ACTIVATION_UPDATE'). ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
            }
        } else {
            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . ': ' . $db->stderr();
        }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function inactivateUser($userinfo, &$existinguser, &$status)
    {
        //found out what usergroup should be used
        $usergroup = $this->params->get('activationgroup');

        //update the usergroup to awaiting activation
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__user SET usergroupid = ' . $usergroup . ' WHERE userid  = ' . $existinguser->userid;
        $db->setQuery($query );

        if ($db->Query()) {
            //update the activation status
            //check to see if the user is already inactivated
            $query = 'SELECT COUNT(*) FROM #__useractivation WHERE userid = ' . $existinguser->userid;
            $db->setQuery($query);
            $count = $db->loadResult();
            if (empty($count)) {
                //if not, then add an activation catch to vbulletin database
                $useractivation = new stdClass;
                $useractivation->userid = $existinguser->userid;
                $useractivation->dateline = time();
                jimport('joomla.user.helper');
                $useractivation->activationid = JUserHelper::genRandomPassword(40);

                $usergroups = JFusionFunction::isAdvancedUsergroupMode($this->getJname()) ? unserialize($this->params->get('usergroup')) : $this->params->get('usergroup');
                $usergroup = (is_array($usergroups)) ? $usergroups[$userinfo->group_id]['defaultgroup'] : $usergroups;
                $useractivation->usergroupid = $usergroup;

                if ($db->insertObject('#__useractivation', $useractivation, 'useractivationid' )) {
                    $apidata = array('existinguser' => $existinguser);
                    $response = $this->helper->apiCall('inactivateUser', $apidata);
                    if (empty($response['errors'])) {
                        $status['debug'][] = JText::_('ACTIVATION_UPDATE'). ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
                    } else {
                        foreach ($response['errors'] as $error) {
                            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . ' ' . $error;
                        }
                    }
                } else {
                    $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . ': ' . $db->stderr();
                }
            } else {
                $status['debug'][] = JText::_('ACTIVATION_UPDATE'). ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
            }
        } else {
            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . ': ' . $db->stderr();
        }

        if (!empty($response['debug'])) {
		    $status['debug']['api_call'] = $response['debug'];
		}
    }

    /**
     * @param object $userinfo
     * @param array $status
     *
     * @return void
     */
    function createUser($userinfo, &$status)
    {
        //get the default user group and determine if we are using simple or advanced
        $usergroups = JFusionFunction::isAdvancedUsergroupMode($this->getJname()) ? unserialize($this->params->get('usergroup')) : $this->params->get('usergroup');

        //return if we are in advanced user group mode but the master did not pass in a group_id
        if (is_array($usergroups) && !isset($userinfo->group_id)) {
            $status['error'][] = JText::_('ERROR_CREATE_USER'). ' ' . JText::_('ADVANCED_GROUPMODE_MASTER_NOT_HAVE_GROUPID');
        } else {
            if (empty($userinfo->activation)) {
                $defaultgroup = (is_array($usergroups)) ? $usergroups[$userinfo->group_id]['defaultgroup'] : $usergroups;
                $setAsNeedsActivation = false;
            } else {
                $defaultgroup = $this->params->get('activationgroup');
                $setAsNeedsActivation = true;
            }

            $apidata = array();
            $apidata['usergroups'] = $usergroups;
            $apidata['defaultgroup'] = $defaultgroup;

            $usertitle = $this->getDefaultUserTitle($defaultgroup);
            $userinfo->usertitle = $usertitle;

            if (!isset($userinfo->password_clear)) {
                //clear password is not available, set a random password for now
                jimport('joomla.user.helper');
                $random_password = JUtility::getHash(JUserHelper::genRandomPassword(10));
                $userinfo->password_clear = $random_password;
            }

            //set the timezone
            if (!isset($userinfo->timezone)) {
                $config = JFactory::getConfig();
                $userinfo->timezone = $config->getValue('config.offset',0);
            }

            $apidata['userinfo'] = $userinfo;

            //performs some final VB checks before saving
            $response = $this->helper->apiCall('createUser', $apidata);
            if (empty($response['errors'])) {
                $userdmid = $response['new_id'];
                //if we set a temp password, we need to move the hashed password over
                if (!isset($userinfo->password_clear)) {
                    $db = JFusionFactory::getDatabase($this->getJname());
                    $query = 'UPDATE #__user SET password = ' . $db->Quote($userinfo->password). ' WHERE userid  = ' . $userdmid;
                    if (!$db->query()) {
                        $status['debug'][] = JText::_('USER_CREATION_ERROR') .'. '. JText::_('USERID') . ' ' . $userdmid . ': '.JText::_('MASTER_PASSWORD_NOT_COPIED');
                    }
                }

                //save the new user
                $status['userinfo'] = $this->getUser($userinfo);

                //does the user still need to be activated?
                if ($setAsNeedsActivation) {
                    $this->inactivateUser($userinfo, $status['userinfo'], $status);
                }

                //return the good news
                $status['debug'][] = JText::_('USER_CREATION') .'. '. JText::_('USERID') . ' ' . $userdmid;
            } else {
                foreach ($response['errors'] as $error)
                {
                    $status['error'][] = JText::_('USER_CREATION_ERROR') . ' ' . $error;
                }
            }

            if (!empty($response['debug'])) {
                $status['debug']['api_call'] = $response['debug'];
            }
        }
    }

    /**
     * @param object &$userinfo
     * @param object &$existinguser
     * @param array &$status
     *
     * @return bool
     */
    function executeUpdateUsergroup(&$userinfo, &$existinguser, &$status)
    {
        $update_groups = false;
        $usergroups = unserialize($this->params->get('usergroup'));

        $usergroupid = $usergroups[$userinfo->group_id]['defaultgroup'];
        $displaygroupid = $usergroups[$userinfo->group_id]['displaygroup'];
        $membergroupids = (isset($usergroups[$userinfo->group_id]['membergroups'])) ? $usergroups[$userinfo->group_id]['membergroups'] : array();

        //check to see if the default groups are different
        if ($usergroupid != $existinguser->group_id ) {
            $update_groups = true;
        } elseif (!empty($usergroups['options']['compare_displaygroups']) && $displaygroupid != $existinguser->displaygroupid ) {
            //check to see if the display groups are different
            $update_groups = true;
        } elseif (!empty($usergroups['options']['compare_membergroups'])) {
            //check to see if member groups are different
            $current_membergroups = explode(',', $existinguser->membergroupids);
            foreach ($membergroupids as $gid) {
                if (!in_array($gid, $current_membergroups)) {
                    $update_groups = true;
                    break;
                }
            }
        }

        if ($update_groups) {
            $this->updateUsergroup($userinfo, $existinguser, $status);
        }

        return $update_groups;
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
        //check to see if we have a group_id in the $userinfo, if not return
        if (!isset($userinfo->group_id)) {
            $status['error'][] = JText::_('GROUP_UPDATE_ERROR'). ": " . JText::_('ADVANCED_GROUPMODE_MASTER_NOT_HAVE_GROUPID');
        } else {
            $usergroups = unserialize($this->params->get('usergroup'));
            if (isset($usergroups[$userinfo->group_id])) {
                $defaultgroup =& $usergroups[$userinfo->group_id]['defaultgroup'];
                $displaygroup =& $usergroups[$userinfo->group_id]['displaygroup'];
                $titlegroupid = (!empty($displaygroup)) ? $displaygroup : $defaultgroup;
                $usertitle = $this->getDefaultUserTitle($titlegroupid);

                $apidata = array(
                    "existinguser" => $existinguser,
                    "userinfo" => $userinfo,
                    "usergroups" => $usergroups,
                    "usertitle" => $usertitle
                );
                $response = $this->helper->apiCall('updateUsergroup', $apidata);

                if (empty($response['errors'])) {
                    $status['debug'][] = JText::_('GROUP_UPDATE'). ': ' . $existinguser->group_id . ' -> ' . $usergroups[$userinfo->group_id]['defaultgroup'];
                } else {
                    foreach ($response['errors'] AS $index => $error) {
                        $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ' ' . $error;
                    }
                }
            } else {
                $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ' ' . JText::_('ADVANCED_GROUPMODE_MASTERGROUP_NOTEXIST');
            }

            if (!empty($response['debug'])) {
                $status['debug']['api_call'] = $response['debug'];
            }
        }
    }

    /**
     * the user's title based on number of posts
     *
     * @param $groupid
     * @param int $posts
     *
     * @return mixed
     */
    function getDefaultUserTitle($groupid, $posts = 0)
    {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT usertitle FROM #__usergroup WHERE usergroupid = '.$groupid;
        $db->setQuery($query);
        $title = $db->loadResult();

        if (empty($title)) {
            $query = 'SELECT title FROM #__usertitle WHERE minposts <= ' . $posts . ' ORDER BY minposts DESC LIMIT 1';
            $db->setQuery($query);
            $title = $db->loadResult();
        }

        return $title;
    }

    /**
     * @param bool $keepalive
     *
     * @return int
     */
    function syncSessions($keepalive = false)
    {
        $debug = (defined('DEBUG_SYSTEM_PLUGIN') ? true : false);
        if ($debug) {
            JError::raiseNotice('500', 'vbulletin keep alive called');
        }
        $options = array();
        //retrieve the values for vb cookies
        $cookie_prefix = $this->params->get('cookie_prefix');
        $vbversion = $this->helper->getVersion();
        if ((int) substr($vbversion, 0, 1) > 3) {
           if (substr($cookie_prefix, -1) !== '_') {
               $cookie_prefix .= '_';
           }
        }
        $cookie_sessionhash = JRequest::getVar($cookie_prefix . 'sessionhash', '', 'cookie');
        $cookie_userid = JRequest::getVar($cookie_prefix . 'userid', '', 'cookie');
        $cookie_password = JRequest::getVar($cookie_prefix . 'password', '', 'cookie');
        $JUser = JFactory::getUser();
        if (JPluginHelper::isEnabled ( 'system', 'remember' )) {
            jimport('joomla.utilities.utility');
            $hash = JUtility::getHash('JLOGIN_REMEMBER');
            $joomla_persistant_cookie = JRequest::getString($hash, '', 'cookie', JREQUEST_ALLOWRAW | JREQUEST_NOTRIM);
        } else {
            $joomla_persistant_cookie = '';
        }
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT userid FROM #__session WHERE sessionhash = ' . $db->Quote($cookie_sessionhash);
        $db->setQuery($query);
        $session_userid = $db->loadResult();

        if (!$JUser->get('guest', true)) {
            //user logged into Joomla so let's check for an active vb session
            if ($debug) {
                JError::raiseNotice('500', 'Joomla user logged in');
            }

            //find the userid attached to Joomla userid
            $joomla_userid = $JUser->get('id');
            $userlookup = JFusionFunction::lookupUser($this->getJname(), $joomla_userid);
            $vb_userid = (!empty($userlookup)) ? $userlookup->userid : 0;

            //is there a valid VB user logged in?
            $vb_session = ((!empty($cookie_userid) && !empty($cookie_password) && $cookie_userid == $vb_userid) || (!empty($session_userid) && $session_userid == $vb_userid)) ? 1 : 0;

            if ($debug) {
                JError::raiseNotice('400', 'vB session active: ' . $vb_session);
            }

            //create a new session if one does not exist and either keep alive is enabled or a joomla persistent cookie exists
            if (!$vb_session) {
                if ((!empty($keepalive) || !empty($joomla_persistant_cookie))) {
                    if ($debug) {
                        JError::raiseNotice('500', 'vbulletin guest');
                        JError::raiseNotice('500', "cookie_sessionhash = $cookie_sessionhash");
                        JError::raiseNotice('500', "session_userid = $session_userid");
                        JError::raiseNotice('500', "vb_userid = $vb_userid");
                    }
                    //enable remember me as this is a keep alive function anyway
                    $options['remember'] = 1;
                    //get the user's info
                    $query = 'SELECT username, email FROM #__user WHERE userid = '.$userlookup->userid;
                    $db->setQuery($query);
                    $user_identifiers = $db->loadObject();
                    $userinfo = $this->getUser($user_identifiers);
                    //create a new session
                    $status = $this->createSession($userinfo, $options);
                    if ($debug) {
                        JFusionFunction::raiseWarning('500', $status);
                    }
                    //signal that session was changed
                    return 1;
                } else {
                   if ($debug) {
                        JError::raiseNotice('500','keep alive disabled or no persistant session found so calling Joomla\'s destorySession');
                    }
                    $JoomlaUser = JFusionFactory::getUser('joomla_int');

	                $userinfo = new stdClass;
	                $userinfo->id = $JUser->id;
	                $userinfo->username = $JUser->username;
	                $userinfo->name = $JUser->name;
	                $userinfo->email = $JUser->email;
	                $userinfo->block = $JUser->block;
	                $userinfo->activation = $JUser->activation;
	                $userinfo->groups = $JUser->groups;
	                $userinfo->password = $JUser->password;
	                $userinfo->password_clear = $JUser->password_clear;

                    $options['clientid'][] = '0';
                    $status = $JoomlaUser->destroySession($userinfo, $options);
                    if ($debug) {
                        JFusionFunction::raiseWarning('500',$status);
                    }
                }
            } elseif ($debug) {
                JError::raiseNotice('400', 'Nothing done as both Joomla and vB have active sessions.');
            }
        } elseif (!empty($session_userid) || (!empty($cookie_userid) && !empty($cookie_password))) {
            //the user is not logged into Joomla and we have an active vB session

           if ($debug) {
                JError::raiseNotice('500','Joomla has a guest session');
            }

            if (!empty($cookie_userid) && $cookie_userid != $session_userid) {
                $status = $this->destroySession(null, null);
                if ($debug) {
                    JError::raiseNotice('500', 'Cookie userid did not match session userid thus destroyed vB\'s session.');
                    JFusionFunction::raiseWarning('500', $status);
                }
            }

            //find the Joomla user id attached to the vB user
            $userlookup = JFusionFunction::lookupUser($this->getJname(), $session_userid, false);

            if (!empty($joomla_persistant_cookie)) {
               if ($debug) {
                    JError::raiseNotice('500','Joomla persistant cookie found so let Joomla handle renewal');
                }
                return 0;
            } elseif (empty($keepalive)) {
               if ($debug) {
                    JError::raiseNotice('500','Keep alive disabled so kill vBs session');
                }
                //something fishy or user chose not to use remember me so let's destroy vB's session
                $this->destroySession(null, null);
                return 1;
            } elseif ($debug) {
                JError::raiseNotice('500','Keep alive enabled so renew Joomla\'s session');
            }

            if (!empty($userlookup)) {
               if ($debug) {
                    JError::raiseNotice('500','Found a phpBB user so attempting to renew Joomla\'s session.');
                }
                //get the user's info
                $db = JFactory::getDBO();
                $query = 'SELECT username, email FROM #__users WHERE id = '.$userlookup->id;
                $db->setQuery($query);
                $user_identifiers = $db->loadObject();
                $JoomlaUser = JFusionFactory::getUser('joomla_int');
                $userinfo = $JoomlaUser->getUser($user_identifiers);
                if (!empty($userinfo)) {
                    global $JFusionActivePlugin;
                    $JFusionActivePlugin = $this->getJname();
                    $status = $JoomlaUser->createSession($userinfo, $options);
                    if ($debug) {
                        JFusionFunction::raiseWarning('500',$status);
                    }
                    //no need to signal refresh as Joomla will recognize this anyway
                    return 0;
                }
            }
        }
        return 0;
    }

    /**
     * AEC Integration Functions
     *
     * @param array &$current_settings
     *
     * @return array
     */

    function AEC_Settings(&$current_settings)
    {
        $settings = array();
        $settings['vb_notice'] = array('fieldset', 'vB - Notice', 'If it is not enabled below to update a user\'s group upon a plan expiration or subscription, JFusion will use vB\'s advanced group mode setting if enabled to update the group.  Otherwise the user\'s group will not be touched.');
        $settings['vb_block_user'] = array('list_yesno', 'vB - Ban User on Expiration', 'Ban the user in vBulletin on a plan\'s expiration.');
        $settings['vb_block_reason'] = array('inputE', 'vB - Ban Reason', 'Message displayed as the reason the user has been banned.');
        $settings['vb_update_expiration_group'] = array('list_yesno', 'vB - Update Group on Expiration', 'Updates the user\'s usergroup in vB on a plan\'s expiration.');
        $settings['vb_expiration_groupid'] = array('list', 'vB - Expiration Group', 'Group to move the user into upon expiration.');
        $settings['vb_unblock_user'] = array('list_yesno', 'vB - Unban User on Subscription', 'Unbans the user in vBulletin on a plan\'s subscription.');
        $settings['vb_update_subscription_group'] = array('list_yesno', 'vB - Update Group on Subscription', 'Updates the user\'s usergroup in vB on a plan\'s subscription.');
        $settings['vb_subscription_groupid'] = array('list', 'vB - Subscription Group', 'Group to move the user into upon a subscription.');
        $settings['vb_block_user_registration'] = array('list_yesno', 'vB - Ban User on Registration', 'Ban the user in vBulletin when a user registers.  This ensures they do not have access to vB until they subscribe to a plan.');
        $settings['vb_block_reason_registration'] = array('inputE', 'vB - Registration Ban Reason', 'Message displayed as the reason the user has been banned.');

        $admin = JFusionFactory::getAdmin($this->getJname());
        $usergroups = $admin->getUsergroupList();
        array_unshift($usergroups, JHTML::_('select.option', '0', '- Select a Group -', 'id', 'name'));
        $v = (isset($current_settings['vb_expiration_groupid'])) ? $current_settings['vb_expiration_groupid'] : '';
        $settings['lists']['vb_expiration_groupid'] = JHTML::_('select.genericlist', $usergroups,  'vb_expiration_groupid', '', 'id', 'name', $v);
        $v = (isset($current_settings['vb_subscription_groupid'])) ? $current_settings['vb_subscription_groupid'] : '';
        $settings['lists']['vb_subscription_groupid'] = JHTML::_('select.genericlist', $usergroups,  'vb_subscription_groupid', '', 'id', 'name', $v);
        return $settings;
    }

    /**
     * @param $settings
     * @param $request
     * @param $userinfo
     */
    function AEC_expiration_action(&$settings, &$request, $userinfo)
    {
        $status = array();
        $status['error'] = array();
        $status['debug'] = array();
        $status['aec'] = 1;
        $status['block_message'] = $settings['vb_block_reason'];

        $existinguser = $this->getUser($userinfo);
        if (!empty($existinguser)) {
            if ($settings['vb_block_user']) {
                $userinfo->block =  1;
                $this->blockUser($userinfo, $existinguser, $status);
            }

            if ($settings['vb_update_expiration_group'] && !empty($settings['vb_expiration_groupid'])) {
                $usertitle = $this->getDefaultUserTitle($settings['vb_expiration_groupid']);

                $apidata = array(
                	"userinfo" => $userinfo,
                	"existinguser" => $existinguser,
                    "aec" => 1,
                	"aecgroupid" => $settings['vb_expiration_groupid'],
                    "usertitle" => $usertitle
                );
                $response = $this->helper->apiCall('unblockUser', $apidata);

                if (empty($response['errors'])) {
                    $status['debug'][] = JText::_('GROUP_UPDATE'). ': ' . $existinguser->group_id . ' -> ' . $settings['vb_expiration_groupid'];
                } else {
                    foreach ($response['errors'] AS $index => $error) {
                        $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ' ' . $error;
                    }
                }
            } else {
                $this->updateUser($userinfo, 0);
            }
        }
    }

    /**
     * @param $settings
     * @param $request
     * @param $userinfo
     */
    function AEC_action(&$settings, &$request, $userinfo)
    {
        $status = array();
        $status['error'] = array();
        $status['debug'] = array();
        $status['aec'] = 1;

        $existinguser = $this->getUser($userinfo);
        if (!empty($existinguser)) {
            if ($settings['vb_unblock_user']) {
                $userinfo->block =  0;
                $this->unblockUser($userinfo, $existinguser, $status);
            }

            if ($settings['vb_update_subscription_group'] && !empty($settings['vb_subscription_groupid'])) {
                $usertitle = $this->getDefaultUserTitle($settings['vb_subscription_groupid']);

                $apidata = array(
                	"userinfo" => $userinfo,
                	"existinguser" => $existinguser,
                    "aec" => 1,
                	"aecgroupid" => $settings['vb_subscription_groupid'],
                    "usertitle" => $usertitle
                );
                $response = $this->helper->apiCall('unblockUser', $apidata);

                if (empty($response['errors'])) {
                    $status['debug'][] = JText::_('GROUP_UPDATE'). ': ' . $existinguser->group_id . ' -> ' . $settings['vb_subscription_groupid'];
                } else {
                    foreach ($response['errors'] AS $index => $error) {
                        $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ' ' . $error;
                    }
                }
            } else {
                $this->updateUser($userinfo, 0);
            }
        }

        $mainframe = JFactory::getApplication();
        if (!$mainframe->isAdmin()) {
            //login to vB
            $options = array();
            $options['remember'] = 1;
            $this->createSession($existinguser, $options);
        }
    }

    /**
     * @param $settings
     * @param $request
     * @param $userinfo
     */
    function AEC_on_userchange_action(&$settings, &$request, $userinfo)
    {
        //Only do something on registration
        if (strcmp($request->trace, 'registration') === 0) {
            $status = array();
            $status['error'] = array();
            $status['debug'] = array();
            $status['aec'] = 1;
            $status['block_message'] = $settings['vb_block_reason_registration'];
            $existinguser = $this->getUser($userinfo);
            if (!empty($existinguser)) {
                if ($settings['vb_block_user_registration']) {
                    $userinfo->block =  1;
                    $this->blockUser($userinfo, $existinguser, $status);
                }
            }
        }
    }
}