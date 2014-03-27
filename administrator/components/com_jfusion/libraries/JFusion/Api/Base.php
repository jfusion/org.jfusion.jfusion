<?php namespace JFusion\Api;
/**
 *
 */
class Base {
	public $encrypt = true;
	public $payload = array();
	public $error = array();
	public $debug = array();
	public $key = null;

	/**
	 * @param $key
	 */
	public function __construct($key)
	{
		$this->key = $key;
		$this->readPayload($this->encrypt);
	}

	/**
	 * @param $encrypt
	 *
	 * @return bool
	 */
	protected function readPayload($encrypt)
	{
		if (!$encrypt && isset($_GET['jfpayload'])) {
			ob_start();
			$payload = json_decode(trim(base64_decode($_GET['jfpayload'])));
			ob_end_clean();
		} else if ($encrypt && isset($_POST['jfpayload'])) {
			$payload = Api::decrypt($this->key , $_POST['jfpayload']);
		}
		if (isset($payload) && is_array($payload) ) {
			$this->payload = $payload;
			return true;
		}
		return false;
	}

	/**
	 * @param $payload
	 *
	 * @return string
	 */
	protected function buildPayload($payload)
	{
		return base64_encode(json_encode($payload));
	}

	/**
	 * @param string|null $url Url of where to redirect to
	 *
	 * @return void
	 */
	protected function doExit($url = null) {
		if ($url && isset($_GET['jfreturn'])) {
			$url .= '&jfreturn=' . $_GET['jfreturn'];
		} else if (isset($_GET['jfreturn'])) {
			$url = base64_decode($_GET['jfreturn']);
		}

		if ($url) {
			header('Location: ' . $url);
		}
		exit();
	}
}