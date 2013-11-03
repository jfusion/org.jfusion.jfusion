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
	public static function getPluginStatus($element, $folder) {
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
                //otherwise just tak on the
                $baseURL = static::getPluginURL($itemid, false);
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
				    $query = $db->getQuery(true)
					    ->delete('#__jfusion_users')
					    ->where('id =' . $joomla_id, 'OR')
					    ->where('username = ' . $db->Quote($userinfo->username))
					    ->where('LOWER(username) = ' . strtolower($db->Quote($userinfo->email)));

				    $db->setQuery($query);
				    try {
					    $db->execute();
				    } catch (Exception $e) {
					    static::raiseWarning($e, $jname);
				    }

				    //Delete old user data in the lookup table
				    $query = $db->getQuery(true)
					    ->delete('#__jfusion_users_plugin')
					    ->where('id =' . $joomla_id, 'OR')
					    ->where('username = ' . $db->Quote($userinfo->username))
					    ->where('LOWER(username) = ' . strtolower($db->Quote($userinfo->email)));
				    $db->setQuery($query);
				    try {
					    $db->execute();
				    } catch (Exception $e) {
					    static::raiseWarning($e, $jname);
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
							    $queries[] = '(id = ' . $joomla_id . ' AND jname = ' . $db->Quote($jname->name) . ')';
						    } else {
							    $queries[] = '(' . $db->Quote($puserinfo->userid) . ',' . $db->Quote($puserinfo->username) . ', ' . $joomla_id . ', ' . $db->Quote($jname->name) . ')';
						    }
						    unset($user);
						    unset($puserinfo);
					    }
				    }
				    if (!empty($queries)) {
					    if ($delete) {
						    $query = $db->getQuery(true)
							    ->delete('#__jfusion_users_plugin');
						    foreach ($queries as $q) {
							    $query->where($q, 'OR');
						    }
					    } else {
						    $query = 'REPLACE INTO #__jfusion_users_plugin (userid,username,id,jname) VALUES (' . implode(',', $queries) . ')';
					    }
					    $db->setQuery($query);
					    try {
						    $db->execute();
					    } catch (Exception $e) {
						    static::raiseWarning($e, $jname);
					    }
				    }
			    } else {
				    if ($delete) {
					    $query = $db->getQuery(true)
						    ->delete('#__jfusion_users_plugin')
					        ->where('id = ' . $joomla_id)
						    ->where('jname = ' . $db->Quote($jname));
				    } else {
					    $query = 'REPLACE INTO #__jfusion_users_plugin (userid,username,id,jname) VALUES (' . $db->Quote($userinfo->userid) . ' ,' . $db->Quote($userinfo->username) . ' ,' . $joomla_id . ' , ' . $db->Quote($jname) . ' )';
				    }
				    $db->setQuery($query);
				    try {
					    $db->execute();
				    } catch (Exception $e) {
					    static::raiseWarning($e, $jname);
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
		            ->where('id = ' . $userid);

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
		                ->where('username = ' . $db->Quote($username));

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
	                static::updateLookup($existinguser, $joomla_id, $jname);
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

	    $query = $db->getQuery(true)
		    ->delete('#__jfusion_users')
		    ->where('id = ' . $userinfo->id, 'OR')
	        ->where('username =' . $db->Quote($userinfo->username))
		    ->where('LOWER(username) = ' . strtolower($db->Quote($userinfo->email)));

        $db->setQuery($query);
	    try {
		    $db->execute();
	    } catch (Exception $e) {
		    static::raiseWarning($e);
	    }

	    $query = $db->getQuery(true)
		    ->delete('#__jfusion_users_plugin')
		    ->where('id = ' . $userinfo->id);
        $db->setQuery($query);
	    try {
		    $db->execute();
	    } catch (Exception $e) {
		    static::raiseWarning($e);
	    }
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
					contentid = ' . $contentid . ',
					component = ' . $fdb->Quote($option) . ',
					forumid = ' . $threadinfo->forumid . ',
					threadid = ' . $threadinfo->threadid . ',
					postid = ' . $threadinfo->postid . ',
					modified = ' . $fdb->Quote($modified) . ',
					jname = ' . $fdb->Quote($jname) . ',
					published = ' . $published . ',
					manual = ' . $manual;
		$fdb->setQuery($query);
		$fdb->execute();
	}

    /**
     * Creates the URL of a Joomla article
     *
     * @param stdClass &$contentitem contentitem
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
	        /** @noinspection PhpUndefinedClassInspection */
	        $article_url = urldecode(K2HelperRoute::getItemRoute($contentitem->id.':'.urlencode($contentitem->alias), $contentitem->catid.':'.urlencode($contentitem->category->alias)));
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

            include_once JPATH_SITE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_content'  . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'route.php';
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
        $joomla_url = static::getJoomlaURL();

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

        $link = '<a href="' . $article_url . '">' . $text . '</a>';

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
	    require_once(JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.parse.php');

	    $parser = new JFusionParse();
	    return $parser->parseCode($text, $to,$options);
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
            $joomla_url = static::getJoomlaURL();
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
		try {
			$db = JFactory::getDBO();
			if ($isPluginUid && !empty($jname)) {
				$userlookup = static::lookupUser($jname, $uid, false, $username);
				if (!empty($userlookup)) {
					$uid = $userlookup->id;
				} else {
					//no user was found
					$avatar = static::getJoomlaURL() . 'components/com_jfusion/images/noavatar.png';
					return $avatar;
				}
			}
			switch($software) {
				case 'gravatar':
					$query = $db->getQuery(true)
						->select('email')
						->from('#__users')
						->where('id = ' . $uid);

					$db->setQuery($query);
					$email = $db->loadResult();
					$avatar = 'http://www.gravatar.com/avatar.php?gravatar_id=' . md5(strtolower($email)) . '&size=40';
					break;
				default:
					$avatar = static::getJoomlaURL() . 'components/com_jfusion/images/noavatar.png';
					break;
			}
		} catch (Exception $e) {
			$avatar = static::getJoomlaURL() . 'components/com_jfusion/images/noavatar.png';
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
            $joomla_source_url = $params->get('source_url', '/');
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
            $joomla_url = static::getJoomlaURL();
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
     * @return int timezone in -6 format
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
	 * @param string $jname
	 * @param bool   $default
	 *
	 * @return mixed;
	 */
	public static function getUserGroups($jname = '', $default = false) {
		jimport('joomla.application.component.helper');
		$params = JComponentHelper::getParams('com_jfusion');
		$usergroups = $params->get('usergroups', new stdClass());

		if ($jname) {
			if (isset($usergroups->{$jname})) {
				$usergroups = $usergroups->{$jname};

				if ($default) {
					if (isset($usergroups[0])) {
						$usergroups = $usergroups[0];
					} else {
						$usergroups = null;
					}
				}
			} else {
				if ($default) {
					$usergroups = null;
				} else {
					$usergroups = array();
				}
			}
		}
		return $usergroups;
	}

	/**
	 * @return stdClass;
	 */
	public static function getUpdateUserGroups() {
		jimport('joomla.application.component.helper');
		$params = JComponentHelper::getParams('com_jfusion');
		$usergroupmodes = $params->get('updateusergroups', new stdClass());
		return $usergroupmodes;
	}

    /**
     * returns true / false if the plugin is in advanced usergroup mode or not...
     *
     * @param string $jname plugin name
     *
     * @return boolean
     */
    public static function updateUsergroups($jname) {
	    $updateusergroups = static::getUpdateUserGroups();
	    $advanced = false;
        if (isset($updateusergroups->{$jname}) && $updateusergroups->{$jname}) {
	        $master = static::getMaster();
	        if ($master->name != $jname) {
		        $advanced = true;
	        }
        }
        return $advanced;
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
	    if (!isset($data)) {
		    $data = array();
	    }

        if (!isset($data[$jname][$table])) {
            $db = JFusionFactory::getDatabase($jname);
            $query = 'SHOW FULL FIELDS FROM ' . $table;
            $db->setQuery($query);
            $fields = $db->loadObjectList();

            foreach ($fields as $f) {
                if ($f->Collation) {
                    $data[$jname][$table][$f->Field] = $f->Collation;
                }
            }
        }

        if (isset($data[$jname][$table][$field]) ) {
	        $encoding = false;
        	list($charset) = explode('_', $data[$jname][$table][$field]);
            switch ($charset) {
                case 'latin1':
                	$encoding = 'ISO-8859-1';
                    break;
                case 'utf8':
                    break;
                default:
	                throw new RuntimeException('JFusion Encoding support missing: ' . $charset);
                    break;
            }
            if ($encoding) {
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
	                throw new RuntimeException('JFusion Encoding failed ' . $charset);
                }
            }
        }
        return $string;
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
    public static function hasFeature($jname, $feature, $itemid = null) {
        $return = false;
	    $admin = JFusionFactory::getAdmin($jname);
	    $public = JFusionFactory::getPublic($jname);
	    $forum = JFusionFactory::getForum($jname);
	    $user = JFusionFactory::getUser($jname);
        switch ($feature) {
            //Admin Features
            case 'wizard':
	            $return = $admin->methodDefined('setupFromPath');
                break;
            //Public Features
            case 'search':
                $return = ($public->methodDefined('getSearchQuery') || $public->methodDefined('getSearchResults'));
                break;
            case 'whosonline':
                $return = $public->methodDefined('getOnlineUserQuery');
                break;
            case 'breadcrumb':
                $return = $public->methodDefined('getPathWay');
                break;
            case 'frontendlanguage':
                $return = $public->methodDefined('setLanguageFrontEnd');
                break;
            case 'frameless':
                $return = $public->methodDefined('getBuffer');
                break;
            //Forum Features
            case 'discussion':
                $return = $forum->methodDefined('createThread');
                break;
            case 'activity':
                $return = ($forum->methodDefined('getActivityQuery') || $forum->methodDefined('renderActivityModule'));
                break;
            case 'threadurl':
                $return = $forum->methodDefined('getThreadURL');
                break;
            case 'posturl':
                $return = $forum->methodDefined('getPostURL');
                break;
            case 'profileurl':
                $return = $forum->methodDefined('getProfileURL');
                break;
            case 'avatarurl':
                $return = $forum->methodDefined('getAvatar');
                break;
            case 'privatemessageurl':
                $return = $forum->methodDefined('getPrivateMessageURL');
                break;
            case 'viewnewmessagesurl':
                $return = $forum->methodDefined('getViewNewMessagesURL');
                break;
            case 'privatemessagecounts':
                $return = $forum->methodDefined('getPrivateMessageCounts');
                break;
            //User Features
            case 'useractivity':
                $return = $user->methodDefined('activateUser');
                break;
            case 'duallogin':
                $return = $user->methodDefined('createSession');
                break;
            case 'duallogout':
                $return = $user->methodDefined('destroySession');
                break;
            case 'updatepassword':
                $return = $user->methodDefined('updatePassword');
                break;
            case 'updateusername':
                $return = $user->methodDefined('updateUsername');
                break;
            case 'updateemail':
                $return = $user->methodDefined('updateEmail');
                break;
            case 'updateusergroup':
                $return = $user->methodDefined('updateUsergroup');
                break;
            case 'updateuserlanguage':
                $return = $user->methodDefined('updateUserLanguage');
                break;
            case 'syncsessions':
                $return = $user->methodDefined('syncSessions');
                break;
            case 'blockuser':
                $return = $user->methodDefined('blockUser');
                break;
            case 'activateuser':
                $return = $user->methodDefined('activateUser');
                break;
            case 'deleteuser':
                $return = $user->methodDefined('deleteUser');
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
		        if ($jname == 'joomla_int') {
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

		if ($isFile) {
			// Try to load the XML file
			$xml = simplexml_load_file($data);
		} else {
			// Try to load the XML string
			$xml = simplexml_load_string($data);
		}

		if ($xml === false) {
			static::raiseError(JText::_('JLIB_UTIL_ERROR_XML_LOAD'));

			if ($isFile) {
				static::raiseError($data);
			}
			foreach (libxml_get_errors() as $error) {
				static::raiseError($error->message);
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

		return new plgAuthenticationJoomla($dispatcher, (array) ($plugin));
	}

	/**
	 * @param string|RuntimeException $msg
	 * @param string           $jname
	 */
	public static function raiseMessage($msg, $jname = '') {
		$app = JFactory::getApplication();
		if ($msg instanceof RuntimeException) {
			$msg = $msg->getMessage();
		}
		if (!empty($jname)) {
			$msg = $jname . ': ' . $msg;
		}
		$app->enqueueMessage($msg, 'message');
	}

	/**
	 * @param string|RuntimeException $msg
	 * @param string           $jname
	 */
	public static function raiseNotice($msg, $jname = '') {
		$app = JFactory::getApplication();
		if ($msg instanceof RuntimeException) {
			$msg = $msg->getMessage();
		}
		if (!empty($jname)) {
			$msg = $jname . ': ' . $msg;
		}
		$app->enqueueMessage($msg, 'notice');
	}

	/**
	 * @param string|RuntimeException $msg
	 * @param string           $jname
	 */
	public static function raiseWarning($msg, $jname = '') {
		$app = JFactory::getApplication();
		if ($msg instanceof RuntimeException) {
			$msg = $msg->getMessage();
		}
		if (!empty($jname)) {
			$msg = $jname . ': ' . $msg;
		}
		$app->enqueueMessage($msg, 'warning');
	}

	/**
	 * @param string|RuntimeException $msg
	 * @param string           $jname
	 */
	public static function raiseError($msg, $jname = '') {
		$app = JFactory::getApplication();
		if ($msg instanceof RuntimeException) {
			$msg = $msg->getMessage();
		}
		if (!empty($jname)) {
			$msg = $jname . ': ' . $msg;
		}
		$app->enqueueMessage($msg, 'error');
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
	public static function raise($type, $message, $jname = '') {
		if (is_array($message)) {
			foreach ($message as $msgtype => $msg) {
				//if still an array implode for nicer display
				if (is_numeric($msgtype)) {
					$msgtype = $jname;
				}
				static::raise($type, $msg, $msgtype);
			}
		} else {
			switch(strtolower($type)) {
				case 'notice':
					static::raiseNotice($message, $jname);
					break;
				case 'error':
					static::raiseError($message, $jname);
					break;
				case 'warning':
					static::raiseWarning($message, $jname);
					break;
				case 'message':
					static::raiseMessage($message, $jname);
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
			JHtml::_('behavior.framework', true);
			JHTML::_('behavior.modal');
			JHTML::_('behavior.tooltip');

			$document = JFactory::getDocument();
			if ( JFactory::getApplication()->isAdmin() ) {

				$keys = array('SESSION_TIMEOUT', 'NOTICE', 'WARNING', 'MESSAGE', 'ERROR', 'DELETED', 'DELETE_PAIR', 'REMOVE', 'OK');

				$url = JURI::root() . 'administrator/index.php';


				$document->addScript('components/com_jfusion/js/jfusion.js');

			} else {
				$keys = array('SESSION_TIMEOUT', 'NOTICE', 'WARNING', 'MESSAGE', 'ERROR');

				$url = JURI::root() . 'index.php';
			}

			static::loadJavascriptLanguage($keys);

			$js=<<<JS
			JFusion.url = '{$url}';
JS;
			$document->addScriptDeclaration($js);
		}
	}

	/**
	 * @param string|array $keys
	 */
	public static function loadJavascriptLanguage($keys) {
		if (!empty($keys)) {
			$document = JFactory::getDocument();

			if (is_array($keys)) {
				foreach($keys as $key) {
					JText::script($key);
				}
			} else {
				JText::script($keys);
			}
		}
	}

	/**
	 * @param string $filename file name or url
	 *
	 * @return boolean|stdClass
	 */
	public static function getImageSize($filename) {
		$result = false;
		ob_start();

		if (strpos($filename, '://') !== false && function_exists('fopen') && ini_get('allow_url_fopen')) {
			$stream = fopen($filename, 'r');

			$rawdata = stream_get_contents($stream, 24);
			if($rawdata) {
				$type = null;
				/**
				 * check for gif
				 */
				if (strlen($rawdata) >= 10 && strpos($rawdata, 'GIF89a') === 0 || strpos($rawdata, 'GIF87a') === 0) {
					$type = 'gif';
				}
				/**
				 * check for png
				 */
				if (!$type && strlen($rawdata) >= 24) {
					$head = unpack('C8', $rawdata);
					$png = array(1 => 137, 2 => 80, 3 => 78, 4 => 71, 5 => 13, 6 => 10, 7 => 26, 8 => 10);
					if ($head === $png) {
						$type = 'png';
					}
				}
				/**
				 * check for jpg
				 */
				if (!$type) {
					$soi = unpack('nmagic/nmarker', $rawdata);
					if ($soi['magic'] == 0xFFD8) {
						$type = 'jpg';
					}
				}
				if (!$type) {
					if ( substr($rawdata, 0, 2) == 'BM' ) {
						$type = 'bmp';
					}
				}
				switch($type) {
					case 'gif':
						$data = unpack('c10', $rawdata);

						$result = new stdClass;
						$result->width = $data[8]*256 + $data[7];
						$result->height = $data[10]*256 + $data[9];
						break;
					case 'png':
						$type = substr($rawdata, 12, 4);
						if ($type === 'IHDR') {
							$info = unpack('Nwidth/Nheight', substr($rawdata, 16, 8));

							$result = new stdClass;
							$result->width = $info['width'];
							$result->height = $info['height'];
						}
						break;
					case 'bmp':
						$header = unpack('H*',$rawdata);
						// Process the header
						// Structure: http://www.fastgraph.com/help/bmp_header_format.html
						// Cut it in parts of 2 bytes
						$header = str_split($header[1], 2);
						$result = new stdClass;
						$result->width = hexdec($header[19] . $header[18]);
						$result->height = hexdec($header[23] . $header[22]);
						break;
					case 'jpg':
						$pos = 0;
						while(1) {
							$pos += 2;
							$data = substr($rawdata, $pos, 9);
							if (strlen($data) < 4) {
								break;
							}
							$info = unpack('nmarker/nlength', $data);
							if ($info['marker'] == 0xFFC0) {
								if (strlen($data) >= 9) {
									$info = unpack('nmarker/nlength/Cprecision/nheight/nwidth', $data);

									$result = new stdClass;
									$result->width = $info['width'];
									$result->height = $info['height'];
								}
								break;
							} else {
								$pos += $info['length'];
								if (strlen($rawdata) < $pos+9) {
									$rawdata .= stream_get_contents($stream, $info['length']+9);
								}
							}
						}
						break;
					default:
						/**
						 * Fallback to original getimagesize this may be slower than the original but safer.
						 */
						$rawdata .= stream_get_contents($stream);
						$temp = tmpfile();
						fwrite($temp, $rawdata);
						$meta_data = stream_get_meta_data($temp);

						$info = getimagesize($meta_data['uri']);

						if ($info) {
							$result = new stdClass;
							$result->width = $info[0];
							$result->height = $info[1];
						}
						fclose($temp);
						break;
				}
			}
			fclose($stream);
		}
		if (!$result) {
			$info = getimagesize($filename);

			if ($info) {
				$result = new stdClass;
				$result->width = $info[0];
				$result->height = $info[1];
			}
		}
		ob_end_clean();
		return $result;
	}
}