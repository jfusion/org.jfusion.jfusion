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
class JElementjfusionsql extends JElement
{
    var $_name = "jfusionsql";
    /**
     * Get an element
     *
     * @param string $name         name of element
     * @param string $value        value of element
     * @param string &$node        node of element
     * @param string $control_name name of controler
     *
     * @return string html
     */
    function fetchElement($name, $value, &$node, $control_name)
    {
    	global $jname;

    	$override_jname = $node->attributes("jname");
    	$jname = (!empty($override_jname)) ? $override_jname : $jname;
		$multiple = ($node->attributes("multiple")) ? " MULTIPLE " : "";
		$add_default = $node->attributes('add_default');
		$param_name = ($multiple) ? $control_name.'['.$name.'][]' : $control_name.'['.$name.']';
		$key = ($node->attributes('key_field')) ? $node->attributes('key_field') : 'value';
		$val = ($node->attributes('value_field')) ? $node->attributes('value_field') : $name;

    	if(!empty($jname)) {
    		if (JFusionFunction::validPlugin($jname)) {
    		    $db =& JFusionFactory::getDatabase($jname);
    		    $query = $node->attributes('query');

                if ($query == 'k2.categories') {
                    $jdb = JFactory::getDBO();
                    $query = 'SELECT enabled FROM #__components WHERE `option` = ' . $jdb->Quote('com_k2');;
                    $db->setQuery( $query );
                    $enabled = $jdb->loadResult();
                    if (empty($enabled)) {
                        return JText::_('K2_NOT_AVAILABLE');
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

            		$results = JElementjfusionsql::buildRecursiveTree(0, '', array(), $children);
            	    return JHTML::_('select.genericlist',  $results, $param_name, 'class="inputbox" '.$multiple, $key, $val, $value, $control_name.$name);
                } else {
    				$db->setQuery($query);
                    $results = $db->loadObjectList();
    				if($results) {
    					if(!empty($add_default)) {
    						array_unshift($results, JHTML::_('select.option', '', '- '.JText::_('SELECT_ONE').' -', $key, $val));
    					}
    					return JHTML::_('select.genericlist',  $results, $param_name, 'class="inputbox" '.$multiple, $key, $val, $value, $control_name.$name);
    				} else {
    					return $db->stderr();
    				}
                }
    		} else {
                return JText::_('SAVE_CONFIG_FIRST');
        	}
        } else {
            return 'Programming error: You must define global $jname before the JParam object can be rendered';
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
    			$list[$id]->name = "$indent$txt";
    			$list = JElementjfusionsql::buildRecursiveTree($id, $indent . '- ', $list, $children, $level+1);
    		}
	    }

		return $list;
	}
}
