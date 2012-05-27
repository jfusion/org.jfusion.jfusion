<?php
/**
* @package JFusion
* @subpackage Elements
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die( 'Restricted access' );

/**
* Require the Jfusion plugin factory
*/
require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.factory.php');
require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.jfusion.php');

/**
* Defines the jfusion usergroup assignments parameter
* @package JFusion
*/
class JFormFieldDiscussionbotparam extends JFormField
{
	/**
	 * Element name
	 *
	 * @access	protected
	 * @var		string
	 */
	public $type = "Discussionbotparam";
    /**
     * Get an element
     *
     * @return string html
     */
	function getInput()
	{
		$mainframe = JFactory::getApplication();

		$db			= JFactory::getDBO();
		$doc 		= JFactory::getDocument();
		$fieldName = $this->formControl.'['.$this->group.'][' . $this->fieldname . ']';
        $name = (string) $this->fieldname;
        $value = $this->value;

	    $query = "SELECT params FROM #__extensions WHERE element = 'jfusion' AND folder = 'content'";

        $db->setQuery($query);
        $results = $db->loadResult();
        if($results) {
			$registry = new JRegistry;
			$registry->loadString($results);
			$params = $registry->toArray();
            $jname = (isset($params['jname'])) ? $params['jname'] : '';
        }

	 	if(empty($jname)) {
	 		return "<span style='float:left; margin: 5px 0; font-weight: bold;'>".JText::_('NO_PLUGIN_SELECT')."</span>";
	 	} else {
	 		static $db_js_loaded;
	 		if(empty($db_js_loaded)) {
				$js = "
				function jDiscussionParamSet(name, base64) {
					var link = 'index.php?option=com_jfusion&task=discussionbot&tmpl=component&jname={$jname}&ename='+name+'&'+name+'=';
					link += base64;
					document.getElementById(name + '_id').value = base64;
					document.getElementById(name + '_link').href = link;
					document.getElementById(name + '_img').src = 'components/com_jfusion/images/filesave.png';
					SqueezeBox.close();
				}";
				$doc->addScriptDeclaration($js);
				$db_js_loaded = 1;
	 		}

			$link = 'index.php?option=com_jfusion&amp;task=discussionbot&amp;tmpl=component&amp;jname='.$jname.'&amp;ename='.$name.'&amp;'.$name.'='.$value;

			JHTML::_('behavior.modal', 'a.modal');
			$html  = '<div class="button2-left"><div class="blank"><a class="modal" id="'.$name.'_link" title="'.JText::_('ASSIGN_PAIRS').'"  href="'.$link.'" rel="{handler: \'iframe\', size: {x: 650, y: 375}}">'.JText::_('ASSIGN_PAIRS').'</a></div></div>'."\n";
			if(!empty($params[$name])) {
				$src = "components/com_jfusion/images/tick.png";
			} else {
				$src = "components/com_jfusion/images/clear.png";
			}
			$html .= '<img id="'.$name.'_img" src="'.$src.'"><input type="hidden" id="'.$name.'_id" name="'.$fieldName.'" value="'.$value.'" />'."\n";

			return $html;
	 	}
	}
}
