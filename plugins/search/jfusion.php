<?php

  /**
 * This is the jfusion user plugin file
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    Plugins
 * @subpackage Search
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');
/**
 * Load the JFusion framework
 */
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.factory.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusion.php';

//get the name of each plugin and add to areas

if(JFusionFunction::isJoomlaVersion('1.6')) {
    /**
	 * Content Search plugin
	 *
	 * @package     Joomla.Plugin
	 * @subpackage  Search.content
	 * @since       1.6
	 */
	class plgSearchJfusion extends JPlugin
	{
	    function plgSearchJfusion(&$subject, $config)
	    {
	        parent::__construct($subject, $config);
	        //load the language
	        $this->loadLanguage('plg_search_jfusion', JPATH_BASE);
	    }   
	    
	    /**
	     * @return array An array of search areas
	     */
	    function onContentSearchAreas()
	    {
	        return plgSearchjfusionAreas();
	    }
	
	    /**
	     * Content Search method
	     * The sql must return the following fields that are used in a common display
	     * routine: href, title, section, created, text, browsernav
	     * @param string Target search string
	     * @param string mathcing option, exact|any|all
	     * @param string ordering option, newest|oldest|popular|alpha|category
	     * @param mixed An array if the search it to be restricted to areas, null if search all
	     */
	    function onContentSearch($text, $phrase='', $ordering='', $areas=null)
	    {
	        return plgSearchjfusion($text,$phrase,$ordering,$areas);
	    }
	}
} else {
	$mainframe = JFactory::getApplication();
	$mainframe->registerEvent('onSearch', 'plgSearchjfusion');
	$mainframe->registerEvent('onSearchAreas', 'plgSearchjfusionAreas');
    JFusionFunction::loadLanguage('plg','search','jfusion');
}

/**
 * Called by joomla framework define the area that can be search in
 *
 * @return array search areas
 */
function &plgSearchjfusionAreas()
{
    static $areas = array();
    //get the softwares with search enabled
    $plugins = JFusionFunction::getPlugins('search');
    $searchplugin = & JPluginHelper::getPlugin('search', 'jfusion');
    $params = new JParameter($searchplugin->params);
    $enabledPlugins = unserialize(base64_decode($params->get('JFusionPluginParam')));
    if (is_array($plugins)) {
        foreach ($plugins as $plugin) {
            if (is_array($enabledPlugins) && array_key_exists($plugin->name, $enabledPlugins)) {
                if ($plugin->name != "joomla_int") {
                    //make sure that search is enabled
                    $title = (!empty($enabledPlugins[$plugin->name]['title'])) ? $enabledPlugins[$plugin->name]['title'] : $plugin->name;
                    $areas[$plugin->name] = $title;
                }
            }
        }
    }
    return $areas;
}
/**
 * Search Function Called by the joomla framework
 *
 * @param string $text     Search text
 * @param string $phrase   Phrases
 * @param string $ordering How to order the search results: null, oldest, popular, category, alpha, or newest
 * @param array  $areas    What area to search
 *
 * @return array with results
 */
function plgSearchjfusion($text, $phrase = '', $ordering = '', $areas = null)
{
    //no text to search
    if (!$text) {
        return array();
    }
    $searchPlugins = plgSearchjfusionAreas();
    if (is_array($areas)) {
        //jfusion plugins to search
        $searchPlugins = array_intersect($areas, array_keys($searchPlugins));
        if (empty($searchPlugins)) {
            return array();
        }
    } else {
        //we need to extract the keys since they are the jnames
        $searchPlugins = array_keys($searchPlugins);
    }
    //get the search plugin parameters
    $plugin = & JPluginHelper::getPlugin('search', 'jfusion');
    $params = new JParameter($plugin->params);
    $pluginParamValue = $params->get('JFusionPluginParam');
    $pluginParamValue = unserialize(base64_decode($pluginParamValue));
    //To hold all the search results
    $sortableResults = array();
    //special array to hold the results that cannot be sorted based on $ordering
    $unsortableResults = array();

    //fields required in order to be able to sort
    switch ($ordering) {
    case 'category':
            $sortField = 'section';
        break;
    case 'alpha':
            $sortField = 'title';
        break;
    case 'popular':
            $sortField = 'hits';
        break;
    case 'oldest':
    case 'newest':
    default:
            $sortField = 'created';
        break;
    }

    foreach ($searchPlugins AS $jname) {
        $searchMe = & JFusionFactory::getPublic($jname);
        if (is_array($pluginParamValue)) {
            $pluginParam = new JParameter('');
            $pluginParam->loadArray($pluginParamValue[$jname]);
        } else {
            $pluginParam = new JParameter('');
        }
        $itemid = & $pluginParam->get('itemid');
        $results = $searchMe->getSearchResults($text, $phrase, $pluginParam, $itemid, $ordering);
        if (is_array($results)) {
            //check to see if the results contain the appropriate field
            if (isset($results[0]->$sortField)) {
        	    $sortableResults = array_merge($sortableResults, $results);
            } else {
                $unsortableResults = array_merge($unsortableResults, $results);
            }
    	}
    }

    //sort the results
    jimport('joomla.utilities.array');
    switch ($ordering) {
    case 'oldest':
            JArrayHelper::sortObjects($sortableResults, 'created');
        break;
    case 'category':
            JArrayHelper::sortObjects($sortableResults, 'section');
        break;
    case 'alpha':
            JArrayHelper::sortObjects($sortableResults, 'title');
        break;
    case 'popular':
            JArrayHelper::sortObjects($sortableResults, 'hits');
        break;
    case 'newest':
    default:
            JArrayHelper::sortObjects($sortableResults, 'created', -1);
        break;
    }

    //tak on unsortable results to the end
    $searchResults = array_merge($sortableResults, $unsortableResults);
    return $searchResults;
}