<?php

/**
 * This is view file for versioncheck
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Versioncheck
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Renders the main admin screen that shows the configuration overview of all integrations
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Versioncheck
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class jfusionViewlanguages extends JView
{
    /**
     * displays the view
     *
     * @param string $tpl template name
     *
     * @return mixed|string html output of view
     */
    function display($tpl = null)
    {
        //get the jfusion news
        ob_start();

	    jimport('joomla.version');
	    $jversion = new JVersion();
        $data = JFusionFunctionAdmin::getFileData('http://update.jfusion.org/jfusion/joomla/?version='.$jversion->getShortVersion());

        $lang_repo = array();
	    $xml = JFusionFunction::getXml($data,false);
        if ($xml) {
	        $languages = $xml->getElementByPath('languages')->children();
	        /**
	         * @ignore
	         * @var $language JSimpleXMLElement
	         */
	        foreach ($languages as $language) {
		        $name = $language->attributes('tag');

		        $lang = new stdClass;
		        $lang->file = $language->getElementByPath('remotefile')->data();
		        $lang->date = $language->getElementByPath('creationdate')->data();
		        $lang->description = $language->getElementByPath('description')->data();
		        $lang->progress = $language->getElementByPath('progress')->data();
		        $lang->translateurl = $language->getElementByPath('translateurl')->data();
		        $lang->currentdate = null;
		        $lang->class = 'row';

		        $lang_repo[$name] = $lang;
	        }
        }

        if (JFusionFunction::isJoomlaVersion('2.5')) {
            $db = JFactory::getDBO();
            $query = 'SELECT element, manifest_cache FROM #__extensions WHERE name LIKE \'jfusion %\' AND type LIKE \'file\' AND client_id = 0';
            $db->setQuery($query);
            $results = $db->loadObjectList();

            if(!empty($results)) {
                foreach ($results as $result) {
                    if (isset($lang_repo[$result->element])) {
                        $cache = json_decode($result->manifest_cache);
                        $lang_repo[$result->element]->currentdate = $cache->creationDate;

                        if ( $lang_repo[$result->element]->currentdate == $lang_repo[$result->element]->date ) {
                            $lang_repo[$result->element]->class = 'good';
                        } else {
                            $lang_repo[$result->element]->class = 'bad';
                        }
                    }
                }
            }
        } else {
            $path = JPATH_ADMINISTRATOR.DS.'language';
            $paths = JFolder::folders($path);
            foreach ($paths as $tag) {
	            $xml = JFusionFunction::getXml($path.DS.$tag.DS.$tag.'.com_jfusion.xml');
                if ($xml) {
                    $date = $xml->getElementByPath('creationdate')->data();
                    if ( $date) {
                        $lang_repo[$tag]->currentdate = $date;
                    }
                }
            }
        }
        ob_end_clean();

        $this->assignRef('lang_repo', $lang_repo);

        parent::display($tpl);
    }
}
