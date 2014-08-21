<?php

/**
 * file containing administrator function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage WordPress
 * @author     JFusion Team- Henk Wevers <webmaster@jfusion.org>
 * @copyright  2014 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');


/**
 * JFusion Admin Class for Wordpress 2+
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage WordPress
 * @author     JFusion Team - Henk Wevers <webmaster@jfusion.org>
 * @copyright  2014 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionAdmin_wordpress extends JFusionAdmin {
   /**
    * @var $helper JFusionHelper_wordpress
    */
   var $helper;

   /**
    * returns the name of this JFusion plugin
    * @return string name of current JFusion plugin
    */
   function getJname() {
      return 'wordpress';
   }

   /**
    * @return string
    */
   function getTablename() {
      return 'users';
   }

   /**
    * @param JDatabaseDriver $db
    * @param string $database_prefix
    * @return array
    */
   function getUsergroupListWPA($db, $database_prefix) {
      $query = $db->getQuery(true)
         ->select('option_value')
         ->from('#__options')
         ->where('option_name = ' . $db->quote($database_prefix . 'user_roles'));

      $db->setQuery($query);
      $roles_ser = $db->loadResult();
      $roles = unserialize($roles_ser);
      $keys = array_keys($roles);
      $usergroups = array();
      $count = count($keys);
      for ($i = 0; $i < $count; $i++) {
         $group = new stdClass;
         $group->id = $i;
         $group->name = $keys[$i];
         $usergroups[$i] = $group;
      }
      return $usergroups;
   }

   /**
    * @param string $softwarePath
    *
    * @return array|bool
    */
   function setupFromPath($softwarePath) {
      $myfile = $softwarePath . 'wp-config.php';

      $params = array();
      $lines = $this->readFile($myfile);
      if ($lines === false) {
         JFusionFunction::raiseWarning(JText::_('WIZARD_FAILURE') . ': ' . $myfile . ' ' . JText::_('WIZARD_MANUAL'), $this->getJname());
         return false;
      } else {
         //parse the file line by line to get only the config variables
         //			$file_handle = fopen($myfile, 'r');
         $table_prefix = '';
         foreach ($lines as $line) {
            if (strpos(trim($line), 'define') === 0) {
               eval($line);
            }
            if (strpos(trim($line), '$table_prefix') === 0) {
               eval($line);
            }
         }

         //save the parameters into array
         $params['database_host'] = DB_HOST;
         $params['database_name'] = DB_NAME;
         $params['database_user'] = DB_USER;
         $params['database_password'] = DB_PASSWORD;
         $params['database_prefix'] = $table_prefix;
         $params['database_type'] = 'mysql';
         $params['source_path'] = $softwarePath;
         $params['database_charset'] = DB_CHARSET;
         $driver = 'mysql';
         $options = array('driver' => $driver, 'host' => $params['database_host'], 'user' => $params['database_user'],
            'password' => $params['database_password'], 'database' => $params['database_name'],
            'prefix' => $params['database_prefix']);
         $db = JDatabaseDriver::getInstance($options);

         //Find the url to Wordpress
         $query = $db->getQuery(true)
            ->select('option_value')
            ->from('#__options')
            ->where('option_name = ' . $db->quote('siteurl'));

         $db->setQuery($query);
         $siteurlraw = $db->loadResult();
         $params['source_url'] = $siteurlraw;
         if (substr($params['source_url'], -1) != '/') {
            //no slashes found, we need to add one
            $params['source_url'] .= '/';
         }

         //Find the url to Wordpress
         $query = $db->getQuery(true)
            ->select('option_value')
            ->from('#__options')
            ->where('option_name = ' . $db->quote('home'));

         $db->setQuery($query);
         $home = $db->loadResult();


         if (!defined('COOKIEHASH')) {
            if ($siteurlraw)
               define('COOKIEHASH', md5($siteurlraw));
            else
               define('COOKIEHASH', '');
         }
         /**
          * @since 2.0.0
          */
         if (!defined('USER_COOKIE'))
            define('USER_COOKIE', 'wordpressuser_' . COOKIEHASH);

         /**
          * @since 2.0.0
          */
         if (!defined('PASS_COOKIE'))
            define('PASS_COOKIE', 'wordpresspass_' . COOKIEHASH);

         /**
          * @since 2.5.0
          */
         if (!defined('AUTH_COOKIE'))
            define('AUTH_COOKIE', 'wordpress_' . COOKIEHASH);

         /**
          * @since 2.6.0
          */
         if (!defined('SECURE_AUTH_COOKIE'))
            define('SECURE_AUTH_COOKIE', 'wordpress_sec_' . COOKIEHASH);

         /**
          * @since 2.6.0
          */
         if (!defined('C'))
            define('LOGGED_IN_COOKIE', 'wordpress_logged_in_' . COOKIEHASH);

         /**
          * @since 2.3.0
          */
         if (!defined('TEST_COOKIE'))
            define('TEST_COOKIE', 'wordpress_test_cookie');

         /**
          * @since 1.2.0
          */
         if (!defined('COOKIEPATH'))
            define('COOKIEPATH', preg_replace('|https?://[^/]+|i', '', $home . '/'));

         /**
          * @since 1.5.0
          */
         if (!defined('SITECOOKIEPATH'))
            define('SITECOOKIEPATH', preg_replace('|https?://[^/]+|i', '', $siteurlraw . '/'));

         /**
          * @since 2.6.0
          */
         if (!defined('ADMIN_COOKIE_PATH'))
            define('ADMIN_COOKIE_PATH', SITECOOKIEPATH . 'wp-admin');

         if (!defined('WP_CONTENT_URL'))
            define('WP_CONTENT_URL', $siteurlraw . '/wp-content');

         if (!defined('WP_PLUGIN_URL'))
            define('WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins'); // full url, no trailing slash

         if (!defined('PLUGINS_COOKIE_PATH'))
            define('PLUGINS_COOKIE_PATH', preg_replace('|https?://[^/]+|i', '', WP_PLUGIN_URL));

         /**
          * @since 2.0.0
          */
         if (!defined('COOKIE_DOMAIN'))
            define('COOKIE_DOMAIN', false);

         $cookie_stuff = array();
         $cookie_stuff['COOKIEHASH'] = COOKIEHASH;
         $cookie_stuff['USER_COOKIE'] = USER_COOKIE;
         $cookie_stuff['PASS_COOKIE'] = PASS_COOKIE;
         $cookie_stuff['AUTH_COOKIE'] = AUTH_COOKIE;
         $cookie_stuff['SECURE_AUTH_COOKIE'] = SECURE_AUTH_COOKIE;
         $cookie_stuff['LOGGED_IN_COOKIE'] = LOGGED_IN_COOKIE;
         $cookie_stuff['TEST_COOKIE'] = TEST_COOKIE;
         $cookie_stuff['COOKIEPATH'] = COOKIEPATH;
         $cookie_stuff['SITECOOKIEPATH'] = SITECOOKIEPATH;
         $cookie_stuff['ADMIN_COOKIE_PATH'] = ADMIN_COOKIE_PATH;
         $cookie_stuff['PLUGINS_COOKIE_PATH'] = PLUGINS_COOKIE_PATH;
         $cookie_stuff['COOKIE_DOMAIN'] = COOKIE_DOMAIN;

         $cookie_stuff = serialize($cookie_stuff);

         $params['cookie_stuff'] = $cookie_stuff;
         $params['cookie_path'] = COOKIEPATH;
         $params['cookie_domain'] = COOKIE_DOMAIN;

         /* DEFAULT ROLE IS DETERMINED BY THE USERGROUP SETTINGS IN JFUSION!
                  // now get the default usergroup
                  // Cannot userery = $db->getQuery(true)
                       ->select('option_value')
                       ->from('#__options')
                       ->where('option_name = ' . $db->quote('default_role'));

                  $db->setQuery($query);
                  $default_role = $db->loadResult();
               }
         */

      }
      return $params;
   }

   /**
    * Returns the a list of users of the integrated software
    *
    * @param int $limitstart start at
    * @param int $limit number of results
    *
    * @return array
    */
   function getUserList($limitstart = 0, $limit = 0) {
      try {
         //getting the connection to the db
         $db = JFusionFactory::getDatabase($this->getJname());

         $query = $db->getQuery(true)
            ->select('user_login as username, user_email as email')
            ->from('#__users');

         $db->setQuery($query, $limitstart, $limit);

         //getting the results
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
            ->from('#__users');

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
      $usergroups = $this->helper->getUsergroupListWP();
      return $usergroups;
   }

   /**
    * @return string|array
    */
   function getDefaultUsergroup() {
      $usergroups = JFusionFunction::getUserGroups($this->getJname(), true);
      if ($usergroups !== null) {
         $group = array();
         foreach ($usergroups as $usergroup) {
            $group[] = $this->helper->getUsergroupNameWP($usergroup);
         }
      } else {
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
            ->select('option_value')
            ->from('#__options')
            ->where('option_name = ' . $db->quote('users_can_register'));

         $db->setQuery($query);
         $auths = $db->loadResult();

         $result = ($auths == '1');
      } catch (Exception $e) {
         JFusionFunction::raiseError($e, $this->getJname());
      }
      return $result;
   }


   /**
    * @return bool
    */
   function allowEmptyCookiePath() {
      return true;
   }

   /**
    * @return bool
    */
   function allowEmptyCookieDomain() {
      return true;
   }

   /**
    * do plugin support multi usergroups
    *
    * @return string UNKNOWN or JNO or JYES or ???
    */
   function requireFileAccess() {
      return 'JNO';
   }

   /**
    * do plugin support multi usergroups
    *
    * @return bool
    */
   function isMultiGroup() {
      return true;
   }
}
