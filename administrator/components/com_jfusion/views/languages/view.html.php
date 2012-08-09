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
                /**
                 * @ignore
                 * @var $language JSimpleXMLElement
                 */
                foreach ($languages as $language) {
                    $name = $language->attributes('tag');

                    $lang = new stdClass;
                    $lang->file = $language->getElementByPath('remotefile')->data();
                    $lang->date = $language->getElementByPath('creationDate')->data();
                    $lang->description = $language->getElementByPath('description')->data();
                    $lang->currentdate = null;
                    $lang->class = 'bad';

                    $lang_repo[$name] = $lang;
                }
            }
        }

        $db = JFactory::getDBO();
        $query = "SELECT element, manifest_cache FROM `#__extensions` WHERE `name` LIKE 'jfusion' AND `type` LIKE 'language' AND `client_id` =1";
        $db->setQuery($query);
        $results = $db->loadObjectList();

        $lang_installed = array();
        if(!empty($results)) {
            foreach ($results as $result) {
                if (isset($lang_repo[$result->element])) {
                    $cache = json_decode($result->manifest_cache);
                    $lang_repo[$result->element]->currentdate = $cache->creationDate;

                    if ( $lang_repo[$result->element]->currentdate != $lang_repo[$result->element]->date ) {
                        $lang_repo[$result->element]->class = 'bad';
                    } else {
                        $lang_repo[$result->element]->class = 'good';
                    }
                }

            }
        }

        unset($parser);
        ob_end_clean();

        $this->assignRef('lang_repo', $lang_repo);

        parent::display($tpl);
    }
}
