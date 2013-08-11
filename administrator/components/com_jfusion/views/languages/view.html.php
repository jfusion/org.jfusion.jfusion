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
class jfusionViewlanguages extends JViewLegacy
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

	    JHTML::_('behavior.modal', 'a.modal');
	    JHtml::_('behavior.framework', true);

	    $document = JFactory::getDocument();
	    $document->addScript('components/com_jfusion/views/'.$this->getName().'/tmpl/default.js');
	    JFusionFunction::loadJavascriptLanguage(array('INSTALL_UPGRADE_LANGUAGE_PACKAGE','INSTALL'));

	    jimport('joomla.version');
	    $jversion = new JVersion();
        $data = JFusionFunctionAdmin::getFileData('http://update.jfusion.org/jfusion/joomla/?version='.$jversion->getShortVersion());

        $lang_repo = array();
	    $xml = JFusionFunction::getXml($data,false);
        if ($xml) {
	        if ($xml->languages) {
		        $languages = $xml->languages->children();

		        /**
		         * @ignore
		         * @var $language SimpleXMLElement
		         */
		        foreach ($languages as $key => $language) {
			        $att = $language->attributes();
			        $lang = new stdClass;
			        $lang->file = (string)$language->remotefile;
			        $lang->date = (string)$language->creationdate;
			        $lang->description = (string)$language->description;
			        $lang->progress = (string)$language->progress;
			        $lang->translateurl = (string)$language->translateurl;
			        $lang->currentdate = null;
			        $lang->extension_id = null;
			        $lang->class = 'row';

			        $lang_repo[(string)$att['tag']] = $lang;
		        }
	        }
        }

	    $db = JFactory::getDBO();
	    $query = 'SELECT element, manifest_cache, extension_id FROM #__extensions WHERE name LIKE \'jfusion %\' AND type LIKE \'file\' AND client_id = 0';
	    $db->setQuery($query);
	    $results = $db->loadObjectList();

	    if(!empty($results)) {
		    foreach ($results as $result) {
			    if (isset($lang_repo[$result->element])) {
				    $cache = json_decode($result->manifest_cache);
				    $lang_repo[$result->element]->currentdate = $cache->creationDate;
				    $lang_repo[$result->element]->extension_id = $result->extension_id;

				    if ( $lang_repo[$result->element]->currentdate == $lang_repo[$result->element]->date ) {
					    $lang_repo[$result->element]->class = 'good';
				    } else {
					    $lang_repo[$result->element]->class = 'bad';
				    }
			    }
		    }
	    }
        ob_end_clean();
	    ksort($lang_repo);
	    $this->lang_repo = $lang_repo;

        parent::display($tpl);
    }
}
