<?php
/**
 * @package JFusion
 * @subpackage Modules
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Class for the JFusion front-end login module
 * @package JFusion
 */
class JFusionHelper {
    /**
     * @param JParameter $params
     * @param string $type
     *
     * @return string
     */
    function getReturnURL($params, $type) {
        $itemid = $params->get($type);
		if( preg_match('(login_custom_redirect|logout_custom_redirect)', $type) && strlen($params->get($type)) > 0 ) {
			$url = $params->get($type);
		} elseif($itemid) {
			$url = 'index.php?Itemid='.$itemid;
			$url = JRoute::_($url);
		} else {
			// Redirect to login
			$uri = JFactory::getURI();
			$url = $uri->toString();
		}
		
		return base64_encode($url);
	}

    /**
     * @return string
     */
    function getType() {
		$user = JFactory::getUser();
	    return (!$user->get('guest')) ? 'logout' : 'login';
	}

    /**
     * @static
     * @param null $id
     * @return bool
     */
    public static function getModuleById($id = null) {
		return self::getModuleQuery('id', $id);
	}

    /**
     * @static
     * @param null $title
     * @return bool
     */
    public static function getModuleByTitle($title = null) {
		return self::getModuleQuery('title', $title);
	}

    /**
     * @param string $type
     * @param null $identifier
     * @return bool
     */
    public function getModuleQuery($type = 'id', $identifier = null) {
		
		if ($identifier == null) {
			return false;
		}
		
		static $modules = array ();
		if ($modules [$identifier]) {
			return $modules [$identifier];
		}
		switch ($type) {
            default;
			case 'id' :
				$where = 'id=' . ( int ) $identifier;
				break;
			case 'title' :
				$where = 'title="' . $identifier . '"';
				break;
		}
		
		$db = JFactory::getDBO ();
		$query = 'SELECT id, title, module, params, content FROM #__modules WHERE ' . $where;
		$db->setQuery ( $query );
		
		if (null === ($modules = $db->loadObjectList ( $type ))) {
			JError::raiseWarning ( 'SOME_ERROR_CODE', JText::_ ( 'Error Loading Modules' ) . $db->getErrorMsg () );
			return false;
		}
		
		//determine if this is a custom module
		$file = $modules [$identifier]->module;
		$custom = substr ( $file, 0, 4 ) == 'mod_' ? 0 : 1;
		$modules [$identifier]->user = $custom;
		// CHECK: custom module name is given by the title field, otherwise it's just 'om' ??
		$modules [$identifier]->name = $custom ? $modules [$identifier]->title : substr ( $file, 4 );
		
		return $modules [$identifier];
	}
}
