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
	 * @param stdClass $userinfo
	 * @param          $jname
	 */
	function bind(stdClass $userinfo, $jname) {
		foreach($userinfo as $key => $value) {
			$this->$key = $value;
		}
		$this->jname = $jname;
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
		if ( is_object($userinfo) ) {
			$userclone = clone $userinfo;
			$userclone->password_clear = '******';
			if (isset($userclone->password)) {
				$userclone->password = substr($userclone->password, 0, 6) . '********';
			}
			if (isset($userclone->password_salt)) {
				$userclone->password_salt = substr($userclone->password_salt, 0, 4) . '*****';
			}
		} else {
			$userclone = $userinfo;
		}
		return $userclone;
	}
}