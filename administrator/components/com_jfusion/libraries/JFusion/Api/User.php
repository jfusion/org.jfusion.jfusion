<?php namespace JFusion\Api;
use JFusion\Factory;

/**
 *
 */
class User extends Base {
	/**
	 * @return mixed
	 */
	public function getUser()
	{
		$plugin = isset($this->payload['plugin']) ? $this->payload['plugin'] : 'joomla_int';

		$userPlugin = Factory::getUser($plugin);
		return $userPlugin->getUser($this->payload['username']);
	}

	/**
	 * @return bool
	 */
	public function setLogin()
	{
		if(!empty($this->payload['username']) && !empty($this->payload['password'])) {
			$session['login'] = $this->payload;
			Api::setSession('user', $session);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @return void
	 */
	public function executeLogin()
	{
		$session = Api::getSession('user', true);
		if (isset($session['login'])) {
			$userinfo = $session['login'];
			if (is_array($userinfo)) {
				$joomla = Platform::getInstance();

				if (isset($userinfo['plugin'])) {
					$joomla->setActivePlugin($userinfo['plugin']);
				}
				$joomla->login($userinfo['username'], $userinfo['password']);
			}
		}
		$this->doExit();
	}

	/**
	 * @return void
	 */
	public function executeLogout()
	{
		if ($this->readPayload(false)) {
			$joomla = Platform::getInstance();

			if (isset($userinfo['plugin'])) {
				$joomla->setActivePlugin($userinfo['plugin']);
			}

			$username = isset($this->payload['username']) ? $this->payload['username'] : null;
			$joomla->logout($username);
		}
		$this->doExit();
	}

	/**
	 * @return void
	 */
	public function executeRegister()
	{
		if ($this->payload) {
			if (isset($this->payload['userinfo']) && get_class($this->payload['userinfo']) == 'stdClass') {

				$joomla = Platform::getInstance();

				if (isset($userinfo['plugin'])) {
					$joomla->setActivePlugin($userinfo['plugin']);
				}

				if (isset($this->payload['overwrite']) && $this->payload['overwrite']) {
					$overwrite = 1;
				} else {
					$overwrite = 0;
				}

				$joomla->register($this->payload['userinfo'], $overwrite);

				$this->error = $joomla->error;
				$this->debug = $joomla->debug;
			} else {
				$this->error[] = 'invalid payload';
			}
		} else {
			$this->error[] = 'invalid payload';
		}
	}

	/**
	 * @return void
	 */
	public function executeUpdate()
	{
		if ($this->payload) {
			if ( isset($this->payload['userinfo']) && is_array($this->payload['userinfo'])) {
				$joomla = Platform::getInstance();

				if (isset($this->payload['overwrite']) && $this->payload['overwrite']) {
					$overwrite = 1;
				} else {
					$overwrite = 0;
				}

				$joomla->update($this->payload['userinfo'], $overwrite);

				$this->error = $joomla->error;
				$this->debug = $joomla->debug;
			} else {
				$this->error[] = 'invalid payload';
			}
		} else {
			$this->error[] = 'invalid payload';
		}
	}

	/**
	 * @return void
	 */
	public function executeDelete()
	{
		if ($this->payload) {
			if (isset($this->payload['userid'])) {
				$joomla = Platform::getInstance();

				$joomla->delete($this->payload['userid']);

				$this->error = $joomla->error;
				$this->debug = $joomla->debug;
			} else {
				$this->error[] = 'invalid payload';
			}
		} else {
			$this->error[] = 'invalid payload';
		}
	}
}