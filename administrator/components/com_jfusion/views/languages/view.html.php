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


        $db = JFactory::getDBO();
        $query = "SELECT element, manifest_cache FROM `#__extensions` WHERE `name` LIKE 'jfusion' AND `type` LIKE 'language' AND `client_id` =1";
        $db->setQuery($query);
        $results = $db->loadObjectList();

        $lang_installed = array();
        if(!empty($results)){
            foreach ($results as $result){
                $cache = json_decode($result->manifest_cache);
                $lang_installed[$result->element] = $cache->creationDate;
            }

        }

        /**
         * @ignore
         * @var $parser JSimpleXML
         */
        $jfusionurl = new stdClass;
        $jfusionurl->url = 'http://update.jfusion.org/';
        $jfusionurl->data = JFusionFunctionAdmin::getFileData($jfusionurl->url);
        $parser = JFactory::getXMLParser('Simple');

        $lang_repo = array();
        //die(htmlspecialchars($jfusionurl->data));
        if ($parser->loadString($jfusionurl->data)) {
            if (isset($parser->document)) {
                //$JFusionVersionInfo = $parser->document;
                //$language_data = $JFusionVersionInfo->getElementByPath('languages');
                $languages = $parser->document->getElementByPath('languages')->children();
                foreach ($languages as $language) {
                    $name = $language->attributes('tag');
                    $lang_repo[$name]['file'] = $language->getElementByPath('remotefile')->data();
                    $lang_repo[$name]['date'] = $language->getElementByPath('creationDate')->data();
                    $lang_repo[$name]['description'] = $language->getElementByPath('description')->data();
                }
            }
        }

        unset($parser);
        ob_end_clean();

        $this->assignRef('lang_installed', $lang_installed);
        $this->assignRef('lang_repo', $lang_repo);

        parent::display($tpl);
    }
}
