<?php

/**
* @package JFusion_universal
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

require_once (JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.curlframeless.php');

/**
 * JFusion Public Class for universal plugin
 * For detailed descriptions on these functions please check the model.abstractpublic.php
 * @package JFusion_universal
 */
class JFusionPublic_universal extends JFusionPublic{

    /**
     * @return string
     */
    function getJname()
	{
		return 'universal';
	}

    /**
     * @return string
     */
    function getRegistrationURL()
	{
		return $this->params->get('registerurl');
	}

    /**
     * @return string
     */
    function getLostPasswordURL()
	{
		return $this->params->get('lostpasswordurl');
	}

    /**
     * @return string
     */
    function getLostUsernameURL()
	{
		return $this->params->get('lostusernameurl');
	}
}