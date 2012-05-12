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
 * load the JFusion framework
 */
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusion.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.abstractadmin.php';

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
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'gallery2';
    }
    function getTablename() {
        return 'User';
    }
    function setupFromPath($forumPath) {
        //check for trailing slash and generate file path
        if (substr($forumPath, -1) == DS) {
            $myfile = $forumPath . 'config.php';
        } else {
            $myfile = $forumPath . DS . 'config.php';
        }
        //try to open the file
        if (($file_handle = @fopen($myfile, 'r')) === false) {
            JError::raiseWarning(500, JText::_('WIZARD_FAILURE') . ": $myfile " . JText::_('WIZARD_MANUAL'));
            //get the default parameters object
            $params = JFusionFactory::getParams($this->getJname());
            return $params;
        } else {
            //parse the file line by line to get only the config variables
            $file_handle = fopen($myfile, 'r');
            while (!feof($file_handle)) {
                $line = fgets($file_handle);
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
        }
        fclose($file_handle);
        //Save the parameters into the standard JFusion params format
        $params = array();
        $params['database_host'] = $config['hostname'];
        $params['database_type'] = $config['type'];
        $params['database_name'] = $config['database'];
        $params['database_user'] = $config['username'];
        $params['database_password'] = $config['password'];
        $params['database_prefix'] = $config['tablePrefix'];
        $params['source_url'] = str_replace("main.php", "", $config['baseUri']);
        $params['cookie_name'] = '';
        $params['source_path'] = $forumPath;
        if (!in_array($params['database_type'], array("mysql", "mysqli"))) {
            if (!function_exists('mysqli_init') && !extension_loaded('mysqli')) {
                $params['database_type'] = "mysql";
            } else {
                $params['database_type'] = "mysqli";
            }
        }
        return $params;
    }
    function getUserList() {
        // initialise some objects
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT g_userName as username, g_email as email, g_id as userid from #__User where g_id != 5';
        $db->setQuery($query);
        $userlist = $db->loadObjectList();
        return $userlist;
    }
    function getUserCount() {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT count(*) from #__User where g_id != 5';
        $db->setQuery($query);
        //getting the results
        $no_users = $db->loadResult();
        return $no_users;
    }
    function getUsergroupList() {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT g_id as id, g_groupName as name FROM #__Group
                WHERE g_id != 4';
        $db->setQuery($query);
        //getting the results
        return $db->loadObjectList();
    }
    function getDefaultUsergroup() {
        $params = JFusionFactory::getParams($this->getJname());
        $usergroup_id = $params->get('usergroup');
        //we want to output the usergroup name
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT g_groupName FROM #__Group WHERE g_id = ' . (int)$usergroup_id;
        $db->setQuery($query);
        return $db->loadResult();
    }
    function allowRegistration() {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = "SELECT g_active FROM #__PluginMap
                  WHERE g_pluginType = 'module' and g_pluginId = 'register';";
        $db->setQuery($query);
        if ($new_registration = $db->loadResult()) {
            if ($new_registration == 0) {
                return false;
            } else {
                return true;
            }
        }
        return false;
    }
    function getSitemapTree($jFusionParam, $jPluginParam, $itemId) {
        require JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'gallery2.php';
        jFusion_g2BridgeCore::loadGallery2Api($this->getJname(),true);
        global $gallery;
        $params = JFusionFactory::getParams($this->getJname());
        $source_url = $params->get('source_url');
        $urlGenerator = new GalleryUrlGenerator();
        $urlGenerator->init(jFusion_g2BridgeCore::getEmbedUri($this->getJname(),$itemId), $source_url, null);
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
    function _getTree(&$items, $urlGenerator, $parent) {
        $albums = array();
        if (!$items) return null;
        foreach ($items as $itemId) {
            // Fetch the details for this item
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
            $node->type = 'separator'; //fool joomap in not trying to add $Itemid=
            $node->link = $urlGenerator->generateUrl(array('view' => 'core.ShowItem', 'itemId' => $node->id), array('forceSessionId' => false, 'forceFullUrl' => true));
            // Make sure it's an album
            if ($entity->getCanContainChildren()) {
                $node->element = "group";
                // Get all child items contained in this album and add them to the tree
                list($ret, $childIds) = GalleryCoreApi::fetchChildItemIdsWithPermission($node->id, 'core.view');
                if ($ret) {
                    // error, skip and continue, catch this error in next component version
                    continue;
                }
                $node->tree = $this->_getTree($childIds, $urlGenerator, $node);
            } else {
                $node->element = "element";
                $node->uid = $parent->uid . 'p' . $entity->getId();
            }
            $albums[] = $node;
        }
        return $albums;
    }
    function show_templateList($name, $value, $node, $control_name) {
        require JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'gallery2.php';
        jFusion_g2BridgeCore::loadGallery2Api($this->getJname(),false);
        list($ret, $themes) = GalleryCoreApi::fetchPluginStatus('theme', true);
        if ($ret) {
            return array($ret, null);
        }
        if (JFusionFunction::isJoomlaVersion('1.6')) {
        	$output = "<select name=\"" . $control_name . "[params][" . $name . "]\" id=\"" . $name . "\" >";
        } else {
        	$output = "<select name=\"" . $control_name . "[" . $name . "]\" id=\"" . $name . "\" >";
        }        
        $output.= "<option value=\"\" ></option>";
        foreach ($themes as $id => $status) {
            if (!empty($status['active'])) {
                $selected = "";
                if ($id == $value) {
                    $selected = "selected";
                }
                $output.= "<option value=\"" . $id . "\" " . $selected . ">" . $id . "</option>";
            }
        }
        $output.= "</select>";
        return $output;
    }
    
	/*
	 * do plugin support multi usergroups
	 * return UNKNOWN for unknown
	 * return JNO for NO
	 * return JYES for YES
	 * return ... ??
	 */
	function requireFileAccess()
	{
		return 'JYES';
	}    
}
