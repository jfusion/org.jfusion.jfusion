<?php
/**
 * @package JFusion
 * @subpackage Modules
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined ( '_JEXEC' ) or trigger_error ( 'Restricted access' );

require_once 'helper/default.php';

if (JPluginHelper::importPlugin ( 'system', 'magelib' )) {
    /**
     * @ignore
     * @var $params JRegistry
     */
	$plgMageLib = new plgSystemMagelib ( );
	$plgMageLib->destroyTemporaryJoomlaSession ();
	if ($plgMageLib->loadAndStartMagentoBootstrap ()) :
		$plgMageLib->startMagentoSession ();
		
		/* Content of Magento logic, blocks or else */
		
		$html = '';
		$blockId = $params->get ( 'block_id', '' );
		echo JFusion_Helper_Mageselectblock::callblock ( $blockId );
		
		/* EOF */
		
		$plgMageLib->stopMagentoSession ();

	endif;
	$plgMageLib->restartJoomlaSession ();
} else {
	$error = JError::raiseWarning ( 0, JText::_ ( 'Plugin system magelib not installed or activated!' ) );
	JError::handleLog ( $error, array() );
}