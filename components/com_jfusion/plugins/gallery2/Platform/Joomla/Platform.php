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
use GalleryCoreApi;
use GalleryEmbed;
use GalleryItem;
use GalleryStatus;
use GalleryUrlGenerator;
use JDocumentHTML;
use JFactory;
use JFusion\Factory;
use JFusion\Framework;
use Joomla\Language\Text;
use JFusion\Plugin\Platform\Joomla;
use JFusion\Plugins\gallery2\Helper;
use JRegistry;
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
						$urlGenerator->init($this->helper->getEmbedUri($config['itemid']), $source_url, null);
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
		$this->helper->loadGallery2Api(true, $itemid);
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
}
