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

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Forum Class for phpBB3
 * For detailed descriptions on these functions please check the model.abstractforum.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Gallery2 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org 
 */
class JFusionForum_gallery2 extends JFusionForum {
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'gallery2';
    }

    /**
     * @param $config
     * @param $view
     * @param $pluginParam
     * @return string
     */
    function renderActivityModule($config, $view, $pluginParam) {
        switch ($view) {
            case 'image_block':
                return $this->renderImageBlock($config, $view, $pluginParam);
            break;
            case 'sidebar':
                return $this->renderSideBar($config, $view, $pluginParam);
            break;
            default:
                return JText::_('NOT IMPLEMENTED YET');
        }
    }

    /**
     * @param $config
     * @param $view
     * @param JRegistry $pluginParam
     * @return mixed|string
     */
    function renderImageBlock($config, $view, $pluginParam) {
		//Initialize the Framework
        $error = false;

        $align = $pluginParam->get('g2_align');
        if (empty($align)) {
            $content = '<div>';
        } else {
            $content = '<div align="' . $align . '">';
        }

        /**
         * @ignore
         * @var $helper JFusionHelper_gallery2
         */
        $helper = JFusionFactory::getHelper($this->getJname());
    	if (!$helper->loadGallery2Api(true)) {
			$content = '<strong>Error</strong><br />Can\'t initialise G2Bridge.';
        } else {
            //Load Parameters
            $block = $pluginParam->get('g2_block');
            $header = $pluginParam->get('g2_header');
            $title = $pluginParam->get('g2_title');
            $date = $pluginParam->get('g2_date');
            $views = $pluginParam->get('g2_views');
            $owner = $pluginParam->get('g2_owner');
            $itemId = (int)$pluginParam->get('g2_itemId');
            $max_size = (int)$pluginParam->get('g2_maxSize');
            $link_target = $pluginParam->get('g2_link_target');
            $frame = $pluginParam->get('g2_frame');
            $strip_anchor = $pluginParam->get('g2_strip_anchor');
            $count = (int)$pluginParam->get('g2_count');

            /* make multiple image if needed */
            if (!empty($count) && $count > 1) {
                $tmp = $block;
                for ($i=1;$i < $count;$i++) {
                    $block .= '|'.$tmp;
                }
            }
            /* Create the show array */
            $array['show'] = array();
            if ($title == 1) {
                $array['show'][] = 'title';
            }
            if ($date == 1) {
                $array['show'][] = 'date';
            }
            if ($views == 1) {
                $array['show'][] = 'views';
            }
            if ($owner == 1) {
                $array['show'][] = 'owner';
            }
            if ($header == 1) {
                $array['show'][] = 'heading';
            }
            $array['show'] = (count($array['show']) > 0) ? implode('|', $array['show']) : 'none';
            /* add itemId if set */
            if (!empty($itemId) && $itemId != - 1) {
                $array['itemId'] = $itemId;
            } else {
                list (, $itemId) = GalleryCoreApi::getPluginParameter('module', 'core', 'id.rootAlbum');
                $array['itemId'] = $itemId;
            }
            /* set the rest */
            $array['blocks'] = $block;
            if (!empty($max_size)) {
                $array['maxSize'] = $max_size;
            }
            $array['linkTarget'] = $link_target;
		    /**
		     * @ignore
		     * @var $ret GalleryStatus
		     */
		    if ($config['debug'] && $frame == 'none') {
                /* Load the module list */
                list($ret, $moduleStatus) = GalleryCoreApi::fetchPluginStatus('module');
                if ($ret) {
                    $content .= JTEXT::_('ERROR_LOADING_GALLERY_MODULES');
                    $error = true;
                }
                if (!isset($moduleStatus['imageframe']) || empty($moduleStatus['imageframe']['active'])) {
                    $content .= JTEXT::_('ERROR_IMAGEFRAME_NOT_READY');
                    $error = true;
                }
            }
            if (!$error) {
                $array['itemFrame'] = $frame;

                if ($block == 'specificItem' && empty($itemId)) {
                    $content .= '<strong>Error</strong><br />You have selected no "itemid" and this must be done if you select "Specific Picture"';
                } else {
                    if (isset($config['itemid']) && $config['itemid'] != 150) {
                        global $gallery;

                        $source_url = $this->params->get('source_url');
                        $urlGenerator = new GalleryUrlGenerator();
                        $urlGenerator->init($helper->getEmbedUri($config['itemid']), $source_url, null);
                        $gallery->setUrlGenerator($urlGenerator);
                    }

                    list($ret, $imageBlockHtml, $headContent) =  GalleryEmbed::getBlock('imageblock', 'ImageBlock', $array);
                    if ($ret) {
                        if ($ret->getErrorCode() == 4194305 || $ret->getErrorCode() == 17) {
                            $content.= '<strong>Error</strong><br />You need to install the Gallery2 Plugin "imageblock".';
                        } else {
                            $content.= '<h2>Fatal G2 error</h2> Here is the error from G2:<br />' . $ret->getAsHtml();
                        }
                    } else {
                        $content.= ($strip_anchor == 1) ? strip_tags($imageBlockHtml, '<img><table><tr><td><div><h3>') : $imageBlockHtml;
	                    /**
	                     * @ignore
	                     * @var $document JDocumentHTML
	                     */
                        $document = JFactory::getDocument();
                        $document->addCustomTag($headContent);
                        /* finish Gallery 2 */
                    }
                    GalleryEmbed::done();
                }
            }
        }
        $content .= '</div>';
        return $content;
    }

    /**
     * @param $config
     * @param $view
     * @param $pluginParam
     * @return string
     */
    function renderSideBar($config, $view, $pluginParam) {
        /**
         * @ignore
         * @var $helper JFusionHelper_gallery2
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        $g2sidebar = $helper->getVar('sidebar', -1);
        if ($g2sidebar != - 1) {
            return '<div id="gsSidebar" class="gcBorder1"> ' . implode('', $g2sidebar) . '</div>';
        } else {
            return 'Sidebar isn\'t initialisies. Maybe there is a Problem with the Bridge';
        }
    }
    /**
     * Returns the Profile Url in Gallery2
     * This Link requires Modules:members enabled in gallery2
     *
     * @param int|string $userid
     *
     * @return string
     * @see Gallery2:Modules:members
     */
    function getProfileURL($userid) {
        return 'main.php?g2_view=members.MembersProfile&amp;g2_userId='.$userid;
    }
}
