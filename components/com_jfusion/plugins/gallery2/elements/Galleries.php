<?php

/**
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Gallery2 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die();

/**
 * Defines the forum select list for JFusion forum plugins
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Gallery2 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JElementGalleries extends JElement {
    var $_name = "Galleries";

    /**
     * @param string $name
     * @param string $value
     * @param \JXMLElement $node
     * @param string $control_name
     * @return mixed|string|void
     */
    function fetchElement($name, $value, &$node, $control_name) {
    	global $jname;
        /**
         * @ignore
         * @var $helper JFusionHelper_gallery2
         */
        $helper = JFusionFactory::getHelper($jname);
        $helper->loadGallery2Api(true);
        list($ret, $tree) = GalleryCoreApi::fetchAlbumTree();
        $output = array();
        if ($ret) {
            return '<div>Could not query Gallery-Tree</div>';
        } else {
            if (!empty($tree)) {
                $titles = array();
                list($ret, $items) = GalleryCoreApi::loadEntitiesById(GalleryUtilities::arrayKeysRecursive($tree));
                if ($ret) {
                    return '<div>Could not query Gallery-Tree</div>';
                } else {
                    foreach ($items as $item) {
                        $title = $item->getTitle() ? $item->getTitle() : $item->getPathComponent();
                        $title = preg_replace('/\r\n/', ' ', $title);
                        $titles[$item->getId() ] = $title;
                    }
                }
                $output[] = JHTML::_('select.option',  - 1, "Default Album" );
                $this->buildTree($tree, $titles, $output, "----| ", true);
            }
        }
        return JHTML::_('select.genericlist', $output, $control_name . '[' . $name . ']', null, 'value', 'text', $value);
    }

    /**
     * @param $tree
     * @param $titles
     * @param $ar
     * @param string $limiter
     * @param bool $sub
     */
    function buildTree($tree, $titles, &$ar, $limiter = '', $sub = false) {
        foreach ($tree as $tItemID => $tItemArray) {
            $name = htmlspecialchars($titles[$tItemID], ENT_QUOTES);
            $ar[] = JHTML::_('select.option', $tItemID, ($sub ? $limiter : "") . $name);            
            $this->buildTree($tItemArray, $titles, $ar, "----".$limiter, true);
        }
    }
}
