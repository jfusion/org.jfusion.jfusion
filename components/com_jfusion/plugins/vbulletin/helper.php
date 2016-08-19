<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Helper Class for vBulletin
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionHelper_vbulletin extends JFusionPlugin
{
    var $vb_data;
    var $backup;

    /**
     * Returns the name for this plugin
     *
     * @return string
     */
    function getJname()
    {
        return 'vbulletin';
    }

    /**
     * @param $data
     * @return string
     */
    function encryptApiData($data) {
        $key = $this->params->get('vb_secret', JFusionFactory::getConfig()->get('secret'));
        $data['jfvbkey'] = $key;
        return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key), serialize($data), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));
    }

    /**
     * @param $task
     * @param $data
     * @return array|mixed
     */
    function apiCall($task, $data) {
        $status = array('success' => 0, 'errors' => array(), 'debug' => array());
        if (!function_exists('mcrypt_encrypt')) {
            $status['errors'][] = 'mcrypt_encrypt Missing';
        } elseif (!function_exists('curl_init')) {
            $status['errors'][] = 'curl_init Missing';
        } else {
            $url = $this->params->get('source_url');
            $version = $this->getVersion();
            if (substr($version, 0, 1) > 3) {
                $url .= $this->params->get('vb4_base_file', 'forum.php');
            } else {
                $url .= 'index.php';
            }
            $post_data = 'jfvbtask=' . $task;
            $post_data.= '&jfvbdata=' . urlencode(stripslashes($this->encryptApiData($data)));
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded', 'Content-Length: ' . strlen($post_data)));
            curl_setopt($ch, CURLOPT_HEADER , 0);
            curl_setopt($ch, CURLOPT_VERBOSE, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

            $curl_response = @curl_exec($ch);
            //detect errors
            if (curl_errno($ch)) {
                $status['errors'][] = curl_errno($ch) . ' ' . curl_error($ch);
            } else {
                //detect redirects as the post data gets lots
                $curlinfo = curl_getinfo($ch);
                if ($curlinfo['url'] != $url) {
                    $status['errors'][] = JText::_('VB_API_REDIRECT') . ': ' . $url . '->' . $curlinfo['url'];
                } else {
                    $curl_response = trim($curl_response);
                    if (strpos($curl_response, '{') !== 0) {
                        if (strpos($curl_response, '<!DOCTYPE') !== false) {
                            //the page was rendered rather than the hook catching
                            $status['errors'][] = JText::_('VB_API_HOOK_NOT_INSTALLED');
	                        $status['debug'][] = htmlspecialchars($curl_response);
                        } else {
                            //there is probably a php error or warning
                            if (($pos = strpos($curl_response, '{')) !== false) {
	                            $response = $this->decode(substr($curl_response, $pos));
                                if ($response === null) {
                                    $status['errors'][] = 'Data corrupted!';
                                } else {
	                                $status = $response;
                                }
	                            $status['debug'][] = htmlspecialchars(substr($curl_response, 0, $pos));
                            } else {
	                            $status['debug'][] = htmlspecialchars($curl_response);
                                if (empty($curl_response)) {
                                    $curl_response = JText::_('VB_API_HOOK_NOT_INSTALLED');
                                }
                                $status['errors'][] = $curl_response;
                            }
                        }
                    } else {
	                    $response = $this->decode($curl_response);
	                    if ($response === null) {
		                    $status['errors'][] = 'Data corrupted!';
	                    } else {
		                    $status = $response;
	                    }
                    }
                }
            }
            curl_close($ch);
        }
        return $status;
    }

	/**
	 *
	 */
	function decode($data) {
		return json_decode($data, true);
	}


    /**
     * Initializes the vBulletin framework
     *
     * @return boolean true on successful initialization
     */
    function vBulletinInit()
    {
        $return = true;
        //only initialize the vb framework if it has not already been done
        if (!defined('VB_AREA')) {
            //load the vbulletin framework
            define('VB_AREA', 'JFusion');
            define('VB_ENTRY', 'JFusion');
            define('THIS_SCRIPT', 'JFusion');
            define('SKIP_SESSIONCREATE', 1);
            define('DIE_QUIETLY', 1);
            define('SKIP_USERINFO', 1);
            define('NOPMPOPUP', 1);
            define('CWD', $this->params->get('source_path'));

            $phrasegroups = array('postbit');
            $specialtemplates = array();
            $globaltemplates = array(
                'bbcode_code_printable',
                'bbcode_html_printable',
                'bbcode_php_printable',
                'bbcode_quote_printable',
                'postbit_attachment',
                'postbit_attachmentimage',
                'postbit_attachmentthumbnail',
                'postbit_external',
            );
            $actiontemplates = array();
            global $vbulletin;
            if (file_exists(CWD)) {
				if (!function_exists('fetch_phrase')) {
                    require_once(CWD . '/includes/functions_misc.php');
                }
                require_once CWD . '/includes/init.php';
                $this->vb_data  = $vbulletin;
                //force into global scope
                $GLOBALS['vbulletin'] = $vbulletin;
                $vbulletin->db->query_first('USE `' . $this->params->get('database_name') . '`');
                //fixed do not remove ascii backspace because we are in joomla everything is utf8
                $vbulletin->options['blankasciistrip'] = 'u8205 u8204 u8237 u8238';
                // set connection to use utf8
                $vbulletin->db->query_first('SET names \'' . $this->params->get('database_charset', 'utf8') . '\'');
                $GLOBALS['db'] = $vbulletin->db;
            } else {
                JFusionFunction::raiseWarning(JText::_('SOURCE_PATH_NOT_FOUND'), $this->getJname());
                $return = false;
            }
        } elseif (defined('VB_AREA') && VB_AREA == 'JFusion') {
        	if (!$this->vb_data) {
				/**
				 * @TODO using defined('VB_AREA') is not safe we we using multi instance VB?
				 * we need to change something to support that. ? or it just failed t o fine CWD
				 */
                $return = false;
        	} else {
                $this->vb_data->db->query_first('USE `' . $this->params->get('database_name') . '`');
                //fixed do not remove ascii backspace because we are in joomla everything is utf8
                $this->vb_data->db->options['blankasciistrip'] = 'u8205 u8204 u8237 u8238';
                // set connection to use utf8
                $this->vb_data->db->query_first('SET names \'' . $this->params->get('database_charset', 'utf8') . '\'');
                if (empty($GLOBALS['vbulletin'])) {
                    $GLOBALS['vbulletin'] = $this->vb_data;
                }
                if (empty($GLOBALS['db'])) {
                    $GLOBALS['db'] = $this->vb_data->db;
                }
            }
        } elseif (defined('VB_AREA')) {
           //vb is calling up JFusion so load the $vbulletin global
           global $vbulletin;
           if (!empty($vbulletin)) {
               $this->vb_data = $vbulletin;
           } else {
               //these is for sure going to lead to something bad so let's die now
               die('vB JFusion Integration Fatal Error - Please contact the site administrator!');
           }
        } else {
            $return = false;
        }
        return $return;
    }

    /**
     * Convert the existinguser variable into something vbulletin understands
     *
     * @param $existinguser object with existing vb userinfo
     *
     * @return array
     */
    function convertUserData($existinguser)
    {
        $userinfo = array('userid' => $existinguser->userid, 'username' => $existinguser->username, 'email' => $existinguser->email, 'password' => $existinguser->password);
        return $userinfo;
    }

    /**
     * Backs up Joomla various Joomla variables before calling vBulletin's data managers
     */
    function backupJoomla()
    {
        $this->backup['globals'] = $GLOBALS;
        //let's take special precautions for Itemid
        $this->backup['itemid'] = JFusionFactory::getApplication()->input->getInt('Itemid', 0);
    }

    /**
     * Restores Joomla various Joomla variables after calling vBulletin's data managers
     */
    function restoreJoomla()
    {
        //restore Joomla autoload function
	    spl_autoload_register(array('JLoader', 'load'));

        if (isset($this->backup['globals'])) {
            $GLOBALS = $this->backup['globals'];
        }
        if (isset($this->backup['itemid'])) {
	        JFusionFactory::getApplication()->input->set('Itemid', $this->backup['itemid']);
            global $Itemid;
            $Itemid = $this->backup['itemid'];
        }
        $this->backup = array();
        //make sure Joomla db object is still connected
        JFusionFunction::reconnectJoomlaDb();
    }

    /**
     * Obtains the version of the integrated vbulletin
     *
     * @return string Version number
     */
    function getVersion()
    {
        static $jfusion_vb_version;
	    try {
		    if(empty($jfusion_vb_version)) {
			    $db = JFusionFactory::getDatabase($this->getJname());

			    $query = $db->getQuery(true)
				    ->select('value')
				    ->from('#__setting')
				    ->where('varname = ' . $db->quote('templateversion'));

			    $db->setQuery($query);
			    $jfusion_vb_version = $db->loadResult();
		    }
		    return $jfusion_vb_version;
	    } catch (Exception $e) {
		    return '';
	    }
    }

    /**
     * Creates a basic vB SEF url for vB 4
     *
     * @param $url
     * @param bool|string $type Type of url eg. forum, thread, member
     *
     * @internal param array $uri Array with uri pieces
     * @return string $url  Appropriate URL
     */
    function getVbURL($url, $type = false)
    {
	    try {
		    $allow_sef = $this->params->get('allow_sef', 1);
		    $vbversion = $this->getVersion();
		    if (!empty($allow_sef) && (int) substr($vbversion, 0, 1) > 3) {
			    $db = JFusionFactory::getDatabase($this->getJname());

			    if (!defined('JFVB_FRIENDLYURL')) {
				    $query = $db->getQuery(true)
					    ->select('value')
					    ->from('#__setting')
					    ->where('varname = ' . $db->quote('friendlyurl'));

				    $db->setQuery($query);
				    $sefmode = $db->loadResult();
				    define('JFVB_FRIENDLYURL', (int) $sefmode);
			    }

			    $uri = new JURI($url);

			    switch ($type) {
				    case 'members':
					    $query = $db->getQuery(true)
						    ->select('username')
						    ->from('#__user')
						    ->where('userid = ' . $uri->getVar('u'));

					    $db->setQuery($query);
					    $username = $db->loadResult();
					    $this->cleanForVbURL($username);
					    $vburi = $uri->getVar('u') . '-' . $username;
					    break;
				    case 'threads':
					    $query = $db->getQuery(true)
						    ->select('title')
						    ->from('#__thread')
						    ->where('threadid = ' . $uri->getVar('t'));

					    $db->setQuery($query);
					    $title = $db->loadResult();
					    $this->cleanForVbURL($title);
					    $vburi = $uri->getVar('t') . '-' . $title;
					    break;
				    case 'post':
					    $pid = $uri->getVar('p');
					    $tid = $uri->getVar('t');

					    $title = null;
					    if (empty($tid)) {
						    $query = $db->getQuery(true)
							    ->select('threadid')
							    ->from('#__post')
							    ->where('postid = ' . $pid);

						    $db->setQuery($query);
						    $tid = $db->loadResult();

						    $query = $db->getQuery(true)
							    ->select('title')
							    ->from('#__thread')
							    ->where('threadid = ' . $tid);

						    $db->setQuery($query);
						    $title = $db->loadResult();
						    $this->cleanForVbURL($title);
					    }

					    $vburi = $tid . '-' . $title;
					    $uri->setVar('viewfull', 1);
					    $type = 'threads';
					    break;
				    case 'forums':
				    default:
					    $vburi = null;
					    break;
			    }
			    if ($vburi) {
				    $query = $uri->getQuery();
				    $fragment = $uri->getFragment();
				    if ($fragment) {
					    $fragment = '#' . $fragment;
				    }

				    switch (JFVB_FRIENDLYURL) {
					    case 1:
						    $url = $uri->getPath() . '?' . $vburi . ($query ? '&' . $query : '') . $fragment;
						    break;
					    case 2:
						    $url = $uri->getPath() . '/' . $vburi . ($query ? '?' . $query : '') . $fragment;
						    break;
					    case 3:
						    $url = $type . '/' . $vburi . ($query ? '?' . $query : '') . $fragment;
						    break;
				    }
			    }
		    }
	    } catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
	    }
        return $url;
    }

    /**
     * Prepares text for a vB SEF URL
     *
     * @param string &$string text to be cleaned
     */
    function cleanForVbURL(&$string)
    {
        $string = preg_replace('*([\s$+,/:=\?@"\'<>%{}|\\^~[\]`\r\n\t\x00-\x1f\x7f]|(?(?<!&)#|#(?![0-9]+;))|&(?!#[0-9]+;)|(?<!&#\d|&#\d{2}|&#\d{3}|&#\d{4}|&#\d{5});)*s', '-', strip_tags($string));
        $string = trim(preg_replace('#-+#', '-', $string), '-');
    }
}