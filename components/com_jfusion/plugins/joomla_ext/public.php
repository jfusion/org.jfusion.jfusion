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
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jplugin.php';

/**
 * JFusion Public Class for an external Joomla database
 * For detailed descriptions on these functions please check the model.abstractpublic.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage JoomlaExt 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionPublic_joomla_ext extends JFusionPublic {
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */    
    function getJname() {
        return 'joomla_ext';
    }

    /**
     * @return string
     */
    function getRegistrationURL() {
        return JFusionJplugin::getRegistrationURL($this->getJname());
    }

    /**
     * @return string
     */
    function getLostPasswordURL() {
        return JFusionJplugin::getLostPasswordURL($this->getJname());
    }

    /**
     * @return string
     */
    function getLostUsernameURL() {
        return JFusionJplugin::getLostUsernameURL($this->getJname());
    }

    /**
     * @param int $limit
     *
     * @return string
     */
    function getOnlineUserQuery($limit) {
        return JFusionJplugin::getOnlineUserQuery($limit);
    }

    /**
     * @return int
     */
    function getNumberOnlineGuests() {
        return JFusionJplugin::getNumberOnlineGuests();
    }

    /**
     * @return int
     */
    function getNumberOnlineMembers() {
        return JFusionJplugin::getNumberOnlineMembers();
    }

    /**
     * Update the language front end param in the account of the user if this one changes it
     * NORMALLY THE LANGUAGE SELECTION AND CHANGEMENT FOR JOOMLA IS PROVIDED BY THIRD PARTY LIKE JOOMFISH
     *
     * @TODO - to implement after the release 1.1.2
     *
     * @param object $userinfo
     * @return array status
     */
    function setLanguageFrontEnd($userinfo) {
        return JFusionJplugin::setLanguageFrontEnd($this->getJname(),$userinfo);
    }
}
