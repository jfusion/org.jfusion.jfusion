<?php namespace JFusion\Plugins\gallery2;

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
use GalleryCoreApi;
use GalleryEmbed;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\Plugin\Plugin;

use Exception;

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

class Helper extends Plugin
{
    var $loadedGallery = false;
    var $registry = array();

    /**
     * @param $fullInit
     * @param array $params
     *
     * @return bool
     */
    function loadGallery2Api($fullInit, $params = array()) {
        if (!$this->loadedGallery) {
            $source_url = $this->params->get('source_url');
            $source_path = $this->params->get('source_path');
	        $index_file = $source_path . 'embed.php';

	        $initParams = array();
	        foreach($params as $key => $value) {
		        $initParams[$key] = $value;
	        }
            $initParams['g2Uri'] = $source_url;
            $initParams['fullInit'] = $fullInit;
            if (!is_file($index_file)) {
                Framework::raiseWarning('The path to the Gallery2(path: ' . $index_file . ') embed file set in the component preferences does not exist', $this->getJname());
            } else {
                if (!class_exists('GalleryEmbed')) {
                    require_once $index_file;
                } else {
                    global $gallery;
	                $config_file = $source_path . 'config.php';
                    require $config_file;
                }
                $ret = GalleryEmbed::init($initParams);
                if ($ret) {
                    Framework::raiseWarning('Error while initialising Gallery2 API', $this->getJname());
                } else {
                    $ret = GalleryCoreApi::setPluginParameter('module', 'core', 'cookie.path', '/');
                    if ($ret) {
                        Framework::raiseWarning('Error while setting cookie path', $this->getJname());
                    } else {
                        if ($fullInit) {
	                        $user = Factory::getApplication()->getUser();
                            if ($user->userid != 0) {
	                            try {
		                            $userPlugin = Factory::getUser($this->getJname());
		                            $g2_user = $userPlugin->getUser($user);
		                            $options = array();
		                            $options['noframework'] = true;
		                            $userPlugin->createSession($g2_user, $options);
	                            } catch (Exception $e) {
		                            Framework::raiseError($e, $this->getJname());
	                            }
                            } else {
                                // commented out we will need to keep an eye on if this will cause problems..
                                //GalleryEmbed::logout();
                            }
                            $cookie_domain = $this->params->get('cookie_domain');
                            if (!empty($cookie_domain)) {
                                $ret = GalleryCoreApi::setPluginParameter('module', 'core', 'cookie.domain', $cookie_domain);
                                if ($ret) {
                                    return false;
                                }
                            }
                            $cookie_path = $this->params->get('cookie_path');
                            if (!empty($cookie_path)) {
                                $ret = GalleryCoreApi::setPluginParameter('module', 'core', 'cookie.path', $cookie_path);
                                if ($ret) {
                                    return false;
                                }
                            }
                        }
                        $this->loadedGallery = true;
                    }
                }
            }
        }
        return $this->loadedGallery;
    }

    /**
     * @param $key
     * @param $value
     */
    function setVar($key, $value) {
        $this->registry[$key] = $value;
    }

    /**
     * @param $key
     * @param mixed $default
     *
     * @return mixed
     */
    function getVar($key, $default = null) {
        if (isset($this->registry[$key])) {
            return $this->registry[$key];
        }
        return $default;
    }
}
