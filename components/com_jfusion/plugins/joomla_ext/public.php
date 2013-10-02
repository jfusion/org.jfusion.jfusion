<?php

/**
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage JoomlaExt 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');
/**
 * load the common Joomla JFusion plugin functions
 */
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'joomla' . DIRECTORY_SEPARATOR . 'model.joomlapublic.php';

/**
 * JFusion Public Class for an external Joomla database
 * For detailed descriptions on these functions please check the model.abstractpublic.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Joomla_ext
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionPublic_joomla_ext extends JFusionJoomlaPublic {
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */    
    function getJname() {
        return 'joomla_ext';
    }
}
