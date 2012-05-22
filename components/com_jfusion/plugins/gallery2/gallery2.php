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
 * JFusion plugin class for Gallery2
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Gallery2
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
if (!class_exists('jFusion_g2BridgeCore')) {
	class jFusion_g2BridgeCore {
	    static $loadedGallery = null;
	    static $registry = array();
	    function loadGallery2Api($jname,$fullInit, $itemId = null) {
	        if (self::$loadedGallery == $jname) {
	            return true;
	        }
	        $params = JFusionFactory::getParams($jname);
	        $source_url = $params->get('source_url');
	        $source_path = $params->get('source_path');
	        if (substr($source_path, -1) == DS) {
	            $index_file = $source_path . 'embed.php';
	        } else {
	            $index_file = $source_path . DS . 'embed.php';
	        }
	        if (substr($source_url, 0, 1) == '/') {
	            $uri = & JURI::getInstance();
	            $base = $uri->toString(array('scheme', 'host', 'port'));
	            $source_url = $base . $source_url;
	        }
	        $initParams["g2Uri"] = $source_url;
	        $initParams["embedUri"] = jFusion_g2BridgeCore::getEmbedUri($jname,$itemId);
	        $initParams["loginRedirect"] = JRoute::_("index.php?option=com_user&view=login");
	        $initParams["fullInit"] = $fullInit;
	        if (!is_file($index_file)) {
	            JError::raiseWarning(500, 'The path to the Gallery2('.$jname.' path: '.$index_file.') embed file set in the component preferences does not exist');
	            return false;
	        }
	        if (!class_exists('GalleryEmbed')) {
	        	require_once $index_file;
	        } else {
	        	global $gallery;
		        if (substr($source_path, -1) == DS) {
		            $config_file = $source_path . 'config.php';
		        } else {
		            $config_file = $source_path . DS . 'config.php';
		        }
		        require $config_file;
	        }
	        $ret = GalleryEmbed::init($initParams);
	        if ($ret) {
	            JError::raiseWarning(500, 'Error while initialising Gallery2 API');
	            return false;
	        }
	        $ret = GalleryCoreApi::setPluginParameter('module', 'core', 'cookie.path', '/');
	        if ($ret) {
	            JError::raiseWarning(500, 'Error while setting cookie path');
	            return false;
	        }
	        if ($fullInit) {
	            $user = JFactory::getUser();
	            if ($user->id != 0) {
	                $userPlugin = JFusionFactory::getUser($jname);
	                $g2_user = $userPlugin->getUser($user->username);
	                $userPlugin->createSession($g2_user, null, false);
	            } else {
	            	// comented out we will need to keep an eye on if this will cause problems..
	                //GalleryEmbed::logout();
	            }
	            $cookie_domain = $params->get('cookie_domain');
	            if (!empty($cookie_domain)) {
	                $ret = GalleryCoreApi::setPluginParameter('module', 'core', 'cookie.domain', $cookie_domain);
	                if ($ret) {
	                    return $ret->getAsHtml();
	                }
	            }
	            $cookie_path = $params->get('cookie_path');
	            if (!empty($cookie_path)) {
	                $ret = GalleryCoreApi::setPluginParameter('module', 'core', 'cookie.path', $cookie_path);
	                if ($ret) {
	                    return $ret->getAsHtml();
	                }
	            }
	        }
	        self::$loadedGallery = $jname;
	        return true;
	    }
	    function getEmbedUri($jname,$itemId = null) {
	        $mainframe = JFactory::getApplication();
	        $router = $mainframe->getRouter();
	        $id = JRequest::getVar('Itemid', -1);
	        if ($itemId !== null) {
	            $id = $itemId;
	        }
	        //Create Gallery Embed Path
	        $path = 'index.php?option=com_jfusion';
	        if ($id > 0) {
	            $path.= '&Itemid=' . $id;
	        } else if ($jname == $itemId) {
	        	$params = JFusionFactory::getParams($jname);
	        	$source_url = $params->get('source_url');
	        	return $source_url;
	        } else {
	            $path.= '&view=frameless&jname='.$jname;
	        }

	        //added check to prevent fatal error when creating session from outside joomla
	        if (class_exists('JRoute')) {
	            $uri = JRoute::_($path, false);
	        } else {
	            $uri = $path;
	        }
	        if ($router->getMode() == JROUTER_MODE_SEF) {
	            if ($mainframe->getCfg('sef_suffix')) {
	                $uri = str_replace(".html", "", $uri);
	            }
	            if (!strpos($uri, "?")) {
	                $uri.= "/";
	            }
	        }
	        return $uri;
	    }
	    static function setVar($jname,$key, $value) {
	        self::$registry[$jname][$key] = $value;
	    }
	    static function getVar($jname,$key, $default = null) {
	        if (isset(self::$registry[$jname][$key])) {
	            return self::$registry[$jname][$key];
	        }
	        return $default;
	    }
	    function setPathway($jname) {
	        global $gallery;
	        $session =& $gallery->getSession();
	        if ($session) {
	            $session->doNotUseTempId();
	        }
	        $mainframe = JFactory::getApplication();
	        $urlGenerator = $gallery->getUrlGenerator();
	        $itemId = (int)GalleryUtilities::getRequestVariables('itemId');
	        $userId = $gallery->getActiveUserId();
	        /* fetch parent sequence for current itemId or Root */
	        if ($itemId) {
	            list($ret, $parentSequence) = GalleryCoreApi::fetchParentSequence($itemId);
	            if ($ret) {
	                return $ret;
	            }
	        } else {
	            list($ret, $rootId) = GalleryCoreApi::getPluginParameter('module', 'core', 'id.rootAlbum');
	            if ($ret) {
	                return $ret;
	            }
	            $parentSequence = array($rootId);
	        }
	        /* Add current item at the end */
	        $parentSequence[] = $itemId;
	        /* shift first parent off, as Joomla adds menu name already.*/
	        array_shift($parentSequence);
	        /* study permissions */
	        if (sizeof($parentSequence) > 0 && $parentSequence[0] != 0) {
	            $ret = GalleryCoreApi::studyPermissions($parentSequence);
	            if ($ret) {
	                return $ret;
	            }
	            /* load the Entities */
	            list($ret, $list) = GalleryCoreApi::loadEntitiesById($parentSequence);
	            if ($ret) {
	                return $ret;
	            }
	            foreach ($list as $it) {
	                $entities[$it->getId() ] = $it;
	            }
	        }
	        $breadcrumbs = & $mainframe->getPathWay();
	        $document = JFactory::getDocument();
	        /* check permissions and push */
	        $i = 1;
	        $limit = count($parentSequence);
	        foreach ($parentSequence as $id) {
	            list($ret, $canSee) = GalleryCoreApi::hasItemPermission($id, 'core.view', $userId);
	            if ($ret) {
	                return $ret;
	            }
	            if ($canSee) {
	                /* push them into pathway */
	                $urlParams = array('view' => 'core.ShowItem', 'itemId' => $id);
	                $title = $entities[$id]->getTitle() ? $entities[$id]->getTitle() : $entities[$id]->getPathComponent();
	                $title = preg_replace('/\r\n/', ' ', $title);
	                $url = $urlGenerator->generateUrl($urlParams);
	                if ($i < $limit) {
	                    $breadcrumbs->addItem($title, $url);
	                } else {
	                    $breadcrumbs->addItem($title, '');
	                    /* description */
	                    $document->setMetaData('description', $entities[$id]->getSummary());
	                    /* keywords */
	                    $document->setMetaData('keywords', $entities[$id]->getKeywords());
	                }
	            }
	            $i++;
	        }
	        return null;
	    }
	}
}
