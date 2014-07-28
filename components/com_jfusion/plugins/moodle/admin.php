<?php namespace JFusion\Plugins\moodle;

/**
 * file containing administrator function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Moodle
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
use Exception;
use JFile;
use JFolder;
use JFusion\Archive\Tar;
use JFusion\Factory;
use JFusion\Framework;
use Joomla\Archive\Archive;
use Joomla\Language\Text;
use JFusion\Plugin\Plugin_Admin;
use Psr\Log\LogLevel;
use RuntimeException;
use SimpleXMLElement;
use stdClass;

defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Admin Class for Moodle 1.8+
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Moodle
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class Admin extends Plugin_Admin
{
    /**
     * @return string
     */
    function getTablename()
    {
        return 'user';
    }

    /**
     * @param string $softwarePath
     * @return array
     */
    function setupFromPath($softwarePath)
    {
        $myfile = $softwarePath . 'config.php';

        $params = array();
        $lines = $this->readFile($myfile);
        if ($lines === false) {
            Framework::raise(LogLevel::WARNING, Text::_('WIZARD_FAILURE') . ': ' . $myfile . ' ' . Text::_('WIZARD_MANUAL'), $this->getJname());
            return false;
        } else {
            //parse the file line by line to get only the config variables
            $CFG = new stdClass();

            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, '$CFG->') !== false && strpos($line, ';') != 0) {
                    eval($line);
                }
            }

            //save the parameters into array
            $params['database_host'] = $CFG->dbhost;
            $params['database_name'] = $CFG->dbname;
            $params['database_user'] = $CFG->dbuser;
            $params['database_password'] = $CFG->dbpass;
            $params['database_prefix'] = $CFG->prefix;
            $params['database_type'] = $CFG->dbtype;

            if (substr($CFG->dataroot, -1) == '/') {
                $params['dataroot'] = $CFG->dataroot;
            } else {
                //no slashes found, we need to add one
                $params['dataroot'] = $CFG->dataroot . '/';
            }
            if (!empty($CFG->passwordsaltmain)) {
                $params['passwordsaltmain'] = $CFG->passwordsaltmain;
            }
            for ($i = 1; $i <= 20; $i++) { //20 alternative salts should be enough, right?
                $alt = 'passwordsaltalt' . $i;
                if (!empty($CFG->$alt)) {
                    $params[$alt] = $CFG->$alt;
                }
            }
            $params['source_path'] = $softwarePath;
            if (substr($CFG->wwwroot, -1) == '/') {
                $params['source_url'] = $CFG->wwwroot;
            } else {
                //no slashes found, we need to add one
                $params['source_url'] = $CFG->wwwroot . '/';
            }

            //return the parameters so it can be saved permanently
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
    function getUserList($limitstart = 0, $limit = 0)
    {
        try {
            //getting the connection to the db
            $db = Factory::getDatabase($this->getJname());

            $query = $db->getQuery(true)
                ->select('username, email')
                ->from('#__user');

            $db->setQuery($query, $limitstart, $limit);

            //getting the results
            $userlist = $db->loadObjectList();
            return $userlist;
        } catch (Exception $e) {
            Framework::raise(LogLevel::ERROR, $e, $this->getJname());
            return array();
        }
    }

    /**
     * @return int
     */
    function getUserCount()
    {
        try {
            //getting the connection to the db
            $db = Factory::getDatabase($this->getJname());

            $query = $db->getQuery(true)
                ->select('count(*)')
                ->from('#__user');

            $db->setQuery($query);
            //getting the results
            $no_users = $db->loadResult();
        } catch (Exception $e) {
            Framework::raise(LogLevel::ERROR, $e, $this->getJname());
            $no_users = 0;
        }
        return $no_users;
    }

    /**
     * @return array
     */
    function getUsergroupList()
    {
	    //get the connection to the db
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->select('id, shortname as name')
		    ->from('#__role');

	    $db->setQuery($query);
	    //getting the results
	    return $db->loadObjectList();
    }

    /**
     * @return bool
     */
    function allowRegistration()
    {
        $result = false;
        try {
            $db = Factory::getDatabase($this->getJname());

            $query = $db->getQuery(true)
                ->select('value')
                ->from('#__config')
                ->where('name = ' . $db->quote('auth'))
                ->where('value = ' . $db->quote('jfusion'));

            $db->setQuery($query);
            $auths = $db->loadResult();
            if (!empty($auths)) {
                $result = true;
            }
        } catch (Exception $e) {
            Framework::raise(LogLevel::ERROR, $e, $this->getJname());
        }
        return $result;
    }

    /**
     * @return bool
     */
    function allowEmptyCookiePath()
    {
        return true;
    }

    /**
     * @return bool
     */
    function allowEmptyCookieDomain()
    {
        return true;
    }

    /**
     * @return mixed|string
     */
    public function moduleInstallation()
    {
        $jname = $this->getJname();
        try {
            try {
                Factory::getDatabase($jname);
            } catch (Exception $e) {
                throw new RuntimeException(Text::_('MOODLE_CONFIG_FIRST'));
            }

            $source_path = $this->params->get('source_path', '');
            if (!file_exists($source_path . 'admin' . DIRECTORY_SEPARATOR . 'auth.php')) {
                return Text::_('MOODLE_CONFIG_SOURCE_PATH');
            }

            $mod_exists = false;
            if (file_exists($source_path . 'auth' . DIRECTORY_SEPARATOR . 'jfusion' . DIRECTORY_SEPARATOR . 'auth.php')) {
                $mod_exists = true;
            }

            if ($mod_exists) {
                $src = 'components/com_jfusion/images/tick.png';
                $mod = 'uninstallModule';
                $text = Text::_('MODULE_UNINSTALL_BUTTON');
            } else {
                $src = 'components/com_jfusion/images/cross.png';
                $mod = 'installModule';
                $text = Text::_('MODULE_INSTALL_BUTTON');
            }

            $html = <<<HTML
                <div class="button2-left">
                    <div class="blank">
                        <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('{$mod}');">{$text}</a>
                    </div>
                </div>

                <img src="{$src}" style="margin-left:10px;" id="usergroups_img"/>
HTML;
        } catch (Exception $e) {
            $html = $e->getMessage();
        }
        return $html;
    }

    /**
     * @return array
     */
    public function installModule()
    {
        $status = array('error' => array(), 'debug' => array());
        $jname = $this->getJname();
        try {
            $db = Factory::getDatabase($jname);
            $source_path = $this->params->get('source_path');
            jimport('joomla.filesystem.archive');
            jimport('joomla.filesystem.file');

            $archive_filename = 'moodle_module_jfusion.tar.gz';
            $old_chdir = getcwd();
            $src_archive = $src_path = realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'install_module';
            $src_code = $src_archive . DIRECTORY_SEPARATOR . 'source';

            // Create an archive to facilitate the installation into the Moodle installation while extracting
            chdir($src_code);
            $tar = new Tar($archive_filename, 'gz');
            $tar->setErrorHandling(PEAR_ERROR_PRINT);
            $tar->createModify('auth lang', '', '');
            chdir($old_chdir);

	        $archive = new Archive();
            $ret = $archive->extract($src_code . DIRECTORY_SEPARATOR . $archive_filename, $source_path);
            JFile::delete($src_code . DIRECTORY_SEPARATOR . $archive_filename);

            if ($ret) {
                $joomla_baseurl = Factory::getParams('joomla_int')->get('source_url');
                $joomla_source_path = JPATH_ROOT . DIRECTORY_SEPARATOR;

                // now set all relevant parameters in Moodles database
                // do not yet activate!

                $querys = array();
                $querys[] = 'REPLACE INTO #__config_plugins SET plugin = \'auth/jfusion\' , name = \'jf_enabled\', value = \'0\'';
                $querys[] = 'REPLACE INTO #__config_plugins SET plugin = \'auth/jfusion\' , name = \'jf_ismaster\', value = \'0\'';
                $querys[] = 'REPLACE INTO #__config_plugins SET plugin = \'auth/jfusion\' , name = \'jf_fullpath\', value = ' . $db->quote($joomla_source_path);
                $querys[] = 'REPLACE INTO #__config_plugins SET plugin = \'auth/jfusion\' , name = \'jf_baseurl\', value = ' . $db->quote($joomla_baseurl);
                $querys[] = 'REPLACE INTO #__config_plugins SET plugin = \'auth/jfusion\' , name = \'jf_loginpath\', value = \'\'';
                $querys[] = 'REPLACE INTO #__config_plugins SET plugin = \'auth/jfusion\' , name = \'jf_logoutpath\', value = \'\'';
                $querys[] = 'REPLACE INTO #__config_plugins SET plugin = \'auth/jfusion\' , name = \'jf_formid\', value = \'login\'';
                $querys[] = 'REPLACE INTO #__config_plugins SET plugin = \'auth/jfusion\' , name = \'jf_relpath\', value = \'0\'';
                $querys[] = 'REPLACE INTO #__config_plugins SET plugin = \'auth/jfusion\' , name = \'jf_cookiedomain\', value = \'\'';
                $querys[] = 'REPLACE INTO #__config_plugins SET plugin = \'auth/jfusion\' , name = \'jf_cookiepath\', value = \'\'';
                $querys[] = 'REPLACE INTO #__config_plugins SET plugin = \'auth/jfusion\' , name = \'jf_username_id\', value = \'\'';
                $querys[] = 'REPLACE INTO #__config_plugins SET plugin = \'auth/jfusion\' , name = \'jf_password_id\', value = \'\'';
                $querys[] = 'REPLACE INTO #__config_plugins SET plugin = \'auth/jfusion\' , name = \'jf_cookie_secure\', value = \'0\'';
                $querys[] = 'REPLACE INTO #__config_plugins SET plugin = \'auth/jfusion\' , name = \'jf_cookie_httponly\', value = \'0\'';
                $querys[] = 'REPLACE INTO #__config_plugins SET plugin = \'auth/jfusion\' , name = \'jf_verifyhost\', value = \'0\'';
                $querys[] = 'REPLACE INTO #__config_plugins SET plugin = \'auth/jfusion\' , name = \'jf_leavealone\', value = \'\'';
                $querys[] = 'REPLACE INTO #__config_plugins SET plugin = \'auth/jfusion\' , name = \'jf_expires\', value = \'1800\'';
                foreach ($querys as $query) {
                    $db->setQuery($query);
                    $db->execute();
                }
                $status['message'] = $jname . ': ' . Text::_('INSTALL_MODULE_SUCCESS');
            } else {
                throw new RuntimeException(Text::sprintf('INSTALL_MODULE_ERROR', $src_archive, $source_path));
            }
        } catch (Exception $e) {
            $status['error'] = $jname . ': ' . $e->getMessage();
        }
        return $status;
    }

    /**
     * @return array
     */
    public function uninstallModule()
    {
        $status = array('error' => array(), 'debug' => array());
        try {
            jimport('joomla.filesystem.file');
            jimport('joomla.filesystem.folder');

            $jname = $this->getJname();
            $db = Factory::getDatabase($jname);
            $source_path = $this->params->get('source_path');
            $xmlfile = realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'install_module' . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR . 'listfiles.xml';

            $listfiles = Framework::getXml($xmlfile);
            $files = $listfiles->file;

            /**
             * @ignore
             * @var $file SimpleXMLElement
             */
            foreach ($files as $file) {
                $file = (string)$file;
                $file = preg_replace('#/#', DIRECTORY_SEPARATOR, $file);
                @chmod($source_path . $file, 0777);
                if (!is_dir($source_path . $file)) {
                    JFile::delete($source_path . $file);
                } else {
                    JFolder::delete($source_path . $file);
                }
            }
            $querys = array();
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_enabled'));
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_ismaster'));
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_fullpath'));
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_baseurl'));
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_loginpath'));
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_logoutpath'));
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_formid'));
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_relpath'));
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_cookiedomain'));
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_cookiepath'));
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_username_id'));
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_password_id'));
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_cookie_secure'));
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_cookie_httponly'));
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_verifyhost'));
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_leavealone'));
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_expires'));
            $querys[] = $db->getQuery(true)
                ->delete('#__config_plugins')
                ->where('plugin = ' . $db->quote('auth/jfusion'))
                ->where('name = ' . $db->quote('jf_expires'));

            foreach ($querys as $query) {
                $db->setQuery($query);
                $db->execute();
            }

            $status['message'] = $jname . ': ' . Text::_('UNINSTALL_MODULE_SUCCESS');

            // remove jfusion as active plugin
            $query = $db->getQuery(true)
                ->select('value')
                ->from('#__config')
                ->where('name = ' . $db->quote('auth'));

            $db->setQuery($query);
            $value = $db->loadResult();
            $auths = explode(',', $value);
            $key = array_search('jfusion', $auths);
            if ($key !== false) {
                $authstr = $auths[0];
                for ($i = 1; $i <= (count($auths) - 1); $i++) {
                    if ($auths[$i] != 'jfusion') {
                        $authstr .= ',' . $auths[$i];
                    }
                }

            }
        } catch (Exception $e) {
            $status['error'] = $e->getMessage();
        }
        return $status;
    }

    /**
     * @return mixed|string
     */
    public function moduleActivation()
    {
        $html = Text::_('MOODLE_CONFIG_FIRST');
        try {
            $jname = $this->getJname();
            $db = Factory::getDatabase($jname);

            $source_path = $this->params->get('source_path');
            $jfusion_auth = $source_path . 'auth' . DIRECTORY_SEPARATOR . 'jfusion' . DIRECTORY_SEPARATOR . 'auth.php';
            if (file_exists($jfusion_auth)) {
                // find out if jfusion is listed in the active auth plugins
                $query = $db->getQuery(true)
                    ->select('value')
                    ->from('#__config')
                    ->where('name = ' . $db->quote('auth'));

                $db->setQuery($query);
                $value = $db->loadResult();
                if (stripos($value, 'jfusion') !== false) {
                    // now find out if we have enabled the plugin
                    $query = $db->getQuery(true)
                        ->select('value')
                        ->from('#__config_plugins')
                        ->where('plugin = ' . $db->quote('auth/jfusion'))
                        ->where('name = ' . $db->quote('jf_enabled'));

                    $db->setQuery($query);
                    $value = $db->loadResult();
                    if ($value == '1') {
                        $activated = 1;
                    } else {
                        $activated = 0;
                    }
                } else {
                    $activated = 0;
                }

                if ($activated) {
                    $src = 'components/com_jfusion/images/tick.png';
                    $text = Text::_('MODULE_DEACTIVATION_BUTTON');
                } else {
                    $src = 'components/com_jfusion/images/cross.png';
                    $text = Text::_('MODULE_ACTIVATION_BUTTON');
                }

                $html = <<<HTML
			    <div class="button2-left">
			        <div class="blank">
			            <a href="javascript:void(0);"  onclick="return JFusion.Plugin.module('activateModule');">{$text}</a>
			        </div>
			    </div>
			    <input type="hidden" name="activation" id="activation" value="{$activated}"/>

			    <img src="{$src}" style="margin-left:10px;"/>
HTML;
            }
        } catch (Exception $e) {
            Framework::raise(LogLevel::ERROR, $e, $this->getJname());
        }
        return $html;
    }

    /**
     * @return array|bool
     */
    public function activateModule()
    {
        try {
            $jname = $this->getJname();
            $db = Factory::getDatabase($jname);

            $activation = ((Factory::getApplication()->input->get('activation', 1)) ? 'true' : 'false');
            if ($activation == 'true') {
                $query = $db->getQuery(true)
                    ->update('#__config_plugins')
                    ->set('value = 1')
                    ->where('plugin = ' . $db->quote('auth/jfusion'))
                    ->where('name = ' . $db->quote('jf_enabled'));

                $db->setQuery($query);
                $db->execute();

                // add jfusion plugin jfusion as active plugin
                $query = $db->getQuery(true)
                    ->select('value')
                    ->from('#__config')
                    ->where('name = ' . $db->quote('auth'));

                $db->setQuery($query);

                $value = $db->loadResult();
                $auths = explode(',', $value);

                $key = array_search('jfusion', $auths);

                if ($key !== false) {
                    // already enabled ?!
                    throw new RuntimeException('key already enabled?');
                }
                $value .= ',jfusion';

                $query = $db->getQuery(true)
                    ->update('#__config')
                    ->set('value = ' . $db->quote($value))
                    ->where('name = ' . $db->quote('auth'));

                $db->setQuery($query);
                $db->execute();
            } else {
                $query = $db->getQuery(true)
                    ->update('#__config_plugins')
                    ->set('value = 0')
                    ->where('plugin = ' . $db->quote('auth/jfusion'))
                    ->where('name = ' . $db->quote('jf_enabled'));

                $db->setQuery($query);
                $db->execute();

                // remove jfusion as active plugin
                $query = $db->getQuery(true)
                    ->select('value')
                    ->from('#__config')
                    ->where('name = ' . $db->quote('auth'));

                $db->setQuery($query);
                $value = $db->loadResult();
                $auths = explode(',', $value);
                $key = array_search('jfusion', $auths);
                if ($key !== false) {
                    $authstr = $auths[0];
                    for ($i = 1; $i <= (count($auths) - 1); $i++) {
                        if ($auths[$i] != 'jfusion') {
                            $authstr .= ',' . $auths[$i];
                        }
                    }

                    $query = $db->getQuery(true)
                        ->update('#__config')
                        ->set('value = ' . $db->quote($authstr))
                        ->where('name = ' . $db->quote('auth'));

                    $db->setQuery($query);
                    $db->execute();
                }
            }
        } catch (Exception $e) {
            $status = array('error' => $e->getMessage());
            return $status;
        }
        return false;
    }

    /**
     * do plugin support multi usergroups
     *
     * @return string UNKNOWN or JNO or JYES or ??
     */
    function requireFileAccess()
    {
        return 'JNO';
    }

    /**
     * do plugin support multi usergroups
     *
     * @return bool
     */
    function isMultiGroup()
    {
        return false;
    }
}