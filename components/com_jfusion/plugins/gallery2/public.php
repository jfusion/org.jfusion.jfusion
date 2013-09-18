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
class JFusionPublic_gallery2 extends JFusionPublic
{
	/**
	 * @var $helper JFusionHelper_gallery2
	 */
	var $helper;

    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'gallery2';
    }

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
        /**
         * @ignore
         * @var $jPluginParam JRegistry
         */
        $jPluginParam = $data->jParam;
        //Handle PHP based Gallery Rewrite
        $segments = JFactory::getApplication()->input->get('jFusion_Route',null,'raw');
        if (!empty($segments)) {
            $path_info = '/' . implode('/', unserialize($segments));
            $path_info = str_replace(':', '-', $path_info);
            $_SERVER['PATH_INFO'] = $path_info;
        }

	    $this->helper->loadGallery2Api(true);
        global $gallery, $user;
        $album = $jPluginParam->get('album', -1);
        if ($album != - 1) {
            $gallery->setConfig('defaultAlbumId', $album);
            $gallery->setConfig('breadcrumbRootId', $album);
        }
        $theme = $jPluginParam->get('show_templateList', '');
        if (!empty($theme)) {
            GalleryEmbed::setThemeForRequest($theme);
        }
        //Check displaying Sidebar
        GalleryCapabilities::set('showSidebarBlocks', ($jPluginParam->get('dispSideBar') == 1));
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
	                throw new RuntimeException( $match1['head'] .': '. $match2['desc'] );
                } else {
	                throw new RuntimeException( 'Gallery2 Internal Error' );
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
	    $this->helper->setPathway();
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
        $data->body = preg_replace_callback('#action="(.*?)"(.*?)>#m',array( &$this,'fixAction'), $data->body);
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
		    	
        //JFusionFunction::raiseWarning($url, $this->getJname());
        $url = htmlspecialchars_decode($url);
        $Itemid = JFactory::getApplication()->input->getInt('Itemid');
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
                $url = JFusionFunction::routeURL($url, $Itemid);
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
        		$replacement .=  '<input type="hidden" name="'. $key .'" value="'.$value . '"/>';
        	}
        }
        return $replacement;
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
                    $pluginParam->set('g2_itemId',$array['itemId']);

	                /**
	                 * @ignore
	                 * @var $forum JFusionForum_gallery2
	                 */
                    $forum = JFusionFactory::getForum($this->getJname());
                    $info->galleryImage = $forum->renderImageBlock($config, 'image_block', $pluginParam);

//                    list(, $views) = GalleryCoreApi::fetchItemViewCount($array['itemId']);
                    $return[] = $info;
                }
            }
        }
        return $return;
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
	    $db = JFusionFactory::getDatabase($this->getJname());

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
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('COUNT(*)')
			    ->from('#__SessionMap')
			    ->where('g_modificationTimestamp  > ' . $active)
			    ->where('g_userId != 5');

		    $db->setQuery($query);
		    $result = $db->loadResult();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
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
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('COUNT(*)')
			    ->from('#__SessionMap')
			    ->where('g_modificationTimestamp  > ' . $active)
			    ->where('g_userId = 5');

	        $db->setQuery($query);
	        $result = $db->loadResult();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $result = 0;
	    }
        return $result;
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

        unset($query['option'], $query['jfile'], $query['Itemid'], $query['jFusion_Route'], $query['view'],$query['layout'], $query['controller'], $query['lang'], $query['task']);

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
            $queries[] = $key.'='.$var;
        }

        $wrap = $jfile . '?'. implode($queries,'&');

        $source_url = $data->jParam->get('source_url');

        //check for trailing slash
        if (substr($source_url, -1) == '/') {
            $url = $source_url . $wrap;
        } else {
            $url = $source_url . '/'. $wrap;
        }

        return $url;
    }
}
