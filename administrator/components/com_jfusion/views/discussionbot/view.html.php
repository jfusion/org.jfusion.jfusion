<?php
/**
* @package JFusion
* @subpackage Views
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.view');
/**
* Renders the a screen that allows the user to choose a JFusion integration method
* @package JFusion
*/
class jfusionViewdiscussionbot extends JViewLegacy
{
    /**
     * @param null $tpl
     * @return mixed
     */
    function display($tpl = null)
    {
    	//load language file
		JFusionFunction::loadLanguage('plg','content','jfusion');

	    $document = JFactory::getDocument();
	    $document->addScript('components/com_jfusion/views/'.$this->getName().'/tmpl/default.js');

		JHTML::_('behavior.modal');

        $mainframe = JFactory::getApplication();
 		$document	= JFactory::getDocument();
        $db			= JFactory::getDBO();
        $ename = JFactory::getApplication()->input->get('ename');
		$jname = JFactory::getApplication()->input->get('jname');

		switch ($ename) {
        	case 'pair_sections' :
        		$title = JText::_('ASSIGN_SECTION_PAIRS');
				$query = 'SELECT id, title as name FROM #__sections WHERE published = 1 AND scope = \'content\' ORDER BY title';
        		$db->setQuery($query);
        		$joomlaoptions = $db->loadObjectList('id');
				break;
        	case 'pair_categories' :
        		$title = JText::_('ASSIGN_CATEGORY_PAIRS');

		        $query	= $db->getQuery(true);
		        $query->select('a.id, a.title as name, a.level');
		        $query->from('#__categories AS a');
		        $query->where('a.parent_id > 0');
		        $query->where('extension = \'com_content\'');
		        $query->where('a.published = 1');
		        $query->order('a.lft');

		        $db->setQuery($query);
		        $joomlaoptions = $db->loadObjectList('id');
		        foreach ($joomlaoptions as &$item) {
			        $repeat = ( $item->level - 1 >= 0 ) ? $item->level - 1 : 0;
			        $item->name = str_repeat('- ', $repeat).$item->name;
		        }

        		break;
        	case 'pair_k2_categories':
        	    $title = JText::_('ASSIGN_K2_CATEGORY_PAIRS');

    	        $query = 'SELECT id, name as title, parent FROM #__k2_categories WHERE id > 0 AND trash = 0 AND published = 1';
	    	    $db->setQuery($query);
    			$items = $db->loadObjectList();
        		$children = array ();
        		if(count($items)){
        			foreach ($items as $v) {
        				$pt = $v->parent;
        				$list = @$children[$pt]?$children[$pt]: array ();
        				array_push($list, $v);
        				$children[$pt] = $list;
        			}
        		}

        		$joomlaoptions = jfusionViewdiscussionbot::buildRecursiveTree(0, '', array(), $children);
        	    break;
        	default:
        		return;
        }

		$hash = JFactory::getApplication()->input->get($ename);
	    $session = JFactory::getSession();
	    $encoded_pairs = $session->get($hash);
		if($encoded_pairs) {
			$pairs = unserialize(base64_decode($encoded_pairs));
		} else {
			$pairs = array();
		}

		//remove pair
		if(JFactory::getApplication()->input->getInt('remove')) {
			$joomlaid = JFactory::getApplication()->input->getInt('remove');
			unset($pairs[$joomlaid]);

			//recode pairs to be added as hidden var to make sure none are lost on submitting another pair
			$encoded_pairs = base64_encode(serialize($pairs));
			$session->set($hash, $encoded_pairs);
		} elseif (JFactory::getApplication()->input->getInt('joomlaid',0)) {
			//add submitted pair
			$joomlaid = JFactory::getApplication()->input->getInt('joomlaid');
			$forumid = JFactory::getApplication()->input->getInt('forumid');
			$pairs[$joomlaid] = $forumid;

			//recode pairs to be added as hidden var to make sure none are lost on submitting another pair
			$encoded_pairs = base64_encode(serialize($pairs));
			$session->set($hash, $encoded_pairs);
		}

		//get the forum listings
		$JFusionForum = JFusionFactory::getForum($jname);
	    try {
		    $forumSelectOptions = $JFusionForum->getForumList();
	    } catch (Exception $e) {
			JFusionFunction::raiseError($e);
		    $forumSelectOptions = array();
	    }

		//joomla select options
        $joomlaSelectOptions = $joomlaoptions;

		$document->addStyleSheet('components/com_jfusion/css/jfusion.css');
        $template = $mainframe->getTemplate();
		$document->addStyleSheet('templates/'.$template.'/css/general.css');
		$document->addStyleSheet('templates/'.$template.'/css/icon.css');
		$document->setTitle($title);
		$css = '.jfusion table.jfusionlist, table.jfusiontable{ font-size:11px; } .jfusion table.jfusionlist tbody tr td { vertical-align:top; }';
		$document->addStyleDeclaration($css);

		//prepare a toolbar
        $apply = JText::_('APPLY');
        $close = JText::_('CLOSE');
	    $toolbar = <<<HTML
	    <div class="btn-toolbar" id="toolbar">
			<div class="btn-group" id="toolbar-apply">
				<a href="#" onclick="window.parent.JFusion.submitParams('{$ename}', '{$encoded_pairs}')" class="btn btn-small btn-success">
					<i class="icon-apply icon-white">
					</i>
					{$apply}
				</a>
			</div>

			<div class="btn-group" id="toolbar-cancel">
				<a href="#" onclick="window.parent.SqueezeBox.close();" class="btn btn-small">
					<i class="icon-cancel ">
					</i>
				    {$close}
				</a>
			</div>
		</div>
HTML;

	    //assign references

	    $this->jname = $jname;
	    $this->toolbar = $toolbar;
	    $this->title = $title;
	    $this->joomlaoptions = $joomlaoptions;
	    $this->joomlaSelectOptions = $joomlaSelectOptions;
	    $this->forumSelectOptions = $forumSelectOptions;
	    $this->pairs = $pairs;

	    $this->ename = $ename;
	    $this->hash = $hash;

		parent::display($tpl);
	}

    /**
     * @static
     * @param $id
     * @param $indent
     * @param $list
     * @param $children
     * @param int $level
     * @return mixed
     */
    public static function buildRecursiveTree($id, $indent, $list, &$children, $level = 0)
	{
	    if (@$children[$id]) {
    		foreach ($children[$id] as $v)
    		{
    			$id = $v->id;
                $pre	= '- ';
    			if ($v->parent == 0) {
    				$txt	= $v->title;
    			} else {
    				$txt	= $pre . $v->title;
    			}
    			$pt = $v->parent;
    			$list[$id] = $v;
    			$list[$id]->name = $indent.$txt;
    			$list = jfusionViewdiscussionbot::buildRecursiveTree($id, $indent . '- ', $list, $children, $level+1);
    		}
	    }

		return $list;
	}
}
