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
	 * @var $lang_repo array
	 */
	var $lang_repo = array();

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

	    $document = JFactory::getDocument();
	    $document->addScript('components/com_jfusion/views/' . $this->getName() . '/tmpl/default.js');
	    JFusionFunction::loadJavascriptLanguage(array('INSTALL_UPGRADE_LANGUAGE_PACKAGE', 'INSTALL', 'UNINSTALL_UPGRADE_LANGUAGE_PACKAGE', 'UNINSTALL'));

	    jimport('joomla.version');
	    $jversion = new JVersion();
        $data = \JFusion\Framework::getFileData('http://update.jfusion.org/jfusion/joomla/?version=' . $jversion->getShortVersion());

	    try {
		    $xml = \JFusion\Framework::getXml($data, false);
	    } catch (Exception $e) {
		    $xml = null;
	    }

        if ($xml) {
	        if ($xml->languages) {
		        $languages = $xml->languages->children();

		        /**
		         * @var $language SimpleXMLElement
		         */
		        foreach ($languages as $language) {
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

			        $this->lang_repo[(string)$att['tag']] = $lang;
		        }
	        }
        }

	    $db = JFactory::getDBO();

	    $query = $db->getQuery(true)
		    ->select('element, manifest_cache, extension_id')
		    ->from('#__extensions')
		    ->where('name LIKE ' . $db->quote('jfusion %'))
	        ->where('type LIKE ' . $db->quote('file'))
		    ->where('client_id = 0');

	    $db->setQuery($query);
	    $results = $db->loadObjectList();

	    if(!empty($results)) {
		    foreach ($results as $result) {
			    if (isset($this->lang_repo[$result->element])) {
				    $cache = json_decode($result->manifest_cache);
				    $this->lang_repo[$result->element]->currentdate = $cache->creationDate;
				    $this->lang_repo[$result->element]->extension_id = $result->extension_id;

				    if ($this->lang_repo[$result->element]->currentdate == $this->lang_repo[$result->element]->date) {
					    $this->lang_repo[$result->element]->class = 'good';
				    } else {
					    $this->lang_repo[$result->element]->class = 'bad';
				    }
			    }
		    }
	    }
        ob_end_clean();
	    ksort($this->lang_repo);

        parent::display($tpl);
    }
}
