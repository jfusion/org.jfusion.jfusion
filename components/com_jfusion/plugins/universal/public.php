<?php

/**
* @package JFusion_universal
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

require_once (JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.curlframeless.php');

/**
 * JFusion Public Class for universal plugin
 * For detailed descriptions on these functions please check the model.abstractpublic.php
 * @package JFusion_universal
 */
class JFusionPublic_universal extends JFusionPublic{

	function getJname()
	{
		return 'universal';
	}

	function getRegistrationURL()
	{
        $params = JFusionFactory::getParams($this->getJname());
		return $params->get('registerurl');
	}

	function getLostPasswordURL()
	{
        $params = JFusionFactory::getParams($this->getJname());
		return $params->get('lostpasswordurl');
	}

	function getLostUsernameURL()
	{
        $params = JFusionFactory::getParams($this->getJname());
		return $params->get('lostusernameurl');
	}
}