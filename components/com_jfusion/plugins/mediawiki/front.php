<?php namespace JFusion\Plugins\mediawiki;

/**
* @package JFusion_mediawiki
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
use JFusion\Plugin\Plugin_Front;
use Joomla\Uri\Uri;

defined('_JEXEC' ) or die('Restricted access' );

/**
 * JFusion Public Class for mediawiki 1.1.x
 * For detailed descriptions on these functions please check the model.abstractpublic.php
 * @package JFusion_mediawiki
 */
class Front extends Plugin_Front
{
    /**
     * @param $data
     */
    function _parseBody(&$data)
	{
	    $regex_body		= array();
	    $replace_body	= array();

		$uri = new Uri($data->integratedURL);
		$regex_body[]	= '#addButton\("/(.*?)"#mS';
		$replace_body[]	= 'addButton("' . $uri->toString(array('scheme', 'host')) . '/$1"';

	    $data->body = preg_replace($regex_body, $replace_body, $data->body);
	}
}