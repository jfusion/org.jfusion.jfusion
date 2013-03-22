<?php
/**
 * @package JFusion
 * @subpackage Models
 * @author JFusion development team -- Morten Hundevad
 * @copyright Copyright (C) 2008 JFusion -- Morten Hundevad. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */
    /**
     * JFusionHelper_universal class
     *
     * @category   JFusion
     * @package    Model
     * @subpackage JFusionHelper
     * @author     JFusion Team <webmaster@jfusion.org>
     * @copyright  2008 JFusion. All rights reserved.
     * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
     * @link       http://www.jfusion.org
     */
class JFusionHelper_universal {
    var $_map = array();
    var $_mapraw = null;

    /**
     * @return string
     */
    function getJname() {
        return 'universal';
    }

    /**
     * @param string $type
     * @return mixed
     */
    function getTablename($type='user') {
        $maped = $this->getMapRaw($type);
        return $maped['table'];
    }

    /**
     * @param string $type
     * @return bool
     */
    function getMapRaw($type='user') {
        if( !is_array($this->_mapraw) ) {
            $params = JFusionFactory::getParams($this->getJname());
            $map = $params->get('map');
            $map = @unserialize($map);
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

    /**
     * @param string $type
     * @return array
     */
    function getMap($type='user') {
        if( empty($this->_map) && !isset($this->_map[$type]) ) {
            $map = $this->getMapRaw($type);
            if(is_array($map)) {
                foreach ($map['field'] as $key => $value) {
                    $obj = new stdClass;
                    $obj->table = $map['table'];
                    $obj->field = $key;
                    $obj->type = $value;
                    if (isset($map['value'][$key])) {
                        $obj->value = $map['value'][$key];
                    }
                    if (isset($map['type'][$key])) {
                        $obj->fieldtype = $map['type'][$key];
                    }
                    $this->_map[$type][$key] = $obj;
                }
            }
        }
        if( is_array($this->_map) && isset($this->_map[$type]) && is_array($this->_map[$type]) ) {
            return $this->_map[$type];
        }
        return array();
    }

    /**
     * @return null
     */
    function getFieldUserID() {
        $userid = $this->getFieldType('USERID');
        if(empty($userid)) $userid = $this->getFieldType('USERNAMEID');
        return $userid;
    }

    /**
     * @return null
     */
    function getFieldEmail() {
        $email = $this->getFieldType('USERNAMEEMAIL');
        if (empty($email)) {
            $email = $this->getFieldType('EMAIL');
        }
        return $email;
    }

    /**
     * @return null
     */
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

    /**
     * @param array $include
     * @param string $type
     * @return array|string
     */
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
                    case 'IPADDRESS':
                        $query[] = $value->field.' as ipaddress';
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
	        $type = new stdClass;
            $types = array();
			$type->name = $type->id = 'MD5';
			$types[$type->id] = $type;

			$type->name = $type->id = 'CUSTOM';
			$types[$type->id] = $type;

			$type->name = $type->id = 'SALT';
			$types[$type->id] = $type;

			$type->name = $type->id = 'NULL';
			$types[$type->id] = $type;

			$type->name = $type->id = 'TIME';
			$types[$type->id] = $type;

			$type->name = $type->id = 'DATE';
			$types[$type->id] = $type;

			$type->name = $type->id = 'VALUE';
			$types[$type->id] = $type;

			$type->name = $type->id = 'ONOFF';
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
            $defaulttype->name = JText::_('CHANGE_ME');

            $fields = array();
            $type = new stdClass;
            $type->name = $type->id = 'IGNORE';
            $fields[$type->id] = $type;

            $type = new stdClass;
            $type->name = $type->id = 'USERID';
            $fields[$type->id] = $type;

            $type = new stdClass;
	        $type->name = $type->id = 'USERNAME';
            $fields[$type->id] = $type;

            $type = new stdClass;
            $type->name = $type->id = 'USERNAMEID';
            $fields[$type->id] = $type;

            $type = new stdClass;
            $type->name = $type->id = 'EMAIL';
            $fields[$type->id] = $type;

            $type = new stdClass;
            $type->name = $type->id = 'USERNAMEEMAIL';
            $fields[$type->id] = $type;

            $type = new stdClass;
	        $type->name = $type->id = 'REALNAME';
            $fields[$type->id] = $type;

            $type = new stdClass;
	        $type->name = $type->id = 'USERNAMEREALNAME';
            $fields[$type->id] = $type;

            $type = new stdClass;
	        $type->name = $type->id = 'USERNAMEIDREALNAME';
            $fields[$type->id] = $type;

            $type = new stdClass;
	        $type->name = $type->id = 'USERNAMEEMAILREALNAME';
            $fields[$type->id] = $type;

            $type = new stdClass;
	        $type->name = $type->id = 'FIRSTNAME';
            $fields[$type->id] = $type;

            $type = new stdClass;
	        $type->name = $type->id = 'LASTNAME';
            $fields[$type->id] = $type;

            $type = new stdClass;
	        $type->name = $type->id = 'GROUP';
            $fields[$type->id] = $type;

            $type = new stdClass;
	        $type->name = $type->id = 'IPADDRESS';
            $fields[$type->id] = $type;

            $type = new stdClass;
	        $type->name = $type->id = 'LASTVISIT';
			$fields[$type->id] = $type;

	        $type = new stdClass;
	        $type->name = $type->id = 'UNIXTIMESTAMP';
	        $fields[$type->id] = $type;

            $type = new stdClass;
	        $type->name = $type->id = 'ACTIVE';
            $type->types[] = $defaulttype;
            $type->types[] = $this->getType('ONOFF');
            $fields[$type->id] = $type;

            $type = new stdClass;
	        $type->name = $type->id = 'INACTIVE';
            $type->types[] = $defaulttype;
            $type->types[] = $this->getType('ONOFF');
            $fields[$type->id] = $type;

            $type = new stdClass;
	        $type->name = $type->id = 'ACTIVECODE';
            $type->types[] = $defaulttype;
            $type->types[] = $this->getType('SALT');
            $type->types[] = $this->getType('CUSTOM');
            $type->types[] = $this->getType('VALUE');
            $fields[$type->id] = $type;

			$type = new stdClass;
			$type->name = $type->id = 'PASSWORD';
			$type->types[] = $defaulttype;
			$type->types[] = $this->getType('MD5');
			$type->types[] = $this->getType('CUSTOM');
			$fields[$type->id] = $type;

			$type = new stdClass;
			$type->name = $type->id = 'SALT';
			$type->types[] = $defaulttype;
			$type->types[] = $this->getType('SALT');
			$type->types[] = $this->getType('CUSTOM');
            $fields[$type->id] = $type;

			$type = new stdClass;
			$type->name = $type->id = 'DEFAULT';
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

    /**
     * @param $type
     * @param $value
     * @param null $userinfo
     * @return int|null|string
     */
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
