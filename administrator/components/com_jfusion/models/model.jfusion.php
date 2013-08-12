<?php

/**
 * Model for all jfusion related function
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Class for general JFusion functions
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionFunction
{
	/**
	 * Returns the JFusion plugin name of the software that is currently the master of user management
	 *
	 * @return object master details
	 */
	public static function getMaster()
	{
		static $jfusion_master;
		if (!isset($jfusion_master)) {
			$db = JFactory::getDBO();

			$query = $db->getQuery(true)
				->select('*')
				->from('#__jfusion')
				->where('master = 1')
				->where('status = 1');

			$db->setQuery($query);
			$jfusion_master = $db->loadObject();
		}
		return $jfusion_master;
	}
	
	/**
	 * Returns the JFusion plugin name of the software that are currently the slaves of user management
	 *
	 * @return object slave details
	 */
	public static function getSlaves()
	{
		static $jfusion_slaves;
		if (!isset($jfusion_slaves)) {
			$db = JFactory::getDBO();

			$query = $db->getQuery(true)
				->select('*')
				->from('#__jfusion')
				->where('slave = 1')
				->where('status = 1');

			$db->setQuery($query);
			$jfusion_slaves = $db->loadObjectList();
		}
		return $jfusion_slaves;
	}
	
    /**
     * Changes plugin status in both Joomla 1.5 and Joomla 1.6
     *
     * @param string $element
     * @param string $folder
     *
     * @return object master details
     */
	public static function getPluginStatus($element,$folder) {
		//get joomla specs
        $db = JFactory::getDBO();

		$query = $db->getQuery(true)
			->select('published')
			->from('#__extensions')
			->where('element = ' . $db->Quote($element))
			->where('folder = ' . $db->Quote($folder));

        $db->setQuery($query);
        $result = $db->loadResult();
        return $result;
	}

    /**
     * Creates a JFusion Joomla compatible URL
     *
     * @param string  $url    string url to be parsed
     * @param string  $itemid string itemid of the JFusion menu item or the name of the plugin for direct link
     * @param string  $jname  optional jname if available to prevent having to find it based on itemid
     * @param boolean $route  boolean optional switch to send url through JRoute::_() (true by default)
     * @param boolean $xhtml  boolean optional switch to turn & into &amp; if $route is true (true by default)
     *
     * @return string Parsed URL
     */
    public static function routeURL($url, $itemid, $jname = '', $route = true, $xhtml = true)
    {
        if (!is_numeric($itemid)) {
            if ($itemid == 'joomla_int') {
                //special handling for internal URLs
                if ($route) {
                    $url = JRoute::_($url, $xhtml);
                }
            } else {
                //we need to create direct link to the plugin
                $params = JFusionFactory::getParams($itemid);
                $url = $params->get('source_url') . $url;
                if ($xhtml) {
                    $url = str_replace('&', '&amp;', $url);
                }
            }
        } else {
            //we need to create link to a joomla itemid
            if (empty($jname)) {
                //determine the jname from the plugin
                static $routeURL_jname;
                if (!is_array($routeURL_jname)) {
                    $routeURL_jname = array();
                }
                if (!isset($routeURL_jname[$itemid])) {
	                $menu = JMenu::getInstance('site');

                    $menu_param = $menu->getParams($itemid);
                    $plugin_param = unserialize(base64_decode($menu_param->get('JFusionPluginParam')));
                    $routeURL_jname[$itemid] = $plugin_param['jfusionplugin'];
                    $jname = $routeURL_jname[$itemid];
                } else {
                    $jname = $routeURL_jname[$itemid];
                }
            }
            //make the URL relative so that external software can use this function
            $params = JFusionFactory::getParams($jname);
            $source_url = $params->get('source_url');
            $url = str_replace($source_url, '', $url);

            $config = JFactory::getConfig();
            $sefenabled = $config->get('sef');
            $params = JFusionFactory::getParams($jname);
            $sefmode = $params->get('sefmode', 1);
            if ($sefenabled && !$sefmode) {
                //otherwise just tak on the URL
                $baseURL = JFusionFunction::getPluginURL($itemid, false);
                $url = $baseURL . $url;
                if ($xhtml) {
                    $url = str_replace('&', '&amp;', $url);
                }
            } else {
                //fully parse the URL if sefmode = 1
                $u = JURI::getInstance($url);
                $u->setVar('jfile', $u->getPath());
                $u->setVar('option', 'com_jfusion');
                $u->setVar('Itemid', $itemid);
                $query = $u->getQuery(false);
                $fragment = $u->getFragment();
                if (isset($fragment)) {
                    $query.= '#' . $fragment;
                }
                if ($route) {
                    $url = JRoute::_('index.php?' . $query, $xhtml);
                } else {
                    $url = 'index.php?' . $query;
                }
            }
        }
        return $url;
    }

    /**
     * Returns either the Joomla wrapper URL or the full URL directly to the forum
     *
     * @param string $url    relative path to a webpage of the integrated software
     * @param string $jname  name of the JFusion plugin used
     * @param string $view   name of the JFusion view used
     * @param string $itemid the itemid
     *
     * @return string full URL to the filename passed to this function
     */
    public static function createURL($url, $jname, $view, $itemid = '')
    {
        if (!empty($itemid)) {
            //use the itemid only to identify plugin name and view type
            $base_url = 'index.php?option=com_jfusion&amp;Itemid=' . $itemid;
        } else {
            $base_url = 'index.php?option=com_jfusion&amp;Itemid=-1&amp;view=' . $view . '&amp;jname=' . $jname;
        }
        if ($view == 'direct') {
            $params = JFusionFactory::getParams($jname);
            $url = $params->get('source_url') . $url;
        } elseif ($view == 'wrapper') {
            //use base64_encode to encode the URL for passing.  But, base64_code uses / which throws off SEF urls.  Thus slashes
            //must be translated into something base64_encode will not generate and something that will not get changed by Joomla or Apache.
            $url = $base_url . '&amp;wrap=' . str_replace('/', '_slash_', base64_encode($url));
            $url = JRoute::_($url);
        } elseif ($view == 'frameless') {
            //split the filename from the query
            $parts = explode('?', $url);
            if (isset($parts[1])) {
                $base_url.= '&amp;jfile=' . $parts[0] . '&amp;' . $parts[1];
            } else {
                $base_url.= '&amp;jfile=' . $parts[0];
            }
            $url = JRoute::_($base_url);
        }
        return $url;
    }

    /**
     * Updates the JFusion user lookup table during login
     *
     * @param object  $userinfo  object containing the userdata
     * @param string  $joomla_id The Joomla ID of the user
     * @param string  $jname     name of the JFusion plugin used
     * @param boolean $delete    deletes an entry from the table
     *
     * @return string nothing
     */
    public static function updateLookup($userinfo, $joomla_id, $jname = '', $delete = false)
    {
	    if ($userinfo) {
		    $db = JFactory::getDBO();
		    //we don't need to update the lookup for internal joomla unless deleting a user
		    if ($jname == 'joomla_int') {
			    if ($delete) {
				    //Delete old user data in the lookup table
				    $query = 'DELETE FROM #__jfusion_users WHERE id =' . $joomla_id . ' OR username = ' . $db->Quote($userinfo->username) . ' OR LOWER(username) = ' . strtolower($db->Quote($userinfo->email));
				    $db->setQuery($query);
				    try {
					    $db->execute();
				    } catch (Exception $e) {
					    FusionFunction::raiseWarning($e, $jname);
				    }

				    //Delete old user data in the lookup table
				    $query = 'DELETE FROM #__jfusion_users_plugin WHERE id =' . $joomla_id . ' OR username = ' . $db->Quote($userinfo->username) . ' OR LOWER(username) = ' . strtolower($db->Quote($userinfo->email));
				    $db->setQuery($query);
				    try {
					    $db->execute();
				    } catch (Exception $e) {
					    FusionFunction::raiseWarning($e, $jname);
				    }
			    }
		    } else {
			    //check to see if we have been given a joomla id
			    if (empty($joomla_id)) {
				    $query = $db->getQuery(true)
					    ->select('id')
					    ->from('#__users')
					    ->where('username = ' . $db->Quote($userinfo->username));

				    $db->setQuery($query);
				    $joomla_id = $db->loadResult();
				    if (empty($joomla_id)) {
					    return;
				    }
			    }
			    if (empty($jname)) {
				    $queries = array();
				    //we need to update each master/slave
				    $query = $db->getQuery(true)
					    ->select('name')
					    ->from('#__jfusion')
					    ->where('master = 1')
					    ->where('slave = 1');

				    $db->setQuery($query);
				    $jnames = $db->loadObjectList();
				    foreach ($jnames as $jname) {
					    if ($jname->name != 'joomla_int') {
						    $user = JFusionFactory::getUser($jname->name);
						    $puserinfo = $user->getUser($userinfo);
						    if ($delete) {
							    $queries[] = '(id = '.$joomla_id.' AND jname = ' . $db->Quote($jname->name) . ')';
						    } else {
							    $queries[] = '(' . $db->Quote($puserinfo->userid) . ',' . $db->Quote($puserinfo->username) . ', '.$joomla_id.', '. $db->Quote($jname->name) . ')';
						    }
						    unset($user);
						    unset($puserinfo);
					    }
				    }
				    if (!empty($queries)) {
					    if ($delete) {
						    $query = 'DELETE FROM #__jfusion_users_plugin WHERE ' . implode(' OR ', $queries);
					    } else {
						    $query = 'REPLACE INTO #__jfusion_users_plugin (userid,username,id,jname) VALUES (' . implode(',', $queries) . ')';
					    }
					    $db->setQuery($query);
					    try {
						    $db->execute();
					    } catch (Exception $e) {
						    JFusionFunction::raiseWarning($e, $jname);
					    }
				    }
			    } else {
				    if ($delete) {
					    $query = 'DELETE FROM #__jfusion_users_plugin WHERE id = '.$joomla_id.' AND jname = '.$db->Quote($jname);
				    } else {
					    $query = 'REPLACE INTO #__jfusion_users_plugin (userid,username,id,jname) VALUES ('.$db->Quote($userinfo->userid) .' ,'.$db->Quote($userinfo->username) .' ,'.$joomla_id.' , '.$db->Quote($jname).' )';
				    }
				    $db->setQuery($query);
				    try {
					    $db->execute();
				    } catch (Exception $e) {
					    JFusionFunction::raiseWarning($e, $jname);
				    }
			    }
		    }
	    }
    }

    /**
     * Returns the userinfo data for JFusion plugin based on the userid
     *
     * @param string  $jname      name of the JFusion plugin used
     * @param string  $userid     The ID of the user
     * @param boolean $isJoomlaId if true, returns the userinfo data based on Joomla, otherwise the plugin
     * @param string  $username   If the userid is that of the plugin, we need the username to find the user in case there is no record in the lookup table
     *
     * @return object database Returns the userinfo as a Joomla database object
     *
     */
    public static function lookupUser($jname, $userid, $isJoomlaId = true, $username = '')
    {
        //initialise some vars
        $db = JFactory::getDBO();
        $result = '';
        if (!empty($userid)) {
            $column = ($isJoomlaId) ? 'a.id' : 'a.userid';

	        $query = $db->getQuery(true)
		        ->select('a.*, b.email')
		        ->from('#__jfusion_users_plugin AS a')
		        ->innerJoin('#__users AS b ON a.id = b.id')
		        ->where($column . ' = ' . $db->Quote($userid))
	            ->where('a.jname = ' . $db->Quote($jname));

            $db->setQuery($query);
            $result = $db->loadObject();
        }
        //for some reason this user is not in the lookup table so let's find them
        if (empty($result)) {
            if ($isJoomlaId) {
                //we have a joomla id so let's setup a temp $userinfo
	            $query = $db->getQuery(true)
		            ->select('username, email')
		            ->from('#__users')
		            ->where('id = '.$userid);

                $db->setQuery($query);
                $result = $db->loadResult();
                $joomla_id = $userid;
            } else {
                //we have a plugin id so we need to find Joomla id then setup a temp $userinfo
                //first try JFusion's user table

	            $query = $db->getQuery(true)
		            ->select('a.id, a.email')
		            ->from('#__users AS a')
		            ->innerJoin('#__jfusion_users as b ON a.id = b.id')
		            ->where('b.username = ' . $db->Quote($username));

                $db->setQuery($query);
                $result = $db->loadObject();
                //not created by JFusion so let's check the Joomla table directly
                if (empty($result)) {
	                $query = $db->getQuery(true)
		                ->select('id, email')
		                ->from('#__users')
		                ->where('username = '.$db->Quote($username));

                    $db->setQuery($query);
                    $result = $db->loadObject();
                }
                if (!empty($result)) {
                    //we have a user
                    $result->username = $username;
                    $joomla_id = $result->id;
                }
            }
            if (!empty($result) && !empty($joomla_id) && !empty($jname)) {
                //get the plugin userinfo - specifically we need the userid which it will provide
                $user = JFusionFactory::getUser($jname);
                $existinguser = $user->getUser($result);
                if (!empty($existinguser)) {
                    //update the lookup table with the new acquired info
                    JFusionFunction::updateLookup($existinguser, $joomla_id, $jname);
                    //return the results
                    $result = new stdClass();
                    $result->userid = $existinguser->userid;
                    $result->username = $existinguser->username;
                    $result->id = $joomla_id;
                    $result->jname = $jname;
                } else {
                    //the user does not exist in the software which means they were probably a guest or deleted from the integrated software
                    //we can't create the user as we have no password
                    $result = new stdClass();
                    $result->userid = '0';
                    $result->username = $username;
                    $result->id = $joomla_id;
                    $result->jname = $jname;
                }
            }
        }
        return $result;
    }

    /**
     * Checks to see if a JFusion plugin is properly configured
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return boolean returns true if plugin is correctly configured
     */
    public static function validPlugin($jname)
    {
        $db = JFactory::getDBO();
	    $query = $db->getQuery(true)
		    ->select('status')
		    ->from('#__jfusion')
		    ->where('name = '.$db->Quote($jname));

        $db->setQuery($query);
        $result = $db->loadResult();
        if ($result == '1') {
            $result = true;
        } else {
            $result = false;
        }
        return $result;
    }

    /**
     * Delete old user data in the lookup table
     *
     * @param object $userinfo userinfo of the user to be deleted
     *
     * @return string nothing
     */
    public static function removeUser($userinfo)
    {
        //Delete old user data in the lookup table
        $db = JFactory::getDBO();
        $query = 'DELETE FROM #__jfusion_users WHERE id =' . $userinfo->id . ' OR username =' . $db->Quote($userinfo->username) . ' OR LOWER(username) = ' . strtolower($db->Quote($userinfo->email));
        $db->setQuery($query);
	    try {
		    $db->execute();
	    } catch (Exception $e) {
		    JFusionFunction::raiseWarning($e);
	    }
        $query = 'DELETE FROM #__jfusion_users_plugin WHERE id =' . $userinfo->id;
        $db->setQuery($query);
	    try {
		    $db->execute();
	    } catch (Exception $e) {
		    JFusionFunction::raiseWarning($e);
	    }
    }

    /**
     * Adds a cookie to the php header
     *
     * @param string $name      cookie name
     * @param string $value     cookie value
     * @param int    $expires   cookie expiry time
     * @param string $path      cookie path
     * @param string $domain    cookie domain
     * @param bool $secure      is the secure
     * @param bool $httponly    is the cookie http only
     * @param bool $mask        should debug info be masked ?
     *
     * @return array            cookie debug info
     */
    public static function addCookie($name, $value, $expires, $path, $domain, $secure=false, $httponly=false, $mask = false)
    {
    	$cookies = JFusionFactory::getCookies();
    	return $cookies->addCookie($name, $value, $expires, $path, $domain, $secure, $httponly, $mask);
    }

    /**
     * Raise warning function that can handle arrays
     *
     * @return array array with the php info values
     */
    public static function phpinfoArray()
    {
        //get the phpinfo and parse it into an array
        ob_start();
        phpinfo();
        $phpinfo = array('phpinfo' => array());
        if (preg_match_all('#(?:<h2>(?:<a name=".*?">)?(.*?)(?:</a>)?</h2>)|(?:<tr(?: class=".*?")?><t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>(?:<t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>(?:<t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>)?)?</tr>)#s', ob_get_clean(), $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (strlen($match[1])) {
                    $phpinfo[$match[1]] = array();
                } else if (isset($match[3])) {
                    $phpinfo[end(array_keys($phpinfo))][$match[2]] = isset($match[4]) ? array($match[3], $match[4]) : $match[3];
                } else {
                    $phpinfo[end(array_keys($phpinfo))][] = $match[2];
                }
            }
        }
        return $phpinfo;
    }

	/**
	 * Updates the discussion bot lookup table
	 * @param int $contentid
	 * @param mixed &$threadinfo object with postid, threadid, and forumid
     * @param string $jname
	 * @param int $published
	 * @param int $manual
     *
     * @return void
	 */
	public static function updateDiscussionBotLookup($contentid, &$threadinfo, $jname, $published = 1, $manual = 0)
	{
		$fdb = JFactory::getDBO();
		$modified = JFactory::getDate()->toUnix();
        $option = JFactory::getApplication()->input->getCmd('option');

        //populate threadinfo with other fields if necessary for content generation purposes
        //mainly used if the thread was just created
        if (empty($threadinfo->component)) {
            $threadinfo->contentid = $contentid;
            $threadinfo->component = $option;
            $threadinfo->modified = $modified;
            $threadinfo->jname = $jname;
            $threadinfo->published = $published;
            $threadinfo->manual = $manual;
        }

		$query = 'REPLACE INTO #__jfusion_discussion_bot SET
					contentid = '.$contentid.',
					component = '.$fdb->Quote($option).',
					forumid = '.$threadinfo->forumid.',
					threadid = '.$threadinfo->threadid.',
					postid = '.$threadinfo->postid.',
					modified = '.$fdb->Quote($modified).',
					jname = '.$fdb->Quote($jname).',
					published = '.$published.',
					manual = '.$manual;
		$fdb->setQuery($query);
		$fdb->execute();
	}

    /**
     * Creates the URL of a Joomla article
     *
     * @param string &$contentitem contentitem
     * @param string $text         string to place as the link
     * @param string $jname        jname
     *
     * @return string link
     */
    public static function createJoomlaArticleURL(&$contentitem, $text, $jname='')
    {
        $mainframe = JFactory::getApplication();
        $option = JFactory::getApplication()->input->get('option');

        if ($option == 'com_k2') {
            include_once JPATH_SITE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_k2' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'route.php';
            $article_url = urldecode(K2HelperRoute::getItemRoute($contentitem->id.':'.urlencode($contentitem->alias),$contentitem->catid.':'.urlencode($contentitem->category->alias)));
        } else {
            if (empty($contentitem->slug) || empty($contentitem->catslug)) {
                //article was edited and saved from editor
                $db = JFactory::getDBO();

	            $query = $db->getQuery(true)
		            ->select('CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(":", a.id, a.alias) ELSE a.id END as slug, CASE WHEN CHAR_LENGTH(cc.alias) THEN CONCAT_WS(":", cc.id, cc.alias) ELSE cc.id END as catslug')
		            ->from('#__content AS a')
	                ->leftJoin('#__categories AS cc ON a.catid = cc.id')
		            ->where('a.id = ' . $contentitem->id);

                $db->setQuery($query);
                $result = $db->loadObject();

                if (!empty($result)) {
                    $contentitem->slug = $result->slug;
                    $contentitem->catslug = $result->catslug;
                }
            }

            include_once JPATH_SITE.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_content'.DIRECTORY_SEPARATOR.'helpers'.DIRECTORY_SEPARATOR.'route.php';
	        $article_url = ContentHelperRoute::getArticleRoute($contentitem->slug, $contentitem->catslug);
        }

        if ($mainframe->isAdmin()) {
            //setup JRoute to use the frontend router
            $app = JApplication::getInstance('site');
            $router = $app->getRouter();
            /**
             * @ignore
             * @var $uri JUri
             */
            $uri = $router->build($article_url);
            $article_url = $uri->toString();
            //remove /administrator from path
            $article_url = str_replace('/administrator', '', $article_url);
        } else {
            $article_url = JRoute::_($article_url);
        }

        //make the URL absolute and clean it up a bit
        $joomla_url = JFusionFunction::getJoomlaURL();

        $juri = new JURI($joomla_url);
        $path = $juri->getPath();
        if ($path != '/') {
            $article_url = str_replace($path, '', $article_url);
        }

        if (substr($joomla_url, -1) == '/') {
            if ($article_url[0] == '/') {
                $article_url = substr($joomla_url, 0, -1) . $article_url;
            } else {
                $article_url = $joomla_url . $article_url;
            }
        } else {
            if ($article_url[0] == '/') {
                $article_url = $joomla_url . $article_url;
            } else {
                $article_url = $joomla_url . '/' . $article_url;
            }
        }

        $link = '<a href="'.$article_url.'">'.$text.'</a>';

        return $link;
    }

    /**
     * Parses text from bbcode to html, html to bbcode, or html to plaintext
     * $options include:
     * strip_all_html - if $to==bbcode, strips all unsupported html from text (default is false)
     * bbcode_patterns - if $to==bbcode, adds additional html to bbcode rules; array [0] startsearch, [1] startreplace, [2] endsearch, [3] endreplace
     * parse_smileys - if $to==html, disables the bbcode smiley parsing; useful for plugins that do their own smiley parsing (default is true)
	 * custom_smileys - if $to==html, adds custom smileys to parser; array in the format of array[$smiley] => $path.  For example $options['custom_smileys'][':-)'] = 'http://mydomain.com/smileys/smile.png';
     * html_patterns - if $to==html, adds additional bbcode to html rules;
     *     Must be an array of elements with the custom bbcode as the key and the value in the format described at http://nbbc.sourceforge.net/readme.php?page=usage_add
     *     For example $options['html_patterns']['mono'] = array('simple_start' => '<tt>', 'simple_end' => '</tt>', 'class' => 'inline', 'allow_in' => array('listitem', 'block', 'columns', 'inline', 'link'));
     * character_limit - if $to==html OR $to==plaintext, limits the number of visible characters to the user
     * plaintext_line_breaks - if $to=='plaintext', should line breaks when converting to plaintext be replaced with <br /> (br) (default), converted to spaces (space), or left as \n (n)
     * plain_tags - if $to=='plaintext', array of custom bbcode tags (without brackets) that should be stripped
     *
     * @param string $text    the actual text
     * @param string $to      what to convert the text to; bbcode, html, or plaintext
     * @param mixed  $options array with parser options
     *
     * @return string with converted text
     */
    public static function parseCode($text, $to, $options = array())
    {
        $options = !is_array($options) ? array() : $options;

        if ($to == 'plaintext') {
            if (!isset($options['plaintext_line_breaks'])) {
                $options['plaintext_line_breaks'] = 'br';
            }

            /**
             * @ignore
             * @var $bbcode BBCode_Parser
             */
            $bbcode = JFusionFactory::getCodeParser();
            $bbcode->SetPlainMode(true);
            if (isset($options['plain_tags']) && is_array($options['plain_tags'])) {
                foreach ($options['plain_tags'] as $tag) {
                    $bbcode->AddRule($tag, array('class' => 'inline', 'allow_in' => array('block', 'inline', 'link', 'list', 'listitem', 'columns', 'image')));
                }
            }

            if (!empty($options['character_limit'])) {
                $bbcode->SetLimit($options['character_limit']);
            }

            //first thing is to protect our code blocks
            $text = preg_replace("#\[code\](.*?)\[\/code\]#si", "[code]<!-- CODE BLOCK -->$1<!-- END CODE BLOCK -->[/code]", $text, '-1', $code_count);

            $text = $bbcode->Parse($text);
            $text = $bbcode->UnHTMLEncode(strip_tags($text));

            //re-encode our code blocks
            if (!empty($code_count)) {
				$text = preg_replace_callback("#<!-- CODE BLOCK -->(.*?)<!-- END CODE BLOCK -->#si",array( 'JFusionFunction','_callback_htmlspecialchars'), $text);
            }

            //catch newly unencoded tags
            $text = strip_tags($text);

            if ($options['plaintext_line_breaks'] == 'br') {
                $text = $bbcode->nl2br($text);
            } elseif ($options['plaintext_line_breaks'] == 'space') {
                $text = str_replace("\n", '  ', $text);
            }
        } elseif ($to == 'html') {
            //Encode html entities added by the plugin prepareText function
            $text = htmlentities($text);

            $bbcode = JFusionFactory::getCodeParser();

            //do not parse & into &amp;
            $bbcode->SetAllowAmpersand(true);

            if (isset($options['html_patterns']) && is_array($options['html_patterns'])) {
                foreach ($options['html_patterns'] as $name => $rule) {
                    $bbcode->AddRule($name, $rule);
                }
            }

            if (!empty($options['parse_smileys'])) {
                $bbcode->SetSmileyURL(JFusionFunction::getJoomlaURL() . 'components/com_jfusion/images/smileys');
            } else {
                $bbcode->SetEnableSmileys(false);
            }

            if (!empty($options['custom_smileys'])) {
                foreach ($options['custom_smileys'] AS $smiley => $path) {
                    $bbcode->AddSmiley($smiley, $path);
                }
            }

            if (!empty($options['character_limit'])) {
                $bbcode->SetLimit($options['character_limit']);
            }

            //disabled this as it caused issues with images and youtube links
            //$bbcode->SetDetectURLs(true);
            //$bbcode->SetURLPattern('<a href="{$url/h}" target="_blank">{$text/h}</a>');

            //first thing is to protect our code blocks
            $text = preg_replace("#\[code\](.*?)\[\/code\]#si", "[code]<!-- CODE BLOCK -->$1<!-- END CODE BLOCK -->[/code]", $text, '-1', $code_count);

            $text = $bbcode->Parse($text);

            //Decode for output
            $text = html_entity_decode($text);

            //re-encode our code blocks
            if (!empty($code_count)) {
                $text = preg_replace_callback("#<!-- CODE BLOCK -->(.*?)<!-- END CODE BLOCK -->#si",array( 'JFusionFunction','_callback_htmlspecialchars'), $text);
            }
        } elseif ($to == 'bbcode') {
            if (!isset($options['bbcode_patterns'])) {
                $options['bbcode_patterns'] = '';
            }
            if (!isset($options['strip_all_html'])) {
                $options['strip_all_html'] = true;
            }

            //remove all line breaks to prevent massive empty space in bbcode
            $text = str_replace(array("\n","\r","\n\r"), '', $text);

            static $search, $replace;
            if (!is_array($search)) {
                $search = $replace = array();
                $search[] = "#<(blockquote|cite)[^>]*>(.*?)<\/\\1>#si";
                $replace[] = "[quote]$2[/quote]";
                $search[] = "#<ol[^>]*>(.*?)<\/ol>#si";
                $replace[] = "[list=1]$1[/list]";
                $search[] = "#<ul[^>]*>(.*?)<\/ul>#si";
                $replace[] = "[list]$1[/list]";
                $search[] = "#<li[^>]*>(.*?)<\/li>#si";
                $replace[] = "[*]$1";
                $search[] = "#<img [^>]*src=['|\"](?!\w{0,10}://)(.*?)['|\"][^>]*>#si";
                $replace[] = array( 'JFusionFunction','_callback_parseTag_img');
                $search[] = "#<img [^>]*src=['|\"](.*?)['|\"][^>]*>#sim";
                $replace[] = "[img]$1[/img]";
                $search[] = "#<a [^>]*href=['|\"]mailto:(.*?)['|\"][^>]*>(.*?)<\/a>#si";
                $replace[] = "[email=$1]$2[/email]";
                $search[] = "#<a [^>]*href=['|\"](?!\w{0,10}://|\#)(.*?)['|\"][^>]*>(.*?)</a>#si";
                $replace[] = array( 'JFusionFunction','_callback_url');
                $search[] = "#<a [^>]*href=['|\"](.*?)['|\"][^>]*>(.*?)<\/a>#si";
                $replace[] = "[url=$1]$2[/url]";
                $search[] = "#<(b|i|u)>(.*?)<\/\\1>#si";
                $replace[] = "[$1]$2[/$1]";
                $search[] = "#<font [^>]*color=['|\"](.*?)['|\"][^>]*>(.*?)<\/font>#si";
                $replace[] = "[color=$1]$2[/color]";
                $search[] = "#<p>(.*?)<\/p>#si";
                $replace[] = array( 'JFusionFunction','_callback_parseTag_p');
            }
            $searchNS = $replaceNS = array();
            //convert anything between code or pre tags to html entities to prevent conversion
            $searchNS[] = "#<(code|pre)[^>]*>(.*?)<\/\\1>#si";
            $replaceNS[] = array( 'JFusionFunction','_callback_code');
            $morePatterns = $options['bbcode_patterns'];
            if (is_array($morePatterns) && isset($morePatterns[0]) && isset($morePatterns[1])) {
                $searchNS = array_merge($searchNS, $morePatterns[0]);
                $replaceNS = array_merge($replaceNS, $morePatterns[1]);
            }
            $searchNS = array_merge($searchNS, $search);
            $replaceNS = array_merge($replaceNS, $replace);
            if (is_array($morePatterns) && isset($morePatterns[2]) && isset($morePatterns[3])) {
                $searchNS = array_merge($searchNS, $morePatterns[2]);
                $replaceNS = array_merge($replaceNS, $morePatterns[3]);
            }
            $text = str_ireplace(array('<br />', '<br>', '<br/>'), "\n", $text);

			foreach ($searchNS as $k => $v) {
	            //check if we need to use callback
	            if(is_array($replaceNS[$k])){
	                $text = preg_replace_callback($searchNS[$k],$replaceNS[$k], $text);
	            } else {
	                $text = preg_replace($searchNS[$k], $replaceNS[$k], $text);
	            }
	        }

            //decode html entities that we converted for code and pre tags
            $text = preg_replace_callback("#\[code\](.*?)\[\/code\]#si",array( 'JFusionFunction','_callback_code_decode'), $text);
            //to prevent a billion line breaks in post, let's convert three line breaks into two
            $text = str_ireplace("\n\n\n", "\n\n", $text);
            //and one more time for good measure (there's gotta be a better way)
            $text = str_ireplace("\n\n\n", "\n\n", $text);
            //Change to ensure that the discussion bot posts the article to the forums when there
            //is an issue with preg_replace( '/\p{Z}/u', ' ', $text ) returning an empty string
            //or a series of whitespace.
            //Change to code David Coutts 03/08/2009
            $text_utf8space_to_space = preg_replace('/\p{Z}/u', ' ', $text);
            //Check to see if the returned function is not empty or purely spaces/
            if (strlen(rtrim($text_utf8space_to_space)) > 0) {
                //function returned properly set the output text to be the right trimmed output of the string
                $text = rtrim($text_utf8space_to_space);
            }

            if ($options['strip_all_html']) {
                $text = strip_tags($text);
            }
        }
        return $text;
    }

    /**
     * Used by the JFusionFunction::parseCode function to parse various tags when parsing to bbcode.
     * For example, some Joomla editors like to use an empty paragraph tag for line breaks which gets
     * parsed into a lot of unnecessary line breaks
     *
     * @param mixed $matches mixed values from preg functions
     * @param string $tag
     *
     * @return string to replace search subject with
     */
    public static function parseTag($matches, $tag = 'p')
    {
        $return = false;
        if ($tag == 'p') {
            $text = trim($matches);
            //remove the slash added to double quotes and slashes added by the e modifier
            $text = str_replace('\"', '"', $text);
            if(empty($text) || ord($text) == 194) {
                //p tags used simply as a line break
                $return = "\n";
            } else {
                $return = $text . "\n\n";
            }
        } elseif ($tag == 'img') {
            $joomla_url = JFusionFunction::getJoomlaURL();
            $juri = new JURI($joomla_url);
            $path = $juri->getPath();
            if ($path != '/'){
                $matches = str_replace($path,'',$matches);
            }
            $url = JRoute::_($joomla_url . $matches);
            $return = $url;
        }
        return $return;
    }

    /**
     * Reconnects Joomla DB if it gets disconnected
     *
     * @return string nothing
     */
    public static function reconnectJoomlaDb()
    {
        //check to see if the Joomla database is still connected
        $db = JFactory::getDBO();
        jimport('joomla.database.database');
        jimport('joomla.database.table');
        $conf = JFactory::getConfig();
        $database = $conf->get('db');
        $connected = true;
        if (!method_exists($db,'connected')){
            $connected = false;	
        } elseif (!$db->connected()){
            $connected = false;
        }

        if (!$connected) {
	        $db->disconnect();
	        $db->connect();
        }
        //try to select the joomla database
        if (!$db->select($database)) {
	        //oops database select failed
	        die('JFusion error: could not select Joomla database when trying to restore Joomla database object');
        } else {
            //database reconnect successful, some final tidy ups
       	
        	//add utf8 support
            $db->setQuery('SET names \'utf8\'');
            $db->execute();
            //legacy $database must be restored
            if (JPluginHelper::getPlugin('system', 'legacy')) {
                $GLOBALS['database'] = $db;
            }
        }
    }



    /**
     * Retrieves the URL to a userprofile of a Joomla supported component
     *
     * @param string  $software    string name of the software
     * @param int     $uid         int userid of the user
     * @param boolean $isPluginUid indicator of plugin in uid
     * @param string  $jname       jname of the plugin
     * @param string  $username    username
     *
     * @return string URL
     */
    public static function getAltProfileURL($software, $uid, $isPluginUid = false, $jname = '', $username = '')
    {
        $db = JFactory::getDBO();
        if ($isPluginUid && !empty($jname)) {
            $userlookup = JFusionFunction::lookupUser($jname, $uid, false, $username);
            if (!empty($userlookup)) {
                $uid = $userlookup->id;
            } else {
                return '';
            }
        }
        if (!empty($uid)) {
	        $query = $db->getQuery(true)
		        ->select('id')
		        ->from('#__menu')
		        ->where('type = '.$db->Quote('component'));

            if ($software == 'cb') {
	            $query->where('link LIKE '.$db->Quote('%com_comprofiler%'));
                $db->setQuery($query,0,1);
                $itemid = $db->loadResult();
                $url = JRoute::_('index.php?option=com_comprofiler&task=userProfile&Itemid=' . $itemid . '&user=' . $uid);
            } elseif ($software == 'jomsocial') {
	            $query->where('link LIKE '.$db->Quote('%com_community%'));
                $db->setQuery($query,0,1);
                $itemid = $db->loadResult();
                $url = JRoute::_('index.php?option=com_community&view=profile&Itemid=' . $itemid . '&userid=' . $uid);
            } elseif ($software == 'joomunity') {
	            $query->where('link LIKE '.$db->Quote('%com_joomunity%'));
                $db->setQuery($query,0,1);
                $itemid = $db->loadResult();
                $url = JRoute::_('index.php?option=com_joomunity&Itemid=' . $itemid . '&cmd=Profile.View.' . $uid);
            } else {
                $url = false;
            }
        } else {
            $url = false;
        }
        return $url;
    }

    /**
     * Retrieves the source of the avatar for a Joomla supported component
     *
     * @param string  $software    software name
     * @param int     $uid         uid
     * @param boolean $isPluginUid boolean if true, look up the Joomla id in the look up table
     * @param string  $jname       needed if $isPluginId = true
     * @param string  $username    username
     *
     * @return string nothing
     */
    public static function getAltAvatar($software, $uid, $isPluginUid = false, $jname = '', $username = '')
    {
        $db = JFactory::getDBO();
        if ($isPluginUid && !empty($jname)) {
            $userlookup = JFusionFunction::lookupUser($jname, $uid, false, $username);
            if (!empty($userlookup)) {
                $uid = $userlookup->id;
            } else {
                //no user was found
                $avatar = JFusionFunction::getJoomlaURL() . 'components/com_jfusion/images/noavatar.png';
                return $avatar;
            }
        }
        if ($software == 'cb') {
	        $query = $db->getQuery(true)
		        ->select('avatar')
		        ->from('#__comprofiler')
		        ->where('user_id = '.$uid);

            $db->setQuery($query);
            $result = $db->loadResult();
            if (!empty($result)) {
                $avatar = JFusionFunction::getJoomlaURL() . 'images/comprofiler/'.$result;
            } else {
                $avatar = JFusionFunction::getJoomlaURL() . 'components/com_comprofiler/plugin/templates/default/images/avatar/nophoto_n.png';
            }
        } elseif ($software == 'jomsocial') {
	        $query = $db->getQuery(true)
		        ->select('avatar')
		        ->from('#__community_users')
		        ->where('userid = '.$uid);

            $db->setQuery($query);
            $result = $db->loadResult();
            if (!empty($result)) {
                $avatar = JFusionFunction::getJoomlaURL() . $result;
            } else {
                $avatar = JFusionFunction::getJoomlaURL() . 'components/com_community/assets/default_thumb.jpg';
            }
        } elseif ($software == 'joomunity') {
	        $query = $db->getQuery(true)
		        ->select('user_picture')
		        ->from('#__joom_users')
		        ->where('user_id = '.$uid);

            $db->setQuery($query);
            $result = $db->loadResult();
            $avatar = JFusionFunction::getJoomlaURL() . 'components/com_joomunity/files/avatars/' . $result;
        } elseif ($software == 'gravatar') {
	        $query = $db->getQuery(true)
		        ->select('email')
		        ->from('#__users')
		        ->where('id = ' . $uid);

            $db->setQuery($query);
            $email = $db->loadResult();
            $avatar = 'http://www.gravatar.com/avatar.php?gravatar_id=' . md5(strtolower($email)) . '&size=40';
        } else {
            $avatar = JFusionFunction::getJoomlaURL() . 'components/com_jfusion/images/noavatar.png';
        }
        return $avatar;
    }

    /**
     * Gets the source_url from the joomla_int plugin
     *
     * @return string Joomla source URL
     */
    public static function getJoomlaURL()
    {
        static $joomla_source_url;
        if (empty($joomla_source_url)) {
            $params = JFusionFactory::getParams('joomla_int');
            $joomla_source_url = $params->get('source_url');
        }
        return $joomla_source_url;
    }

    /**
     * Gets the base url of a specific menu item
     *
     * @param int $itemid int id of the menu item
     * @param boolean $xhtml  return URL with encoded ampersands
     *
     * @return string parsed base URL of the menu item
     */
    public static function getPluginURL($itemid, $xhtml = true)
    {
        static $jfusionPluginURL;
        if (!is_array($jfusionPluginURL)) {
            $jfusionPluginURL = array();
        }
        if (!isset($jfusionPluginURL[$itemid])) {
            $joomla_url = JFusionFunction::getJoomlaURL();
            $baseURL = JRoute::_('index.php?option=com_jfusion&Itemid=' . $itemid, false);
            if (!strpos($baseURL, '?')) {
                $baseURL = preg_replace('#\.[\w]{3,4}\z#is', '', $baseURL);
                if (substr($baseURL, -1) != '/') {
                    $baseURL.= '/';
                }
            }
            $juri = new JURI($joomla_url);
            $path = $juri->getPath();
            if ($path != '/') {
                $baseURL = str_replace($path, '', $baseURL);
            }
            if (substr($joomla_url, -1) == '/') {
                if ($baseURL[0] == '/') {
                    $baseURL = substr($joomla_url, 0, -1) . $baseURL;
                } else {
                    $baseURL = $joomla_url . $baseURL;
                }
            } else {
                if ($baseURL[0] == '/') {
                    $baseURL = $joomla_url . $baseURL;
                } else {
                    $baseURL = $joomla_url . '/' . $baseURL;
                }
            }

            $jfusionPluginURL[$itemid] = $baseURL;
        }

        //let's clean up the URL here before passing it
        if($xhtml) {
            $url = str_replace('&', '&amp;', $jfusionPluginURL[$itemid]);
        } else {
            $url = $jfusionPluginURL[$itemid];
        }
        return $url;
    }

    /**
     * hides sensitive information
     *
     * @param object $userinfo userinfo
     *
     * @return string parsed userinfo object
     */
    public static function anonymizeUserinfo($userinfo)
    {
        if ( is_object($userinfo) ) {
            $userclone = clone $userinfo;
            $userclone->password_clear = '******';
            if (isset($userclone->password)) {
                $userclone->password = substr($userclone->password, 0, 6) . '********';
            }
            if (isset($userclone->password_salt)) {
                $userclone->password_salt = substr($userclone->password_salt, 0, 4) . '*****';
            }
        } else {
            $userclone = $userinfo;
        }
        return $userclone;
    }

    /**
     * checks if the user is an admin
     *
     * @return boolean to indicate admin status
     */
    public static function isAdministrator()
    {
        $mainframe = JFactory::getApplication();
        if ($mainframe->isAdmin()) {
            //we are on admin side, lets confirm that the user has access to user manager
            $juser = JFactory::getUser();

	        if ($juser->authorise('core.manage', 'com_users')) {
		        $debug = true;
	        } else {
		        $debug = false;
	        }
        } else {
            $debug = false;
        }
        return $debug;
    }

    /**
     * Converts a string to all ascii characters
     *
     * @param string $input str to convert
     *
     * @return string converted string
     */
    public static function strtoascii($input)
    {
        $output = '';
        foreach (str_split($input) as $char) {
            $output.= '&#' . ord($char) . ';';
        }
        return $output;
    }

    /**
     * Retrieves the current timezone based on user preference
     * Defaults to Joomla global config for timezone
     * Hopefully the need for this will be deprecated in Joomla 1.6
     *
     * @return timezone in -6 format
     */
    public static function getJoomlaTimezone()
    {
        static $timezone;
        if (!isset($timezone)) {
            $mainframe = JFactory::getApplication();
            $timezone = $mainframe->getCfg('offset');

            $JUser = JFactory::getUser();
            if (!$JUser->guest) {
                $timezone = $JUser->getParam('timezone', $timezone);
            }
        }
        return $timezone;
    }

    /**
     * return the correct usergroups for a given user
     *
     * @param string $jname plugin name
     * @param object|null $userinfo user with correct usergroups, if null it will return the usergroup for new users
     *
     * @return array
     */
    public static function getCorrectUserGroups($jname,$userinfo) {
        $params = JFusionFactory::getParams($jname);
        $usergroups = $params->get('usergroup',null);
		$multiusergroup = $params->get('multiusergroup',null);
        $group = array();
        if ($usergroups !== null) {
            if ($userinfo === null) {
                if (substr($usergroups, 0, 2) != 'a:') {
                    $group = array($usergroups);
                }
            } else {
                $usergroups = (substr($usergroups, 0, 2) == 'a:') ? unserialize($usergroups) : $usergroups;
                if (is_array($usergroups) ) {
                    if (isset($userinfo->group_id) && isset($usergroups[$userinfo->group_id])) {
                        $usergroup = $usergroups[$userinfo->group_id];
	                    if (is_array($usergroup)) {
		                    //use the first var in the array
		                    $keys = array_keys($usergroup);
		                    $usergroup = $usergroup[$keys[0]];
	                    }
                        $group = array($usergroup);
                    }
                } else {
                    $group = array($usergroups);
                }
            }
        } else if ($multiusergroup !== null) {
        	$master = JFusionFunction::getMaster();

	        $multiusergroupdefault = $params->get('multiusergroupdefault');
	        $multiusergroup = (substr($multiusergroup, 0, 2) == 'a:') ? unserialize($multiusergroup) : $multiusergroup;

			if (!is_array($multiusergroup)) {
				$group = array($multiusergroup);
	        } else {
                $groups = array();
                if ($userinfo) {
                    if (isset($userinfo->groups)) {
                        $groups = $userinfo->groups;
                    } elseif (isset($userinfo->group_id)) {
                        $groups[] = $userinfo->group_id;
                    }
                }

                $mastergroups = isset($multiusergroup[$master->name]) ? $multiusergroup[$master->name] : array();
                $slavegroups = isset($multiusergroup[$jname]) ? $multiusergroup[$jname] : array();

                foreach ($mastergroups as $key => $mastergroup) {
                    if ( count($mastergroup) == count($groups) ) {
                        $count = 0;
                        foreach ($mastergroup as $value) {
                            if (in_array($value, $groups, true)) {
                                $count++;
                            }
                        }
                        if (count($groups) == $count ) {
                            $group =  $slavegroups[$key];
                            break;
                        }
                    }
                }
                if (!count($group) && isset($slavegroups[$multiusergroupdefault])) {
                    $group =  $slavegroups[$multiusergroupdefault];
                }
            }
        }
		return $group;
    }

    /**
     * compare set of usergroup with a user returns true if the usergroups are correct
     *
     * @param object $userinfo user with current usergroups
     * @param array $usergroups array with the correct usergroups
     *
     * @return true/false
     */
    public static function compareUserGroups($userinfo,$usergroups) {
    	if (!is_array($usergroups)) {
    		$usergroups = array($usergroups);
    	}
        $correct = false;
    	if (isset($userinfo->groups)) {
			$count = 0;
			if ( count($usergroups) == count($userinfo->groups) ) {
				foreach ($usergroups as $key => $group) {
    	    		if (in_array($group, $userinfo->groups, true)) {
    					$count++;
					}
	        	}
				if (count($userinfo->groups) == $count) {
                    $correct = true;
				}
			}
    	} else {
    		foreach ($usergroups as $key => $group) {
    			if ($group == $userinfo->group_id) {
                    $correct = true;
                    break;
    			}
        	}
    	}
		return $correct;
    }

    /**
     * returns true / false if the plugin is in advanced usergroup mode or not...
     *
     * @param string $jname plugin name
     *
     * @return true/false
     */
    public static function isAdvancedUsergroupMode($jname) {
        static $advanced = array();
        if (!isset($advanced[$jname])) {
            $params = JFusionFactory::getParams($jname);
            $usergroup = $params->get('usergroup');
            $multiusergroup = $params->get('multiusergroup');
            if (substr($usergroup, 0, 2) == 'a:' || substr($multiusergroup, 0, 2) == 'a:') {
                $advanced[$jname] = true;
            } else {
                $advanced[$jname] = false;
            }
        }
        return $advanced[$jname];
    }

    /**
     * @static
     * @param $extension
     * @param $type
     * @param $name
     * @param null $basePath
     *
     * @return bool
     */
    public static function loadLanguage($extension,$type,$name, $basePath = null){
		$extension = $extension.'_'.$type.'_'.$name;
	    if ($basePath == null) {
		    $basePath = JPATH_ADMINISTRATOR;
	    }
	    $lang = JFactory::getLanguage();
	    return $lang->load(strtolower($extension), $basePath, null, false, false)
	    ||	$lang->load(strtolower($extension), JPATH_PLUGINS .DIRECTORY_SEPARATOR.$type.DIRECTORY_SEPARATOR.$name, null, false, false)
	    ||	$lang->load(strtolower($extension), $basePath, $lang->getDefault(), false, false)
	    ||	$lang->load(strtolower($extension), JPATH_PLUGINS .DIRECTORY_SEPARATOR.$type.DIRECTORY_SEPARATOR.$name, $lang->getDefault(), false, false);
    }

	/**
	 * Convert a utf-8 joomla string in to a valid encoding matching the table/filed it will be sent to
	 *
	 * @static
	 *
	 * @param string $string string to convert
	 * @param string $jname  used to get the database object, and point to the static stored data
	 * @param string $table  table that we will be looking at
	 * @param string $field  field that we will be looking at
	 *
	 * @throws RuntimeException
	 * @return bool|string
	 */
    public static function encodeDBString($string, $jname, $table, $field) {
        static $data;
        $data = array();
        if (!isset($data[$jname][$table])) {
            $db = JFusionFactory::getDatabase($jname);
            $query = 'SHOW FULL FIELDS FROM '.$table;
            $db->setQuery($query);
            $fields = $db->loadObjectList();

            foreach ($fields as $f) {
                if ($f->Collation) {
                    $data[$jname][$table][$f->Field] = $f->Collation;
                }
            }
        }

        if (isset($data[$jname][$table][$field]) ) {
        	list($charset) = explode('_', $data[$jname][$table][$field]);
            switch ($charset) {
                case 'latin1':
                	$encoding = 'ISO-8859-1';
                    break;
                case 'utf8':
	                $encoding = false;
                    break;
                default:
	                throw new RuntimeException('JFusion Encoding support missing: '.$charset);
                    break;
            }
            if ($encoding) {
            	$converted = false;
	            if (function_exists ('iconv')) {
                    $converted = iconv('utf-8', $encoding, $string);
	            } else if (function_exists('mb_convert_encoding')) {
                    $converted = mb_convert_encoding($string, $encoding, 'utf-8');
                } else {
		            throw new RuntimeException('JFusion: missing iconv or mb_convert_encoding');
                }
                if ($converted !== false) {
                	$string = $converted;
                } else {
	                throw new RuntimeException('JFusion Encoding failed '.$charset);
                }
            }
        }
        return $string;
    }

    /**
     * @static
     * @param $matches
     *
     * @return string
     */
    public static function _callback_htmlspecialchars($matches)
    {
        return htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
    }

    /**
     * @static
     * @param $matches
     *
     * @return string
     */
    public static function _callback_code($matches)
    {
        return '[code]'.htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8').'[/code]';
    }

    /**
     * @static
     * @param $matches
     *
     * @return string
     */
    public static function _callback_code_decode($matches)
    {
        return '[code]'.htmlspecialchars_decode($matches[1], ENT_QUOTES).'[/code]';
    }

    /**
     * @static
     * @param $matches
     *
     * @return string
     */
    public static function _callback_parseTag_img($matches)
    {
        return '[img]'.JFusionFunction::parseTag($matches[1],'img').'[/img]';
    }

    /**
     * @static
     * @param $matches
     *
     * @return string
     */
    public static function _callback_parseTag_p($matches)
    {
        return JFusionFunction::parseTag($matches[1], 'p');
    }

    /**
     * @static
     * @param $matches
     *
     * @return string
     */
    public static function _callback_url($matches)
    {
    	return '[url='.JRoute::_(JFusionFunction::getJoomlaURL().$matches[1]).']'.$matches[2].'[/url]';
    }

    /**
     * Check if feature exists
     *
     * @static
     * @param string $jname
     * @param string $feature feature
     * @param int $itemid itemid
     *
     * @return bool
     */
    public static function hasFeature($jname,$feature,$itemid=null) {
        $return = false;
        switch ($feature) {
            //admin
            case 'wizard':
                $admin = JFusionFactory::getAdmin($jname);
                $return = self::methodDefined($admin,'setupFromPath');
                break;
            //public
            case 'search':
                $public = JFusionFactory::getPublic($jname);
                $return = (self::methodDefined($public,'getSearchQuery') || self::methodDefined($public,'getSearchResults'));
                break;
            case 'whosonline':
                $public = JFusionFactory::getPublic($jname);
                $return = self::methodDefined($public,'getOnlineUserQuery');
                break;
            case 'breadcrumb':
                $public = JFusionFactory::getPublic($jname);
                $return = self::methodDefined($public,'getPathWay');
                break;
            case 'frontendlanguage':
                $public = JFusionFactory::getPublic($jname);
                $return = self::methodDefined($public,'setLanguageFrontEnd');
                break;
            case 'frameless':
                $public = JFusionFactory::getPublic($jname);
                $return = self::methodDefined($public,'getBuffer');
                break;
            //forum
            case 'discussion':
                $forum = JFusionFactory::getForum($jname);
                $return = self::methodDefined($forum,'createThread');
                break;
            case 'activity':
                $forum = JFusionFactory::getForum($jname);
                $return = (self::methodDefined($forum,'getActivityQuery') || self::methodDefined($forum,'renderActivityModule'));
                break;
            case 'threadurl':
                $forum = JFusionFactory::getForum($jname);
                $return = self::methodDefined($forum,'getThreadURL');
                break;
            case 'posturl':
                $forum = JFusionFactory::getForum($jname);
                $return = self::methodDefined($forum,'getPostURL');
                break;
            case 'profileurl':
                $forum = JFusionFactory::getForum($jname);
                $return = self::methodDefined($forum,'getProfileURL');
                break;
            case 'avatarurl':
                $forum = JFusionFactory::getForum($jname);
                $return = self::methodDefined($forum,'getAvatar');
                break;
            case 'privatemessageurl':
                $forum = JFusionFactory::getForum($jname);
                $return = self::methodDefined($forum,'getPrivateMessageURL');
                break;
            case 'viewnewmessagesurl':
                $forum = JFusionFactory::getForum($jname);
                $return = self::methodDefined($forum,'getViewNewMessagesURL');
                break;
            case 'privatemessagecounts':
                $forum = JFusionFactory::getForum($jname);
                $return = self::methodDefined($forum,'getPrivateMessageCounts');
                break;
            //user
            case 'useractivity':
                $user = JFusionFactory::getUser($jname);
                $return = self::methodDefined($user,'activateUser');
                break;
            case 'duallogin':
                $user = JFusionFactory::getUser($jname);
                $return = self::methodDefined($user,'createSession');
                break;
            case 'duallogout':
                $user = JFusionFactory::getUser($jname);
                $return = self::methodDefined($user,'destroySession');
                break;
            case 'updatepassword':
                $user = JFusionFactory::getUser($jname);
                $return = self::methodDefined($user,'updatePassword');
                break;
            case 'updateusername':
                $user = JFusionFactory::getUser($jname);
                $return = self::methodDefined($user,'updateUsername');
                break;
            case 'updateemail':
                $user = JFusionFactory::getUser($jname);
                $return = self::methodDefined($user,'updateEmail');
                break;
            case 'updateusergroup':
                $user = JFusionFactory::getUser($jname);
                $return = self::methodDefined($user,'updateUsergroup');
                break;
            case 'updateuserlanguage':
                $user = JFusionFactory::getUser($jname);
                $return = self::methodDefined($user,'updateUserLanguage');
                break;
            case 'syncsessions':
                $user = JFusionFactory::getUser($jname);
                $return = self::methodDefined($user,'syncSessions');
                break;
            case 'blockuser':
                $user = JFusionFactory::getUser($jname);
                $return = self::methodDefined($user,'blockUser');
                break;
            case 'activateuser':
                $user = JFusionFactory::getUser($jname);
                $return = self::methodDefined($user,'activateUser');
                break;
            case 'deleteuser':
                $user = JFusionFactory::getUser($jname);
                $return = self::methodDefined($user,'deleteUser');
                break;
            case 'redirect_itemid':
                if ($itemid) {
                    $app = JFactory::getApplication();
                    $menus = $app->getMenu('site');
                    $item = $menus->getItem($itemid);
                    if ($item && $item->params->get('visual_integration') == 'frameless') {
                        $return = true;
                    }
                }
                break;
            case 'config':
		        if ($jname=='joomla_int') {
			        $return = false;
			        break;
		        }
            case 'any':
                $return = true;
                break;
        }
        return $return;
    }

    /**
     * Function to check if a method has been defined inside a plugin like: setupFromPath
     *
     * @static
     * @param object|string $class
     * @param $method
     *
     * @return bool
     */
    public static function methodDefined($class,$method) {
        if ( is_object($class) ) {
            $name = get_class  ( $class );
        } else {
            $name = $class;
        }

        //if the class name is the abstract class then return false
        $abstractClassNames = array('JFusionAdmin', 'JFusionAuth', 'JFusionForum', 'JFusionPublic', 'JFusionUser');
        if (in_array($name, $abstractClassNames)) {
            $return = false;
        } else {
            try {
                $m = new ReflectionMethod($class, $method);
                if ( $m->getDeclaringClass()->getName() == $name ) {
                    $return = true;
                } else {
                    $return = false;
                }
            } catch (Exception $e) {
                $return = false;
            }
        }
        return $return;
    }

	/**
	 * Checks to see if a JFusion plugin is properly configured
	 *
	 * @param string $data file path or file content
	 * @param boolean $isFile load from file
	 *
	 * @return SimpleXMLElement returns true if plugin is correctly configured
	 */
	public static function getXml($data, $isFile=true)
	{
		// Disable libxml errors and allow to fetch error information as needed
		libxml_use_internal_errors(true);

		if ($isFile)
		{
			// Try to load the XML file
			$xml = simplexml_load_file($data);
		}
		else
		{
			// Try to load the XML string
			$xml = simplexml_load_string($data);
		}

		if ($xml === false)
		{
			JFusionFunction::raiseError(JText::_('JLIB_UTIL_ERROR_XML_LOAD'));

			if ($isFile)
			{
				JFusionFunction::raiseError($data);
			}
			foreach (libxml_get_errors() as $error)
			{
				JFusionFunction::raiseError($error->message);
			}
		}
		return $xml;
	}

	/**
	 * @return plgAuthenticationJoomla
	 */
	public static function getJoomlaAuth() {
		$dispatcher = JEventDispatcher::getInstance();

		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select('folder, type, element AS name, params')
			->from('#__extensions')
			->where('element = ' . $db->Quote('joomla'))
			->where('type =' . $db->Quote('plugin'))
			->where('folder =' . $db->Quote('authentication'));

		$plugin = $db->setQuery($query)->loadObject();
		$plugin->type = $plugin->folder;

		$path = JPATH_PLUGINS . '/authentication/joomla/joomla.php';
		require_once $path;

		$className = 'plg' . $plugin->type . $plugin->name;
		return new plgAuthenticationJoomla($dispatcher, (array) ($plugin));
	}

	/**
	 * @param string|RuntimeException $msg
	 * @param string           $jname
	 */
	public static function raiseMessage($msg, $jname='') {
		$app = JFactory::getApplication();
		if ($msg instanceof RuntimeException) {
			$msg = $msg->getMessage();
		}
		if (!empty($jname)) {
			$msg = $jname.': '.$msg;
		}
		$app->enqueueMessage($msg,'message');
	}

	/**
	 * @param string|RuntimeException $msg
	 * @param string           $jname
	 */
	public static function raiseNotice($msg, $jname='') {
		$app = JFactory::getApplication();
		if ($msg instanceof RuntimeException) {
			$msg = $msg->getMessage();
		}
		if (!empty($jname)) {
			$msg = $jname.': '.$msg;
		}
		$app->enqueueMessage($msg, 'notice');
	}

	/**
	 * @param string|RuntimeException $msg
	 * @param string           $jname
	 */
	public static function raiseWarning($msg, $jname='') {
		$app = JFactory::getApplication();
		if ($msg instanceof RuntimeException) {
			$msg = $msg->getMessage();
		}
		if (!empty($jname)) {
			$msg = $jname.': '.$msg;
		}
		$app->enqueueMessage($msg,'warning');
	}

	/**
	 * @param string|RuntimeException $msg
	 * @param string           $jname
	 */
	public static function raiseError($msg, $jname='') {
		$app = JFactory::getApplication();
		if ($msg instanceof RuntimeException) {
			$msg = $msg->getMessage();
		}
		if (!empty($jname)) {
			$msg = $jname.': '.$msg;
		}
		$app->enqueueMessage($msg,'error');
	}

	/**
	 * Raise warning function that can handle arrays
	 *
	 * @param        $type
	 * @param array  $message   message itself
	 * @param string $jname
	 *
	 * @return string nothing
	 */
	public static function raise($type, $message, $jname='') {
		if (is_array($message)) {
			foreach ($message as $msgtype => $msg) {
				//if still an array implode for nicer display
				if (is_numeric($msgtype)) {
					$msgtype = $jname;
				}
				JFusionFunction::raise($type, $msg, $msgtype);
			}
		} else {
			switch(strtolower($type)) {
				case 'notice':
					JFusionFunction::raiseNotice($message, $jname);
					break;
				case 'error':
					JFusionFunction::raiseError($message, $jname);
					break;
				case 'warning':
					JFusionFunction::raiseWarning($message, $jname);
					break;
				case 'message':
					JFusionFunction::raiseMessage($message, $jname);
					break;
			}
		}
	}

	/**
	 * @return array
	 */
	public static function renderMessage() {
		$app = JFactory::getApplication();

		$messages = $app->getMessageQueue();

		$list = array();
		if (is_array($messages) && !empty($messages))
		{
			foreach ($messages as $msg)
			{
				if (isset($msg['type']) && isset($msg['message']))
				{
					$list[$msg['type']][] = $msg['message'];
				}
			}
		}
		return $list;
	}

	/**
	 * @return void
	 */
	public static function initJavaScript() {
		static $js;
		if (!$js) {
			$text = array();
			if ( JFactory::getApplication()->isAdmin() ) {
				$keys = array( 'SESSION_TIMEOUT', 'NOTICE', 'WARNING', 'MESSAGE', 'ERROR', 'DELETED', 'DELETE_PAIR', 'REMOVE', 'OK');

				$url = JURI::root() . 'administrator/index.php';
			} else {
				$keys = array( 'SESSION_TIMEOUT', 'NOTICE', 'WARNING', 'MESSAGE', 'ERROR');

				$url = JURI::root() . 'index.php';
			}

			foreach($keys as $key) {
				$text[$key] = JText::_($key);
			}
			$text = json_encode($text);

			$js=<<<JS
			JFusion.url = '{$url}';

			JFusion.text = {$text};
JS;

			$document = JFactory::getDocument();
			$document->addScriptDeclaration($js);
		}
	}

	/**
	 * @param string|array $keys
	 */
	public static function loadJavascriptLanguage($keys) {
		JFusionFunction::initJavaScript();
		if (!empty($keys)) {
			$document = JFactory::getDocument();

			$js = '';
			if (is_array($keys)) {
				foreach($keys as $key) {
					$text = JText::_($key, true);
					$js .=<<<JS
			JFusion.text['{$key}'] = '{$text}';
JS;
				}
			} else {
				$text = JText::_($keys, true);
				$js .=<<<JS
			JFusion.text['{$keys}'] = '{$text}';
JS;
			}
			$document->addScriptDeclaration($js);
		}
	}
}