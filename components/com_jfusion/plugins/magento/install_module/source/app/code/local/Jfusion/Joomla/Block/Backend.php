<?php
/**
 * Jfusion_Joomla_Block_Backend class
 *
 * @category   JFusion
 * @package    Model
 * @subpackage Jfusion_Joomla_Block_Backend
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Jfusion_Joomla_Block_Backend extends Mage_Core_Block_Template {

    /**
     *
     */
    public function __construct() {
		$this->setTemplate ( 'joomla/backend.phtml' );
	}

    /**
     * @return mixed
     */
    public function getCustomAdminUrl() {
		return Mage::helper('joomla')->getData ( 'custom_adminurl' );
	}

    /**
     * @return mixed
     */
    public function getJoomlaBaseUrl() {
		return Mage::helper('joomla')->getData ( 'baseurl' );
	}
}