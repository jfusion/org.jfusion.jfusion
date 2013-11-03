<?php

/**
 * file containing administrator function for the jfusion plugin
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

class JFusionAdmin_gallery2 extends JFusionAdmin 
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
    function getTablename() {
        return 'User';
    }

    /**
     * @param string $softwarePath
     *
     * @return array
     */
    function setupFromPath($softwarePath) {
	    $myfile = $softwarePath . 'config.php';

        $params = array();
        $config = array();
        //try to open the file
	    $lines = $this->readFile($myfile);
        if ($lines === false) {
            JFusionFunction::raiseWarning(JText::_('WIZARD_FAILURE') . ': '.$myfile. ' ' . JText::_('WIZARD_MANUAL'), $this->getJname());
	        return false;
            //get the default parameters object
        } else {
            //parse the file line by line to get only the config variables
	        foreach ($lines as $line) {
		        if (strpos($line, '$storeConfig') === 0) {
			        preg_match("/.storeConfig\['(.*)'\] = (.*);/", $line, $matches);
			        $name = trim($matches[1], " '");
			        $value = trim($matches[2], " '");
			        $config[$name] = $value;
		        }
		        if (strpos($line, '$gallery->setConfig') === 0) {
			        preg_match("/.gallery->setConfig\('(.*)',(.*)\)/", $line, $matches);
			        $name = trim($matches[1], " '");
			        $value = trim($matches[2], " '");
			        $config[$name] = $value;
		        }
	        }
            $params['database_host'] = $config['hostname'];
            $params['database_type'] = $config['type'];
            $params['database_name'] = $config['database'];
            $params['database_user'] = $config['username'];
            $params['database_password'] = $config['password'];
            $params['database_prefix'] = $config['tablePrefix'];
            $params['source_url'] = str_replace('main.php', '', $config['baseUri']);
            $params['cookie_name'] = '';
            $params['source_path'] = $softwarePath;
            if (!in_array($params['database_type'], array('mysql', 'mysqli'))) {
                if (!function_exists('mysqli_init') && !extension_loaded('mysqli')) {
                    $params['database_type'] = 'mysql';
                } else {
                    $params['database_type'] = 'mysqli';
                }
            }
        }
        //Save the parameters into the standard JFusion params format
        return $params;
    }

    /**
     * Get a list of users
     *
     * @param int $limitstart
     * @param int $limit
     *
     * @return array
     */
    function getUserList($limitstart = 0, $limit = 0) {
	    try {
	        // initialise some objects
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('g_userName as username, g_email as email, g_id as userid')
			    ->from('#__User')
			    ->where('g_id != 5');

	        $db->setQuery($query, $limitstart, $limit);
	        $userlist = $db->loadObjectList();
	    } catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
		    $userlist = array();
		}
        return $userlist;
    }

    /**
     * @return int
     */
    function getUserCount() {
	    try {
	        //getting the connection to the db
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('count(*)')
			    ->from('#__User')
			    ->where('g_id != 5');

	        $db->setQuery($query);
	        //getting the results
	        $no_users = $db->loadResult();
	    } catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
		    $no_users = 0;
		}
        return $no_users;
    }

    /**
     * @return array
     */
    function getUsergroupList() {
	    try {
	        //getting the connection to the db
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('g_id as id, g_groupName as name')
			    ->from('#__Group')
			    ->where('g_id != 4');

	        $db->setQuery($query);
	        //getting the results
	        return $db->loadObjectList();
	    } catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
		    return array();
	    }
    }
    /**
     * @return string|array
     */
    function getDefaultUsergroup() {
	    try {
		    $usergroups = JFusionFunction::getUserGroups($this->getJname(), true);

		    if ($usergroups !== null) {
			    $db = JFusionFactory::getDatabase($this->getJname());

			    $group = array();
			    foreach($usergroups as $usergroup) {
				    $query = $db->getQuery(true)
					    ->select('g_groupName')
					    ->from('#__Group')
					    ->where('g_id = ' . $db->quote((int)$usergroup));

				    $db->setQuery($query);
				    $group[] = $db->loadResult();
			    }
		    } else {
			    $group = '';
		    }
	    } catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
		    $group = '';
		}
	    return $group;
    }
    /**
     * @return bool
     */
    function allowRegistration() {
	    $result = false;
	    try {
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('g_active')
			    ->from('#__PluginMap')
			    ->where('g_pluginType = ' . $db->quote('module'))
			    ->where('g_pluginId = ' . $db->quote('register'));

	        $db->setQuery($query);
	        $new_registration = $db->loadResult();
		    if ($new_registration != 0) {
			    $result = true;
		    }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		}
	    return $result;
    }

    /**
     * @param JRegistry $jFusionParam
     * @param JRegistry $jPluginParam
     * @param $itemId
     *
     * @return array|null
     */
    function getSitemapTree($jFusionParam, $jPluginParam, $itemId) {
        $this->helper->loadGallery2Api(true);
        global $gallery;
        $source_url = $this->params->get('source_url');
        $urlGenerator = new GalleryUrlGenerator();
        $urlGenerator->init($this->helper->getEmbedUri($itemId), $source_url, null);
        $album = $jPluginParam->get('album');
        if ($album == - 1) {
            $album = 7;
        }
        // Fetch all items contained in the root album
        list($ret, $rootItems) = GalleryCoreApi::fetchChildItemIdsWithPermission($album, 'core.view');
        if ($ret) {
            return null;
        }
        $parent = $node = new stdClass();
        $parent->uid = $this->getJname();
        $tree = $this->_getTree($rootItems, $urlGenerator, $parent);
        return $tree;
    }
    /**
     * @param $items
     * @param GalleryUrlGenerator $urlGenerator
     * @param $parent
     * @return array|null
     */
    function _getTree(&$items, $urlGenerator, $parent) {
        $albums = array();
        if (!$items) return null;
        foreach ($items as $itemId) {
            // Fetch the details for this item
	        /**
	         * @ignore
	         * @var $helper JFusionHelper_gallery2
	         * @var $user GalleryUser
	         * @var $entity GalleryItem
	         */
            list($ret, $entity) = GalleryCoreApi::loadEntitiesById($itemId);
            if ($ret) {
                // error, skip and continue, catch this error in next component version
                continue;
            } // Fetch the details for this item
            $node = new stdClass();
            $node->id = $entity->getId();
            $node->uid = $parent->uid . 'a' . $entity->getId();
            $node->name = $entity->getTitle();
            $node->pid = $entity->getParentId();
            $node->modified = $entity->getModificationTimestamp();
            $node->type = 'separator'; //fool joomla in not trying to add $Itemid=
            $node->link = $urlGenerator->generateUrl(array('view' => 'core.ShowItem', 'itemId' => $node->id), array('forceSessionId' => false, 'forceFullUrl' => true));
            // Make sure it's an album
            if ($entity->getCanContainChildren()) {
                $node->element = 'group';
                // Get all child items contained in this album and add them to the tree
                list($ret, $childIds) = GalleryCoreApi::fetchChildItemIdsWithPermission($node->id, 'core.view');
                if ($ret) {
                    // error, skip and continue, catch this error in next component version
                    continue;
                }
                $node->tree = $this->_getTree($childIds, $urlGenerator, $node);
            } else {
                $node->element = 'element';
                $node->uid = $parent->uid . 'p' . $entity->getId();
            }
            $albums[] = $node;
        }
        return $albums;
    }

    /**
     * @param $name
     * @param $value
     * @param $node
     * @param $control_name
     * @return array|string
     */
    function show_templateList($name, $value, $node, $control_name) {
	    $this->helper->loadGallery2Api(false);
        list($ret, $themes) = GalleryCoreApi::fetchPluginStatus('theme', true);
        if ($ret) {
            return array($ret, null);
        }
	    $cname = $control_name . '[params][' . $name . ']';

        $output = '<select name="' . $cname.'" id="'.$name.'">';

        $output.= '<option value="" ></option>';
        foreach ($themes as $id => $status) {
            if (!empty($status['active'])) {
                $selected = '';
                if ($id == $value) {
                    $selected = 'selected';
                }
                $output.= '<option value="'.$id.'" '.$selected.'>'.$id.'</option>';
            }
        }
        $output.= '</select>';
        return $output;
    }

    /**
     * do plugin support multi usergroups
     *
     * @return string UNKNOWN or JNO or JYES or ??
     */
    function requireFileAccess()
	{
		return 'JYES';
	}

	/**
	 * @return bool do the plugin support multi instance
	 */
	function multiInstance()
	{
		return false;
	}

	/**
	 * do plugin support multi usergroups
	 *
	 * @return bool
	 */
	function isMultiGroup()
	{
		return true;
	}
}
