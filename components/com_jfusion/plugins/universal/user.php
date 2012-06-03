<?php

/**
* @package JFusion_universal
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

/**
 * Load the JFusion framework
 */
require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.jfusion.php');
require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.abstractuser.php');
require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.jplugin.php');

require_once(dirname(__FILE__).DS.'map.php');

/**
 * JFusion User Class for universal
 * For detailed descriptions on these functions please check the model.abstractuser.php
 * @package JFusion_universal
 */
class JFusionUser_universal extends JFusionUser {

    /**
     * @param object $userinfo
     * @return null|object
     */
    function getUser($userinfo)
    {
        // initialise some objects
		$map = JFusionMap::getInstance($this->getJname());

		$email = $map->getFieldEmail();
		$username = $map->getFieldUsername();

		//get the identifier
		list($identifier_type,$identifier) = $this->getUserIdentifier($userinfo,$username->field,$email->field);

        $db = JFusionFactory::getDatabase($this->getJname());

		$f = array('USERID','USERNAMEID','USERNAME', 'EMAIL', 'USERNAMEEMAIL', 'REALNAME', 'USERNAMEREALNAME', 'USERNAMEIDREALNAME', 'USERNAMEEMAILREALNAME', 'PASSWORD', 'SALT', 'GROUP', 'ACTIVE', 'INACTIVE','ACTIVECODE','FIRSTNAME','LASTNAME');
		$field = $map->getQuery($f);
//        $query = 'SELECT '.$field.' NULL as reason, a.lastLogin as lastvisit'.
        $query = 'SELECT '.$field.' '.
            'FROM #__'.$map->getTablename().' '.
            'WHERE '.$identifier_type.'=' . $db->Quote($identifier);

        $db->setQuery($query );
        $result = $db->loadObject();

		if ( $result ) {
			if (isset($result->firstname)) {
				$result->name = $result->firstname;
				if (isset($result->lastname)) {
					$result->name .= ' '.$result->lastname;
				}
			}
			$result->block = 0;

			if ( isset($result->inactive) ) {
				$inactive = $map->getFieldType('INACTIVE');
				if ($inactive->value['on'] == $result->inactive ) {
					$result->block = 1;
				}
			}
			if ( isset($result->active) ) {
				$active= $map->getFieldType('ACTIVE');
				if ($active->value['on'] != $result->active ) {
					$result->block = 1;
				}
			}
			unset($result->inactive,$result->active);

			$group = $map->getFieldType('GROUP','group');
			$userid = $map->getFieldType('USERID','group');
			$groupt = $map->getTablename('group');
			if ( !isset($result->group_id) && $group && $userid && $groupt ) {
				$f = array('GROUP');
				$field = $map->getQuery($f,'group');

				$query = 'SELECT '.$field.' '.
						'FROM #__'.$groupt.' '.
						'WHERE '.$userid->field.'=' . $db->Quote($result->userid);
				$db->setQuery($query );
				$result2 = $db->loadObject();
				
				if ($result2) {
					$result->group_id = base64_encode($result2->group_id);
				}
			}
		}
        return $result;
    }

    /**
     * @return string
     */
    function getJname()
    {
        return 'universal';
    }

    /**
     * @param object $userinfo
     * @return array
     */
    function deleteUser($userinfo)
    {
        //setup status array to hold debug info and errors
        $status = array('error' => array(),'debug' => array());

		$map = JFusionMap::getInstance($this->getJname());
		$userid = $map->getFieldUserID();

        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'DELETE FROM #__'.$map->getTablename().' '.
            'WHERE '.$userid->field.'=' . $db->Quote($userinfo->userid);

		$db->setQuery($query);
        if (!$db->query()) {
			$status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' .  $db->stderr();
        } else {
            $group = $map->getFieldType('GROUP','group');
            if ( isset($group) ) {
                $userid = $map->getFieldType('USERID','group');

                $maped = $map->getMap('group');
                $andwhere = '';
                foreach ($maped as $value) {
                    $field = $value->field;
                    switch ($value->type) {
                        case 'DEFAULT':
                            if ( $value->fieldtype == 'VALUE' ) {
                                $andwhere .= ' AND '.$field.' = '.$db->Quote($value->value);
                            }
                            break;
                    }
                }

                $db = JFusionFactory::getDatabase($this->getJname());
                $query = 'DELETE FROM #__'.$map->getTablename('group').' '.
                    'WHERE '.$userid->field.'=' . $db->Quote($userinfo->userid).$andwhere;
                $db->setQuery($query );
                if (!$db->query()) {
                    $status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' .  $db->stderr();
                } else {
                    $status['debug'][] = JText::_('USER_DELETION'). ' ' . $userinfo->username;
                }
            }
        }
		return $status;
    }

    /**
     * @param object $userinfo
     * @param array $options
     * @return array
     */
    function destroySession($userinfo, $options) {
    	$cookie_backup = $_COOKIE;
		$_COOKIE = array();
		$_COOKIE['jfusionframeless'] = true;    	
		$status = JFusionJplugin::destroySession($userinfo, $options,$this->getJname(),'no_brute_force');
		$_COOKIE = $cookie_backup;
        $params = JFusionFactory::getParams($this->getJname());
		JFusionFunction::addCookie($params->get('cookie_name'), '',0,$params->get('cookie_path'),$params->get('cookie_domain'),$params->get('secure'),$params->get('httponly'));
		return $status;
	}

    /**
     * @param object $userinfo
     * @param array $options
     * @return array|string
     */
    function createSession($userinfo, $options) {
		//do not create sessions for blocked users
		if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status = array('error' => array(),'debug' => array());
            $status['error'][] = JText::_('FUSION_BLOCKED_USER');
            return $status;
		}
    	$cookie_backup = $_COOKIE;
		$_COOKIE = array();
		$_COOKIE['jfusionframeless'] = true;
		$status = JFusionJplugin::createSession($userinfo, $options,$this->getJname(),'no_brute_force');
		$_COOKIE = $cookie_backup;
		return $status;
    }

/*
    function filterUsername($username)
    {
        //no username filtering implemented yet
        return $username;
    }
*/
    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function updatePassword($userinfo, &$existinguser, &$status)
    {
		$map = JFusionMap::getInstance($this->getJname());
		$db = JFusionFactory::getDatabase($this->getJname());
		$maped = $map->getMap();
		$params = JFusionFactory::getParams($this->getJname());

		$userid = $map->getFieldUserID();
		$qset = array();

		foreach ($maped as $value) {
			switch ($value->type) {
				case 'PASSWORD':
					if ( isset($userinfo->password_clear) ) {
						$qset[] = $value->field.' = '.$db->quote($map->getValue($value->fieldtype,$userinfo->password_clear,$userinfo));
					} else {
						$qset[] = $value->field.' = '.$db->quote($userinfo->password);
					}
					break;
				case 'SALT':
					if (!isset($userinfo->password_salt)) {
						$qset[] = $value->field.' = '.$db->quote($map->getValue($value->fieldtype,$value->value,$userinfo));
					} else {
						$qset[] = $value->field.' = '.$db->quote($existinguser->password_salt);
		            }
					break;
			}
		}

        $query = 'UPDATE #__'.$map->getTablename().' '.
            'SET '.implode  ( ', '  , $qset  ).' '.
            'WHERE '.$userid->field.'=' . $db->Quote($existinguser->userid);

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
     */
    function updateUsername($userinfo, &$existinguser, &$status)
    {

    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function updateEmail($userinfo, &$existinguser, &$status)
    {
    	$map = JFusionMap::getInstance($this->getJname());
        $params = JFusionFactory::getParams($this->getJname());

		$userid = $map->getFieldUserID();
		$email = $map->getFieldEmail();

        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__'.$map->getTablename().' '.
            'SET '.$email->field.' = '.$db->quote($userinfo->email) .' '.
            'WHERE '.$userid->field.'=' . $db->Quote($existinguser->userid);
        $db->setQuery($query );
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
     */
    function updateUsergroup($userinfo, &$existinguser, &$status)
  	{
    	//get the usergroup and determine if working in advanced or simple mode
    	$usergroups =& $userinfo->reference->usergroup;

    	if(is_array($usergroups)) {
      		//check to see if we have a group_id in the $userinfo, if not return
			if(!isset($userinfo->group_id)) {
				$status['error'][] = JText::_('GROUP_UPDATE_ERROR'). ": " . JText::_('ADVANCED_GROUPMODE_SOURCE_NOT_HAVE_GROUPID');
			} else {
                if(isset($usergroups[$userinfo->group_id])) {
                    $db = JFusionFactory::getDatabase($this->getJname());
                    $map = JFusionMap::getInstance($this->getJname());
                    $params = JFusionFactory::getParams($this->getJname());

                    $userid = $map->getFieldUserID();
                    $group = $map->getFieldType('GROUP');

                    if ( isset($group) ) {
                        $table = $map->getTablename();
                    } else {
                        $table = $map->getTablename('group');
                        $userid = $map->getFieldType('USERID','group');
                        $group = $map->getFieldType('GROUP','group');
                    }

                    $maped = $map->getMap('group');
                    $andwhere = '';
                    if (count($maped) ) {
                        foreach ($maped as $key => $value) {
                            $field = $value->field;
                            switch ($value->type) {
                                case 'DEFAULT':
                                    if ( $value->fieldtype == 'VALUE' ) {
                                        $andwhere .= ' AND '.$field.' = '.$db->Quote($value->value);
                                    }
                                    break;
                            }
                        }
                    }

                    $query = 'UPDATE #__'.$table.' '.
                        'SET '.$group->field.' = '.$db->quote(base64_decode($usergroups[$userinfo->group_id])) .' '.
                        'WHERE '.$userid->field.'=' . $db->Quote($existinguser->userid).$andwhere;
                    $db->setQuery($query );
                    if (!$db->query()) {
                        $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
                    } else {
                        $status['debug'][] = JText::_('GROUP_UPDATE'). ': ' . base64_decode($existinguser->group_id) . ' -> ' . base64_decode($usergroups[$userinfo->group_id]);
                    }
                }
            }
		} else {
			$status['error'][] = JText::_('GROUP_UPDATE_ERROR');
		}
	}

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function blockUser($userinfo, &$existinguser, &$status)
    {
		$map = JFusionMap::getInstance($this->getJname());
		$userid = $map->getFieldUserID();
		$active = $map->getFieldType('ACTIVE');
		$inactive = $map->getFieldType('INACTIVE');

		if ( $userid && ( isset($active) || isset($inactive) ) ) {
            $userStatus = null;
			if ($userinfo->block) {
				if ( isset($inactive) ) {
					$userStatus = $inactive->value['on'];
				}
				if ( isset($active) ) {
					$userStatus = $active->value['off'];
				}
			} else {
				if ( isset($inactive) ) {
					$userStatus = $inactive->value['off'];	
				}
				if ( isset($active) ) {
					$userStatus = $active->value['on'];
				}
			}
            if ($userStatus != null) {
                $db = JFusionFactory::getDatabase($this->getJname());
                $query = 'UPDATE #__'.$map->getTablename().' '.
                    'SET '.$active->field.' = '. $db->Quote($userStatus) .' '.
                    'WHERE '.$userid->field.'=' . $db->Quote($existinguser->userid);
                $db->setQuery($query );
                if (!$db->query()) {
                    $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
                } else {
                    $status['debug'][] = JText::_('ACTIVATION_UPDATE'). ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
                }
            }
		}
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function unblockUser($userinfo, &$existinguser, &$status)
    {
		$map = JFusionMap::getInstance($this->getJname());
		$userid = $map->getFieldUserID();
		$active = $map->getFieldType('ACTIVE');
		$inactive = $map->getFieldType('INACTIVE');

		if ( $userid && ( $active || $inactive ) ) {
            $userStatus = null;
			if ( isset($inactive) ) $userStatus = $inactive->value['off'];
			if ( isset($active) ) $userStatus = $active->value['on'];

			$db = JFusionFactory::getDatabase($this->getJname());
			$query = 'UPDATE #__'.$map->getTablename().' '.
					'SET '.$active->field.' = '. $db->Quote($userStatus) .' '.
					'WHERE '.$userid->field.'=' . $db->Quote($existinguser->userid);
			$db->setQuery($query );
			if (!$db->query()) {
				$status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
	        } else {
				$status['debug'][] = JText::_('BLOCK_UPDATE'). ': ' . $existinguser->block . ' -> ' . $userinfo->block;
	        }
		}
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function activateUser($userinfo, &$existinguser, &$status)
    {
		$map = JFusionMap::getInstance($this->getJname());
		$userid = $map->getFieldUserID();
		$activecode = $map->getFieldType('ACTIVECODE');

		if ( $userid && $activecode ) {
			$db = JFusionFactory::getDatabase($this->getJname());
			$query = 'UPDATE #__'.$map->getTablename().' '.
					'SET '.$activecode->field.' = '. $db->Quote($userinfo->activation) .' '.
					'WHERE '.$userid->field.'=' . $db->Quote($existinguser->userid);
			$db->setQuery($query );
	        if (!$db->query()) {
	            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
	        } else {
	          $status['debug'][] = JText::_('ACTIVATION_UPDATE'). ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
	        }
		}
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function inactivateUser($userinfo, &$existinguser, &$status)
    {
		$map = JFusionMap::getInstance($this->getJname());
		$userid = $map->getFieldUserID();
		$activecode = $map->getFieldType('ACTIVECODE');

		if ( $userid && $activecode ) {
			$db = JFusionFactory::getDatabase($this->getJname());
			$query = 'UPDATE #__'.$map->getTablename().' '.
					'SET '.$activecode->field.' = '. $db->Quote($userinfo->activation) .' '.
					'WHERE '.$userid->field.'=' . $db->Quote($existinguser->userid);
			$db->setQuery($query );
	        if (!$db->query()) {
	            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
	        } else {
	          $status['debug'][] = JText::_('ACTIVATION_UPDATE'). ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
	        }
		}
    }

    /**
     * @param object $userinfo
     * @param array $status
     */
    function createUser($userinfo, &$status)
    {
	    $params = JFusionFactory::getParams($this->getJname());
		//get the default user group and determine if we are using simple or advanced
		$usergroups =& $userinfo->reference->usergroup;
	    //check to make sure that if using the advanced group mode, $userinfo->group_id exists
		if(is_array($usergroups) && !isset($userinfo->group_id)) {
			$status['error'][] = JText::_('GROUP_UPDATE_ERROR'). ": " . JText::_('ADVANCED_GROUPMODE_SOURCE_NOT_HAVE_GROUPID');
		} else {
            $map = JFusionMap::getInstance($this->getJname());

            $userid = $map->getFieldUserID();
            if(empty($userid)) {
                $status['error'][] = JText::_('USER_CREATION_ERROR'). ': ' . JText::_('UNIVERSAL_NO_USERID_SET'). ': ' . $this->getJname();
            } else {
                $password = $map->getFieldType('PASSWORD');
                if(empty($password)) {
                    $status['error'][] = JText::_('USER_CREATION_ERROR'). ': ' . JText::_('UNIVERSAL_NO_PASSWORD_SET'). ': ' . $this->getJname();
                } else {
                    $email = $map->getFieldEmail();
                    if(empty($email)) {
                        $status['error'][] = JText::_('USER_CREATION_ERROR'). ': ' . $this->getJname() . ': ' . JText::_('UNIVERSAL_NO_EMAIL_SET');
                    } else {
                        $user = new stdClass;
                        $maped = $map->getMap();
                        foreach ($maped as $key => $value) {
                            $field = $value->field;
                            switch ($value->type) {
                                case 'IGNORE':
                                    break;
                                case 'USERID':
                                    $user->$field = NULL;
                                    break;
                                case 'REALNAME':
                                    $user->$field = $userinfo->name;
                                    break;
                                case 'FIRSTNAME':
                                    list($firstname,$lastname) = explode(' ',$userinfo->name ,2);
                                    $user->$field = $firstname;
                                    break;
                                case 'LASTNAME':
                                    list($firstname,$lastname) = explode(' ',$userinfo->name ,2);
                                    $user->$field = $lastname;
                                    break;
                                case 'GROUP':
                                    $user->$field = (is_array($usergroups)) ? base64_decode($usergroups[$userinfo->group_id]) : $usergroups;;
                                    break;
                                case 'USERNAME':
                                case 'USERNAMEID':
                                case 'USERNAMEREALNAME':
                                case 'USERNAMEIDREALNAME':
                                    $user->$field = $userinfo->username;
                                    break;
                                case 'EMAIL':
                                case 'USERNAMEEMAIL':
                                case 'USERNAMEEMAILREALNAME':
                                    $user->$field = $userinfo->email;
                                    break;
                                case 'ACTIVE':
                                    if ($userinfo->block){
                                        $user->$field = $value->value['off'];
                                    } else {
                                        $user->$field = $value->value['on'];
                                    }
                                    break;
                                case 'INACTIVE':
                                    if ($userinfo->block){
                                        $user->$field = $value->value['on'];
                                    } else {
                                        $user->$field = $value->value['off'];
                                    }
                                    break;
                                case 'PASSWORD':
                                    if ( isset($userinfo->password_clear) ) {
                                        $user->$field = $map->getValue($value->fieldtype,$userinfo->password_clear,$userinfo);
                                    } else {
                                        $user->$field = $userinfo->password;
                                    }
                                    break;
                                case 'SALT':
                                    if (!isset($userinfo->password_salt)) {
                                        $user->$field = $map->getValue($value->fieldtype,$value->value,$userinfo);
                                    } else {
                                        $user->$field = $userinfo->password_salt;
                                    }
                                    break;
                                case 'DEFAULT':
                                    $val = isset($value->value) ? $value->value : null;
                                    $user->$field = $map->getValue($value->fieldtype,$val,$userinfo);
                                    break;
                            }
                        }

                        $db = JFusionFactory::getDatabase($this->getJname());
                        //now append the new user data
                        if (!$db->insertObject('#__'.$map->getTablename(), $user, $userid->field )) {
                            //return the error
                            $status['error'] = JText::_('USER_CREATION_ERROR'). ': ' . $db->stderr();
                        } else {
                            $group = $map->getFieldType('GROUP');

                            if ( !isset($group) ) {
                                $groupuserid = $map->getFieldType('USERID','group');
                                if( isset($groupuserid) ) {
                                    $addgroup = new stdClass;

                                    $maped = $map->getMap('group');
                                    foreach ($maped as $key => $value) {
                                        $field = $value->field;
                                        switch ($value->type) {
                                            case 'USERID':
                                                $field2 = $userid->field;
                                                $addgroup->$field = $user->$field2;
                                                break;
                                            case 'GROUP':
                                                $addgroup->$field = (is_array($usergroups)) ? base64_decode($usergroups[$userinfo->group_id]) : $usergroups;
                                                break;
                                            case 'DEFAULT':
                                                $addgroup->$field = $map->getValue($value->fieldtype,$value->value,$userinfo);
                                                break;
                                        }
                                    }
                                    if (!$db->insertObject('#__'.$map->getTablename('group'), $addgroup, $groupuserid->field )) {
                                        //return the error
                                        $status['error'] = JText::_('USER_CREATION_ERROR'). ': ' . $db->stderr();
                                        return;
                                    }
                                }
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
}
