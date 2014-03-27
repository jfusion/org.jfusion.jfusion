<?php namespace JFusion;

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

use Joomla\Language\Text;


use JFusion\Parser\Parser;
use JFusionFunction;
use JMenu;
use JRoute;
use JUri;
use \stdClass;
use \SimpleXMLElement;
use \Exception;
use \RuntimeException;


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
class Framework
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
			$db = Factory::getDBO();

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
			$db = Factory::getDBO();

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
                $params = Factory::getParams($itemid);
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
            $params = Factory::getParams($jname);
            $source_url = $params->get('source_url');
            $url = str_replace($source_url, '', $url);

            $config = Factory::getConfig();
            $sefenabled = $config->get('sef');
            $params = Factory::getParams($jname);
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
                $u = JUri::getInstance($url);
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
     * Updates the JFusion user lookup table during login
     *
     * @param object  $userinfo         object containing the userdata
     * @param string  $jname            name of the JFusion plugin used
     * @param object  $exsistinginfo    object containing the userdata
     * @param string  $exsistingname    name of the JFusion plugin used
     */
	public static function updateLookup($userinfo, $jname, $exsistinginfo, $exsistingname)
    {
	    if ($userinfo) {
		    $db = Factory::getDBO();
		    //we don't need to update the lookup for internal joomla unless deleting a user

		    try {
			    $query = $db->getQuery(true)
				    ->select('*')
				    ->from('#__jfusion_users_plugin')
				    ->where('( userid = ' . $db->quote($exsistinginfo->userid) . ' AND ' . 'jname = ' . $db->quote($exsistingname) . ' )', 'OR')
				    ->where('( userid = ' . $db->quote($userinfo->userid) . ' AND ' . 'jname = ' . $db->quote($jname) . ' )', 'OR')
				    ->where('( email = ' . $db->quote($userinfo->email) . ' AND ' . 'jname = ' . $db->quote($jname) . ' )', 'OR');
			    $db->setQuery($query);

			    $db->loadObjectList('jname');
			    $list = $db->loadResult();

			    if (empty($list)) {
				    $first = new stdClass();
				    $first->id = -1;
				    $first->username = $exsistinginfo->username;
				    $first->userid = $exsistinginfo->userid;
				    $first->email = $exsistinginfo->email;
				    $first->jname = $exsistingname;
				    $db->insertObject('#__jfusion_users_plugin', $first, 'autoid');

				    $first->id = $first->autoid;
				    $db->updateObject('#__jfusion_users_plugin', $first, 'autoid');

				    $second = new stdClass();
				    $second->id = $first->id;
				    $second->username = $userinfo->username;
				    $second->userid = $userinfo->userid;
				    $second->email = $userinfo->email;
				    $second->jname = $jname;
				    $db->insertObject('#__jfusion_users_plugin', $second);
			    } else if (!isset($list[$exsistingname])) {
				    $first = new stdClass();
				    $first->id = $list[$jname]->id;
				    $first->username = $exsistinginfo->username;
				    $first->userid = $exsistinginfo->userid;
				    $first->email = $exsistinginfo->email;
				    $first->jname = $exsistingname;
				    $db->insertObject('#__jfusion_users_plugin', $first, 'autoid');
			    } else if (!isset($list[$jname])) {
				    $first = new stdClass();
				    $first->id = $list[$exsistingname]->id;
				    $first->username = $userinfo->username;
				    $first->userid = $userinfo->userid;
				    $first->userid = $userinfo->userid;
				    $first->jname = $jname;
				    $db->insertObject('#__jfusion_users_plugin', $first, 'autoid');
			    } else {
				    $first = $list[$jname];
				    $first->username = $userinfo->username;
				    $first->userid = $userinfo->userid;
				    $first->email = $userinfo->email;
				    $db->updateObject('#__jfusion_users_plugin', $first, 'autoid');
			    }
		    } catch (Exception $e) {
			    static::raiseError($e);
		    }
	    }
    }

	/**
	 * Updates the JFusion user lookup table during login
	 *
	 * @param string  $jname            name of the JFusion plugin used
	 * @param stdClass  $userinfo    object containing the userdata
	 */
	public static function deleteLookup($jname, $userinfo)
	{
		if ($userinfo) {
			$db = Factory::getDBO();
			//we don't need to update the lookup for internal joomla unless deleting a user

			try {
				$query = $db->getQuery(true)
					->delete('#__jfusion_users_plugin')
					->where('userid = ' . $db->quote($userinfo->userid))
					->where('jname = ' . $db->quote($jname));

				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e) {
				static::raiseError($e);
			}
		}
	}

    /**
     * Returns the userinfo data for JFusion plugin based on the userid
     *
     * @param string  $jname      name of the JFusion plugin used
     * @param stdClass  $exsistinginfo     user info
     * @param string $exsistingname if true, returns the userinfo data based on Joomla, otherwise the plugin
     *
     * @return stdClass|null returns user login info from the requester software or null
     *
     */
	public static function lookupUser($jname, $exsistinginfo, $exsistingname)
    {
	    $result = null;
	    if ($exsistinginfo) {
	        //initialise some vars
	        $db = Factory::getDBO();

		    $query = $db->getQuery(true)
			    ->select('b.*')
			    ->from('#__jfusion_users_plugin AS a')
			    ->innerJoin('#__jfusion_users_plugin AS b ON a.id = b.id')
			    ->where('b.jname = ' . $db->quote($jname))
		        ->where('a.jname = ' . $db->quote($exsistingname));

	        $search = array();
	        if (isset($exsistinginfo->userid)) {
		        $search[] = 'userid = ' . $db->quote($exsistinginfo->userid);
	        }
	        if (isset($exsistinginfo->username)) {
		        $search[] = 'username = ' . $db->quote($exsistinginfo->username);
	        }
		    if (isset($exsistinginfo->email)) {
			    $search[] = 'email = ' . $db->quote($exsistinginfo->email);
		    }
	        if (!empty($search)) {
		        $query->where('( ' . implode(' OR ', $search) . ' )');
		        $db->setQuery($query);
		        $result = $db->loadObject();
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
	    /**
	     * TODO: need to be change to remove the user corectly with the new layout.
	     */
	    //Delete old user data in the lookup table
	    $db = Factory::getDBO();

	    $query = $db->getQuery(true)
		    ->delete('#__jfusion_users')
		    ->where('id = ' . $userinfo->id, 'OR')
	        ->where('username =' . $db->quote($userinfo->username))
		    ->where('LOWER(username) = ' . strtolower($db->quote($userinfo->email)));

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
	    $parser = new Parser();
	    return $parser->parseCode($text, $to, $options);
    }

	/**
	 * Retrieves the source of the avatar for a Joomla supported component
	 *
	 * @param string  $software    software name
	 * @param stdClass $userinfo
	 *
	 * @return string nothing
	 */
	public static function getAltAvatar($software, $userinfo)
	{
		$application = Factory::getApplication();
		try {
			if (!$userinfo) {
				//no user was found
				return $application->getDefaultAvatar();
			} else {
				switch($software) {
					case 'gravatar':
						$avatar = 'http://www.gravatar.com/avatar.php?gravatar_id=' . md5(strtolower($userinfo->email)) . '&size=40';
						break;
					default:
						$avatar = $application->getDefaultAvatar();
						break;
				}
			}
		} catch (Exception $e) {
			$avatar = $application->getDefaultAvatar();
		}
		return $avatar;
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
            $juri = new JUri($joomla_url);
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
            $db = Factory::getDatabase($jname);
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
	    $admin = Factory::getAdmin($jname);
	    $public = Factory::getFront($jname);
	    $forum = Factory::getForum($jname);
	    $user = Factory::getUser($jname);
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
                    $app = Factory::getApplication();
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
			static::raiseError(Text::_('JLIB_UTIL_ERROR_XML_LOAD'));

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
	 * @param string|RuntimeException $msg
	 * @param string           $jname
	 */
	public static function raiseMessage($msg, $jname = '') {
		$app = Factory::getApplication();
		if ($msg instanceof Exception) {
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
		$app = Factory::getApplication();
		if ($msg instanceof Exception) {
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
		$app = Factory::getApplication();
		if ($msg instanceof Exception) {
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
		$app = Factory::getApplication();
		if ($msg instanceof Exception) {
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
						$header = unpack('H*', $rawdata);
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

	/**
	 * @param $seed
	 *
	 * @return string
	 */
	public static function getHash($seed)
	{
		return md5(Factory::getConfig()->get('secret') . $seed);
	}



	/**
	 * @param string $jname
	 * @param bool   $default
	 *
	 * @return mixed;
	 */
	public static function getUserGroups($jname = '', $default = false) {
		$params = Factory::getConfig();
		$usergroups = $params->get('usergroups', false);

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
		$params = Factory::getConfig();
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
			$master = Framework::getMaster();
			if ($master->name != $jname) {
				$advanced = true;
			}
		}
		return $advanced;
	}

	/**
	 * authenticate a user/password
	 *
	 * @param string $username username
	 * @param string $password password
	 *
	 * @return boolean
	 */
	public static function authenticate($username, $password) {
		$updateusergroups = static::getUpdateUserGroups();
		$advanced = false;
		if (isset($updateusergroups->{$jname}) && $updateusergroups->{$jname}) {
			$master = Framework::getMaster();
			if ($master->name != $jname) {
				$advanced = true;
			}
		}
		return $advanced;
	}

	/**
	 * Generate a random password
	 *
	 * @param   integer  $length  Length of the password to generate
	 *
	 * @return  string  Random Password
	 *
	 * @since   11.1
	 */
	public static function genRandomPassword($length = 8)
	{
		$salt = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$base = strlen($salt);
		$makepass = '';

		/*
		 * Start with a cryptographic strength random string, then convert it to
		 * a string with the numeric base of the salt.
		 * Shift the base conversion on each character so the character
		 * distribution is even, and randomize the start shift so it's not
		 * predictable.
		 */
		$random = static::genRandomBytes($length + 1);
		$shift = ord($random[0]);

		for ($i = 1; $i <= $length; ++$i)
		{
			$makepass .= $salt[($shift + ord($random[$i])) % $base];
			$shift += ord($random[$i]);
		}

		return $makepass;
	}

	/**
	 * Generate random bytes.
	 *
	 * @param   integer  $length  Length of the random data to generate
	 *
	 * @return  string  Random binary data
	 *
	 * @since  12.1
	 */
	public static function genRandomBytes($length = 16)
	{
		$length = (int) $length;
		$sslStr = '';

		/*
		 * If a secure randomness generator exists and we don't
		 * have a buggy PHP version use it.
		 */
		if (function_exists('openssl_random_pseudo_bytes')
			&& (version_compare(PHP_VERSION, '5.3.4') >= 0 || IS_WIN))
		{
			$sslStr = openssl_random_pseudo_bytes($length, $strong);

			if ($strong)
			{
				return $sslStr;
			}
		}

		/*
		 * Collect any entropy available in the system along with a number
		 * of time measurements of operating system randomness.
		 */
		$bitsPerRound = 2;
		$maxTimeMicro = 400;
		$shaHashLength = 20;
		$randomStr = '';
		$total = $length;

		// Check if we can use /dev/urandom.
		$urandom = false;
		$handle = null;

		// This is PHP 5.3.3 and up
		if (function_exists('stream_set_read_buffer') && @is_readable('/dev/urandom'))
		{
			$handle = @fopen('/dev/urandom', 'rb');

			if ($handle)
			{
				$urandom = true;
			}
		}

		while ($length > strlen($randomStr))
		{
			$bytes = ($total > $shaHashLength)? $shaHashLength : $total;
			$total -= $bytes;

			/*
			 * Collect any entropy available from the PHP system and filesystem.
			 * If we have ssl data that isn't strong, we use it once.
			 */
			$entropy = rand() . uniqid(mt_rand(), true) . $sslStr;
			$entropy .= implode('', @fstat(fopen(__FILE__, 'r')));
			$entropy .= memory_get_usage();
			$sslStr = '';

			if ($urandom)
			{
				stream_set_read_buffer($handle, 0);
				$entropy .= @fread($handle, $bytes);
			}
			else
			{
				/*
				 * There is no external source of entropy so we repeat calls
				 * to mt_rand until we are assured there's real randomness in
				 * the result.
				 *
				 * Measure the time that the operations will take on average.
				 */
				$samples = 3;
				$duration = 0;

				for ($pass = 0; $pass < $samples; ++$pass)
				{
					$microStart = microtime(true) * 1000000;
					$hash = sha1(mt_rand(), true);

					for ($count = 0; $count < 50; ++$count)
					{
						$hash = sha1($hash, true);
					}

					$microEnd = microtime(true) * 1000000;
					$entropy .= $microStart . $microEnd;

					if ($microStart >= $microEnd)
					{
						$microEnd += 1000000;
					}

					$duration += $microEnd - $microStart;
				}

				$duration = $duration / $samples;

				/*
				 * Based on the average time, determine the total rounds so that
				 * the total running time is bounded to a reasonable number.
				 */
				$rounds = (int) (($maxTimeMicro / $duration) * 50);

				/*
				 * Take additional measurements. On average we can expect
				 * at least $bitsPerRound bits of entropy from each measurement.
				 */
				$iter = $bytes * (int) ceil(8 / $bitsPerRound);

				for ($pass = 0; $pass < $iter; ++$pass)
				{
					$microStart = microtime(true);
					$hash = sha1(mt_rand(), true);

					for ($count = 0; $count < $rounds; ++$count)
					{
						$hash = sha1($hash, true);
					}

					$entropy .= $microStart . microtime(true);
				}
			}

			$randomStr .= sha1($entropy, true);
		}

		if ($urandom)
		{
			@fclose($handle);
		}

		return substr($randomStr, 0, $length);
	}
}