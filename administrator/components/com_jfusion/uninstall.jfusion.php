<?php

/**
 * Uninstaller file
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Install
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Get the extension id
 * Grabbed this from the JPackageMan installer class with modification
 *
 * @param string $type        type
 * @param int    $id          id
 * @param string $group       group
 * @param string $description description
 *
 * @return unknown_type
 */

require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.factory.php');

/**
 * @param $type
 * @param $id
 * @param $group
 * @param $description
 */
function _uninstallPlugin($type, $id, $group, $description)
{
    $db = JFactory::getDBO();
    $result = $id;
	$jversion = new JVersion;
    $version = $jversion->getShortVersion();
    if(version_compare($version, '1.6') >= 0) {
        switch ($type) {
        case 'plugin':
            $db->setQuery("SELECT extension_id FROM #__extensions WHERE folder = '$group' AND element = '$id'");
            $result = $db->loadResult();
            break;
        case 'module':
            $db->setQuery("SELECT extension_id FROM #__extensions WHERE element = '$id'");
            $result = $db->loadResult();
            break;
        }
    } else {
        switch ($type) {
        case 'plugin':
            $db->setQuery("SELECT id FROM #__plugins WHERE folder = '$group' AND element = '$id'");
            $result = $db->loadResult();
            break;
        case 'module':
            $db->setQuery("SELECT id FROM #__modules WHERE module = '$id'");
            $result = $db->loadResult();
            break;
        }
    }
    if ($result) {
        $tmpinstaller = new JInstaller();
        $uninstall_result = $tmpinstaller->uninstall($type, $result, 0);
        if (!$uninstall_result) {
            $color = '#f9ded9';
            $description = JText::_('UNINSTALL') . ' ' . $description . ' ' . JText::_('FAILED');
        } else {
            $color = '#d9f9e2';
            $description = JText::_('UNINSTALL') . ' ' . $description . ' ' . JText::_('SUCCESS');
        }
        $html = <<<HTML
        <table style="background-color:{$color}; width:100%;">
            <tr style="height:30px">
                <td>
                    <font size="2">
                        <b>{$description}</b>
                    </font>
                </td>
             </tr>
        </table>
HTML;
        echo $html;
    }
}

/**
 * @return bool
 */
function com_uninstall() {
    $return = true;
    echo '<h2>JFusion ' . JText::_('UNINSTALL') . '</h2><br/>';

    //restore the normal login behaviour
    $db = JFactory::getDBO();

	$jversion = new JVersion;
    $version = $jversion->getShortVersion();
    if(version_compare($version, '1.6') >= 0){
        $db->setQuery('UPDATE #__extensions SET enabled = 1 WHERE element =\'joomla\' and folder = \'authentication\'');
        $db->Query();
        $db->setQuery('UPDATE #__extensions SET enabled = 1 WHERE element =\'joomla\' and folder = \'user\'');
        $db->Query();
    } else {
        $db->setQuery('UPDATE #__plugins SET published = 1 WHERE element =\'joomla\' and folder = \'authentication\'');
        $db->Query();
        $db->setQuery('UPDATE #__plugins SET published = 1 WHERE element =\'joomla\' and folder = \'user\'');
        $db->Query();
    }

    echo '<table style="background-color:#d9f9e2;" width ="100%"><tr style="height:30px">';
    echo '<td><font size="2"><b>' . JText::_('NORMAL_JOOMLA_BEHAVIOR_RESTORED') . '</b></font></td></tr></table>';

    //uninstall the JFusion plugins
    _uninstallPlugin('plugin', 'jfusion', 'user', 'JFusion User Plugin');
    _uninstallPlugin('plugin', 'jfusion', 'authentication', 'JFusion Authentication Plugin');
    _uninstallPlugin('plugin', 'jfusion', 'search', 'JFusion Search Plugin');
    _uninstallPlugin('plugin', 'jfusion', 'content', 'JFusion Discussion Bot Plugin');
    _uninstallPlugin('plugin', 'jfusion', 'system', 'JFusion System Plugin');

    //uninstall the JFusion Modules
    _uninstallPlugin('module', 'mod_jfusion_login', '', 'JFusion Login Module');
    _uninstallPlugin('module', 'mod_jfusion_activity', '', 'JFusion Activity Module');
    _uninstallPlugin('module', 'mod_jfusion_user_activity', '', 'JFusion User Activity Module');
    _uninstallPlugin('module', 'mod_jfusion_whosonline', '', 'JFusion Whos Online Module');

    //see if any mods from jfusion plugins need to be removed
    $plugins = JFusionFactory::getPlugins('all',true,false);
    foreach($plugins as $plugin) {
    	$JFusionPlugin = JFusionFactory::getAdmin($plugin->name);
        list ($success,$reasons) = $JFusionPlugin->uninstall();
    	if (!$success) {
            echo '<table style="background-color:#f9ded9;" width ="100%"><tr style="height:30px">';
            echo '<td><font size="2"><b>'.JText::_('UNINSTALL') . ' ' . $plugin->name . ' ' . JText::_('FAILED') . ': </b></font></td></tr>';
            if (is_array($reasons)) {
                foreach ($reasons as $r) {
                    echo '<td style="padding-left: 15px;">'.$r.'</td></tr>';
                }
            }
            echo '</table>';
    	    $return = false;
    	}
    }

    //remove the jfusion tables.
    $db = JFactory::getDBO();
    $query = 'DROP TABLE #__jfusion';
    $db->setQuery($query);
    if (!$db->Query()){
        echo $db->stderr() . '<br />';
        $return = false;
    }

    $query = 'DROP TABLE #__jfusion_sync';
    $db->setQuery($query);
    if (!$db->Query()){
        echo $db->stderr() . '<br />';
        $return = false;
    }

    $query = 'DROP TABLE #__jfusion_sync_details';
    $db->setQuery($query);
    if (!$db->Query()){
        echo $db->stderr() . '<br />';
        $return = false;
    }

    $query = 'DROP TABLE #__jfusion_users';
    $db->setQuery($query);
    if (!$db->Query()){
        echo $db->stderr() . '<br />';
        $return = false;
    }

    $query = 'DROP TABLE #__jfusion_users_plugin';
    $db->setQuery($query);
    if (!$db->Query()){
        echo $db->stderr() . '<br />';
        $return = false;
    }

    $query = 'DROP TABLE #__jfusion_discussion_bot';
    $db->setQuery($query);
    if (!$db->queryBatch()){
    	echo $db->stderr() . '<br />';
    	$return = false;
    }

    return $return;
}