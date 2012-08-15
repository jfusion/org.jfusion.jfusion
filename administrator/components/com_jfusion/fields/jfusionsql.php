<?php

/**
 * This is the jfusion sql element file
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die();
/**
 * Require the Jfusion plugin factory
 */
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.factory.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusion.php';

/**
 * JFusion Element class sql
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFormFieldjfusionsql extends JFormField
{
    public $type = 'jfusionsql';
    /**
     * Get an element
     *
     * @return string html
     */
    protected function getInput()
    {
    	global $jname;

    	$override_jname =  (string) $this->element['jname'];
    	$jname = (!empty($override_jname)) ? $override_jname : $jname;
		$multiple = ($this->element['multiple']) ? ' MULTIPLE ' : '';
		$add_default = $this->element['add_default'];
		$key = ($this->element['key_field']) ? (string) $this->element['key_field'] : 'value';
		$val = ($this->element['value_field']) ? (string) $this->element['value_field'] : '';
        $param_name = ($multiple) ? $this->formControl.'['.$this->group.']['.$this->fieldname.'][]' : $this->formControl.'['.$this->group.']['.$this->fieldname.']';

		if(!empty($jname)) {
    		if (JFusionFunction::validPlugin($jname)) {
		    	$db =& JFusionFactory::getDatabase($jname);
		    	$query = (string) $this->element['query'];

		    	//some special queries for discussion bot
		    	if ($query == 'joomla.categories') {
		    	    //joomla 1.6+
        			$query	= $db->getQuery(true);
        			$query->select('a.id, a.title as name, a.level');
        			$query->from('#__categories AS a');
        			$query->where('a.parent_id > 0');
        			$query->where('extension = "com_content"');
                    $query->where('a.published = 1');
        			$query->order('a.lft');

        			$db->setQuery($query);
        			$items = $db->loadObjectList();
        			foreach ($items as &$item) {
        				$repeat = ( $item->level - 1 >= 0 ) ? $item->level - 1 : 0;
        				$item->name = str_repeat('- ', $repeat).$item->name;
        			}
        			return JHTML::_('select.genericlist',  $items, $param_name, 'class="inputbox" '.$multiple, $key, $val, $this->value, $this->formControl.'_'.$this->group.'_'.$this->fieldname);
		    	} elseif ($query == 'k2.categories') {
		    	    $jdb = JFactory::getDBO();
                    $query = 'SELECT enabled FROM #__extensions WHERE element = ' . $jdb->Quote('com_k2');
                    $db->setQuery( $query );
                    $enabled = $jdb->loadResult();
                    if (empty($enabled)) {
                        return '<span style="float:left; margin: 5px 0; font-weight: bold;">' . JText::_('K2_NOT_AVAILABLE') . '</span>';
                    }
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

            		$results = JFormFieldjfusionsql::buildRecursiveTree(0, '', array(), $children);
            		return JHTML::_('select.genericlist',  $results, $param_name, 'class="inputbox" '.$multiple, $key, $val, $this->value, $this->formControl.'_'.$this->group.'_'.$this->fieldname);
		    	} else {
    				$db->setQuery($this->element['query']);
                    $results = $db->loadObjectList();
    				if($results) {
    					if(!empty($add_default)) {
    						array_unshift($results, JHTML::_('select.option', '', '- '.JText::_('SELECT_ONE').' -', $key, $val));
    					}
    					return JHTML::_('select.genericlist',  $results, $param_name, 'class="inputbox" '.$multiple, $key, $val, $this->value, $this->formControl.'_'.$this->group.'_'.$this->fieldname);
    				} else {
    					return '<span style="float:left; margin: 5px 0; font-weight: bold;">' . $db->stderr() . '</span>';
    				}
		    	}
    		} else {
                return '<span style="float:left; margin: 5px 0; font-weight: bold;">' . JText::_('SAVE_CONFIG_FIRST') . '</span>';
        	}
        } else {
            return '<span style="float:left; margin: 5px 0; font-weight: bold;">Programming error: You must define global \$jname before the JParam object can be rendered</span>';
        }
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
    			$list = JFormFieldjfusionsql::buildRecursiveTree($id, $indent . '- ', $list, $children, $level+1);
    		}
	    }

		return $list;
	}
}
