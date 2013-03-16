<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

/**
 * @ignore
 */
if (!defined('IN_PHPBB')) {
    exit;
}


//options
define('FORCE_REDIRECT_AFTER_LOGIN', 0);  //set this to one if you are getting a blank page after the user logs in
define('FORCE_REDIRECT_AFTER_LOGOUT', 0);  //set this to one if you are getting a blank page after the user logs out


/**
 * Login function
 *
 * @param string &$username
 * @param string &$password
 *
 * @return array
 */
function login_jfusion(&$username, &$password) {
    require_once 'auth_db.php';
    $result = login_db($username, $password);

    if (defined('ADMIN_START')){
        global $phpbb_root_path, $phpEx, $db, $user;
        $redirect = request_var('redirect', "{$phpbb_root_path}adm/index.$phpEx");
    }

    if (!defined('ADMIN_START')) {
        //check to see if login successful and jFusion is not active
        //backup phpbb globals
        jfusion_backup_restore_globals('backup');

        global $JFusionActive, $phpbb_root_path, $phpEx, $db, $user;
        if ($result['status'] == LOGIN_SUCCESS && empty($JFusionActive)) {
            $mainframe = startJoomla();
            //define that the phpBB3 JFusion plugin needs to be excluded
            global $JFusionActivePlugin;
            $JFusionActivePlugin ='JFUSION_JNAME';
            // do the login
            $credentials = array('username' => $username, 'password' => $password);
            $options = array('entry_url' => JURI::root() . 'index.php?option=com_user&task=login', 'silent' => true);

            //detect if the session should be remembered
            if (!empty($_POST['autologin'])) {
                $options['remember'] = 1;
            } else {
                $options['remember'] = 0;
            }

            $mainframe->login($credentials, $options);

            //clean up the joomla session object before continuing
            $session = JFactory::getSession();
            $id = $session->getId();
            $session_data = session_encode();
            $session->close();

            //if we are not frameless, then we need to manually update the session data as on some servers, this data is getting corrupted
            //by php session_write_close and thus the user is not logged into Joomla.  php bug?
            if (!defined('IN_JOOMLA')) {
                /**
                 * @ignore
                 * @var $session_table JTableSession
                 */
                $session_table = JTable::getInstance('session');
                if ($session_table->load($id)) {
                    $session_table->data = $session_data;
                    $session_table->store();
                } else {
                    // if load failed then we assume that it is because
                    // the session doesn't exist in the database
                    // therefore we use insert instead of store
                    $app = JFactory::getApplication();
                    $session_table->data = $session_data;
                    $session_table->insert($id, $app->getClientId());
                }
            }


            if (FORCE_REDIRECT_AFTER_LOGIN) {
                if (isset($_REQUEST['redirect']) && defined('IN_JOOMLA')) {
                    $itemid = JRequest::getInt('Itemid');
                    $url = JFusionFunction::getPluginURL($itemid, false);
                    $redirect = str_replace('./', '', $_REQUEST['redirect']);
                    if (strpos($redirect, 'mode=login') !== false) {
                        $redirect = 'index.php';
                    }
                    $redirect = str_replace('?', '&', $redirect);
                    $redirect = $url . "&jfile=" . $redirect;
                } else {
                        //redirect to prevent fatal errors on some servers
                        $uri = JURI::getInstance();
                        //remove sid from URL
                        $query = $uri->getQuery(true);
                        if (isset($query['sid'])) {
                            unset($query['sid']);
                        }
                        $uri->setQuery($query);
                        //add a variable to ensure refresh
                        $redirect = $uri->toString();
                }

                //recreate phpBB database connection
                $dbhost = $dbuser = $dbpasswd = $dbname = $dbport = null;
                include $phpbb_root_path . 'config.' . $phpEx;
                $db->sql_connect($dbhost, $dbuser, $dbpasswd, $dbname, $dbport, false, false);
                unset($dbpasswd);

                //create phpBB user session
                $user->session_create($result['user_row']['user_id'], 0, $options['remember']);

                $url = str_replace('&amp;', '&', $redirect);

                header("Location: $url");
                exit();
            } else {
                //recreate phpBB database connection
                $dbhost = $dbuser = $dbpasswd = $dbname = $dbport = null;
                include $phpbb_root_path . 'config.' . $phpEx;
                $db->sql_connect($dbhost, $dbuser, $dbpasswd, $dbname, $dbport, false, false);
                unset($dbpasswd);
            }
        }

        //backup phpbb globals
        jfusion_backup_restore_globals('restore');
    }
    return $result;
}

/**
 * @param $data
 */
function logout_jfusion(&$data) {
    //check to see if JFusion is not active
    global $JFusionActive, $db, $user, $phpbb_root_path, $phpEx;
    if (empty($JFusionActive)) {
        //backup phpbb globals
        jfusion_backup_restore_globals('backup');

        //define that the phpBB3 JFusion plugin needs to be excluded
        global $JFusionActivePlugin;
        $JFusionActivePlugin ='JFUSION_JNAME';
        $mainframe = startJoomla();
        // logout any joomla users
        $mainframe->logout();

        // clean up session
        $session = JFactory::getSession();
        $session->close();

        $link = null;
        if (FORCE_REDIRECT_AFTER_LOGOUT) {
            //redirect to prevent fatal errors on some servers
            $uri = JURI::getInstance();
            //remove sid from URL
            $query = $uri->getQuery(true);
            if (isset($query['sid'])) {
                unset($query['sid']);
            }
            $uri->setQuery($query);
            //add a variable to ensure refresh
            $link = $uri->toString();
        }

        //recreate phpBB database connection
        $dbhost = $dbuser = $dbpasswd = $dbname = $dbport = null;
        include $phpbb_root_path . 'config.' . $phpEx;
        $db->sql_connect($dbhost, $dbuser, $dbpasswd, $dbname, $dbport, false, false);
        unset($dbpasswd);

        if (FORCE_REDIRECT_AFTER_LOGOUT) {
            //clear the session
            if ($data['user_id'] != ANONYMOUS)
            {
                // Delete existing session, update last visit info first!
                if (!isset($data['session_time']))
                {
                    $data['session_time'] = time();
                }

                $sql = 'UPDATE ' . USERS_TABLE . '
                    SET user_lastvisit = ' . (int) $data['session_time'] . '
                    WHERE user_id = ' . (int) $data['user_id'];
                $db->sql_query($sql);

                if ($user->cookie_data['k'])
                {
                    $sql = 'DELETE FROM ' . SESSIONS_KEYS_TABLE . '
                        WHERE user_id = ' . (int) $data['user_id'] . "
                            AND key_id = '" . $db->sql_escape(md5($user->cookie_data['k'])) . "'";
                    $db->sql_query($sql);
                }

                // Reset the data array
                $data = array();

                $sql = 'SELECT *
                    FROM ' . USERS_TABLE . '
                    WHERE user_id = ' . ANONYMOUS;
                $result = $db->sql_query($sql);
                $data = $db->sql_fetchrow($result);
                $db->sql_freeresult($result);
            }

            $cookie_expire = $user->time_now - 31536000;
            $user->set_cookie('u', '', $cookie_expire);
            $user->set_cookie('k', '', $cookie_expire);
            $user->set_cookie('sid', '', $cookie_expire);
            unset($cookie_expire);

            header("Location: $link");
            exit();
        }
        //backup phpbb globals
        jfusion_backup_restore_globals('restore');
    }
}

/**
 * @return JApplication
 */
function startJoomla() {
    define('_JFUSIONAPI_INTERNAL', true);
    require_once 'JFUSION_PATH' . DIRECTORY_SEPARATOR  . 'jfusionapi.php';
    $mainframe = JFusionAPIInternal::startJoomla();
    return $mainframe;
}

/**
 * @param $action
 */
function jfusion_backup_restore_globals($action) {
    static $phpbb_globals;

    if (!is_array($phpbb_globals)) {
        $phpbb_globals = array();
    }

    if ($action == 'backup') {
        foreach ($GLOBALS as $n => $v) {
            $phpbb_globals[$n] = $v;
        }
    } else {
        foreach ($phpbb_globals as $n => $v) {
            $GLOBALS[$n] = $v;
        }
    }
}