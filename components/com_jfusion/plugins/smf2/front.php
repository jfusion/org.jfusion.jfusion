<?php namespace JFusion\Plugins\smf2;

/**
* @package JFusion_SMF
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
use JFusion\Plugin\Plugin_Front;

defined('_JEXEC' ) or die('Restricted access' );

/**
 * JFusion Public Class for SMF 1.1.x
 * For detailed descriptions on these functions please check the model.abstractpublic.php
 * @package JFusion_SMF
 */
class Front extends Plugin_Front
{
    /**
     * @return string
     */
    function getRegistrationURL()
	{
		return 'index.php?action=register';
	}

    /**
     * @return string
     */
    function getLostPasswordURL()
	{
		return 'index.php?action=reminder';
	}

    /**
     * @return string
     */
    function getLostUsernameURL()
	{
		return 'index.php?action=reminder';
	}
}