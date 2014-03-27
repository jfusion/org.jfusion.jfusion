<?php namespace JFusion\Api;
/**
 *
 */
class Status extends Base {
	public $encrypt = false;

	/**
	 * @return array
	 */
	public function getKey()
	{
//      $hash = sha1($hash); //to improve variance
//		srand((double) microtime() * 1000000);
//		$iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_NOFB), MCRYPT_RAND);
		$iv = '';

		$seed = hexdec(substr(md5(microtime()), -8)) & 0x7fffffff;
		mt_srand($seed);
		for($i = 0; $i < 32; $i++) {
			$iv .= chr(mt_rand(0, 255));
		}

		Api::setSession('hash', $iv);
		return $iv;
	}

	/**
	 * @return array
	 */
	public function getPing()
	{
		return 'pong';
	}
}