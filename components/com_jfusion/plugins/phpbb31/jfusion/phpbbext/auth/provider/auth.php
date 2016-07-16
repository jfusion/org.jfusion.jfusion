<?php
/**
*
* @package phpBB Extension - JFusion phpBB Extension
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace jfusion\phpbbext\auth\provider;

/**
* @ignore
 * extends \phpbb\extension\base
*/
class auth extends \phpbb\auth\provider\db
{
	/**
	 * {@inheritdoc}
	 */
	public function login($username, $password)
	{
		$result = parent::login($username, $password);
		global $JFusionActive;
		if ($result['status'] == LOGIN_SUCCESS && empty($JFusionActive)) {
			//check to see if login successful and jFusion is not active
			$joomla = $this->startJoomla();

			//backup phpbb globals
			$joomla->backupGlobal();
			$this->request->enable_super_globals();

			$autologin = $this->request->variable('autologin', null, false, \phpbb\request\request_interface::POST);

			//detect if the session should be remembered
			if (!empty($autologin)) {
				$remember = 1;
			} else {
				$remember = 0;
			}
			$joomla->setActivePlugin($this->config['jfusion_phpbbext_jname']);
			$joomla->login($username, $password, $remember);

			if ($this->config['jfusion_phpbbext_redirect_login']) {
				if ($this->request->is_set('redirect') && defined('IN_JOOMLA')) {
					$itemid = \JFusionFactory::getApplication()->input->getInt('Itemid');
					$url = \JFusionFunction::getPluginURL($itemid, false);
					$redirect = str_replace('./', '', $this->request->variable('redirect', null));
					if (strpos($redirect, 'mode=login') !== false) {
						$redirect = 'index.php';
					}
					$redirect = str_replace('?', '&', $redirect);
					$redirect = $url . "&jfile=" . $redirect;
				} else {
					//redirect to prevent fatal errors on some servers
					$uri = \JURI::getInstance();
					//remove sid from URL
					$query = $uri->getQuery(true);
					if (isset($query['sid'])) {
						unset($query['sid']);
					}
					$uri->setQuery($query);
					//add a variable to ensure refresh
					$redirect = $uri->toString();
				}
/**
TODO: still needed?
				//recreate phpBB database connection
				$dbhost = $dbuser = $dbpasswd = $dbname = $dbport = null;
				include $this->phpbb_root_path . 'config.' . $this->php_ext;
				$this->db->sql_connect($dbhost, $dbuser, $dbpasswd, $dbname, $dbport, false, false);
				unset($dbpasswd);
*/
				//create phpBB user session
				$this->user->session_create($result['user_row']['user_id'], 0, $remember);

				$url = str_replace('&amp;', '&', $redirect);

				header('Location: ' . $url);
				exit();
			} else {
/**
TODO: still needed?
				//recreate phpBB database connection
				$dbhost = $dbuser = $dbpasswd = $dbname = $dbport = null;
				include $this->phpbb_root_path . 'config.' . $this->php_ext;
				$this->db->sql_connect($dbhost, $dbuser, $dbpasswd, $dbname, $dbport, false, false);
				unset($dbpasswd);
*/
			}
			//backup phpbb globals
			$joomla->restoreGlobal();
			$this->request->disable_super_globals();
		}
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function logout($data, $new_session)
	{
		parent::logout($data, $new_session);
		//check to see if JFusion is not active
		global $JFusionActive;
		if (empty($JFusionActive)) {
			$joomla = $this->startJoomla();

			//backup phpbb globals
			$joomla->backupGlobal();
			$this->request->enable_super_globals();

			//define that the phpBB3 JFusion plugin needs to be excluded
			$joomla->setActivePlugin($this->config['jfusion_phpbbext_jname']);

			$joomla->logout();

			$link = null;
			if ($this->config['jfusion_phpbbext_redirect_logout']) {
				//redirect to prevent fatal errors on some servers
				$uri = \JURI::getInstance();
				//remove sid from URL
				$query = $uri->getQuery(true);
				if (isset($query['sid'])) {
					unset($query['sid']);
				}
				$uri->setQuery($query);
				//add a variable to ensure refresh
				$link = $uri->toString();
			}
/**
TODO: still needed?
			//recreate phpBB database connection
			$dbhost = $dbuser = $dbpasswd = $dbname = $dbport = null;
			include $this->phpbb_root_path . 'config.' . $this->php_ext;
			$this->db->sql_connect($dbhost, $dbuser, $dbpasswd, $dbname, $dbport, false, false);
			unset($dbpasswd);
*/
			if ($this->config['jfusion_phpbbext_redirect_logout']) {
				//clear the session
				if ($data['user_id'] != ANONYMOUS) {
					// Delete existing session, update last visit info first!
					if (!isset($data['session_time'])) {
						$data['session_time'] = time();
					}

					$sql = 'UPDATE ' . USERS_TABLE . '
                    SET user_lastvisit = ' . (int) $data['session_time'] . '
                    WHERE user_id = ' . (int) $data['user_id'];
					$this->db->sql_query($sql);

					if ($this->user->cookie_data['k']) {
						$sql = 'DELETE FROM ' . SESSIONS_KEYS_TABLE . '
                        WHERE user_id = ' . (int) $data['user_id'] . "
                            AND key_id = '" . $this->db->sql_escape(md5($this->user->cookie_data['k'])) . "'";
						$this->db->sql_query($sql);
					}

					// Reset the data array
					$data = array();

					$sql = 'SELECT *
                    FROM ' . USERS_TABLE . '
                    WHERE user_id = ' . ANONYMOUS;
					$result = $this->db->sql_query($sql);
					$data = $this->db->sql_fetchrow($result);
					$this->db->sql_freeresult($result);
				}

				$cookie_expire = $this->user->time_now - 31536000;
				$this->user->set_cookie('u', '', $cookie_expire);
				$this->user->set_cookie('k', '', $cookie_expire);
				$this->user->set_cookie('sid', '', $cookie_expire);
				unset($cookie_expire);

				header('Location: ' . $link);
				exit();
			}
			//backup phpbb globals
			$joomla->restoreGlobal();
			$this->request->disable_super_globals();
		}
	}

	/**
	 * @return \JFusionAPIInternal
	 */
	function startJoomla() {
		define('_JFUSIONAPI_INTERNAL', true);
		$apipath = $this->config['jfusion_phpbbext_apipath'];
		require_once $apipath . DIRECTORY_SEPARATOR  . 'jfusionapi.php';
		return \JFusionAPIInternal::getInstance();
	}
}
