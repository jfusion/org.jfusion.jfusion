<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage MyBB
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Public Class for MyBB
 * For detailed descriptions on these functions please check the model.abstractpublic.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage MyBB
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionPublic_mybb extends JFusionPublic {
    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'mybb';
    }

    /**
     * @return string
     */
    function getRegistrationURL() {
        return 'member.php?action=register';
    }

    /**
     * @return string
     */
    function getLostPasswordURL() {
        return 'member.php?action=lostpw';
    }

    /**
     * @return string
     */
    function getLostUsernameURL() {
        return 'member.php?action=lostpw';
    }

    /**
     * @params object $data
     */

    /* temp disabled native frameless
    function getBuffer(&$data) {
        // Get the path
        $params = JFusionFactory::getParams($this->getJname());
        $source_path = $params->get('source_path');
        //get the filename
        $jfile = JRequest::getVar('jfile' , 'index.php');
        if (!$jfile) {
            $jfile = 'index.php';
        }
        //combine the path and filename
        if (substr($source_path, -1) == DIRECTORY_SEPARATOR) {
            $index_file = $source_path . $jfile;
        } else {
            $index_file = $source_path . DIRECTORY_SEPARATOR . $jfile;
        }
        if (!is_file($index_file)) {
            JFusionFunction::raiseWarning(500, 'The path to the requested does not exist');
        } else {
            //set the current directory to MyBB
            chdir($source_path);
            // set scope for variables required later
            global $mybb, $theme, $templates, $db, $lang, $plugins, $session, $cache;
            global $debug, $templatecache, $templatelist, $maintimer, $globaltime, $parsetime;
            // Get the output
            ob_start();
            include_once ($index_file);
            $data->buffer = ob_get_contents();
            ob_end_clean();
            //change the current directory back to Joomla.
            chdir(JPATH_SITE);
        }
    }
*/

    /**
     * @param object $data
     *
     * @return void
     */
    function parseBody(&$data) {
        $regex_body = array();
        $replace_body = array();
        $callback_body = array();
        $params = JFusionFactory::getParams($this->getJname());

        $regex_body[] = '#action="(.*?)"(.*?)>#m';
        $replace_body[] = '';//$this->fixAction("index.php$1","$2","' . $data->baseURL . '")';
        $callback_body[] = 'fixAction';

        $regex_body[]	= '#(?<=href=["\'])[./|/](.*?)(?=["\'])#mS';
        $replace_body[] = '';
        $callback_body[] = 'fixUrl';

        $regex_body[] = '#(?<=href=["\'])(?!\w{0,10}://|\w{0,10}:)(.*?)(?=["\'])#mSi';
        $replace_body[] = '';
        $callback_body[] = 'fixUrl';

        $regex_body[]	= '#(?<=href=["\'])'.$data->integratedURL.'(.*?)(?=["\'])#m';
        $replace_body[] = '';
        $callback_body[] = 'fixUrl';

        $regex_body[]	= '#(?<=href=\\\")'.$data->integratedURL.'(.*?)(?=\\\")#mS';
        $replace_body[] = '';
        $callback_body[] = 'fixUrl';

        $regex_body[] = '#(src)=["\'][./|/](.*?)["\']#mS';
        $replace_body[] = '$1="' . $data->integratedURL . '$2"';
        $callback_body[] = '';

        $regex_body[] = '#(src)=["\'](?!\w{0,10}://|\w{0,10}:)(.*?)["\']#mS';
        $replace_body[] = '$1="' . $data->integratedURL . '$2"';
        $callback_body[] = '';

        foreach ($regex_body as $k => $v) {
            //check if we need to use callback
            if(!empty($callback_body[$k])){
                $data->body = preg_replace_callback($regex_body[$k],array( &$this,$callback_body[$k]), $data->body);
            } else {
                $data->body = preg_replace($regex_body[$k], $replace_body[$k], $data->body);
            }
        }
    }

    /**
     * @param object $data
     *
     * @return void
     */
    function parseHeader(&$data) {
        static $regex_header, $replace_header;
        if (!$regex_header || !$replace_header) {
            // Define our preg arrays
            $regex_header = array();
            $replace_header = array();
            $callback_header = array();

            //fix for URL redirects
            $regex_header[] = '#(?<=<meta http-equiv="refresh" content=")(.*?)(?=")#mi';
            $replace_header[] = ''; //$this->fixRedirect("$1","' . $data->baseURL . '")';
            $callback_header[] = 'fixRedirect';
        }
        foreach ($regex_header as $k => $v) {
            //check if we need to use callback
            if(!empty($callback_header[$k])){
                $data->header = preg_replace_callback($regex_header[$k],array( &$this,$callback_header[$k]), $data->header);
            } else {
                $data->header = preg_replace($regex_header[$k], $replace_header[$k], $data->header);
            }
        }
    }
}
