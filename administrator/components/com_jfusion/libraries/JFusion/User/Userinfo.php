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
 */
class Userinfo {
	private $jname = null;
	private $userinfo = array('userid'=> null,
		'username'=> null,
		'email'=> null,
		'password'=> null,
		'password_clear' => null,
		'block' => true,
		'activation' => null,
		'groups' => array(),
		'groupnames' => array());

	/**
	 * @param $jname
	 */
	function __construct($jname)
	{
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
		switch($name) {
			case 'block' :
				if ($value) {
					$value = true;
				} else {
					$value = false;
				}
				break;
			case 'activation' :
				if (empty($value)) {
					$value = null;
				}
				break;
		}
		$this->userinfo[$name] = $value;
	}

	/**
	 * @param $name
	 *
	 * @return null
	 */
	public function __get($name)
	{
		if (array_key_exists($name, $this->userinfo)) {
			return $this->userinfo[$name];
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
		return isset($this->userinfo[$name]);
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
//				unset($this->userinfo[$name]);
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
		$object = new stdClass();

		foreach($this->userinfo as $key => $value) {
			$object->$key = $value;
		}
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