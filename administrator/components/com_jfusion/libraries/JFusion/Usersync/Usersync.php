<?php namespace JFusion\Usersync;

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

use JFusion\Factory;
use JFusion\Framework;
use Joomla\Language\Text;

use stdClass;
use Exception;

/**
 * Prevent time-outs
 */
ob_start();
set_time_limit(0);
ini_set('memory_limit', '256M');

ini_set('upload_max_filesize', '128M');
ini_set('post_max_size', '256M');
ini_set('max_input_time', '7200');
ini_set('max_execution_time', '0');
ini_set('expect.timeout', '7200');
ini_set('default_socket_timeout', '7200');
ob_end_clean();

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
class Usersync
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
    public static function getLogData($syncid, $type = 'all', $limitstart, $limit, $sort, $dir)
    {
        $db = Factory::getDBO();

	    $query = $db->getQuery(true)
		    ->select('*')
		    ->from('#__jfusion_sync_details')
		    ->where('syncid = ' . $db->quote($syncid));

	    if (!empty($sort)) {
		    $query->order($sort . ' ' . $dir);
	    }

        if ($type != 'all') {
	        $query->where('action = ' . $db->quote($type));
        }
        $db->setQuery($query, $limitstart, $limit);
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
        $db = Factory::getDBO();

	    $query = $db->getQuery(true)
		    ->select('COUNT(*)')
		    ->from('#__jfusion_sync_details')
		    ->where('syncid = ' . $db->quote($syncid));

        if ($type != 'all') {
	        $query->where('action = ' . $db->quote($type));
        }
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
        $db = Factory::getDBO();

	    $data = new stdClass;
	    $data->syncdata = $serialized;
	    $data->syncid = $syncdata['syncid'];
	    $data->time_start = time();
	    $data->action = $syncdata['action'];

	    $db->insertObject('#__jfusion_sync', $data);
    }

    /**
     * Update syncdata
     *
     * @param string &$syncdata the actual syncdata
     *
     * @return string nothing
     */
	public static function updateSyncdata(&$syncdata)
    {
        //serialize the $syncdata to allow storage in a SQL field
        $serialized = base64_encode(serialize($syncdata));
        //find out if the syncid already exists
        $db = Factory::getDBO();

	    $query = $db->getQuery(true)
		    ->update('#__jfusion_sync')
		    ->set('syncdata = ' . $db->quote($serialized))
		    ->where('syncid = ' . $db->quote($syncdata['syncid']));

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Get syncdata
     *
     * @param string $syncid the usersync id
     *
     * @return array
     */
    public static function getSyncdata($syncid)
    {
        $db = Factory::getDBO();

	    $query = $db->getQuery(true)
		    ->select('syncdata')
		    ->from('#__jfusion_sync')
		    ->where('syncid = ' . $db->quote($syncid));

        $db->setQuery($query);
        $serialized = $db->loadResult();
        $syncdata = unserialize(base64_decode($serialized));
        //note we do not want to append the errors as then it gets saved to the database as well in syncExecute
        //get the errors and append it
        //$syncdata['errors'] = \JFusion\Usersync\Usersync::getLogData($syncid);
        return $syncdata;
    }

	/**
	 * Fix sync errors
	 *
	 * @param string $syncid    the usersync id
	 * @param array  $syncError the actual syncError data
	 *
	 * @param        $limitstart
	 * @param        $limit
	 * @param        $sort
	 * @param        $dir
	 *
	 * @return string nothing
	 */
    public static function syncError($syncid, $syncError, $limitstart, $limit, $sort, $dir)
    {
	    $synclog = static::getLogData($syncid, 'error', $limitstart, $limit, $sort, $dir);
	    foreach ($syncError as $id => $error) {
		    try {
			    if (isset($error['action']) && isset($synclog[$id]) && $error['action']) {
				    $data = unserialize($synclog[$id]->data);
				    if ($error['action'] == '1') {
					    $userinfo = Factory::getUser($data['conflict']['jname'])->getUser($data['conflict']['userinfo']);

					    $userPlugin = Factory::getUser($data['user']['jname']);
					    $status = $userPlugin->updateUser($userinfo, 1);
					    if (!empty($status['error'])) {
						    Framework::raise('error', $status['error'], $data['user']['jname'] . ' ' . Text::_('USER') . ' ' . Text::_('UPDATE'));
					    } else {
						    Framework::raiseMessage(Text::_('USER') . ' ' . $userinfo->username . ' ' . Text::_('UPDATE'), $data['user']['jname']);
						    static::markResolved($id);
						    $userPlugin->updateLookup($data['user']['userinfo'], $userinfo);
					    }
				    } elseif ($error['action'] == '2') {
					    $userinfo = Factory::getUser($data['user']['jname'])->getUser($data['user']['userinfo']);

					    $userPlugin = Factory::getUser($data['conflict']['jname']);

					    $status = $userPlugin->updateUser($userinfo, 1);
					    if (!empty($status['error'])) {
						    Framework::raise('error', $status['error'], $data['conflict']['jname'] . ' ' . Text::_('USER') . ' ' . Text::_('UPDATE'));
					    } else {
						    Framework::raiseMessage(Text::_('USER') . ' ' . $userinfo->username . ' ' . Text::_('UPDATE'), $data['conflict']['jname']);
						    static::markResolved($id);
						    $userPlugin->updateLookup($userinfo, $data['user']['userinfo']);
					    }
				    } elseif ($error['action'] == '3') {
					    //delete the first entity
					    //prevent Joomla from deleting all the slaves via the user plugin if it is set as master
					    global $JFusionActive;
					    $JFusionActive = 1;

					    $userPlugin = Factory::getUser($error['user_jname']);
					    $status = $userPlugin->deleteUser($data['user']['userinfo']);
					    if (!empty($status['error'])) {
						    Framework::raise('error', $status['error'], $error['user_jname'] . ' ' . Text::_('ERROR') . ' ' .  Text::_('DELETING') . ' ' . Text::_('USER') . ' ' . $error['user_username']);
					    } else {
						    static::markResolved($id);
						    Framework::raiseMessage(Text::_('SUCCESS') . ' ' . Text::_('DELETING') . ' ' . Text::_('USER') . ' ' . $error['user_username'], $error['user_jname']);
						    $userPlugin->deleteLookup($data['user']['userinfo']);
					    }
				    } elseif ($error['action'] == '4') {
					    //delete the second entity (conflicting plugin)
					    //prevent Joomla from deleting all the slaves via the user plugin if it is set as master
					    global $JFusionActive;
					    $JFusionActive = 1;
					    $userPlugin = Factory::getUser($error['conflict_jname']);
					    $status = $userPlugin->deleteUser($data['conflict']['userinfo']);
					    if (!empty($status['error'])) {
						    Framework::raise('error', $status['error'], $error['conflict_jname'] . ' ' . Text::_('ERROR') . ' ' . Text::_('DELETING') . ' ' .  Text::_('USER') . ' ' . $error['conflict_username']);
					    } else {
						    static::markResolved($id);
						    Framework::raiseMessage(Text::_('SUCCESS') . ' ' . Text::_('DELETING') . ' ' . Text::_('USER') . ' ' . $error['user_username'], $error['conflict_jname']);
						    $userPlugin->deleteLookup($data['conflict']['userinfo']);
					    }
				    }
			    }
		    } catch (Exception $e) {
			    Framework::raiseError($e);
		    }
	    }
    }

    /**
     * Marks an error in sync details as resolved to prevent it from constantly showing up in the resolve error view
     * @param $id
     */
	public static function markResolved($id) {
        $db = Factory::getDBO();

		$query = $db->getQuery(true)
			->update('#__jfusion_sync_details')
			->set('action = ' . $db->quote('resolved'))
			->where('id = ' . $db->quote($id));

        $db->setQuery($query);
        $db->execute();
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
	    try {
		    if (empty($syncdata['completed'])) {
			    //setup some variables
			    $MasterPlugin = Factory::getAdmin($syncdata['master']);
			    $MasterUser = Factory::getUser($syncdata['master']);

			    $syncid = $syncdata['syncid'];
			    $sync_active = static::getSyncStatus($syncid);
			    $db = Factory::getDBO();
			    if (!$sync_active) {
				    //tell JFusion a sync is in progress
				    static::changeSyncStatus($syncid, 1);
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
					    for ($i = $plugin_offset; $i < count($syncdata['slave_data']);$i++) {
						    $syncdata['plugin_offset'] = $i;
						    //get a list of users
						    $jname = $syncdata['slave_data'][$i]['jname'];
						    if ($jname) {
							    $SlavePlugin = Factory::getAdmin($jname);
							    $SlaveUser = Factory::getUser($jname);
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
							    }
							    //perform the actual sync
							    for ($j = $user_offset;$j < count($userlist);$j++) {
								    $syncdata['user_offset']++;
								    $status = array();
								    $userinfo = null;
								    try {
									    if ($action == 'master') {
										    $userinfo = $SlaveUser->getUser($userlist[$j]);
										    $status = $MasterUser->updateUser($userinfo);
									    } else {
										    $userinfo = $MasterUser->getUser($userlist[$j]);
										    $status = $SlaveUser->updateUser($userinfo);
									    }

									    //populate userinfo if not done by plugin
									    if (empty($status['userinfo'])) {
										    if ($action == 'master') {
											    $status['userinfo'] = $MasterUser->getUser($userlist[$j]);
										    } else {
											    $status['userinfo'] = $SlaveUser->getUser($userlist[$j]);
										    }
									    }
								    } catch (Exception $e) {
									    $status['error'] = $e->getMessage();
									    $status['userinfo'] = null;
								    }

								    $sync_log = new stdClass;
								    $sync_log->syncid = $syncdata['syncid'];
								    $sync_log->jname = $jname;
								    $sync_log->message = '';
								    $sync_log->data = '';

								    $sync_log->username = $userlist[$j]->username;
								    $sync_log->email = $userlist[$j]->email;

								    if (!empty($status['error'])) {
									    $status['action'] = 'error';
									    $sync_log->message = (is_array($status['error'])) ? implode('; ', $status['error']) : $status['error'];
									    $sync_error = array();
									    $sync_error['conflict']['userinfo'] = $status['userinfo'];
									    $sync_error['conflict']['error'] = $status['error'];
									    $sync_error['conflict']['debug'] = (!empty($status['debug'])) ? $status['debug'] : '';
									    $sync_error['conflict']['jname'] = $action_reverse_name;
									    $sync_error['user']['jname'] = $action_name;
									    $sync_error['user']['userinfo'] = $userinfo;
									    $sync_error['user']['userlist'] = $userlist[$j];
									    $sync_log->data = serialize($sync_error);
									    $syncdata['sync_errors']++;
								    } else {
									    //usersync loggin enabled
									    $sync_log->username = isset($status['userinfo']->username) ? $status['userinfo']->username : $userinfo->username;
									    $sync_log->email = isset($status['userinfo']->email) ? $status['userinfo']->email : $userinfo->email;
									    //update the lookup table
									    if ($action == 'master') {
										    $MasterUser->updateLookup($status['userinfo'], $userinfo);
									    } else {
										    $SlaveUser->updateLookup($userinfo, $status['userinfo']);
									    }
								    }
								    $sync_log->action = $status['action'];

								    //append the error to the log
								    $db->insertObject('#__jfusion_sync_details', $sync_log);

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
									    static::updateSyncdata($syncdata);
									    //update counters
									    $user_count = 1;
									    $user_batch--;
								    } else {
									    //update counters
									    $user_count++;
									    $user_batch--;
								    }

								    if ($syncdata['synced_users'] == $syncdata['total_to_sync']) {
									    break;
								    } elseif ($user_batch == 0 || $syncdata['slave_data'][$i]['total'] == 0) {
									    //exit the process to prevent an apache timeout; it will resume on the next ajax call
									    //save the syncdata before exiting
									    if ($syncdata['slave_data'][$i]['total'] == 0) {
										    //will force  the next plugin and first user of that plugin on resume
										    $syncdata['plugin_offset'] += 1;
										    $syncdata['user_offset'] = 0;
									    }
									    static::updateSyncdata($syncdata);
									    //tell Joomla the batch has completed
									    static::changeSyncStatus($syncid, 0);
									    return;
								    }
							    }
						    }
					    }
				    }

				    if ($syncdata['synced_users'] == $syncdata['total_to_sync']) {
					    //end of sync, save the final data
					    $syncdata['completed'] = true;
					    static::updateSyncdata($syncdata);

					    //update the finish time
					    $db = Factory::getDBO();

					    $query = $db->getQuery(true)
						    ->update('#__jfusion_sync')
						    ->set('time_end = ' . $db->quote(time()))
						    ->where('syncid = ' . $db->quote($syncdata['syncid']));

					    $db->setQuery($query);
					    $db->execute();
				    }
				    static::updateSyncdata($syncdata);
				    static::changeSyncStatus($syncid, 0);
			    }
		    }
	    } catch (Exception $e) {
		    Framework::raiseError($e);
	    }
    }

    /**
     * @static
     * @param $syncid
     * @param $status
     */
    public static function changeSyncStatus($syncid, $status) {
        $db = Factory::getDBO();

	    $query = $db->getQuery(true)
		    ->update('#__jfusion_sync')
		    ->set('active = ' . (int) $status)
		    ->where('syncid = ' . $db->quote($syncid));

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * @static
     * @param string $syncid
     * @return int|mixed
     */
    public static function getSyncStatus($syncid = '') {
        if (!empty($syncid)) {
            $db = Factory::getDBO();

	        $query = $db->getQuery(true)
		        ->select('active')
		        ->from('#__jfusion_sync')
		        ->where('syncid = ' . $db->quote($syncid));

            $db->setQuery($query);
            $status = $db->loadResult();
            return $status;
        }
        return 0;
    }
}