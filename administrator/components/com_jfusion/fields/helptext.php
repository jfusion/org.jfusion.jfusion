<?php

/**
 * This is the jfusion helptext element file
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
 * JFusion Element class helptext
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFormFieldhelptext extends JFormField
{
    public $type = "helptext";
    /**
     * Get an element
     *
     * @return string html
     */
    protected function getInput()
    {
        return "<span style='float:left; margin: 5px 0; font-weight: bold;'>".JText::_($this->value)."</span>";
    }
}
