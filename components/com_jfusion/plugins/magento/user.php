<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Magento
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion User Class for Magento 1.1
 * For detailed descriptions on these functions please check the model.abstractuser.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Magento
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionUser_magento extends JFusionUser {
	/**
	 * Magento does not have usernames.
	 *  The user is identified by an 'identity_id' that is found through the users e-mail address.
	 *  To make it even more difficult for us, there is no simple tablecontaining all userdata, but
	 *  the userdata is arranged in tables for different variable types.
	 *  User attributes are identified by fixed attribute ID's in these tables
	 *
	 *  The usertables are:
	 *  customer_entity
	 *  customer_address_entity
	 *  customer_address_entity_datetime
	 *  customer_address_entity_decimal
	 *  customer_address_entity_int
	 *  customer_address_entity_text
	 *  customer_address_entity_varchar
	 *  customer_entity_datetime
	 *  customer_entity_decimal
	 *  customer_entity_int
	 *  customer_entity_text
	 *  customer_entity_varchar
	 *
	 * @param $status
	 *
	 * @throws RuntimeException
	 * @return MagentoSoapClient
	 */
	function connectToApi(&$status) {
		$apipath = $this->params->get('source_url') . 'index.php/api/?wsdl';
		$apiuser = $this->params->get('apiuser','');
		$apikey = $this->params->get('apikey','');
		if (!$apiuser || !$apikey) {
			throw new RuntimeException('Could not login to Magento API (empty apiuser and/or apikey)');
		} else {
			try {
				require_once JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'soapclient.php';

				$proxi = new MagentoSoapClient($apipath);
				if($proxi->login($apiuser, $apikey)) {
					$status['debug'][] = 'Logged into Magento API as ' . $apiuser . ' using key, message:' . $apikey;
				}
			} catch (Soapfault $fault) {
				/** @noinspection PhpUndefinedFieldInspection */
				throw new RuntimeException('Could not login to Magento API as ' . $apiuser . ' using key ' . $apikey . ',message:' . $fault->faultstring);
			}
		}
		return $proxi;
	}

	/**
	 * Returns an array of Magento entity types
	 *
	 * @param $eav_entity_code
	 *
	 * @return bool
	 */
	function getMagentoEntityTypeID($eav_entity_code) {
		static $eav_entity_types;
		try {
			if (!isset($eav_entity_types)) {
				$db = JFusionFactory::getDataBase($this->getJname());

				$query = $db->getQuery(true)
					->select('entity_type_id, entity_type_code')
					->from('#__eav_entity_type');

				$db->setQuery($query);

				$result = $db->loadObjectList();
				for ($i = 0;$i < count($result);$i++) {
					$eav_entity_types[$result[$i]->entity_type_code] = $result[$i]->entity_type_id;
				}
			}
			return $eav_entity_types[$eav_entity_code];
		} catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
			return false;
		}
	}

	/**
	 * Returns a Magento UserObject for the current installation
	 * (see eav_entity_type)
	 * please note, this is all my coding, so please report bugs to me, not to the Magento developers
	 *
	 * @author henk wevers
	 *
	 * @param $entity_type_code
	 *
	 * @return bool|array
	 */
	function getMagentoDataObjectRaw($entity_type_code) {
		static $eav_attributes;
		try {
			if (!isset($eav_attributes[$entity_type_code])) {
				// first get the entity_type_id to access the attribute table
				$entity_type_id = $this->getMagentoEntityTypeID('customer');
				$db = JFusionFactory::getDataBase($this->getJname());
				// Get a database object
				$query = $db->getQuery(true)
					->select('attribute_id, attribute_code, backend_type')
					->from('#__eav_attribute')
					->where('entity_type_id = ' . (int)$entity_type_id);

				$db->setQuery($query);
				//getting the results
				$result = $db->loadObjectList();
				for ($i = 0;$i < count($result);$i++) {
					$eav_attributes[$entity_type_code][$i]['attribute_code'] = $result[$i]->attribute_code;
					$eav_attributes[$entity_type_code][$i]['attribute_id'] = $result[$i]->attribute_id;
					$eav_attributes[$entity_type_code][$i]['backend_type'] = $result[$i]->backend_type;
				}
			}
			return $eav_attributes[$entity_type_code];
		} catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
			return false;
		}

	}

	/**
	 * @param $entity_type_code
	 *
	 * @return array
	 */
	function getMagentoDataObject($entity_type_code) {
		$result = $this->getMagentoDataObjectRaw($entity_type_code);
		$dataObject = array();
		for ($i = 0;$i < count($result);$i++) {
			$dataObject[$result[$i]['attribute_code']]['attribute_id'] = $result[$i]['attribute_id'];
			$dataObject[$result[$i]['attribute_code']]['backend_type'] = $result[$i]['backend_type'];
		}
		return $dataObject;
	}

	/**
	 * @param $entity_type_code
	 * @param $entity_id
	 * @param $entity_type_id
	 *
	 * @return array|bool
	 */
	function fillMagentoDataObject($entity_type_code, $entity_id, $entity_type_id) {
		$result = array();
		try {
			$result = $this->getMagentoDataObjectRaw($entity_type_code);
			if ($result) {
				// walk through the array and fill the object requested
				/**
				 * @TODO This can be smarter by reading types at once and put the data them in the right place
				 *       for now I'm trying to get this working. optimising comes next
				 */
				$filled_object = array();
				$db = JFusionFactory::getDataBase($this->getJname());
				for ($i = 0;$i < count($result);$i++) {
					$query = $db->getQuery(true)
						->where('entity_id = ' . (int)$entity_id)
						->where('entity_type_id = ' . (int)$entity_type_id);

					if ($result[$i]['backend_type'] == 'static') {
						$query->select($result[$i]['attribute_code'])
							->from('#__' . $entity_type_code . '_entity');
					} else {
						$query->select('value')
							->from('#__' . $entity_type_code . '_entity_' . $result[$i]['backend_type']);
					}
					$db->setQuery($query);

					$filled_object[$result[$i]['attribute_code']]['value'] = $db->loadResult();
					$filled_object[$result[$i]['attribute_code']]['attribute_id'] = $result[$i]['attribute_id'];
					$filled_object[$result[$i]['attribute_code']]['backend_type'] = $result[$i]['backend_type'];
				}
				$result = $filled_object;
			}
		} catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
		}
		return $result;
	}
	/**
	 * @param object $userinfo
	 * @return null|object
	 */
	function getUser($userinfo) {
		$identifier = $userinfo;
		if (is_object($userinfo)) {
			$identifier = $userinfo->email;
		}

		// Get the user id
		$db = JFusionFactory::getDataBase($this->getJname());

		$query = $db->getQuery(true)
			->select('entity_id')
			->from('#__customer_entity')
			->where('email = ' . $db->Quote($identifier));

		$db->setQuery($query);
		$entity = (int)$db->loadResult();
		// check if we have found the user, if not return failure
		$instance = null;
		if ($entity) {
			// Return a Magento customer array
			$magento_user = $this->fillMagentoDataObject('customer', $entity, 1);
			if ($magento_user) {
				// get the static data also
				$query = $db->getQuery(true)
					->select('email, group_id, created_at, updated_at, is_active')
					->from('#__customer_entity')
					->where('entity_id = ' . $db->Quote($entity));

				$db->setQuery($query);
				$result = $db->loadObject();
				if ($result) {
					$instance = new stdClass;
					$instance->group_id = $result->group_id;
					if ($instance->group_id == 0) {
						$instance->group_name = 'Default Usergroup';
					} else {
						$query = $db->getQuery(true)
							->select('customer_group_code')
							->from('#__customer_group')
							->where('customer_group_id = ' . $result->group_id);

						$db->setQuery($query);
						$instance->group_name = $db->loadResult();
					}
					$instance->groups = array($instance->group_id);
					$instance->groupnames = array($instance->group_name);

					$magento_user['email']['value'] = $result->email;
					$magento_user['created_at']['value'] = $result->created_at;
					$magento_user['updated_at']['value'] = $result->updated_at;
                    $is_active = $result->is_active; //TO DO: have to figure out what theirs means
					$instance->userid = $entity;
					$instance->username = $magento_user['email']['value'];
					$name = $magento_user['firstname']['value'];
					if ($magento_user['middlename']['value']) {
						$name = $name . ' ' . $magento_user['middlename']['value'];
					}
					if ($magento_user['lastname']['value']) {
						$name = $name . ' ' . $magento_user['lastname']['value'];
					}
					$instance->name = $name;
					$instance->email = $magento_user['email']['value'];
					$password = $magento_user['password_hash']['value'];
					$hashArr = explode(':', $password);
					$instance->password = $hashArr[0];
					if (!empty($hashArr[1])) {
						$instance->password_salt = $hashArr[1];
					}
					$instance->activation = '';
					if ($magento_user['confirmation']['value']) {
						$instance->activation = $magento_user['confirmation']['value'];
					}
					$instance->registerDate = $magento_user['created_at']['value'];
					$instance->lastvisitDate = $magento_user['updated_at']['value'];
					if ($instance->activation) {
						$instance->block = 1;
					} else {
						$instance->block = 0;
					}
				}
			}
		}
		return $instance;
	}
	/**
	 * returns the name of this JFusion plugin
	 * @return string name of current JFusion plugin
	 */
	function getJname()
	{
		return 'magento';
	}

	/**
	 * @param object $userinfo
	 * @param array $options
     *
	 * @return array
	 */
	function destroySession($userinfo, $options) {
		return $this->curlLogout($userinfo, $options,$this->params->get('logout_type'));
	}

	/**
	 * @param object $userinfo
	 * @param array $options
     *
	 * @return array|string
	 */
	function createSession($userinfo, $options) {
		$status = array('error' => array(),'debug' => array());
		if (!empty($userinfo->block) || !empty($userinfo->activation)) {
			$status['error'][] = JText::_('FUSION_BLOCKED_USER');
		} else {
			$status = $this->curlLogin($userinfo, $options, $this->params->get('brute_force'));
		}
		return $status;
	}
	/**
	 * @param $len
	 * @param null $chars
	 * @return string
	 */
	function getRandomString($len, $chars = null) {
		if (is_null($chars)) {
			$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		}
		mt_srand(10000000 * (double)microtime());
		for ($i = 0, $str = '', $lc = strlen($chars) - 1;$i < $len;$i++) {
			$str.= $chars[mt_rand(0, $lc) ];
		}
		return $str;
	}

	/**
	 * @param string $username
	 * @return string
	 */
	function filterUsername($username) {
		//no username filtering implemented yet
		return $username;
	}

	/**
	 * @param $user
	 * @param $entity_id
	 * @return bool
	 */
	function update_create_Magentouser($user, $entity_id) {
		try {
			$db = JFusionFactory::getDataBase($this->getJname());
			$sqlDateTime = date('Y-m-d H:i:s', time());
			// transactional handling of this update is a necessarily
			if (!$entity_id) { //create an (almost) empty user
				// first get the current increment
				// This method is an empty implemented method into the core of joomla database class
				// So, we need to implement it for our purpose that's why there is a new factory for magento
				$db->transactionStart();
				$query = $db->getQuery(true)
					->select('increment_last_id')
					->from('#__eav_entity_store')
					->where('entity_type_id = ' . (int)$this->getMagentoEntityTypeID('customer'))
					->where('store_id = 0');

				$db->setQuery($query);
				$db->execute();

				$increment_last_id_int = ( int )$db->loadresult();
				$increment_last_id = sprintf("%'09u", ($increment_last_id_int + 1));

				$query = $db->getQuery(true)
					->update('#__eav_entity_store')
					->set('increment_last_id = '.$db->quote($increment_last_id))
					->where('entity_type_id = ' . (int)$this->getMagentoEntityTypeID('customer'))
					->where('store_id = 0');

				$db->setQuery($query);
				$db->execute();

				$entry = new stdClass;
				$entry->entity_type_id = (int)$this->getMagentoEntityTypeID('customer');
				$entry->increment_id = $increment_last_id;
				$entry->is_active = 1;
				$entry->created_at = $sqlDateTime;
				$entry->updated_at = $sqlDateTime;

				$db->insertObject('#__customer_entity', $entry);
				// so far so good, now create an empty user, to be updates later
				$entity_id = $db->insertid();
			} else { // we are updating
				$query = $db->getQuery(true)
					->update('#__customer_entity')
					->set('updated_at = '.$db->quote($sqlDateTime))
					->where('entity_id = ' . (int)$entity_id);

				$db->setQuery($query);
				$db->execute();
			}
			// the basic userrecord is created, now update/create the eav records
			for ($i = 0;$i < count($user);$i++) {
				if ($user[$i]['backend_type'] == 'static') {
					if (isset($user[$i]['value'])) {
						$query = $db->getQuery(true)
							->update('#__customer_entity')
							->set($user[$i]['attribute_code']. ' = ' . $db->Quote($user[$i]['value']))
							->where('entity_id = ' . (int)$entity_id);

						$db->setQuery($query);
						$db->execute();
					}
				} else {
					if (isset($user[$i]['value'])) {
						$query = $db->getQuery(true)
							->select('value')
							->from('#__customer_entity' . '_' . $user[$i]['backend_type'])
							->where('entity_id = ' . (int)$entity_id)
							->where('entity_type_id = ' . (int)$this->getMagentoEntityTypeID('customer'))
							->where('attribute_id = ' . (int)$user[$i]['attribute_id']);

						$db->setQuery($query);
						$db->execute();
						$result = $db->loadresult();

						if ($result) {
							// we do not update an empty value, but remove the record instead
							if ($user[$i]['value'] == '') {
								$query = $db->getQuery(true)
									->delete('#__customer_entity' . '_' . $user[$i]['backend_type'])
									->where('entity_id = ' .  (int)$entity_id)
									->where('entity_type_id = ' .  (int)$this->getMagentoEntityTypeID('customer'))
									->where('attribute_id = ' .  (int)$user[$i]['attribute_id']);
							} else {
								$query = $db->getQuery(true)
									->update('#__customer_entity'. '_' . $user[$i]['backend_type'])
									->set('value = ' . $db->Quote($user[$i]['value']))
									->where('entity_id = ' . (int)$entity_id)
									->where('entity_type_id = ' . (int)$this->getMagentoEntityTypeID('customer'))
									->where('attribute_id = ' . (int)$user[$i]['attribute_id']);

							}
						} else { // must create
							$entry = new stdClass;
							$entry->value = $user[$i]['value'];
							$entry->attribute_id = $user[$i]['attribute_id'];
							$entry->entity_id = $entity_id;
							$entry->entity_type_id = (int)$this->getMagentoEntityTypeID('customer');

							$db->insertObject('#__customer_entity' . '_' . $user[$i]['backend_type'], $entry);
						}
						$db->setQuery($query);
						$db->execute();
					}
				}
			}
			// Change COMMIT TRANSACTION to COMMIT - This last is used in mysql but in fact it depends of the database system
			$db->transactionCommit();
			$result = false;
		} catch (Exception $e) {
			if (isset($db)) {
				$db->transactionRollback();
			}
			$result = $e->getMessage();
		}
		return $result; //NOTE false is NO ERRORS!
	}

	/**
	 * @param $Magento_user
	 * @param $attribute_code
	 * @param $value
	 */
	function fillMagentouser(&$Magento_user, $attribute_code, $value) {
		for ($i = 0;$i < count($Magento_user);$i++) {
			if ($Magento_user[$i]['attribute_code'] == $attribute_code) {
				$Magento_user[$i]['value'] = $value;
			}
		}
	}

	/**
	 * @param object $userinfo
	 * @param array $status
	 *
	 * @return void
	 */
	function createUser($userinfo, &$status) {
		$magentoVersion = $this->params->get('magento_version','1.7');

		$usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(), $userinfo);
		if (empty($usergroups)) {
			$status['error'][] = JText::_('ERROR_CREATING_USER') . ': ' . JText::_('USERGROUP_MISSING');
		} else {
			$usergroup = $usergroups[0];
			$db = JFusionFactory::getDataBase($this->getJname());
			//prepare the variables
			// first get some default stuff from Magento
/*
			$query = $db->getQuery(true)
				->select('default_group_id')
				->from('#__core_website')
				->where('is_default = 1');
*/

			//        $default_group_id = (int) $db->loadResult();

			$query = $db->getQuery(true)
				->select('default_store_id')
				->from('#__core_store_group')
				->where('group_id = ' . (int)$usergroup);

			$db->setQuery($query);
			$default_store_id = (int)$db->loadResult();

			$query = $db->getQuery(true)
				->select('name, website_id')
				->from('#__core_store')
				->where('store_id = ' . (int)$default_store_id);

			$db->setQuery($query);
			$result = $db->loadObject();
			$default_website_id = (int)$result->website_id;
			$default_created_in_store = $result->name;
			$magento_user = $this->getMagentoDataObjectRaw('customer');
			if ($userinfo->activation) {
				$this->fillMagentouser($magento_user, 'confirmation', $userinfo->activation);
			}
			$this->fillMagentouser($magento_user, 'created_in', $default_created_in_store);
			$this->fillMagentouser($magento_user, 'email', $userinfo->email);
			$parts = explode(' ', $userinfo->name);
			$this->fillMagentouser($magento_user, 'firstname', $parts[0]);
			if (count($parts) > 1) {
				$this->fillMagentouser($magento_user, 'lastname', $parts[(count($parts) - 1) ]);
			} else {
				// Magento needs Firstname AND Lastname, so add a dot when lastname is empty
				$this->fillMagentouser($magento_user, 'lastname', '.');
			}
			$middlename = '';
			for ($i = 1;$i < (count($parts) - 1);$i++) {
				$middlename = $middlename . ' ' . $parts[$i];
			}
			if ($middlename) {
				$this->fillMagentouser($magento_user, 'middlename', $middlename);
			}

			if (version_compare($magentoVersion,'1.8','<')) {
				if (isset($userinfo->password_clear) && strlen($userinfo->password_clear) != 32) {
					$password_salt = $this->getRandomString(2);
					$this->fillMagentouser($magento_user, 'password_hash', md5($password_salt . $userinfo->password_clear) . ':' . $password_salt);
				} else {
					if (!empty($userinfo->password_salt)) {
						$this->fillMagentouser($magento_user, 'password_hash', $userinfo->password . ':' . $userinfo->password_salt);
					} else {
						$this->fillMagentouser($magento_user, 'password_hash', $userinfo->password);
					}
				}
			} else {
				if (isset($userinfo->password_clear) && strlen($userinfo->password_clear) != 32) {
					$password_salt = $this->getRandomString(32);
					$this->fillMagentouser($magento_user, 'password_hash', hash('sha256',$password_salt . $userinfo->password_clear) . ':' . $password_salt);
				} else {
					if (!empty($userinfo->password_salt)) {
						$this->fillMagentouser($magento_user, 'password_hash', $userinfo->password . ':' . $userinfo->password_salt);
					} else {
						$this->fillMagentouser($magento_user, 'password_hash', $userinfo->password);
					}
				}
				
			}

			/*     $this->fillMagentouser($magento_user,'prefix','');
			 $this->fillMagentouser($magento_user,'suffix','');
			$this->fillMagentouser($magento_user,'taxvat','');
			*/
			$this->fillMagentouser($magento_user, 'group_id', $usergroup);
			$this->fillMagentouser($magento_user, 'store_id', $default_store_id);
			$this->fillMagentouser($magento_user, 'website_id', $default_website_id);
			//now append the new user data
			$errors = $this->update_create_Magentouser($magento_user, 0);
			if ($errors) {
				$status['error'][] = JText::_('USER_CREATION_ERROR') . $errors;
			} else {
				//return the good news
				$status['debug'][] = JText::_('USER_CREATION');
				$status['userinfo'] = $this->getUser($userinfo);
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
	function updatePassword($userinfo, $existinguser, &$status) {
		$magentoVersion = $this->params->get('magento_version','1.7');

		$magento_user = $this->getMagentoDataObjectRaw('customer');
		if (version_compare($magentoVersion,'1.8','<')) {
			$password_salt = $this->getRandomString(2);
			$this->fillMagentouser($magento_user, 'password_hash', md5($password_salt . $userinfo->password_clear) . ':' . $password_salt);
		} else {
			$password_salt = $this->getRandomString(32);
			$this->fillMagentouser($magento_user, 'password_hash', hash('sha256',$password_salt . $userinfo->password_clear) . ':' . $password_salt);
		}
		$errors = $this->update_create_Magentouser($magento_user, $existinguser->userid);
		if ($errors) {
			$status['error'][] = JText::_('PASSWORD_UPDATE_ERROR');
		} else {
			$status['debug'][] = JText::_('PASSWORD_UPDATE') . $existinguser->password;
		}
	}

	/**
     * @TODO update username code
     *
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array $status
	 *
	 * @return void
	 */
	function updateUsername($userinfo, &$existinguser, &$status) {
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array $status
	 *
	 * @return void
	 */
	function activateUser($userinfo, &$existinguser, &$status) {
		$magento_user = $this->getMagentoDataObjectRaw('customer');
		$this->fillMagentouser($magento_user, 'confirmation', '');
		$errors = $this->update_create_Magentouser($magento_user, $existinguser->userid);
		if ($errors) {
			$status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR');
		} else {
			$status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
		}
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array $status
	 *
	 * @return void
	 */
	function inactivateUser($userinfo, &$existinguser, &$status) {
		$magento_user = $this->getMagentoDataObjectRaw('customer');
		$this->fillMagentouser($magento_user, 'confirmation', $userinfo->activation);
		$errors = $this->update_create_Magentouser($magento_user, $existinguser->userid);
		if ($errors) {
			$status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR');
		} else {
			$status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
		}
	}

	/**
	 * @param object $userinfo
	 *
	 * @return array
	 */
	function deleteUser($userinfo) {
		//setup status array to hold debug info and errors
		$status = array('error' => array(),'debug' => array());
		//set the userid
		//check to see if a valid $userinfo object was passed on
		if (!is_object($userinfo)) {
			$status['error'][] = JText::_('NO_USER_DATA_FOUND');
		} else {
			$existinguser = $this->getUser($userinfo);
			if (!empty($existinguser)) {
				$user_id = $existinguser->userid;
				// this can be complicated so we are going to use the Magento customer API
				// for the time being. Speed is not a great issue here
				// connect to host
				try {
					$proxi = $this->connectToApi($status);

					try {
						$proxi->call('customer.delete', $user_id);
						$status['debug'][] = 'Magento API: Delete user with id '.$user_id.' , email ' . $userinfo->email;
					} catch (Soapfault $fault) {
						/** @noinspection PhpUndefinedFieldInspection */
						$status['error'][] = 'Magento API: Could not delete user with id '.$user_id.' , message: ' . $fault->faultstring;
					}

					try {
						$proxi->endSession();
					} catch (Soapfault $fault) {
						/** @noinspection PhpUndefinedFieldInspection */
						$status['error'][] = 'Magento API: Could not end this session, message: ' . $fault->faultstring;
					}
				} catch (Exception $e) {
					$status['error'][] = $e->getMessage();
				}
			}
		}
		return $status;
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array $status
	 * @param $jname
	 *
	 * @return void
	 */
	function updateEmail($userinfo, &$existinguser, &$status, $jname) {
		//set the userid
		$user_id = $existinguser->userid;
		$new_email = $userinfo->email;
		$update = array('email' => $new_email);
		// this can be complicated so we are going to use the Magento customer API
		// for the time being. Speed is not a great issue here
		// connect to host
		try {
			$proxi = $this->connectToApi($status);

			try {
				$proxi->call('customer.update', array($user_id, $update));
			} catch (Soapfault $fault) {
				/** @noinspection PhpUndefinedFieldInspection */
				$status['error'][] = 'Magento API: Could not update email of user with id '.$user_id.' , message: ' . $fault->faultstring;
			}
			try {
				$proxi->endSession();
			} catch (Soapfault $fault) {
				/** @noinspection PhpUndefinedFieldInspection */
				$status['error'][] = 'Magento API: Could not end this session, message: ' . $fault->faultstring;
			}
		} catch (Exception $e) {
			$status['error'][] = $e->getMessage();
		}
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array $status
	 *
	 * @return void
	 */
	function updateUsergroup($userinfo, &$existinguser, &$status) {
		try {
			$usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(), $userinfo);
			if (empty($usergroups)) {
				throw new RuntimeException(JText::_('USERGROUP_MISSING'));
			} else {
				$usergroup = $usergroups[0];
				//set the usergroup in the user table
				$db = JFusionFactory::getDataBase($this->getJname());

				$query = $db->getQuery(true)
					->update('#__customer_entity')
					->set('group_id = ' . (int)$usergroup)
					->where('entity_id = ' . (int)$existinguser->userid);

				$db->setQuery($query);
				$db->execute();
				$status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode (' , ', $existinguser->groups) . ' -> ' . $usergroup;
			}
		} catch (Exception $e) {
			$status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . $e->getMessage();
		}
	}
}