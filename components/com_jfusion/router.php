<?php

/**
 * This is file that creates URLs for the jfusion component
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Router
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');


//load params class
jimport('joomla.html.parameter');

/**
 * build the SEF URL
 *
 * @param array &$query query to build
 *
 * @return string URL
 */
function jfusionBuildRoute(&$query)
{
    $segments = array();
    //make sure the url starts with the filename
    if (isset($query['jfile'])) {
        $segments[] = $query['jfile'];
        unset($query['jfile']);
    }
    foreach ($query as $key => $value) {
        if ($key != 'option' && $key != 'Itemid' && $key != 'lang') {
            if (is_array($value)) {
                foreach ($value as $array_key => $array_value) {
                    $segments[] = $key . '[' . $array_key . '],' . $array_value;
                    unset($query[$key]);
                }
            } else {
                $segments[] = $key . ',' . $value;
                unset($query[$key]);
            }
        }
    }
    if (count($segments)) {
	    $config = JFactory::getConfig();
	    $sef_suffix = $config->get('sef_suffix');
	    if (!$sef_suffix) {
		    $segments[count($segments) - 1].= '/';
	    }
    }

    if (defined('ROUTED_JNAME')) {
        $public = JFusionFactory::getPublic(ROUTED_JNAME);
        $public->buildRoute($segments);
    }

    return $segments;
}

/**
 * reconstruct the SEF URL
 *
 * @param array $segments segments to parse
 *
 * @throws RuntimeException
 * @return string vars
 */
function jfusionParseRoute($segments)
{
    //needed to force Joomla to use JDocumentHTML when adding a .html suffix is enabled
	JFactory::getApplication()->input->set('format', 'html');

    $vars = array();
	JFactory::getApplication()->input->set('jFusion_Route', serialize($segments));
    if (isset($segments[0])) {
        if (!strpos($segments[0], ',') && !strpos($segments[0], '&')) {
            $vars['jfile'] = $segments[0];
            //check to see if file has extention (fix for add suffix mode)
            $ext = pathinfo($vars['jfile'], PATHINFO_EXTENSION);
            if (!strlen($ext) == 3 && !strlen($ext) == 4 ) {
            	//add a default extention
            	$vars['jfile'] .= '.php';
            }
        }
        unset($segments[0]);

        //parse all other segments
        if (!empty($segments)) {
            foreach ($segments as $segment) {
                $parts = explode(',', $segment);
                if (isset($parts[1])) {
                    //check for an array
                    if (strpos($parts[0], '[')) {
                        //prepare the variable
                        $array_parts = explode('[', $parts[0]);
                        $array_index = substr_replace($array_parts[1], '', -1);
                        //set the variable
                        if (empty($vars[$array_parts[0]])) {
                            $vars[$array_parts[0]] = array();
                        }
                        $vars[$array_parts[0]][$array_index] = $parts[1];
                    } else {
                        $vars[$parts[0]] = $parts[1];
                    }
                }
            }
        }
    }

    unset($segments);
    
    $menu = JMenu::getInstance('site');
    $item = $menu->getActive();
    $vars += $item->query;

	foreach ($vars as $key => $var) {
		$_GET[$key] = $var;
	}

	if ($vars['view'] == 'plugin') {
	    $JFusionPluginParam = $item->params->get('JFusionPluginParam');
	    if (empty($JFusionPluginParam)) {
		    throw new RuntimeException(JText::_('ERROR_PLUGIN_CONFIG'));
	    } else {
	        //load custom plugin parameter
	        $jPluginParam = new JRegistry('');
	        $jPluginParam->loadArray(unserialize(base64_decode($JFusionPluginParam)));
	        $jname = $jPluginParam->get('jfusionplugin');

		    require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'import.php';
	
	        if (!empty($jname)) {
	            $public = JFusionFactory::getPublic($jname);
	            $public->parseRoute($vars);
	        }

		    if (!defined('ROUTED_JNAME')) {
			    define('ROUTED_JNAME', $jname);
		    }
	    }
	}
    return $vars;
}
