<?php

/**
 * Model that handles the usersync
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
 * Prevent time-outs
 */
@set_time_limit(0);
@ini_set('memory_limit', '256M');
@ini_set('upload_max_filesize', '128M');
@ini_set('post_max_size', '256M');
@ini_set('max_input_time', '7200');
@ini_set('max_execution_time', '0');
@ini_set('expect.timeout', '7200');
@ini_set('default_socket_timeout', '7200');

/**
 * Class for usersync JFusion functions
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionUsersync
{
    /**
     * Retrieve log data
     *
     * @param string $syncid the usersync id
     * @param string $type
     * @param int $limitstart
     * @param int $limit
     * @param string $sort
     * @param string $dir
     *
     * @return string nothing
     */
    public static function getLogData($syncid, $type = 'all', $limitstart=null, $limit=null, $sort='id', $dir='')
    {
        $db = JFactory::getDBO();

        if (empty($limit)) {
            $mainframe  = JFactory::getApplication();
            $client     = JRequest::getWord( 'filter_client', 'site' );
            $option     = JRequest::getCmd('option');
            $sort       = $mainframe->getUserStateFromRequest( "$option.$client.filter_order",      'filter_order',     'id',       'cmd' );
            $dir        = $mainframe->getUserStateFromRequest( "$option.$client.filter_order_Dir",  'filter_order_Dir', '',         'word' );
            $limit      = (int)$mainframe->getUserStateFromRequest( 'global.list.limit', 'limit', $mainframe->getCfg('list_limit'), 'int' );
            $limitstart = 0;
        }

        $sortStatement = (!empty($sort)) ? "ORDER BY $sort $dir" : '';
        $whereStatement = "WHERE syncid = {$db->Quote($syncid)}";
        if ($type != 'all') {
            $whereStatement.= " AND action = '$type'";
        }
        $query = "SELECT * FROM #__jfusion_sync_details $whereStatement $sortStatement";
        $db->setQuery($query,$limitstart,$limit);
        $results = $db->loadObjectList('id');

        return $results;
    }
    
    /**
     * Count log data
     *
     * @param string $syncid the usersync id
     * @param string $type
     *
     * @return int count results
     */
    public static function countLogData($syncid, $type = 'all')
    {
        $db = JFactory::getDBO();
        $whereStatement = "WHERE syncid = {$db->Quote($syncid)}";
        if ($type != 'all') {
            $whereStatement.= " AND action = '$type'";
        }
        $query = "SELECT COUNT(*) FROM #__jfusion_sync_details $whereStatement";
        $db->setQuery($query);
        return $db->loadResult();
    }    

    /**
     * Save sync data
     *
     * @param string &$syncdata the actual syncdata
     *
     * @return string nothing
     */
    public static function saveSyncdata(&$syncdata)
    {
        //serialize the $syncdata to allow storage in a SQL field
        $serialized = base64_encode(serialize($syncdata));
        $db = JFactory::getDBO();
        $query = 'INSERT INTO #__jfusion_sync (syncdata, syncid, time_start, action) VALUES (' . $db->Quote($serialized) . ', ' . $db->Quote($syncdata['syncid']) . ', ' . $db->Quote(time()) . ', ' . $db->Quote($syncdata['action']) . ')';
        $db->setQuery($query);
        $db->query();
    }

    /**
     * Update syncdata
     *
     * @param string &$syncdata the actual syncdata
     *
     * @return string nothing
     */
    function updateSyncdata(&$syncdata)
    {
        //serialize the $syncdata to allow storage in a SQL field
        $serialized = base64_encode(serialize($syncdata));
        //find out if the syncid already exists
        $db = JFactory::getDBO();
        $query = 'UPDATE #__jfusion_sync SET syncdata = ' . $db->Quote($serialized) . ' WHERE syncid =' . $db->Quote($syncdata['syncid']);
        $db->setQuery($query);
        $db->query();
    }

    /**
     * Get syncdata
     *
     * @param string $syncid the usersync id
     *
     * @return string nothing
     */
    public static function getSyncdata($syncid)
    {
        $db = JFactory::getDBO();
        $query = 'SELECT syncdata FROM #__jfusion_sync WHERE syncid =' . $db->Quote($syncid);
        $db->setQuery($query);
        $serialized = $db->loadResult();
        $syncdata = unserialize(base64_decode($serialized));
        //note we do not want to append the errors as then it gets saved to the database as well in syncExecute
        //get the errors and append it
        //$syncdata['errors'] = JFusionUsersync::getLogData($syncid);
        return $syncdata;
    }

    /**
     * Fix sync errors
     *
     * @param string $syncid    the usersync id
     * @param array $syncError the actual syncError data
     *
     * @return string nothing
     */
    public static function syncError($syncid, $syncError)
    {
        //Load debug library
        include_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.debug.php';
        ?><div style="border: 0 none ; margin: 0; padding: 0 5px; width: 800px;">
        <?php
        $synclog = JFusionUsersync::getLogData($syncid,'error');
        foreach ($syncError as $id => $error) {
        	if (isset($error['action'])) {
	            $data = unserialize($synclog[$id]->data);
	            if ($error['action'] == '1') {
	                //update the first entity
	                echo '<h2>' . $data['user']['jname'] . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE') . '</h2>';
	                $JFusionPlugin = & JFusionFactory::getUser($data['user']['jname']);
	                debug::show($data['conflict']['userinfo'], $data['conflict']['jname'] . ' ' . JText::_('USER') . ' ' . JText::_('INFORMATION'), 1);
	                $status = $JFusionPlugin->updateUser($data['conflict']['userinfo'], 1);
	                if (!empty($status['error'])) {
	                    debug::show($status['error'], $data['user']['jname'] . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('ERROR'), 0);
	                    debug::show($status['debug'], $data['user']['jname'] . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('DEBUG'), 0);
	                } else {
	                    JFusionUsersync::markResolved($id);
	                    debug::show($status['debug'], $data['user']['jname'] . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('DEBUG'), 0);
	                    JFusionFunction::updateLookup($data['user']['userinfo'], 0, $data['user']['jname']);
	                }
	            } elseif ($error['action'] == '2') {
	                //update the second entity (conflicting plugin)
	                echo '<h2>' . $data['conflict']['jname'] . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE') . '</h2>';
	                $JFusionPlugin = & JFusionFactory::getUser($data['conflict']['jname']);
	                debug::show($data['user']['userinfo'], $data['user']['jname'] . ' ' . JText::_('USER') . ' ' . JText::_('INFORMATION'), 1);
	                $status = $JFusionPlugin->updateUser($data['user']['userinfo'], 1);
	                if (!empty($status['error'])) {
	                    debug::show($status['error'], $data['conflict']['jname'] . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('ERROR'), 0);
	                    debug::show($status['debug'], $data['conflict']['jname'] . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('DEBUG'), 0);
	                } else {
	                    JFusionUsersync::markResolved($id);
	                    debug::show($status['debug'], $data['conflict']['jname'] . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('DEBUG'), 0);
	                    JFusionFunction::updateLookup($data['user']['userinfo'], 0, $data['user']['jname']);
	                }
	            } elseif ($error['action'] == '3') {
	                //delete the first entity
	                //prevent Joomla from deleting all the slaves via the user plugin if it is set as master
	                global $JFusionActive;
	                $JFusionActive = 1;
	                $JFusionPlugin = & JFusionFactory::getUser($error['user_jname']);
	                $status = $JFusionPlugin->deleteUser($data['user']['userinfo']);
	                if (!empty($status['error'])) {
	                    //delete error
	                    echo '<img src="components/com_jfusion/images/error.png" width="32" height="32">' . JText::_('ERROR') . ' ' . JText::_('DELETING') . ' ' . $error['user_jname'] . ' ' . JText::_('USER') . ' ' . $error['user_username'] . '<br/>';
	                } else {
	                    JFusionUsersync::markResolved($id);
	                    //delete success
	                    echo '<img src="components/com_jfusion/images/updated.png" width="32" height="32">' . JText::_('SUCCESS') . ' ' . JText::_('DELETING') . ' ' . $error['user_jname'] . ' ' . JText::_('USER') . ' ' . $error['user_username'] . '<br/>';
	                    JFusionFunction::updateLookup($data['user']['userinfo'], 0, $error['conflict_jname'], true);
	                }
	            } elseif ($error['action'] == '4') {
	                //delete the second entity (conflicting plugin)
	                //prevent Joomla from deleting all the slaves via the user plugin if it is set as master
	                global $JFusionActive;
	                $JFusionActive = 1;
	                $JFusionPlugin = & JFusionFactory::getUser($error['conflict_jname']);
	                $status = $JFusionPlugin->deleteUser($data['conflict']['userinfo']);
	                if (!empty($status['error'])) {
	                    //delete error
	                    echo '<img src="components/com_jfusion/images/error.png" width="32" height="32">' . JText::_('ERROR') . ' ' . JText::_('DELETING') . ' ' . $error['conflict_jname'] . ' ' . JText::_('USER') . ' ' . $error['conflict_username'] . '<br/>';
	                } else {
	                    JFusionUsersync::markResolved($id);
	                    //delete success
	                    echo '<img src="components/com_jfusion/images/updated.png" width="32" height="32">' . JText::_('SUCCESS') . ' ' . JText::_('DELETING') . ' ' . $error['conflict_jname'] . ' ' . JText::_('USER') . ' ' . $error['conflict_username'] . '<br/>';
	                    JFusionFunction::updateLookup($data['conflict']['userinfo'], 0, $error['conflict_jname'], true);
	                }
	            }
        	}
        }
        ?></div><?php
        echo "<h2>" . JText::_('CONFLICT_RESOLUTION_COMPLETE') . "</h2>";
    }

    /**
     * Marks an error in sync details as resolved to prevent it from constantly showing up in the resolve error view
     * @param $id
     */
    function markResolved($id) {
        $db = JFactory::getDBO();
        $query = "UPDATE #__jfusion_sync_details SET action = 'resolved' WHERE id = $id";
        $db->setQuery($query);
        $db->query();
    }

    /**
     * Save log data
     *
     * @param string &$syncdata     the actual syncdata
     * @param string $action        the type of sync action required
     * @param int    $plugin_offset the plugin offset
     * @param int    $user_offset   the user offset
     *
     * @return string nothing
     */
    public static function syncExecute(&$syncdata, $action, $plugin_offset, $user_offset)
    {
        if (empty($syncdata['completed'])) {
            //setup some variables
            $MasterPlugin = & JFusionFactory::getAdmin($syncdata['master']);
            $MasterUser = & JFusionFactory::getUser($syncdata['master']);
            $sync_log = new stdClass;
            $syncid =& $syncdata['syncid'];
            $sync_active = JFusionUsersync::getSyncStatus($syncid);
            $db = JFactory::getDBO();
            if (!$sync_active) {
                //tell JFusion a sync is in progress
                JFusionUsersync::changeSyncStatus($syncid, 1);
                //only store syncdata every 20 users for better performance
                $store_interval = 20;
                $user_count = 1;
                //going to die every x users so that apache doesn't time out
                if (!isset($syncdata['userbatch'])) {
                     $syncdata['userbatch'] = 500;
                }
                $user_batch = $syncdata['userbatch'];
                //we should start with the import of slave users into the master
                if ($syncdata['slave_data']) {
                    for ($i = $plugin_offset;$i < count($syncdata['slave_data']);$i++) {
                        $syncdata['plugin_offset'] = $i;
                        //get a list of users
                        $jname = $syncdata['slave_data'][$i]['jname'];
                        if ($jname) {
                            $SlavePlugin = & JFusionFactory::getAdmin($jname);
                            $SlaveUser = & JFusionFactory::getUser($jname);
                            if ($action == 'master') {
                                $userlist = $SlavePlugin->getUserList($user_offset, $syncdata['userbatch']);
                                $action_name = $jname;
                                $action_reverse_name = $syncdata['master'];
                            } else {
                                $userlist = $MasterPlugin->getUserList($user_offset, $syncdata['userbatch']);
                                $action_name = $syncdata['master'];
                                $action_reverse_name = $jname;
                            }

                            //catch to determine if the plugin supports limiting users for sync performance
                            if (count($userlist) != $syncdata['slave_data'][$i]['total_to_sync']) {
                                //the userlist has already been limited so just start with the first one from the retrieved results
                                $user_offset = 0;
                                $users_limited = true;
                            }

                            //perform the actual sync
                            for ($j = $user_offset;$j < count($userlist);$j++) {
                                $syncdata['user_offset']++;
                                if ($action == 'master') {
                                    $userinfo = $SlaveUser->getUser($userlist[$j]);
                                    $status = $MasterUser->updateUser($userinfo, 0);
                                } else {
                                    $userinfo = $MasterUser->getUser($userlist[$j]);
                                    $status = $SlaveUser->updateUser($userinfo, 0);
                                }

                                //populate userinfo if not done by plugin
                                if (empty($status['userinfo'])) {
                                    if ($action == 'master') {
                                        $status['userinfo'] = $MasterUser->getUser($userlist[$j]);
                                    } else {
                                        $status['userinfo'] = $SlaveUser->getUser($userlist[$j]);
                                    }
                                }

                                if (!empty($status['error'])) {
                                    $status['action'] = 'error';
                                    $sync_log->syncid = $syncdata['syncid'];
                                    $sync_log->action = $status['action'];
                                    $sync_log->username = $userinfo->username;
                                    $sync_log->email = $userinfo->email;
                                    $sync_log->jname = $jname;
                                    $sync_log->message = (is_array($status['error'])) ? implode('; ',$status['error']) : $status['error'];
                                    $sync_error = array();
                                    $sync_error['conflict']['userinfo'] = $status['userinfo'];
                                    $sync_error['conflict']['error'] = $status['error'];
                                    $sync_error['conflict']['debug'] = (!empty($status['debug'])) ? $status['debug'] : '';
                                    $sync_error['conflict']['jname'] = $action_reverse_name;
                                    $sync_error['user']['jname'] = $action_name;
                                    $sync_error['user']['userinfo'] = $userinfo;
                                    $sync_error['user']['userlist'] = $userlist[$j];
                                    $sync_log->data = serialize($sync_error);
                                    $syncdata['sync_errors'] = 1;
                                } else {
                                    //usersync loggin enabled
                                    $sync_log->syncid = $syncdata['syncid'];
                                    $sync_log->action = $status['action'];
                                    $sync_log->username = $status['userinfo']->username;
                                    $sync_log->email = $status['userinfo']->email;
                                    $sync_log->jname = $jname;
                                    $sync_log->message = '';
                                    $sync_log->data = '';

                                    //update the lookup table
                                    if ($action == "master") {
                                        JFusionFunction::updateLookup($userinfo, 0, $jname);
                                    } else {
                                        JFusionFunction::updateLookup($SlaveUser->getUser($userlist[$j]), 0, $jname);
                                    }
                                }

                                //append the error to the log
                                $db->insertObject('#__jfusion_sync_details', $sync_log);
                                $sync_log = new stdClass;

                                //update the counters
                                $syncdata['slave_data'][$i][$status['action']]+= 1;
                                $syncdata['slave_data'][$i]['total']-= 1;
                                $syncdata['synced_users']+= 1;
                                //update the database
                                if ($user_count >= $store_interval) {
                                    if ($syncdata['slave_data'][$i]['total'] == 0) {
                                        //will force the next plugin and first user of that plugin on resume
                                        $syncdata['plugin_offset'] += 1;
                                        $syncdata['user_offset'] = 0;
                                    }
                                    JFusionUsersync::updateSyncdata($syncdata);
                                    //update counters
                                    $user_count = 1;
                                    $user_batch--;
                                } else {
                                    //update counters
                                    $user_count++;
                                    $user_batch--;
                                }
                                $percent = ($syncdata['synced_users'] / $syncdata['total_to_sync']) * 100;
                                if ($percent == '100') {
                                    break;
                                } elseif ($user_batch == 0 || $syncdata['slave_data'][$i]['total'] == 0) {
                                    //exit the process to prevent an apache timeout; it will resume on the next ajax call

                                    //save the syncdata before exiting
                                    if ($syncdata['slave_data'][$i]['total'] == 0) {
                                        //will force  the next plugin and first user of that plugin on resume
                                        $syncdata['plugin_offset'] += 1;
                                        $syncdata['user_offset'] = 0;
                                    }
                                    JFusionUsersync::updateSyncdata($syncdata);
                                    //tell Joomla the batch has completed
                                    JFusionUsersync::changeSyncStatus($syncid, 0);
                                    jexit();
                                }
                            }
                        }
                    }

                    //end of sync, save the final data
                    $syncdata['completed'] = 'true';
                    JFusionUsersync::updateSyncdata($syncdata);
                    JFusionUsersync::changeSyncStatus($syncid, 0);
                    //update the finish time
                    $db = JFactory::getDBO();
                    $query = 'UPDATE #__jfusion_sync SET time_end = ' . $db->Quote(time()) . ' WHERE syncid =' . $db->Quote($syncdata['syncid']);
                    $db->setQuery($query);
                    $db->query();
                }
            } elseif ((($syncdata['synced_users'] / $syncdata['total_to_sync']) * 100) == 100) {
                 //end of sync, save the final data
                $syncdata['completed'] = 'true';
                JFusionUsersync::updateSyncdata($syncdata);
                JFusionUsersync::changeSyncStatus($syncid, 0);
                //update the finish time
                $db = JFactory::getDBO();
                $query = 'UPDATE #__jfusion_sync SET time_end = ' . $db->Quote(time()) . ' WHERE syncid =' . $db->Quote($syncdata['syncid']);
                $db->setQuery($query);
                $db->query();
            } else {
                jexit();
            }
        }
    }

    /**
     * @static
     * @param $syncid
     * @param $status
     */
    public static function changeSyncStatus($syncid, $status) {
        $db = JFactory::getDBO();
        $query = "UPDATE #__jfusion_sync SET active = " . (int) $status . " WHERE syncid = " . $db->Quote($syncid);
        $db->setQuery($query);
        $db->query();
    }

    /**
     * @static
     * @param string $syncid
     * @return int|mixed
     */
    public static function getSyncStatus($syncid = '') {
        if (!empty($syncid)) {
            $db = JFactory::getDBO();
            $query = "SELECT active FROM #__jfusion_sync WHERE syncid = " . $db->Quote($syncid);
            $db->setQuery($query);
            $status = $db->loadResult();
            return $status;
        }
        return 0;
    }
}