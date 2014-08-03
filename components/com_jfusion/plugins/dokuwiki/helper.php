<?php namespace JFusion\Plugins\dokuwiki;

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Dokuwiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

use JFile;
use JFusion\Plugin\Plugin;

/**
 * JFusion Helper Class for Dokuwiki
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Dokuwiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Helper extends Plugin
{
    /**
     * @var Auth_Basic|Auth_Plain|Auth_Mysql
     */
    var $auth;

	/**
	 * @param string $instance instance name of this plugin
	 */
	function __construct($instance)
	{
		parent::__construct($instance);

	    $conf = $this->getConf();

	    if ($conf && isset($conf['authtype']))  {
		    if ($conf['authtype'] == 'authmysql') {
			    if (!class_exists('Jfusion_DokuWiki_Mysql')) {
				    require_once('auth' . DIRECTORY_SEPARATOR . 'mysql.class.php');
			    }
			    $this->auth = new Auth_Mysql($this);
		    } elseif ($conf['authtype'] == 'authplain') {
			    if (!class_exists('Jfusion_DokuWiki_Plain')) {
				    require_once('auth' . DIRECTORY_SEPARATOR . 'plain.class.php');
			    }
			    $this->auth = new Auth_Plain($this);
		    }
	    }

	    if (!$this->auth) {
		    if (!class_exists('Jfusion_DokuWiki_Basic')) {
			    require_once('auth' . DIRECTORY_SEPARATOR . 'basic.class.php');
		    }
		    $this->auth = new Auth_Basic($this);
	    }
    }

    /**
     * Defines constants required by Dokuwiki
     *
     * @param $nosession    boolean to set the NOSESSION constant; false by default
     */
    function defineConstants($nosession = false) {
        $conf = $this->getConf();

        $source_url = $this->params->get('source_url');
        $doku_rel = preg_replace('#(\w{0,10}://)(.*?)/(.*?)#is', '$3', $source_url);
        $doku_rel = preg_replace('#//+#', '/', "/$doku_rel/");
        if (!defined('DOKU_REL')) {
            define('DOKU_REL', $doku_rel);
        }
        if (!defined('DOKU_URL')) {
            define('DOKU_URL', $source_url);
        }
        if (!defined('DOKU_BASE')) {
            define('DOKU_BASE', DOKU_URL);
        }
        if ($nosession && !defined('NOSESSION')) {
            define('NOSESSION', 1);
        }
        if (!defined('DOKU_COOKIE')) {
            define('DOKU_COOKIE', 'DW' . md5(DOKU_REL . (($conf['securecookie']) ? $_SERVER['SERVER_PORT'] : '')));
        }
    }

    /**
     * Retrieves, sets, and returns Dokuwiki cookie salt
     * @return string   cookie salt
     */

    function getCookieSalt() {
        static $dokuwiki_cookie_salt;

        if (empty($dokuwiki_cookie_salt)) {
            $source_path = $this->params->get('source_path');

            $conf = $this->getConf();
            $data_dir = (isset($conf['savedir'])) ? $source_path . DIRECTORY_SEPARATOR . $conf['savedir'] : $source_path . DIRECTORY_SEPARATOR . 'data';

            //get the cookie salt file
            $saltfile = $data_dir . DIRECTORY_SEPARATOR . 'meta' . DIRECTORY_SEPARATOR . '_htcookiesalt';
            jimport('joomla.filesystem.file');
            $dokuwiki_cookie_salt = file_get_contents($saltfile);
            if(empty($dokuwiki_cookie_salt)){
                $dokuwiki_cookie_salt = uniqid(rand(), true);
                JFile::write($saltfile, $dokuwiki_cookie_salt);
            }
        }
        return $dokuwiki_cookie_salt;
    }
    
    /**
     * Retrieves, sets, and returns Dokuwiki Version
     *
     * @param string $v
     *
     * @return string   version
     */

    function getVersion($v = 'version') {
        static $dokuwiki_version;

        if (empty($dokuwiki_version)) {
            $source_path = $this->params->get('source_path');

            jimport('joomla.filesystem.file');
            $file_version = file_get_contents($source_path . 'VERSION');
            $matches = array();
            if (preg_match('#([a-z]*)([0-9]*-[0-9]*-[0-9]*)([a-z]*)#is', $file_version, $matches)) {
	            list($fullversion, $rc, $version, $sub) = $matches;
	            $dokuwiki_version['full'] = $fullversion;
	            $dokuwiki_version['version'] = $version . $rc . $sub;
            }
        }
        if (isset($dokuwiki_version[$v])) {
        	return $dokuwiki_version[$v];
        }
        return null;
    }

    /**
     * @param bool $path
     * @return bool|string
     */
    function getConfigPath($path = false) {
	    static $config_path;
	    if (!isset($config_path) || $path !== false) {
		    if (empty($path)) {
			    $path = $this->params->get('source_path');
		    }

		    //standard config path
		    $config_path = $path . 'conf' . DIRECTORY_SEPARATOR;

		    //check to see if conf directory is located somewhere else
		    if (file_exists($path  . 'inc' . DIRECTORY_SEPARATOR . 'preload.php')) {
			    include_once $path  . 'inc' . DIRECTORY_SEPARATOR . 'preload.php';
			    if (defined('DOKU_CONF')) {
				    $config_path = DOKU_CONF;
				    //make sure we have a ending DIRECTORY_SEPARATOR
				    if (substr($config_path, -1) != DIRECTORY_SEPARATOR) {
					    $config_path .= DIRECTORY_SEPARATOR;
				    }
			    }
		    }
	    }
        return $config_path;
    }

    /**
     * This method should handle any login logic and report back to the subject
     *
     * @param string|bool $path path to config file
     *
     * @return array on success, false on Error
     * @since 1.5
     * @access public
     */
    function getConf($path = false)
    {
        static $config;
        if (!isset($config) || $path !== false) {
            if (!$path) {
                $path = $this->params->get('source_path');
            }

	        if (!empty($path)) {
		        $path = $this->getConfigPath($path);

		        $myfile = array();
		        $myfile[] = $path . 'dokuwiki.php';
		        $myfile[] = $path . 'local.php';
		        $myfile[] = $path . 'local.protected.php';

		        $conf = array();
		        foreach ($myfile as $file) {
			        if (file_exists($file)) {
				        require($file);
			        }
		        }
		        if (!count($conf)) {
			        $config = false;
		        } else {
			        $config = $conf;
		        }
	        }
        }
        return $config;
    }

    /**
     * This method should handle any login logic and report back to the subject
     *
     * @return string True on success
     * @since 1.5
     * @access public
     */
    function getDefaultUsergroup()
    {
        $conf = $this->getConf();
        $usergroup = 'user';
        if (!empty($conf['defaultgroup'])) {
            $usergroup = $conf['defaultgroup'];
        }
        return $usergroup;
    }

	/**
	 * Encryption using blowfish algorithm
	 *
	 * @param   string  $data original data
	 * @param   string  $secret the secret
	 *
	 * @return  string  the encrypted result
	 *
	 * @access  public
	 *
	 * @author  lem9
	 */
	function PMA_blowfish_encrypt($data, $secret)
	{
		$pma_cipher = new Auth_Blowfish();
		$encrypt = '';

		$data .= '_'; // trimming fixed for DokuWiki FS#1690 FS#1713
		$mod = strlen($data) % 8;

		if ($mod > 0) {
			$data .= str_repeat("\0", 8 - $mod);
		}

		foreach (str_split($data, 8) as $chunk) {
			$encrypt .= $pma_cipher->encryptBlock($chunk, $secret);
		}
		return base64_encode($encrypt);
	}

	/**
	 * Decryption using blowfish algorithm
	 *
	 * @param   string  $encdata encrypted data
	 * @param   string  $secret the secret
	 *
	 * @return  string  original data
	 *
	 * @access  public
	 *
	 * @author  lem9
	 */
	function PMA_blowfish_decrypt($encdata, $secret)
	{
		$pma_cipher = new Auth_Blowfish();
		$decrypt = '';
		$data = base64_decode($encdata);

		foreach (str_split($data, 8) as $chunk) {
			$decrypt .= $pma_cipher->decryptBlock($chunk, $secret);
		}
		return substr(rtrim($decrypt, "\0"), 0, -1); // trimming fixed for DokuWiki FS#1690 FS#1713
	}
}