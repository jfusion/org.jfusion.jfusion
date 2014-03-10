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
	 * @var string $jname
	 */
	var $jname;

	/**
	 * @var string $title
	 */
	var $title;

	/**
	 * @var string $ename
	 */
	var $ename;

	/**
	 * @var string $hash
	 */
	var $hash;

	/**
	 * @var array $pairs
	 */
	var $pairs = array();

	/**
	 * @var array $joomlaoptions
	 */
	var $joomlaoptions = array();

	/**
	 * @var array $joomlaSelectOptions
	 */
	var $joomlaSelectOptions = array();

	/**
	 * @var array $forumSelectOptions
	 */
	var $forumSelectOptions = array();

	/**
	 * @var string $toolbar
	 */
	var $toolbar;

    /**
     * @param null $tpl
     * @return mixed
     */
    function display($tpl = null)
    {
    	//load language file
	    $lang = JFactory::getLanguage();
	    $lang->load('com_jfusion');
	    $lang->load('plg_content_jfusion', JPATH_ADMINISTRATOR);

	    $document = JFactory::getDocument();
	    $document->addScript('components/com_jfusion/views/' . $this->getName() . '/tmpl/default.js');

        $mainframe = JFactory::getApplication();
 		$document	= JFactory::getDocument();
        $db			= JFactory::getDBO();
	    $this->ename = JFactory::getApplication()->input->get('ename');
	    $this->jname = JFactory::getApplication()->input->get('jname');

		switch ($this->ename) {
        	case 'pair_sections' :
		        $this->title = JText::_('ASSIGN_SECTION_PAIRS');

		        $query = $db->getQuery(true)
			        ->select('id, title as name')
			        ->from('#__sections')
			        ->where('published = 1')
			        ->where('scope = ' . $db->quote('content'))
			        ->order('title');

        		$db->setQuery($query);
		        $this->joomlaoptions = $db->loadObjectList('id');
				break;
        	case 'pair_categories' :
		        $this->title = JText::_('ASSIGN_CATEGORY_PAIRS');

		        $query	= $db->getQuery(true)
			        ->select('a.id, a.title as name, a.level')
			        ->from('#__categories AS a')
			        ->where('a.parent_id > 0')
			        ->where('extension = \'com_content\'')
			        ->where('a.published = 1')
			        ->order('a.lft');

		        $db->setQuery($query);
		        $this->joomlaoptions = $db->loadObjectList('id');
		        foreach ($this->joomlaoptions as &$item) {
			        $repeat = ($item->level - 1 >= 0) ? $item->level - 1 : 0;
			        $item->name = str_repeat('- ', $repeat) . $item->name;
		        }

        		break;
        	case 'pair_k2_categories':
		        $this->title = JText::_('ASSIGN_K2_CATEGORY_PAIRS');

		        $query = $db->getQuery(true)
			        ->select('id, name as title, parent')
			        ->from('#__k2_categories')
			        ->where('id > 0')
			        ->where('trash = 0')
			        ->where('published = 1');

	    	    $db->setQuery($query);
    			$items = $db->loadObjectList();
        		$children = array ();
        		if(count($items)){
        			foreach ($items as $v) {
        				$pt = $v->parent;
				        $list = (isset($children[$pt]) && $children[$pt]) ? $children[$pt] : array ();
        				array_push($list, $v);
        				$children[$pt] = $list;
        			}
        		}

		        $this->joomlaoptions = jfusionViewdiscussionbot::buildRecursiveTree(0, '', array(), $children);
        	    break;
        	default:
        		return;
        }

	    $this->hash = JFactory::getApplication()->input->get($this->ename);
	    $session = JFactory::getSession();
	    $encoded_pairs = $session->get($this->hash);
		if($encoded_pairs) {
			$this->pairs = unserialize(base64_decode($encoded_pairs));
		}

		//remove pair
		if(JFactory::getApplication()->input->getInt('remove')) {
			$joomlaid = JFactory::getApplication()->input->getInt('remove');
			unset($this->pairs[$joomlaid]);

			//recode pairs to be added as hidden var to make sure none are lost on submitting another pair
			$encoded_pairs = base64_encode(serialize($this->pairs));
			$session->set($this->hash, $encoded_pairs);
		} elseif (JFactory::getApplication()->input->getInt('joomlaid', 0)) {
			//add submitted pair
			$joomlaid = JFactory::getApplication()->input->getInt('joomlaid');
			$forumid = JFactory::getApplication()->input->getInt('forumid');
			$this->pairs[$joomlaid] = $forumid;

			//recode pairs to be added as hidden var to make sure none are lost on submitting another pair
			$encoded_pairs = base64_encode(serialize($this->pairs));
			$session->set($this->hash, $encoded_pairs);
		}

		//get the forum listings
		$JFusionForum = JFusionFactory::getForum($this->jname);
	    try {
		    $this->forumSelectOptions = $JFusionForum->getForumList();
	    } catch (Exception $e) {
			JFusionFunction::raiseError($e, $JFusionForum->getJname());
	    }

		//joomla select options
        $template = $mainframe->getTemplate();
		$document->addStyleSheet('templates/' . $template . '/css/general.css');
		$document->addStyleSheet('templates/' . $template . '/css/icon.css');
		$document->setTitle($this->title);
		$css = '.jfusion table.jfusionlist, table.jfusiontable{ font-size:11px; } .jfusion table.jfusionlist tbody tr td { vertical-align:top; }';
		$document->addStyleDeclaration($css);

		//prepare a toolbar
        $apply = JText::_('APPLY');
        $close = JText::_('CLOSE');
	    $this->toolbar = <<<HTML
	    <div class="btn-toolbar" id="toolbar">
			<div class="btn-group" id="toolbar-apply">
				<a href="#" onclick="window.parent.JFusion.submitParams('{$this->ename}', '{$encoded_pairs}')" class="btn btn-small btn-success">
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
	    $this->joomlaSelectOptions = $this->joomlaoptions;

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
	    if (isset($children[$id]) && $children[$id]) {
    		foreach ($children[$id] as $v)
    		{
    			$id = $v->id;
                $pre	= '- ';
    			if ($v->parent == 0) {
    				$txt	= $v->title;
    			} else {
    				$txt	= $pre . $v->title;
    			}
    			$list[$id] = $v;
    			$list[$id]->name = $indent . $txt;
    			$list = jfusionViewdiscussionbot::buildRecursiveTree($id, $indent . '- ', $list, $children, $level+1);
    		}
	    }

		return $list;
	}
}
