<?php namespace JFusion\Plugins\dokuwiki;

/**
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage JoomlaExt 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
use JFusion\Plugin\Platform\Joomla;
use JRegistry;
use JText;

defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Authentication Class for an external Joomla database
 * For detailed descriptions on these functions please check the model.abstractauth.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Joomla_ext
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Platform_Joomla extends Joomla
{
	/**
	 * @var $helper Helper
	 */
	var $helper;

	/************************************************
	 * For JFusion Search Plugin
	 ***********************************************/
	/**
	 * Retrieves the search results to be displayed.  Placed here so that plugins that do not use the database can retrieve and return results
	 * @param string &$text string text to be searched
	 * @param string &$phrase string how the search should be performed exact, all, or any
	 * @param JRegistry &$pluginParam custom plugin parameters in search.xml
	 * @param int $itemid what menu item to use when creating the URL
	 * @param string $ordering
	 *
	 * @return array of results as objects
	 *
	 * Each result should include:
	 * $result->title = title of the post/article
	 * $result->section = (optional) section of  the post/article (shows underneath the title; example is Forum Name / Thread Name)
	 * $result->text = text body of the post/article
	 * $result->href = link to the content (without this, joomla will not display a title)
	 * $result->browsernav = 1 opens link in a new window, 2 opens in the same window
	 * $result->created = (optional) date when the content was created
	 */
	function getSearchResults(&$text, &$phrase, JRegistry &$pluginParam, $itemid, $ordering) {
		$highlights = array();
		$search = new Search($this->getJname());
		$results = $search->ft_pageSearch($text, $highlights);
		//pass results back to the plugin in case they need to be filtered

		$this->filterSearchResults($results, $pluginParam);
		$rows = array();
		$pos = 0;

		foreach ($results as $key => $index) {
			$rows[$pos]->title = JText::_($key);
			$rows[$pos]->text = $search->getPage($key);
			$rows[$pos]->created = $search->getPageModifiedDateTime($key);
			//dokuwiki doesn't track hits
			$rows[$pos]->hits = 0;
			$rows[$pos]->href = Framework::routeURL(str_replace(':', ';', $this->getSearchResultLink($key)), $itemid);
			$rows[$pos]->section = JText::_($key);
			$pos++;
		}
		return $rows;
	}

	/**
	 * @param mixed $post
	 *
	 * @return string
	 */
	function getSearchResultLink($post) {
		return 'doku.php?id=' . $post;
	}
}
