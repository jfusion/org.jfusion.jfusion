<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Function that registers the JFusion phpBB3 hooks
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 *
 * @param phpbb_hook &$hook
 */
function phpbb_hook_register(&$hook)
{
    global $phpbb_root_path, $phpEx, $db, $config, $user;
    //Register the hooks
    foreach ($hook->hooks as $definition => $hooks) {
        foreach ($hooks as $function => $data) {
            $callback = $definition == '__global' ? $function : $definition . '_' . $function;
            $hook->register(array($definition, $function), array('JFusionHook', $callback));
        }
    }
}
/**
 * JFusion Hooks for phpBB3
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionHook {
    /**
     * Throws an exception at the end of the phpBB3 execution to return to JFusion
     *
     * @param mixed $hook
     *
     * @throws RuntimeException
     */
    public static function exit_handler($hook) {
        //throw an exception to allow Joomla to continue
        throw new RuntimeException('phpBB exited.');
    }

    /**
     * @static
     * @param $hook
     * @param $url
     * @param bool|array $params
     * @param bool $is_amp
     * @param bool $session_id
     * @return string
     */
    public static function append_sid($hook, $url, $params = false, $is_amp = true, $session_id = false) {
        global $_SID, $_EXTRA_URL, $phpbb_hook;
        $params_is_array = is_array($params);
        // Get anchor
        $anchor = '';
//	    $url = str_replace('../', '' , $url);
        if (strpos($url, '#') !== false) {
            list($url, $anchor) = explode('#', $url, 2);
            $anchor = '#' . $anchor;
        } else if (!$params_is_array && strpos($params, '#') !== false) {
            list($params, $anchor) = explode('#', $params, 2);
            $anchor = '#' . $anchor;
        }
        // Handle really simple cases quickly
        if ($_SID == '' && $session_id === false && empty($_EXTRA_URL) && !$params_is_array && !$anchor) {
            if ($params === false) {
                return $url;
            }
            $url_delim = (strpos($url, '?') === false) ? '?' : (($is_amp) ? '&amp;' : '&');
            return $url . ($params !== false ? $url_delim . $params : '');
        }
        // Assign sid if session id is not specified
        if ($session_id === false) {
            $session_id = $_SID;
        }
        $amp_delim = ($is_amp) ? '&amp;' : '&';
        $url_delim = (strpos($url, '?') === false) ? '?' : $amp_delim;
        // Appending custom url parameter?
        $append_url = (!empty($_EXTRA_URL)) ? implode($amp_delim, $_EXTRA_URL) : '';
        // Use the short variant if possible ;)
        if ($params === false) {
            // Append session id
            if (!$session_id) {
                return $url . (($append_url) ? $url_delim . $append_url : '') . $anchor;
            } else {
                return $url . (($append_url) ? $url_delim . $append_url . $amp_delim : $url_delim) . 'sid=' . $session_id . $anchor;
            }
        }
        // Build string if parameters are specified as array
        if (is_array($params)) {
            $output = array();
            foreach ($params as $key => $item) {
                if ($item === null) {
                    continue;
                }
                if ($key == '#') {
                    $anchor = '#' . $item;
                    continue;
                }
                $output[] = $key . '=' . $item;
            }
            $params = implode($amp_delim, $output);
        }
        // Append session id and parameters (even if they are empty)
        // If parameters are empty, the developer can still append his/her parameters without caring about the delimiter
        return $url . (($append_url) ? $url_delim . $append_url . $amp_delim : $url_delim) . $params . ((!$session_id) ? '' : $amp_delim . 'sid=' . $session_id) . $anchor;
    }
    /**
     * Function that allows for the user object to contain the correct url
     *
     * @param $hook
     */
    public static function phpbb_user_session_handler($hook) {
	    /**
	     * @var $request \phpbb\request\request
	     * @var $user \phpbb\user
	     */
        //we need to change the $user->page array as it does not detect some POST values
        global $user, $request;
        //set our current phpBB3 filename
	    $mainframe = JFusionFactory::getApplication();

        $jfile = $mainframe->input->get('jfile');
        if (empty($jfile)) {
            $jfile = 'index.php';
        }
        $user->page['page_name'] = $jfile;
        //parse our GET variables

	    $list = $request->variable_names(\phpbb\request\request_interface::GET);

	    $get_vars = array();
	    foreach ($list as $var) {
		    $get_vars[$var] = $request->variable($var, "", false, \phpbb\request\request_interface::GET);
	    }

	    //Some params where changed to POST therefore we need to include some of those
        $post_include = array('i', 'mode');
        foreach ($post_include as $value) {
			if ($request->is_set_post($value) && empty($get_vars[$value])) {
	            $get_vars[$value] = $request->variable($value, "", false, \phpbb\request\request_interface::POST);
            }
        }
        //unset Joomla vars
        unset($get_vars['option'], $get_vars['Itemid'], $get_vars['jFusion_Route'], $get_vars['jfile']);
        $query_array = array();
        foreach ($get_vars as $key => $value) {
        	$query_array[] = $key . '=' . $value;
        }
        $query_string = implode('&', $query_array);
        $user->page['query_string'] = $query_string;
        if (empty($query_string)) {
            $user->page['page'] = $jfile;
        } else {
            $user->page['page'] = $jfile . '?' . $query_string;
        }
        //set the script path to allow for email notifications with correct URLs
        $Itemid = $mainframe->input->getInt('Itemid');
        //Get the base URL to the specific JFusion plugin
        $baseURL = JFusionFunction::getPluginURL($Itemid, false);
        if (substr($baseURL, -1) != '/') {
            //non-SEF mode
            global $source_url;
            $uri = new JURI($source_url);
            $path = $uri->getPath();
            if (substr($path, -1) != '/') {
                $path.= '/';
            }
            $user->page['script_path'] = $path;
            $user->page['root_script_path'] = $path;
            $user->host = $uri->getHost();
        } else {
            //SEF mode
            $uri = new JURI($baseURL);
            $path = $uri->getPath();
            $user->page['script_path'] = $path;
            $user->page['root_script_path'] = $path;
            $uri = new JURI(JURI::base());
            $user->host = $uri->getHost();
        }
    }

    /**
     * @static
     * @param $hook
     * @param $handle
     * @param bool $include_once
     */
    public static function template_display($hook, $handle, $include_once = true) {
        global $template, $jname;
        $params = JFusionFactory::getParams($jname);
        $lostpassword_url = $params->get('lostpassword_url');
        $register_url = $params->get('register_url');
        if (!empty($lostpassword_url)) {
            $template->_tpldata['.'][0]['U_SEND_PASSWORD'] = $lostpassword_url;
        }
        if (!empty($register_url)) {
            $template->_tpldata['.'][0]['U_REGISTER'] = $register_url;
        }
    }
    /**
     * Function not implemented
     *
     * @param string $errno
     * @param string $msg_text
     * @param string $errfile
     * @param string $errline
     */
    public static function msg_handler($errno, $msg_text, $errfile, $errline) {
        msg_handler($errno, $msg_text, $errfile, $errline);
    }
}
