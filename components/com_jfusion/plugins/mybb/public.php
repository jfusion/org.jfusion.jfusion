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
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'mybb';
    }
    function getRegistrationURL() {
        return 'member.php?action=register';
    }
    function getLostPasswordURL() {
        return 'member.php?action=lostpw';
    }
    function getLostUsernameURL() {
        return 'member.php?action=lostpw';
    }
    function &getBuffer() {
        // Get the path
        $params = JFusionFactory::getParams($this->getJname());
        $source_path = $params->get('source_path');
        //get the filename
        $jfile = JRequest::getVar('jfile', '', 'GET', 'STRING');
        if (!$jfile) {
            //use the default index.php
            $jfile = 'index.php';
        }
        //combine the path and filename
        if (substr($source_path, -1) == DS) {
            $index_file = $source_path . $jfile;
        } else {
            $index_file = $source_path . DS . $jfile;
        }
        if (!is_file($index_file)) {
            JError::raiseWarning(500, 'The path to the requested does not exist');
            return null;
        }
        //set the current directory to MyBB
        chdir($source_path);
        /* set scope for variables required later */
        define('IN_PHPBB', true);
        global $phpbb_root_path, $phpEx, $db, $config, $user, $auth, $cache, $template;
        // Get the output
        ob_start();
        include_once ($index_file);
        $buffer = ob_get_contents();
        ob_end_clean();
        //change the current directory back to Joomla.
        chdir(JPATH_SITE);
        return $buffer;
    }
    function parseBody(&$buffer, $baseURL, $fullURL, $integratedURL) {
        static $regex_body, $replace_body;
        if (!$regex_body || !$replace_body) {
            // Define our preg arrayshttp://www.jfusion.org/administrator/index.php?option=com_extplorer#
            $regex_body = array();
            $replace_body = array();
            //convert relative links with query into absolute links
            $regex_body[] = '#href="./(.*)\?(.*)"#mS';
            $replace_body[] = 'href="' . $baseURL . '&jfile=$1&$2"';
            //convert relative links without query into absolute links
            $regex_body[] = '#href="./(.*)"#mS';
            $replace_body[] = 'href="' . $baseURL . '&jfile=$1"';
            //convert relative links from images into absolute links
            $regex_body[] = '#(src="|url\()./(.*)("|\))#mS';
            $replace_body[] = '$1' . $integratedURL . '$2$3"';
            //convert links to the same page with anchors
            $regex_body[] = '#href="\#(.*?)"#';
            $replace_body[] = 'href="' . $fullURL . '&#$1"';
            //update site URLs to the new Joomla URLS
            $regex_body[] = '#'.preg_quote($integratedURL,'#').'(.*)\?(.*)\"#mS';
            $replace_body[] = $baseURL . '&jfile=$1&$2"';
            //convert action URLs inside forms to absolute URLs
            //$regex_body[]    = '#action="(.*)"#mS';
            //$replace_body[]    = 'action="'.$integratedURL.'/"';
            
        }
        $buffer = preg_replace($regex_body, $replace_body, $buffer);
    }
    function parseHeader(&$buffer, $baseURL, $fullURL, $integratedURL) {
        static $regex_header, $replace_header;
        if (!$regex_header || !$replace_header) {
            // Define our preg arrays
            $regex_header = array();
            $replace_header = array();
            //convert relative links into absolute links
            $regex_header[] = '#(href|src)=("./|"/)(.*?)"#mS';
            $replace_header[] = 'href="' . $integratedURL . '$3"';
            //$regex_header[]    = '#(href|src)="(.*)"#mS';
            //$replace_header[]    = 'href="'.$integratedURL.'$2"';
            
        }
        $buffer = preg_replace($regex_header, $replace_header, $buffer);
    }
}
