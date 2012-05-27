<?php
/**
 * @package JFusion
 * @subpackage Models
 * @author JFusion development team -- Morten Hundevad
 * @copyright Copyright (C) 2008 JFusion -- Morten Hundevad. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */
if ( !class_exists('JFusionMap') ) {
    /**
     * JFusionMap class
     *
     * @category   JFusion
     * @package    Model
     * @subpackage JFusionMap
     * @author     JFusion Team <webmaster@jfusion.org>
     * @copyright  2008 JFusion. All rights reserved.
     * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
     * @link       http://www.jfusion.org
     */
	class JFusionMap {
		var $_jname = null;
		var $_map = array();
		var $_mapraw = null;

        /**
         * @param $jname
         */
        function JFusionMap($jname)
		{
		    $this->_jname = $jname;
		}

        /**
         * @param string $type
         * @return mixed
         */
        function getTablename($type='user')
	    {
			$map = JFusionMap::getInstance($this->getJname());

			$maped = $map->getMapRaw($type);

			return $maped['table'];
	    }

        /**
         * @return null
         */
        function getJname()
	    {
	        return $this->_jname;
	    }

        public static function &getInstance( $jname )
		{
			static $instances;

			if (!isset( $instances[$jname] )) {
				$instance	= new JFusionMap($jname);
				$instances[$jname] = & $instance;
			}
			return $instances[$jname];
		}

		function getMapRaw($type='user') {
			if( !is_array($this->_mapraw) ) {
		        $params = JFusionFactory::getParams($this->getJname());
		        $map = $params->get('map');
		        if(is_array($map)) {
					$this->_mapraw = $map;
				}
			}
			if( is_array($this->_mapraw) ) {
				if( isset($this->_mapraw[$type]) && is_array($this->_mapraw[$type]) ) {
					return $this->_mapraw[$type];
				}
			}
            return false;
		}

		function getMap($type='user') {
			if( empty($this->_map) ) {
		        $params = JFusionFactory::getParams($this->getJname());
		        $map = $params->get('map');
		        if(is_array($map)) {
					$this->_map = $map;
					$temp = array();
					foreach ($this->_map as $keytype => $value) {
						$temp[$keytype] = array();
						if( isset($value['field']) ) {
							foreach ($value['field'] as $key => $val) {
								$obj = new stdClass;
								$obj->table = $value['table'];
								$obj->field = $key;
								$obj->type = $val;
								if (isset($value['value'][$key])) {
									$obj->value = $value['value'][$key];
								}
								if (isset($value['type'][$key])) {
									$obj->fieldtype = $value['type'][$key];
								}
								$temp[$keytype][$key] = $obj;
								$this->_map = $temp;
							}
						}
					}
				}
			}
			if( is_array($this->_map) ) {
				if( isset($this->_map[$type]) && is_array($this->_map[$type]) ) {
					return $this->_map[$type];
				}
			}
            return array();
		}

		function getFieldUserID() {
			$userid = $this->getFieldType('USERID');
	    	if(empty($userid)) $userid = $this->getFieldType('USERNAMEID');
	    	return $userid;
		}

		function getFieldEmail() {
			$email = $this->getFieldType('USERNAMEEMAIL');
			if (empty($email)) {
				$email = $this->getFieldType('EMAIL');
			}
	    	return $email;
		}

		function getFieldUsername() {
			$username = $this->getFieldType('USERNAME');
			if (empty($username)) {
				$username = $this->getFieldType('USERNAMEEMAIL');
			}
			if(empty($username)) {
				$username = $this->getFieldType('USERNAMEID');
			}
	    	return $username;
		}

		function getQuery($include=array(),$type='user') {
	// a.validation_code as activation, a.is_activated, NULL as reason, a.lastLogin as lastvisit '.
			$query = array();
			$map = $this->getMap($type);
			foreach ($map as $value) {
				if ( in_array($value->type, $include) ) {
					switch ($value->type) {
					    case 'LASTVISIT':
					    	$query[] = $value->field.' as lastvisit';
					    	break;
					    case 'GROUP':
					    	$query[] = $value->field.' as group_id';
					    	break;
					    case 'SALT':
					    	$query[] = $value->field.' as password_salt';
					    	break;
					    case 'PASSWORD':
					    	$query[] = $value->field.' as password';
					    	break;
					    case 'REALNAME':
					    	$query[] = $value->field.' as name';
					    	break;
					    case 'FIRSTNAME':
					    	$query[] = $value->field.' as firstname';
					    	break;
					    case 'LASTNAME':
					    	$query[] = $value->field.' as lastname';
					    	break;
					    case 'USERID':
					    	$query[] = $value->field.' as userid';
					    	break;
					    case 'USERNAME':
					    	$query[] = $value->field.' as username';
					    	break;
					    case 'USERNAMEID':
					    	$query[] = $value->field.' as username';
					    	$query[] = $value->field.' as userid';
					    	break;
					    case 'EMAIL':
					    	$query[] = $value->field.' as email';
					    	break;
					    case 'USERNAMEEMAIL':
							$query[] = $value->field.' as username';
					    	$query[] = $value->field.' as email';
					    	break;
					    case 'ACTIVE':
							$query[] = $value->field.' as active';
					    	break;
					    case 'INACTIVE':
							$query[] = $value->field.' as inactive';
					    	break;
					    case 'ACTIVECODE':
							$query[] = $value->field.' as activation';
					    	break;
					    case 'USERNAMEREALNAME':
					    	$query[] = $value->field.' as name';
					    	$query[] = $value->field.' as username';
					    	break;
					    case 'USERNAMEIDREALNAME':
					    	$query[] = $value->field.' as name';
					    	$query[] = $value->field.' as username';
					    	$query[] = $value->field.' as userid';
					    	break;
					    case 'USERNAMEEMAILREALNAME':
					    	$query[] = $value->field.' as name';
					    	$query[] = $value->field.' as username';
					    	$query[] = $value->field.' as email';
					    	break;
					}
				}
			}
			$query = implode  ( ', ' , $query );
			return $query;
		}

        /**
         * @param null $field
         * @param string $type
         * @return null
         */
        function getFieldType($field=null,$type='user') {
			$maped = $this->getMap($type);
            foreach ($maped as $value) {
                if ( $field == $value->type ) {
                    return $value;
                }
            }
			return null;
		}

        /**
         * @param null $t
         * @return array
         */
        function getType($t=null) {
	    	static $types = null;

	    	if ( !is_array($types) ) {
	    		$types = array();
					$type = new stdClass;
					$type->id = 'MD5';
					$type->name = 'MD5';
				$types['MD5'] = $type;
					$type = new stdClass;
					$type->id = 'CUSTOM';
					$type->name = 'CUSTOM';
				$types['CUSTOM'] = $type;
					$type = new stdClass;
					$type->id = 'SALT';
					$type->name = 'SALT';
				$types['SALT'] = $type;
					$type = new stdClass;
					$type->id = 'NULL';
					$type->name = 'NULL';
				$types['NULL'] = $type;
					$type = new stdClass;
					$type->id = 'TIME';
					$type->name = 'TIME';
				$types['TIME'] = $type;
					$type = new stdClass;
					$type->id = 'DATE';
					$type->name = 'DATE';
				$types['DATE'] = $type;
					$type = new stdClass;
					$type->id = 'VALUE';
					$type->name = 'VALUE';
				$types['VALUE'] = $type;
					$type = new stdClass;
					$type->id = 'ONOFF';
					$type->name = 'ONOFF';
				$types['ONOFF'] = $type;
			}

	    	if ( $t ) {
				return $types[$t];
	    	}
			return $types;
	    }

        /**
         * @param null $field
         * @return array
         */
        function getField($field=null) {
	    	static $fields;

	    	if ( !is_array($fields) ) {
				$defaulttype = new stdClass;
				$defaulttype->id = '';
				$defaulttype->name = 'Change Me';

				$fields = array();
					$type = new stdClass;
					$type->id = 'IGNORE';
					$type->name = 'IGNORE';
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'USERID';
					$type->name = 'USERID';
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'USERNAME';
					$type->name = 'USERNAME';
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'USERNAMEID';
					$type->name = 'USERNAMEID';
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'EMAIL';
					$type->name = 'EMAIL';
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'USERNAMEEMAIL';
					$type->name = 'USERNAMEEMAIL';
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'REALNAME';
					$type->name = 'REALNAME';
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'USERNAMEREALNAME';
					$type->name = 'USERNAMEREALNAME';
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'USERNAMEIDREALNAME';
					$type->name = 'USERNAMEIDREALNAME';
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'USERNAMEEMAILREALNAME';
					$type->name = 'USERNAMEEMAILREALNAME';
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'FIRSTNAME';
					$type->name = 'FIRSTNAME';
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'LASTNAME';
					$type->name = 'LASTNAME';
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'GROUP';
					$type->name = 'GROUP';
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'LASTVISIT';
					$type->name = 'LASTVISIT';
	//			$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'ACTIVE';
					$type->name = 'ACTIVE';
					$type->types[] = $defaulttype;
					$type->types[] = $this->getType('ONOFF');
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'INACTIVE';
					$type->name = 'INACTIVE';
					$type->types[] = $defaulttype;
					$type->types[] = $this->getType('ONOFF');
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'ACTIVECODE';
					$type->name = 'ACTIVECODE';
					$type->types[] = $defaulttype;
					$type->types[] = $this->getType('SALT');
					$type->types[] = $this->getType('CUSTOM');
					$type->types[] = $this->getType('VALUE');
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'PASSWORD';
					$type->name = 'PASSWORD';
					$type->types[] = $defaulttype;
					$type->types[] = $this->getType('MD5');
					$type->types[] = $this->getType('CUSTOM');
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'SALT';
					$type->name = 'SALT';
					$type->types[] = $defaulttype;
					$type->types[] = $this->getType('SALT');
					$type->types[] = $this->getType('CUSTOM');
				$fields[$type->id] = $type;
					$type = new stdClass;
					$type->id = 'DEFAULT';
					$type->name = 'DEFAULT';
					$type->types[] = $defaulttype;
					$type->types[] = $this->getType('NULL');
					$type->types[] = $this->getType('TIME');
					$type->types[] = $this->getType('DATE');
					$type->types[] = $this->getType('CUSTOM');
					$type->types[] = $this->getType('VALUE');
				$fields[$type->id] = $type;
	    	}
	    	if ( $field ) {
				return $fields[$field];
	    	}
			return $fields;
	    }

	    function getValue($type,$value, $userinfo=null ) {
	    	$out = '';
	    	$value = html_entity_decode($value);
			switch ($type) {
			    case 'CUSTOM':
					$value = rtrim(trim($value),';');
					ob_start();
					$out = eval("return $value;");
					$error = ob_get_contents();
					ob_end_clean();
					if (strlen($error)) {
						die($error);
					}
			    	break;
			    case 'TIME':
					$out = time();
					break;
				case 'DATE':
					$out = date($value);
					break;
			    case 'VALUE':
					$out = $value;
					break;
			    case 'SALT':
					$len = 4;
					$base='ABCDEFGHKLMNOPQRSTWXYZabcdefghjkmnpqrstwxyz123456789';
					$max=strlen($base)-1;
					$activatecode='';
					mt_srand((double)microtime()*1000000);
					while (strlen($activatecode)<$len+1) $out.=$base{mt_rand(0,$max)};
					break;
			    case 'MD5':
					$out = md5($value);
					break;
			    case 'NULL':
					$out = null;
					break;
			}
			return $out;
	    }
	}
}
