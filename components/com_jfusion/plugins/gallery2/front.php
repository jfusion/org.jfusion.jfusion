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
use GalleryCapabilities;
use GalleryCoreApi;
use GalleryEmbed;
use GalleryItem;
use GalleryPermissionHelper_simple;
use GalleryUtilities;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\Plugin\Plugin_Front;

use JRegistry;
use RuntimeException;
use stdClass;

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
class Front extends Plugin_Front
{
	/**
	 * @var $helper Helper
	 */
	var $helper;

    /**
     * @return string
     */
    function getRegistrationURL() {
        return '?g2_view=core.UserAdmin&g2_subView=register.UserSelfRegistration';
    }

    /**
     * @return string
     */
    function getLostPasswordURL() {
        return '?g2_view=core.UserAdmin&g2_subView=core.UserRecoverPassword';
    }

	/**
	 * @param object &$data
	 *
	 * @throws RuntimeException
	 * @return void
	 */
    function getBuffer(&$data) {
        //Handle PHP based Gallery Rewrite
        $segments = Factory::getApplication()->input->get('jFusion_Route', null, 'raw');
        if (!empty($segments)) {
            $path_info = '/' . implode('/', unserialize($segments));
            $path_info = str_replace(':', '-', $path_info);
            $_SERVER['PATH_INFO'] = $path_info;
        }

	    $this->helper->loadGallery2Api(true);
        global $gallery, $user;
        $album = $data->mParam->get('album', -1);
        if ($album != - 1) {
            $gallery->setConfig('defaultAlbumId', $album);
            $gallery->setConfig('breadcrumbRootId', $album);
        }
        $theme = $data->mParam->get('show_templateList', '');
        if (!empty($theme)) {
            GalleryEmbed::setThemeForRequest($theme);
        }
        //Check displaying Sidebar
        GalleryCapabilities::set('showSidebarBlocks', ($data->mParam->get('dispSideBar') == 1));
        // Start the Embed Handler
        ob_start();
        //$ret = $gallery->setActiveUser($userinfo);
        $g2data = GalleryEmbed::handleRequest();
        $output = ob_get_contents();
        ob_end_clean();
        // Handle File Output
        if (trim($output)) {
            if (preg_match('%<h2>\s(?<head>.*)\s</h2>%', $output, $match1) && preg_match('%<p class="giDescription">\s(?<desc>.*)\s</p>%', $output, $match2)) {
                echo '<pre>';
                var_dump($match1);
                var_dump($match2);
                echo '</pre>';
                if (isset($match1['head']) && isset($match2['desc'])) {
	                throw new RuntimeException($match1['head'] . ': ' . $match2['desc']);
                } else {
	                throw new RuntimeException('Gallery2 Internal Error');
                }
            } else {
                print $output;
                exit();
            }
        }
        /* Register Sidebare for Module Usage */
        if (isset($g2data['sidebarBlocksHtml'])) {
	        $this->helper->setVar('sidebar', $g2data['sidebarBlocksHtml']);
        }
        if (isset($g2data['bodyHtml']) && isset($g2data['headHtml'])) {
            $buffer = '<html><head>' . $g2data['headHtml'] . '</head><body>' . $g2data['bodyHtml'] . '</body></html>';
            $data->body = $g2data['bodyHtml'];
            $data->header = $g2data['headHtml'];
            $data->buffer = $buffer;
        }
    }

    /**
     * @param object $data
     *
     * @return void
     */
    function parseBody(&$data) {
        //fix for form actions    	
        $data->body = preg_replace_callback('#action="(.*?)"(.*?)>#m', array(&$this, 'fixAction'), $data->body);
    }

    /**
     * @param object $data
     *
     * @return void
     */
    function parseHeader(&$data) {
    }

    /**
     * Fix action
     *
     * @param array $matches
     *
     * @return string html
     */
	function fixAction($matches)
    {
		$url = $matches[1];
		$extra = $matches[2];
		$baseURL = $this->data->baseURL;
		    	
        //\JFusion\Framework::raiseWarning($url, $this->getJname());
        $url = htmlspecialchars_decode($url);
        $Itemid = Factory::getApplication()->input->getInt('Itemid');
        $extra = stripslashes($extra);
        if (substr($baseURL, -1) != '/') {
            //non-SEF mode
            $url_details = parse_url($url);
            $url_variables = array();
            if (isset($url_details['query'])) {
                parse_str($url_details['query'], $url_variables);
            }
            //set the correct action and close the form tag
            $replacement = 'action="' . $url . '"' . $extra . '>';
            $replacement.= '<input type="hidden" name="Itemid" value="' . $Itemid . '"/>';
			$replacement.= '<input type="hidden" name="option" value="com_jfusion"/>';
        } else {
            //check to see what SEF mode is selected
            $sefmode = $this->params->get('sefmode');
            if ($sefmode == 1) {
                //extensive SEF parsing was selected
                $url = Framework::routeURL($url, $Itemid);
                $replacement = 'action="' . $url . '"' . $extra . '>';
                return $replacement;
            } else {
                //simple SEF mode
                $url_details = parse_url($url);
                $url_variables = array();
                if (isset($url_details['query'])) {
                    parse_str($url_details['query'], $url_variables);
                }
                $replacement = 'action="' . $baseURL . '"' . $extra . '>';
            }
        }
        unset($url_variables['option'], $url_variables['Itemid']);
        if (is_array($url_variables)){
        	foreach ($url_variables as $key => $value){
        		$replacement .=  '<input type="hidden" name="'. $key . '" value="' . $value . '"/>';
        	}
        }
        return $replacement;
    }

    /**
     * function to generate url for wrapper
     * @param &$data
     *
     * @return string returns the url
     */
    function getWrapperURL($data)
    {
        //get the url
        $query = ($_GET);
        if(isset($query['jfile'])){
            $jfile = $query['jfile'];
        } else {
            $jfile = 'index.php';
        }

        unset($query['option'], $query['jfile'], $query['Itemid'], $query['jFusion_Route'], $query['view'], $query['layout'], $query['controller'], $query['lang'], $query['task']);

        $queries = array();

        if (!isset($query['g2_itemId'])) {
            /**
             * @ignore
             * @var $mParam JRegistry
             */
            $mParam = $data->mParam;
            $album = $mParam->get('album', false);
            if ($album) {
                $query['g2_itemId'] = $album;
            }
        }

        foreach($query as $key => $var) {
            $queries[] = $key . '=' . $var;
        }

        $wrap = $jfile . '?'. implode($queries, '&');

        $source_url = $this->params->get('source_url');

        //check for trailing slash
        if (substr($source_url, -1) == '/') {
            $url = $source_url . $wrap;
        } else {
            $url = $source_url . '/' . $wrap;
        }

        return $url;
    }

	/**
	 * @return array
	 */
	function getPathWay()
	{
		$pathway = array();

		global $gallery;
		$session = $gallery->getSession();
		if ($session) {
			$session->doNotUseTempId();
		}
		/**
		 * @ignore
		 * @var $entities GalleryItem[]
		 * @var $it GalleryItem
		 */
		$entities = array();
		$urlGenerator = $gallery->getUrlGenerator();
		$itemId = (int) GalleryUtilities::getRequestVariables('itemId');
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
			GalleryCoreApi::requireOnce('modules/core/classes/helpers/GalleryPermissionHelper_simple.class');
			$ret = GalleryPermissionHelper_simple::studyPermissions($parentSequence);
			if ($ret) {
				return $ret;
			} else {
				/* load the Entities */
				list($ret, $list) = GalleryCoreApi::loadEntitiesById($parentSequence);
				if ($ret) {
					return $ret;
				} else {
					foreach ($list as $it) {
						$entities[$it->getId() ] = $it;
					}
				}
			}
		}
		/* check permissions and push */
		$i = 1;
		$limit = count($parentSequence);
		foreach ($parentSequence as $id) {
			list($ret, $canSee) = GalleryCoreApi::hasItemPermission($id, 'core.view', $userId);
			if ($ret) {
				return $ret;
			} else {
				if ($canSee) {
					/* push them into pathway */
					$urlParams = array('view' => 'core.ShowItem', 'itemId' => $id);
					$title = $entities[$id]->getTitle() ? $entities[$id]->getTitle() : $entities[$id]->getPathComponent();
					$title = preg_replace('/\r\n/', ' ', $title);
					$url = $urlGenerator->generateUrl($urlParams);

					$path = new stdClass();
					$path->title = $title;
					$path->url = $url;
					$pathway[] = $path;
				}
				$i++;
			}
		}
		return $pathway;
	}
}
