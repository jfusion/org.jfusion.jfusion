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
require_once(JPATH_ADMINISTRATOR .DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_jfusion'.DIRECTORY_SEPARATOR.'models'.DIRECTORY_SEPARATOR.'model.factory.php');
require_once(JPATH_ADMINISTRATOR .DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_jfusion'.DIRECTORY_SEPARATOR.'models'.DIRECTORY_SEPARATOR.'model.jfusion.php');

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
	public $type = 'Discussionbotparam';
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

	    $query = 'SELECT params FROM #__extensions WHERE element = \'jfusion\' AND folder = \'content\'';

        $db->setQuery($query);
        $results = $db->loadResult();
        if($results) {
			$registry = new JRegistry($results);
			$params = $registry->toArray();
            $jname = (isset($params['jname'])) ? $params['jname'] : '';
        }

	 	if(empty($jname)) {
	 		return '<span style="float:left; margin: 5px 0; font-weight: bold;">'.JText::_('NO_PLUGIN_SELECT').'</span>';
	 	} else {
	 		static $js_loaded;

	 		if(empty($js_loaded)) {
                $js = <<<JS
                function jDiscussionParamSet(name, base64) {
					$(name + '_id').value = base64;
					$(name + '_img').set('src', 'components/com_jfusion/images/filesave.png');
					SqueezeBox.close();
				}
JS;
				$doc->addScriptDeclaration($js);
			    $js_loaded = 1;
	 		}

		    jimport( 'joomla.user.helper' );
		    $hash = JUtility::getHash( $name.JUserHelper::genRandomPassword());
		    $session = JFactory::getSession();
		    $session->set($hash, $value);

			$link = 'index.php?option=com_jfusion&amp;task=discussionbot&amp;tmpl=component&amp;jname='.$jname.'&amp;ename='.$name.'&amp;'.$name.'='.$hash;

			JHTML::_('behavior.modal', 'a.modal');

            $assign_paits = JText::_('ASSIGN_PAIRS');

            if(!empty($params[$name])) {
                $src = 'components/com_jfusion/images/tick.png';
            } else {
                $src = 'components/com_jfusion/images/clear.png';
            }

            $html =<<<HTML
			<div class="button2-left">
			    <div class="blank">
			        <a class="modal" id="{$name}_link" title="{$assign_paits}"  href="{$link}" rel="{handler: 'iframe', size: {x: 650, y: 375}}">{$assign_paits}</a>
                </div>
            </div>
			<img id="{$name}_img" src="{$src}">
			<input type="hidden" id="{$name}_id" name="{$fieldName}" value="{$value}" />
HTML;
			return $html;
	 	}
	}
}
