<?php namespace JFusion\Api;
/**
 *
 */
class Cookie extends Base {
	/**
	 * @return bool
	 */
	public function setCookies()
	{
		if (is_array($this->payload)) {
			$session['cookies'] = $this->payload;
			Api::setSession('cookie', $session);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @return void
	 */
	public function executeCookies()
	{
		if ($this->readPayload(false)) {
			$session = Api::getSession('cookie', true);

			if ( isset($session['cookies']) && count($session['cookies']) && is_array($session['cookies']) ) {
				foreach($session['cookies'] as $value ) {
					header('Set-Cookie: ' . $value, false);
				}
			}

			if ( count($this->payload['url']) ) {
				foreach($this->payload['url'] as $key => $value ) {
					unset($this->payload['url'][$key]);

					$this->payload = $this->buildPayload($this->payload);

					$this->doExit($key . '?jfpayload=' . $this->payload . '&PHPSESSID=' . $value . '&jftype=execute&jfclass=cookie&jftask=cookies');
				}
			} else {
				$this->doExit();
			}
		}
		exit;
	}
}