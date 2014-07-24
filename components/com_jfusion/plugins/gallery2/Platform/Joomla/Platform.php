<?php namespace JFusion\Plugins\gallery2\Platform\Joomla;

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
use Exception;
use GalleryCapabilities;
use GalleryCoreApi;
use GalleryEmbed;
use GalleryItem;
use GalleryPermissionHelper_simple;
use GalleryStatus;
use GalleryUrlGenerator;
use GalleryUtilities;
use JDocumentHTML;
use JFactory;
use JFusion\Factory;
use JFusion\Framework;
use Joomla\Language\Text;
use JFusion\Plugin\Platform\Joomla;
use JFusion\Plugins\gallery2\Helper;
use JRegistry;
use JRoute;
use RuntimeException;
use stdClass;

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
class Platform extends Joomla
{
	/**
	 * @var $helper Helper
	 */
	var $helper;

	/**
	 * @param $name
	 * @param $value
	 * @param $node
	 * @param $control_name
	 * @return array|string
	 */
	function showTemplateList($name, $value, $node, $control_name)
	{
		$this->helper->loadGallery2Api(false);
		list($ret, $themes) = GalleryCoreApi::fetchPluginStatus('theme', true);
		if ($ret) {
			return array($ret, null);
		}
		$cname = $control_name . '[params][' . $name . ']';

		$output = '<select name="' . $cname . '" id="' . $name . '">';

		$output.= '<option value="" ></option>';
		foreach ($themes as $id => $status) {
			if (!empty($status['active'])) {
				$selected = '';
				if ($id == $value) {
					$selected = 'selected';
				}
				$output.= '<option value="' . $id . '" ' . $selected . '>' . $id . '</option>';
			}
		}
		$output.= '</select>';
		return $output;
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
				return Text::_('NOT IMPLEMENTED YET');
		}
	}

	/**
	 * @param $config
	 * @param $view
	 * @param JRegistry $pluginParam
	 *
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

		if (!$this->helper->loadGallery2Api(true)) {
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
					$block .= '|' . $tmp;
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
					$content .= Text::_('ERROR_LOADING_GALLERY_MODULES');
					$error = true;
				}
				if (!isset($moduleStatus['imageframe']) || empty($moduleStatus['imageframe']['active'])) {
					$content .= Text::_('ERROR_IMAGEFRAME_NOT_READY');
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
						$urlGenerator->init($this->getEmbedUri($config['itemid']), $source_url, null);
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
		$g2sidebar = $this->helper->getVar('sidebar', -1);
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
		return 'main.php?g2_view=members.MembersProfile&amp;g2_userId=' . $userid;
	}

	/************************************************
	 * Functions For JFusion Who's Online Module
	 ***********************************************/
	/**
	 * Returns a query to find online users
	 * Make sure columns are named as userid, username, username_clean (if applicable), name (of user), and email
	 *
	 * @param array $usergroups
	 *
	 * @return string
	 */
	function getOnlineUserQuery($usergroups = array())
	{
		$db = Factory::getDatabase($this->getJname());

		//get a unix time from 5 minutes ago
		date_default_timezone_set('UTC');
		$now = time();
		$active = strtotime('-5 minutes', $now);

		$query = $db->getQuery(true)
			->select('DISTINCT u.g_id AS userid, u.g_userName as username, u.g_fullName AS name')
			->from('#__User AS u')
			->innerJoin('#__SessionMap AS s ON s.g_userId = u.g_id')
			->where('s.g_modificationTimestamp > ' . $active);

		if (!empty($usergroups)) {
			$usergroups = implode(',', $usergroups);

			$query->innerJoin('#__usergroupmap AS g ON u.g_id = g.g_userId')
				->where('g.g_groupId IN (' . $usergroups . ')');
		}

		$query = (string)$query;
		return $query;
	}
	/**
	 * Returns number of members
	 * @return int
	 */
	function getNumberOnlineMembers() {
		try {
			//get a unix time from 5 minutes ago
			date_default_timezone_set('UTC');
			$now = time();
			$active = strtotime('-5 minutes', $now);
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('COUNT(*)')
				->from('#__SessionMap')
				->where('g_modificationTimestamp  > ' . $active)
				->where('g_userId != 5');

			$db->setQuery($query);
			$result = $db->loadResult();
		} catch (Exception $e) {
			Framework::raiseError($e, $this->getJname());
			$result = 0;
		}
		return $result;
	}
	/**
	 * Returns number of guests
	 * @return int
	 */
	function getNumberOnlineGuests() {
		try {
			//get a unix time from 5 minutes ago
			date_default_timezone_set('UTC');
			$now = time();
			$active = strtotime('-5 minutes', $now);
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('COUNT(*)')
				->from('#__SessionMap')
				->where('g_modificationTimestamp  > ' . $active)
				->where('g_userId = 5');

			$db->setQuery($query);
			$result = $db->loadResult();
		} catch (Exception $e) {
			Framework::raiseError($e, $this->getJname());
			$result = 0;
		}
		return $result;
	}

	/**
	 * @param string &$text
	 * @param string &$phrase
	 * @param JRegistry &$pluginParam
	 * @param int $itemid
	 * @param string $ordering
	 * @return array
	 */
	function getSearchResults(&$text, &$phrase, &$pluginParam, $itemid, $ordering) {
		$initParams = array();
		$initParams['embedUri'] = $this->getEmbedUri($itemid);
		$initParams['loginRedirect'] = JRoute::_('index.php?option=com_user&view=login');
		$this->helper->loadGallery2Api(true, $initParams);
		global $gallery;
		$urlGenerator = $gallery->getUrlGenerator();
		/* start preparing */
		$text = trim($text);
		$return = array();
		if ($text != '') {
			//Limitation so prevent overheads -1 = unlimited
			$limit = - 1;
			list(, $result['GalleryCoreSearch']) = GalleryEmbed::search($text, 'GalleryCoreSearch', 0, $limit);
			foreach ($result as $section => $resultArray) {
				if ($resultArray['count'] == 0) {
					continue;
				}
				foreach ($resultArray['results'] as $array) {
					$info = new stdClass();
					$info->href = $urlGenerator->generateUrl(array('view' => 'core.ShowItem', 'itemId' => $array['itemId']));
					/**
					 * @ignore
					 * @var $item GalleryItem
					 * @var $parent GalleryItem
					 */
					list($ret, $item) = GalleryCoreApi::loadEntitiesById($array['itemId']);
					if ($ret) {
						continue;
					}
					$info->title = $item->getTitle() ? $item->getTitle() : $item->getPathComponent();
					$info->title = preg_replace('/\r\n/', ' ', $info->title);
					$info->section = $section;
					$info->created = $item->getcreationTimestamp();
					$description = $item->getdescription();
					$info->text = empty($description) ? $item->getSummary() : $description;
					$info->browsernav = 2;
					$item->getparentId();
					if ($item->getparentId() != 0) {
						list($ret, $parent) = GalleryCoreApi::loadEntitiesById($item->getparentId());
						if ($ret) {
							continue;
						}
						$parent = $parent->getTitle() ? $parent->getTitle() : $parent->getPathComponent();
						$info->section = preg_replace('/\r\n/', ' ', $parent);
						if (strpos(strtolower($info->section), 'gallery') !== 0) {
							$info->section = 'Gallery/' . $info->section;
						}
					}

					$config['itemid'] = $itemid;
					$config['debug'] = true;
					$pluginParam->set('g2_itemId', $array['itemId']);

					$info->galleryImage = $this->renderImageBlock($config, 'image_block', $pluginParam);

//                    list(, $views) = GalleryCoreApi::fetchItemViewCount($array['itemId']);
					$return[] = $info;
				}
			}
		}
		return $return;
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

		$initParams = array();
		$initParams['loginRedirect'] = JRoute::_('index.php?option=com_user&view=login');
		$this->helper->loadGallery2Api(true, $initParams);

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
				$url = Factory::getApplication()->routeURL($url, $Itemid);
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

	/**
	 * @param null $itemId
	 * @return string
	 */
	function getEmbedUri($itemId = null) {
		$mainframe = Factory::getApplication();
		$id = $mainframe->input->get('Itemid', -1);
		if ($itemId !== null) {
			$id = $itemId;
		}
		//Create Gallery Embed Path
		$path = 'index.php?option=com_jfusion';
		if ($id > 0) {
			$path .= '&Itemid=' . $id;
		} else if ($this->getJname() == $itemId) {
			$source_url = $this->params->get('source_url');
			return $source_url;
		} else {
			$path .= '&view=frameless&jname=' . $this->getJname();
		}

		//added check to prevent fatal error when creating session from outside joomla
		if (class_exists('JRoute')) {
			$uri = JRoute::_($path, false);
		} else {
			$uri = $path;
		}
		if (Factory::getConfig()->get('sef_suffix')) {
			$uri = str_replace('.html', '', $uri);
		}
		if (!strpos($uri, '?')) {
			$uri .= '/';
		}
		return $uri;
	}
}
