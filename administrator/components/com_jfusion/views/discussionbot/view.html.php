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
class jfusionViewdiscussionbot extends JView
{
    /**
     * @param null $tpl
     * @return mixed
     */
    function display($tpl = null)
    {
    	//load language file
		JFusionFunction::loadLanguage('plg','content','jfusion');
		
		JHTML::_('behavior.modal');

        $mainframe = JFactory::getApplication();
 		$document	= JFactory::getDocument();
        $db			= JFactory::getDBO();
        $dbtask = JRequest::getVar('ename');
		$jname = JRequest::getVar('jname');

		switch ($dbtask) {
        	case 'pair_sections' :
        		$title = JText::_('ASSIGN_SECTION_PAIRS');
				$query = "SELECT id, title as name FROM #__sections WHERE published = 1 AND scope = 'content' ORDER BY title";
        		$db->setQuery($query);
        		$joomlaoptions = $db->loadObjectList('id');
				break;
        	case 'pair_categories' :
        		$title = JText::_('ASSIGN_CATEGORY_PAIRS');

        		if (JFusionFunction::isJoomlaVersion('1.6')) {
        		    $query	= $db->getQuery(true);
        			$query->select('a.id, a.title as name, a.level');
        			$query->from('#__categories AS a');
        			$query->where('a.parent_id > 0');
        			$query->where('extension = "com_content"');
                    $query->where('a.published = 1');
        			$query->order('a.lft');

        			$db->setQuery($query);
        			$joomlaoptions = $db->loadObjectList('id');
        			foreach ($joomlaoptions as &$item) {
        				$repeat = ( $item->level - 1 >= 0 ) ? $item->level - 1 : 0;
        				$item->name = str_repeat('- ', $repeat).$item->name;
        			}
        		} else {
    			    $query = 'SELECT c.id, CONCAT_WS( "/",s.title, c.title ) AS name FROM #__categories AS c LEFT JOIN #__sections AS s ON s.id=c.section WHERE c.published = 1 AND s.scope = "content" ORDER BY s.title, c.title';
    			    $db->setQuery($query);
        		    $joomlaoptions = $db->loadObjectList('id');
        		}

        		break;
        	case 'pair_k2_categories':
        	    $title = JText::_('ASSIGN_K2_CATEGORY_PAIRS');

    	        $query = "SELECT id, name as title, parent FROM #__k2_categories WHERE id > 0 AND trash = 0 AND published = 1";
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

		$encoded_pairs = JRequest::getVar($dbtask);
		if($encoded_pairs) {
			$pairs = unserialize(base64_decode($encoded_pairs));
		} else {
			$pairs = array();
		}

		//remove pair
		if(JRequest::getInt('remove')) {
			$joomlaid = JRequest::getInt('remove');
			unset($pairs[$joomlaid]);

			//recode pairs to be added as hidden var to make sure none are lost on submitting another pair
			$encoded_pairs = base64_encode(serialize($pairs));
		} elseif (JRequest::getInt('joomlaid',0)) {
			//add submitted pair
			$joomlaid = JRequest::getInt('joomlaid');
			$forumid = JRequest::getInt('forumid');
			$pairs[$joomlaid] = $forumid;

			//recode pairs to be added as hidden var to make sure none are lost on submitting another pair
			$encoded_pairs = base64_encode(serialize($pairs));
		}


		//get the forum listings
		$JFusionForum =& JFusionFactory::getForum($jname);
		$forumSelectOptions = $JFusionForum->getForumList();
		//joomla select options
        $joomlaSelectOptions = $joomlaoptions;

        //best to do this only for J1.5 due to J1.6+ new structure or for K2
        if (!JFusionFunction::isJoomlaVersion('1.6') && $dbtask != 'pair_k2_categories') {
            if(!empty($pairs)) {
    	        //remove paired sections/categories from select options
    	        foreach($pairs AS $jid => $fid) {
    	        	unset($joomlaSelectOptions[$jid]);
    	        }
            }
        }

		$document->addStyleSheet('components/com_jfusion/css/jfusion.css');
        $template = $mainframe->getTemplate();
		$document->addStyleSheet("templates/$template/css/general.css");
		$document->addStyleSheet("templates/$template/css/icon.css");
		$document->setTitle($title);
		$css = 'table.adminlist, table.admintable{ font-size:11px; } table.adminlist tbody tr td { vertical-align:top; }';
		$document->addStyleDeclaration($css);

		//prepare a toolbar
        $apply = JText::_('APPLY');
        $close = JText::_('CLOSE');
        if (JFusionFunction::isJoomlaVersion('1.6')) {
            $toolbar = <<<HTML
                <div class="m">
                    <div class="toolbar-list" id="toolbar">
                        <ul>
                            <li class="button" id="toolbar-apply">
                                <a href="javascript:void(0);" onclick="window.parent.jDiscussionParamSet('{$dbtask}', '{$encoded_pairs}');" class="toolbar"><span class="icon-32-apply"></span>{$apply}</a>
                            </li>
                            <li class="button" id="toolbar-cancel">
                                <a href="javascript:void(0);" onclick="window.parent.SqueezeBox.close();" class="toolbar"><span class="icon-32-cancel"></span>{$close}</a>
                            </li>
                        </ul>
                        <div class="clr"></div>
                    </div>
                </div>
HTML;
        } else {
            $toolbar = <<<HTML
    		    <div id='My Toolbar' class='toolbar'>\
                    <table class='toolbar'>
                        <tbody>
                            <tr>
                                <td id='My Toolbar-apply' class='button'>
                                    <a class='toolbar' onclick=\"window.parent.jDiscussionParamSet('{$dbtask}', '{$encoded_pairs}');\" href='javascript: void(0);'>
                                        <span title='".JText::_('APPLY')."' class='icon-32-apply'></span>".JText::_('APPLY')."
                                    </a>
                                </td>
                                <td id='My Toolbar-cancel' class='button'>
                                    <a class='toolbar' onclick=\"window.parent.SqueezeBox.close();\" href='javascript:void(0);'>
                                        <span title='".JText::_('CANCEL')."' class='icon-32-cancel'></span>".JText::_('CANCEL')."
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
HTML;
        }

	    //assign references
	    $this->assignRef('jname', $jname);
		$this->assignRef('toolbar', $toolbar);
		$this->assignRef('title', $title);
		$this->assignRef('dbtask', $dbtask);
		$this->assignRef('joomlaoptions', $joomlaoptions);
		$this->assignRef('joomlaSelectOptions', $joomlaSelectOptions);
		$this->assignRef('forumSelectOptions', $forumSelectOptions);
		$this->assignRef('pairs', $pairs);
		$this->assignRef('encoded_pairs', $encoded_pairs);

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
    			$list[$id]->name = "$indent$txt";
    			$list = jfusionViewdiscussionbot::buildRecursiveTree($id, $indent . '- ', $list, $children, $level+1);
    		}
	    }

		return $list;
	}
}
