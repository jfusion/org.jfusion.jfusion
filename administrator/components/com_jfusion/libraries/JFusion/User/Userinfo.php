<?php namespace JFusion\User;
/**
 * Created by PhpStorm.
 * User: fanno
 * Date: 30-03-14
 * Time: 19:49
 */
use stdClass;

/**
 * Class Userinfo
 *
 * Properties home user information, Below is the required parameters for jfusion framework
 *
 * @property mixed|null $userid userid
 * @property string|null $username username
 * @property string|null $email email
 * @property string|null $password password (already encrypted)
 * @property string|null $password_salt password_salt
 * @property string|null $password_clear password_clear password in clear text
 * @property boolean $block block
 * @property string|null $activation activation
 * @property array $groups groups
 * @property array $groupnames groupnames
 */
class Userinfo {
	private $jname = null;
	private $userinfo = null;

	/**
	 *
	 */

	/**
	 * @param $jname
	 */
	function __construct($jname)
	{
		/**
		 * TODO: maybe add a check that check if jname is a valid jname ? that then throw new \InvalidParameterException ??
		 */
		$this->userinfo = new stdClass();
		$this->userinfo->userid = null;
		$this->userinfo->username = null;
		$this->userinfo->email = null;
		$this->userinfo->password = null;
		$this->userinfo->password_salt = null;
		$this->userinfo->password_clear = null;
		$this->userinfo->block = true;
		$this->userinfo->activation = null;
		$this->userinfo->groups = array();
		$this->userinfo->groupnames = array();

		$this->jname = $jname;
	}

	/**
	 * @param stdClass $userinfo
	 */

	function bind(stdClass $userinfo) {
		foreach($userinfo as $key => $value) {
			$this->$key = $value;
		}
	}

	/**
	 * @param $name
	 * @param $value
	 */
	public function __set($name, $value)
	{
		if ($name != 'jname') {
			switch($name) {
				case 'block' :
					if ($value) {
						$value = true;
					} else {
						$value = false;
					}
					break;
				case 'activation':
				case 'password_clear';
					if (empty($value)) {
						$value = null;
					}
					break;
				case 'groups';
				case 'groupnames';
					if (!is_array($value)) {
						$value = array();
					}
					break;

			}
			$this->userinfo->$name = $value;
		}
	}

	/**
	 * @param $name
	 *
	 * @return null
	 */
	public function __get($name)
	{
		if (isset($this->userinfo->$name)) {
			return $this->userinfo->$name;
		}
		return null;
	}

	/**
	 * @param $name
	 *
	 * @return bool
	 */
	public function __isset($name)
	{
		return isset($this->userinfo->$name);
	}

	/**
	 * @param $name
	 */
	public function __unset($name)
	{
		switch($name) {
			case 'block' :
				break;
			default:
//				unset($this->userinfo->$name);
		}
	}

	/**
	 * @return null
	 */
	function getJname() {
		return $this->jname;
	}

	/**
	 * @return stdClass
	 */
	function toObject() {
		$object = clone($this->userinfo);
		$object->jname = $this->jname;
		return $object;
	}

	/**
	 * hides sensitive information
	 *
	 * @return string parsed userinfo object
	 */
	public function getAnonymizeed()
	{
		$userinfo = $this->toObject();
		$userinfo->password_clear = '******';
		if (isset($userinfo->password)) {
			$userinfo->password = substr($userinfo->password, 0, 6) . '********';
		}
		if (isset($userinfo->password_salt)) {
			$userinfo->password_salt = substr($userinfo->password_salt, 0, 4) . '*****';
		}
		return $userinfo;
	}
}