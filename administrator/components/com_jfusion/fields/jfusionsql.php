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
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'import.php';
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

    	$override_jname = (string) $this->element['jname'];
    	$jname = (!empty($override_jname)) ? $override_jname : $jname;
		$multiple = ($this->element['multiple']) ? ' MULTIPLE ' : '';
		$add_default = $this->element['add_default'];
		$key = ($this->element['key_field']) ? (string) $this->element['key_field'] : 'value';
		$val = ($this->element['value_field']) ? (string) $this->element['value_field'] : '';
        $param_name = ($multiple) ? $this->formControl . '[' . $this->group . '][' . $this->fieldname . '][]' : $this->formControl . '[' . $this->group . '][' . $this->fieldname . ']';

	    try {
		    if($jname) {
			    $user = JFusionFactory::getUser($jname);
			    if ($user->isConfigured()) {
				    $query = (string) $this->element['query'];

				    //some special queries for discussion bot
				    if ($query == 'joomla.categories') {
					    $db = JFactory::getDBO();

					    $query = $db->getQuery(true)
						    ->select('a.id, a.title as name, a.level')
						    ->from('#__categories AS a')
						    ->where('a.parent_id > 0')
						    ->where('extension = \'com_content\'')
						    ->where('a.published = 1')
						    ->order('a.lft');

					    $db->setQuery($query);
					    $items = $db->loadObjectList();
					    foreach ($items as &$item) {
						    $repeat = ($item->level - 1 >= 0) ? $item->level - 1 : 0;
						    $item->name = str_repeat('- ', $repeat) . $item->name;
					    }
					    $output = JHTML::_('select.genericlist',  $items, $param_name, 'class="inputbox" ' . $multiple, $key, $val, $this->value, $this->formControl . '_' . $this->group . '_' . $this->fieldname);
				    } elseif ($query == 'k2.categories') {
					    $db = JFactory::getDBO();

					    $query = $db->getQuery(true)
						    ->select('enabled')
						    ->from('#__extensions')
						    ->where('element = ' . $db->quote('com_k2'));

					    $db->setQuery($query);
					    $enabled = $db->loadResult();
					    if (empty($enabled)) {
						    throw new RuntimeException(JText::_('K2_NOT_AVAILABLE'));
					    }
					    $query = $db->getQuery(true)
						    ->select('id, name as title, parent')
						    ->from('#__k2_categories')
						    ->where('id > 0')
						    ->where('trash = 0')
						    ->where('published = 1');

					    $db->setQuery($query);
					    $items = $db->loadObjectList();
					    $children = array ();
					    if(count($items)) {
						    foreach ($items as $v) {
							    $pt = $v->parent;
							    $list = (isset($children[$pt]) && $children[$pt]) ? $children[$pt] : array();
							    array_push($list, $v);
							    $children[$pt] = $list;
						    }
					    }

					    $results = JFormFieldjfusionsql::buildRecursiveTree(0, '', array(), $children);
					    $output = JHTML::_('select.genericlist',  $results, $param_name, 'class="inputbox" ' . $multiple, $key, $val, $this->value, $this->formControl . '_' . $this->group . '_' . $this->fieldname);
				    } else {
					    $db = JFusionFactory::getDatabase($jname);
					    $db->setQuery($this->element['query']);

					    $results = $db->loadObjectList();

					    if(!empty($add_default)) {
						    array_unshift($results, JHTML::_('select.option', '', '- ' . JText::_('SELECT_ONE') . '  -', $key, $val));
					    }
					    $output = JHTML::_('select.genericlist',  $results, $param_name, 'class="inputbox" ' . $multiple, $key, $val, $this->value, $this->formControl . '_' . $this->group . '_' . $this->fieldname);
				    }
			    } else {
				    throw new RuntimeException(JText::_('SAVE_CONFIG_FIRST'));
			    }
		    } else {
			    throw new RuntimeException('Programming error: You must define global $jname before the JParam object can be rendered.');
		    }
	    } catch (Exception $e) {
		    $output = '<span style="float:left; margin: 5px 0; font-weight: bold;">' . $e->getMessage() . '</span>';
	    }
	    return $output;
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
    			$list = JFormFieldjfusionsql::buildRecursiveTree($id, $indent . '- ', $list, $children, $level+1);
    		}
	    }

		return $list;
	}
}
