<?php

/**
 * installer model
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
 * Require the Joomla Installer model
 */
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_installer' . DS . 'models' . DS . 'install.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusion.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'defines.php';
jimport('joomla.installer.helper');

/**
 * Class to manage plugin install in JFusion
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionModelInstaller extends InstallerModelInstall
{
    /** @var object JTable object */
    var $_table = null;
    /** @var object JTable object */
    var $_url = null;
    /**
     * Overridden constructor
     *
     * @access    protected
     */
    function __construct()
    {
        // Load the language file
        $lang = JFactory::getLanguage();
        $lang->load('com_installer');
        parent::__construct();
    }

    /**
     * Replaces original Install() method.
     *
     * @return array|bool Result of the JFusion plugin install
     */
    function install()
    {
    	$result = array();
    	$result['status'] = false;
        $this->setState('action', 'install');
        $package = null;
        switch (JRequest::getWord('installtype')) {
            case 'folder':
                $package = $this->_getPackageFromFolder();
                break;
            case 'upload':
                $package = $this->_getPackageFromUpload();
                break;
            case 'url':
                $package = $this->_getPackageFromUrl();
                break;
            default:
                $this->setState('message', JText::_('NO_INSTALL_TYPE'));
                $result['message'] = JText::_('NO_INSTALL_TYPE');
                break;
        }
        if (!isset($result['message'])) {
            // Was the package unpacked?
            if (!$package) {
                $this->setState('message', JText::_('NO_PACKAGE_FOUND'));
                $result['message'] = JText::_('NO_PACKAGE_FOUND');
            } else {
                // custom installer
                $installer = new JfusionPluginInstaller($this);

                // Install the package
                $installer->install($package['dir'], $result);

                // Cleanup the install files
                if (!is_file($package['packagefile'])) {
                    $config = JFactory::getConfig();
                    $package['packagefile'] = $config->getValue('config.tmp_path') . DS . $package['packagefile'];
                }
                if ( $result['status'] && is_file($package['packagefile']) ) {
                    //save a copy of the plugin for safe keeping
                    $dest = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'packages' . DS . JFile::getName($package['packagefile']);
                    if ( $package['packagefile'] != $dest) {
                        JFile::copy($package['packagefile'],$dest);
                    }
                }

                JInstallerHelper::cleanupInstall($package['packagefile'], $package['extractdir']);
            }
        }
        //return the results array
        return $result;
    }

    /**
     * Replaces original Install() method.
     *
     * @param string $filename
     *
     * @return true|false Result of the JFusion plugin install
     */
    function installZIP($filename)
    {
    	$result = array();
        $mainframe = JFactory::getApplication();
        $this->setState('action', 'install');

        // custom installer
        $installer = new JfusionPluginInstaller($this);

        $package = JInstallerHelper::unpack($filename);

        // Install the package
        $installer->install($package['dir'], $result);

        // Cleanup the install files
        if (!is_file($package['packagefile'])) {
            $config = JFactory::getConfig();
            $package['packagefile'] = $config->getValue('config.tmp_path') . DS . $package['packagefile'];
        }
       // JInstallerHelper::cleanupInstall($package['packagefile'], $package['extractdir']);

        //return the results array
        return $result;
    }

    /**
     * Installer class for JFusion plugins
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return true|false Result of the JFusion plugin uninstall
     */
    function uninstall($jname)
    {
        $db = JFactory::getDBO();
        $db->setQuery('SELECT id FROM #__jfusion WHERE name =' . $db->Quote($jname));
        $myId = $db->loadResult();
        $result['status'] = false;
        if (!$myId) { 
            $result['message'] = 'JFusion ' . JText::_('PLUGIN') . ' ' . JText::_('UNINSTALL') . ' ' . JText::_('FAILED');
        } else {
            $installer = new JfusionPluginInstaller($this);
            // Install the package
            $result = $installer->uninstall($jname);
        }
        return $result;
    }

    /**
     * Copy function for JFusion plugins
     *
     * @param string $jname     name of the JFusion plugin used
     * @param string $new_jname name of the new plugin
     * @param bool $update    update existing plugin
     *
     * @return true|false Result of the JFusion plugin uninstall
     */
    function copy($jname, $new_jname, $update = false)
    {
        $db = JFactory::getDBO();
        $db->setQuery('SELECT id FROM #__jfusion WHERE name =' . $db->Quote($jname));
        $myId = $db->loadResult();
        $result['status'] = false;
        if (!$myId) {
            $result['message'] = 'JFusion ' . JText::_('PLUGIN') . ' ' . JText::_('COPY') . ' ' . JText::_('FAILED');
        } else {
            $installer = new JfusionPluginInstaller($this);
            // Install the package
            if (!$installer->copy($jname, $new_jname, $update)) {
                // There was an error installing the package
                $result['message'] = 'JFusion ' . JText::_('PLUGIN') . ' ' . JText::_('COPY') . ' ' . JText::_('FAILED');
            } else {
                // Package installed successfully
                $result['message'] = 'JFusion ' . JText::_('PLUGIN') . ' ' . JText::_('COPY') . ' ' . JText::_('SUCCESS');
                $result['status'] = true;
            }
        }
        return $result;
    }
}
/**
 * Installer class for JFusion plugins
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionPluginInstaller extends JObject
{
    var $manifest;
    /**
     * Overridden constructor
     *
     * @param object &$parent parent object
     *
     * @access    protected
     */
    function __construct(&$parent)
    {
//        $this->parent = JInstaller::getInstance();
        $this->parent = new JInstaller;
        $this->parent->setOverwrite(true);
        $this->filterInput = JFilterInput::getInstance();
    }

    /**
     * handles JFusion plugin installation
     *
     * @param mixed $dir install path
     * @param array &$result
     *
     * @return array
     */
    function install($dir = null, &$result)
    {
        // Get a database connector object
        $db = JFactory::getDBO();
        $result['status'] = false;
        $result['jname'] = null;
        if (!$dir && !JFolder::exists($dir)) {
            $this->parent->abort(JText::_('INSTALL_INVALID_PATH'));
            $result['message'] = JText::_('INSTALL_INVALID_PATH');
        } else {
            $this->parent->setPath('source', $dir);


            // Get the extension manifest object
            $manifest = $this->_getManifest($dir);
            if (is_null($manifest)) {
                $this->parent->abort(JText::_('INSTALL_NOT_VALID_PLUGIN'));
                $result['message'] = JText::_('INSTALL_NOT_VALID_PLUGIN');
            } else {
                $this->manifest = $manifest;

                /**
                 * ---------------------------------------------------------------------------------------------
                 * Manifest Document Setup Section
                 * ---------------------------------------------------------------------------------------------
                 */
                // Set the extensions name
                /**
                 * Check that the plugin is an actual JFusion plugin
                 */
                $name = $this->getElementByPath($this->manifest,'name');
	            $name = $this->filterInput->clean($this->getData($name), 'string');

                $result['jname'] = $name;
                $this->set('name', $name);

                // installation path
                $this->parent->setPath('extension_root', JFUSION_PLUGIN_PATH . DS . $name);
                // get files to copy
                $element = $this->getElementByPath($this->manifest,'files');

                /**
                 * ---------------------------------------------------------------------------------------------
                 * Filesystem Processing Section
                 * ---------------------------------------------------------------------------------------------
                 */

                // If the plugin directory does not exist, lets create it
                $created = false;
                if (!file_exists($this->parent->getPath('extension_root'))) {
                    if (!$created = JFolder::create($this->parent->getPath('extension_root'))) {
                        $msg = JText::_('PLUGIN') . ' ' . JText::_('INSTALL') . ': ' . JText::_('INSTALL_FAILED_DIRECTORY') . ': "' . $this->parent->getPath('extension_root') . '"';
                        $this->parent->abort($msg);
                        $result['message'] = $msg;
                        return $result;
                    }
                }
                /*
                * If we created the plugin directory and will want to remove it if we
                * have to roll back the installation, lets add it to the installation
                * step stack
                */
                if ($created) {
                    $this->parent->pushStep(array('type' => 'folder', 'path' => $this->parent->getPath('extension_root')));
                }
                // Copy all necessary files
                if ($this->parent->parseFiles($element, -1) === false) {
                    // Install failed, roll back changes
                    $this->parent->abort();
                    $result['message'] = JText::_('PLUGIN') . ' ' . $name . ' ' . JText::_('INSTALL') . ': ' . JText::_('FAILED');;
                } else {
                    /**
                     * ---------------------------------------------------------------------------------------------
                     * Language files Processing Section
                     * ---------------------------------------------------------------------------------------------
                     */
	                $languageFolder = $dir. DS.'language';
	                if (JFolder::exists($languageFolder)) {
		                $files = JFolder::files($languageFolder);
		                foreach ($files as $file) {
			                $dest = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'language' . DS . substr($file,0,5);
			                JFolder::create($dest);
			                JFile::copy($languageFolder. DS .$file, $dest . DS . $file);
		                }
	                }

                    /**
                     * ---------------------------------------------------------------------------------------------
                     * Database Processing Section
                     * ---------------------------------------------------------------------------------------------
                     */
                    //determine the features of the plugin
                    $dual_login = $slave = null;
                    //$features = array('master', 'slave', 'dual_login', 'check_encryption', 'activity', 'search', 'discussion');
                    $features = array('master', 'slave', 'dual_login', 'check_encryption');
                    foreach ($features as $f) {
                        $xml = $this->getElementByPath($this->manifest,$f);

	                    if ($xml instanceof JSimpleXMLElement || $xml instanceof JXMLElement) {
		                    $$f = $this->filterInput->clean($this->getData($xml), 'integer');
	                    } elseif ($f == 'master' || $f == 'check_encryption') {
                            $$f = 0;
                        } else {
                            $$f = 3;
                        }
                    }
                    //let's check to see if a plugin with the same name is already installed
                    $db->setQuery('SELECT id, ' . implode(', ', $features) . ' FROM #__jfusion WHERE name = ' . $db->Quote($name));
                    $plugin = $db->loadObject();
                    if (!empty($plugin)) {
                        if (!$this->parent->getOverwrite()) {
                            // Install failed, roll back changes
                            $msg = JText::_('PLUGIN') . ' ' . JText::_('INSTALL') . ': ' . JText::_('PLUGIN') . ' "' . $name . '" ' . JText::_('ALREADY_EXISTS');
                            $this->parent->abort($msg);
                            $result['message'] = $msg;
                            return $result;
                        } else {
                            //enable/disable features and update the plugin files
                            //store enabled/disabled features to update copies
                            global $plugin_features;
                            $plugin_features = array();
                            $plugin_files = $this->backup($name);
                            $query = 'UPDATE #__jfusion SET plugin_files = ' . $db->Quote($plugin_files);
                            foreach ($features as $f) {
                                if (($$f == 3 && $plugin->$f != 3) || ($$f != 3 && $plugin->$f == 3)) {
                                    $query.= ', ' . $f . '=' . $$f;
                                    $plugin_features[$f] = $$f;
                                }
                            }
                            $query.= ' WHERE id = ' . $plugin->id;
                            $db->setQuery($query);
                            $db->query();

                            //set the overwrite tag
                            $result['overwrite'] = 1;
                        }
                    } else {
                        //prepare the variables
                        $result['overwrite'] = 0;
                        $plugin_entry = new stdClass;
                        $plugin_entry->id = null;
                        $plugin_entry->name = $name;
                        $plugin_entry->dual_login = $dual_login;
                        $plugin_entry->slave = $slave;
                        $plugin_entry->plugin_files = $this->backup($name);
                        //now append the new plugin data
                        if (!$db->insertObject('#__jfusion', $plugin_entry, 'id')) {
                            // Install failed, roll back changes
                            $msg = JText::_('PLUGIN') . ' ' . JText::_('INSTALL') . ' ' . JText::_('ERROR') . ': ' . $db->stderr();
                            $this->parent->abort($msg);
                            $result['message'] = $msg;
                            return $result;
                        }
                        $this->parent->pushStep(array('type' => 'plugin', 'id' => $plugin_entry->id));
                    }
                    /**
                     * ---------------------------------------------------------------------------------------------
                     * Finalization and Cleanup Section
                     * ---------------------------------------------------------------------------------------------
                     */

                    //check to see if this is updating a plugin that has been copied
                    $query = 'SELECT name FROM #__jfusion WHERE original_name = '.$db->Quote($name);
                    $db->setQuery($query);
                    $copiedPlugins = $db->loadObjectList();
                    foreach ($copiedPlugins as $plugin) {
                        //update the copied version with the new files
                        $this->copy($name, $plugin->name, true);
                    }
                    if ($result['overwrite'] == 1) {
                        $result['message'] = JText::_('PLUGIN') . ' ' .$name .' ' . JText::_('UPDATE') . ': ' . JText::_('SUCCESS');
                    } else {
                        $result['message'] = JText::_('PLUGIN') . ' ' .$name .' ' . JText::_('INSTALL') . ': ' . JText::_('SUCCESS');
                    }
                    $result['status'] = true;
                }
            }
        }
        return $result;
    }
    /**
     * handles JFusion plugin un-installation
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return boolean
     */
    function uninstall($jname)
    {
    	$result['status'] = false;
        if (JFusionFunction::validPlugin($jname)) {
            //if this plugin had been valid, call its uninstall function if it exists
            $JFusionAdmin = JFusionFactory::getAdmin($jname);
            $result = $JFusionAdmin->uninstall();
            $reason = '';
            if (is_array($result)) {
                $success = $result[0];
                if (is_array($result[1])) {
                    $reason = implode('</li><li>'.$jname . ': ',$result[1]);
                } elseif (!empty($result[1])) {
                    $reason = $result[1];
                }
            } else {
                $success = $jname . ': ' . $result;
            }
            if (!$success) {
                $msg = JText::_('PLUGIN') . ' ' .$jname .' ' . JText::_('UNINSTALL') . ' ' . JText::_('FAILED') . ': ' . $reason;
                $this->parent->abort($msg);
                $result['message'] = $msg;
                return $result;
            }
        }
        $db = JFactory::getDBO();

        $query = 'SELECT name , original_name from #__jfusion WHERE name = ' . $db->Quote($jname);
        $db->setQuery($query);
        $plugin = $db->loadObject();
        $removeLanguage = true;
        if (!$plugin || $plugin->original_name) {
            $removeLanguage = false;
        }

        // delete raw
        $db->setQuery('DELETE FROM #__jfusion WHERE name = ' . $db->Quote($jname));
        if (!$db->query()) {
            $this->parent->abort($db->stderr());
        }
        $db->setQuery('DELETE FROM #__jfusion_discussion_bot WHERE jname = ' . $db->Quote($jname));
        if (!$db->query()) {
            $this->parent->abort($db->stderr());
        }
        $db->setQuery('DELETE FROM #__jfusion_users_plugin WHERE jname = ' . $db->Quote($jname));
        if (!$db->query()) {
            $this->parent->abort($db->stderr());
        }
        $dir = JFUSION_PLUGIN_PATH . DS . $jname;
        if (!$jname || !JFolder::exists($dir)) {
            $this->parent->abort(JText::_('UNINSTALL_ERROR_PATH'));
            $result['message'] = JText::_('UNINSTALL_ERROR_PATH');
        } else {
            /**
             * ---------------------------------------------------------------------------------------------
             * Remove Language files Processing Section
             * ---------------------------------------------------------------------------------------------
             */
            // Get the extension manifest object
            $manifest = $this->_getManifest($dir);
            if (is_null($manifest)) {
                $this->parent->abort(JText::_('INSTALL_NOT_VALID_PLUGIN'));
                $result['message'] = JText::_('INSTALL_NOT_VALID_PLUGIN');
            } else {
                $this->manifest = $manifest;

                if ($removeLanguage) {
	                $languageFolder = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'language';
	                if (JFolder::exists($languageFolder)) {
		                $files = JFolder::files(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'language',  'com_jfusion.plg_'.$jname.'.ini',true);
		                foreach ($files as $file) {
			                $file = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'language' . DS . substr($file,0,5). DS . $file;
			                JFile::delete($file);
		                }
	                }
                }

                // remove files
                if (!JFolder::delete($dir)) {
                    $this->parent->abort(JText::_('UNINSTALL_ERROR_DELETE'));
                    $result['message'] = JText::_('UNINSTALL_ERROR_DELETE');
                } else {
                    //return success
                    $msg = JText::_('PLUGIN') . ' ' .$jname .' ' . JText::_('UNINSTALL') . ': ' . JText::_('SUCCESS');
                    $result['message'] = $msg;
                    $result['status'] = true;
                    $result['jname'] = $jname;
                }
            }
        }
        return $result;
    }
    /**
     * handles copying JFusion plugins
     *
     * @param string  $jname     name of the JFusion plugin used
     * @param string  $new_jname name of the copied plugin
     * @param boolean $update    mark if we updating a copied plugin
     *
     * @return boolean
     */
    function copy($jname, $new_jname, $update = false)
    {
        $dir = JFUSION_PLUGIN_PATH . DS . $jname;
        $new_dir = JFUSION_PLUGIN_PATH . DS . $new_jname;
        $result['status'] = false;
        if (!$jname || !JFolder::exists($dir)) {
            $this->parent->abort(JText::_('COPY_ERROR_PATH'));
            $result['message'] = JText::_('COPY_ERROR_PATH');
        } else if (!JFolder::copy($dir, $new_dir, null, $update)) {
            //copy the files
            $this->parent->abort(JText::_('COPY_ERROR'));
            $result['message'] = JText::_('COPY_ERROR');
        } else {
            // Define our preg arrays
            $regex = array();
            $replace = array();
            //change the classname
            $regex[] = '#JFusion(Auth|User|Forum|Public|Admin|Helper)_' . $jname . '#ms';
            $replace[] = 'JFusion$1_' . $new_jname;
            //change the jname function
            $regex[] = '#return \'' . $jname . '\';#ms';
            $replace[] = 'return \'' . $new_jname . '\';';
            //update the XML name tag
            $regex[] = '#<name>' . $jname . '</name>#ms';
            $replace[] = '<name>' . $new_jname . '</name>';
            /**
             * ---------------------------------------------------------------------------------------------
             * Copy Language files Processing Section
             * @todo - This section may be improved but works actually
             * ---------------------------------------------------------------------------------------------
             */
            $manifest = $this->_getManifest($dir);
            if (is_null($manifest)) {
                $this->parent->abort(JText::_('INSTALL_NOT_VALID_PLUGIN'));
                $result['message'] = JText::_('INSTALL_NOT_VALID_PLUGIN');
            } else {
                $this->manifest = $manifest;
                $childrens = array();
                $path = '';

                /**
                 * ---------------------------------------------------------------------------------------------
                 * Rename class files and xml file of the new plugin create
                 * ---------------------------------------------------------------------------------------------
                 */
                //define which files need parsing
                $parse_files = array($new_dir . DS . 'auth.php', $new_dir . DS . 'admin.php', $new_dir . DS . 'user.php', $new_dir . DS . 'jfusion.xml', $new_dir . DS . 'forum.php', $new_dir . DS . 'public.php', $new_dir . DS . 'helper.php');
                foreach ($parse_files as $parse_file) {
                    if (file_exists($parse_file)) {
                        $file_data = JFile::read($parse_file);
                        $file_data = preg_replace($regex, $replace, $file_data);
                        JFile::write($parse_file, $file_data);
                    }
                }
                $db = JFactory::getDBO();
                if ($update) {
                    //update the copied plugin files
                    $plugin_files = $this->backup($new_jname);
                    $query = 'UPDATE #__jfusion SET plugin_files = ' . $db->Quote($plugin_files);
                    //get the features of the updated plugin
                    global $plugin_features;
                    if (empty($plugin_features)) {
                        //copy() was called directly because we are upgrading the component
                        $features = array('master', 'slave', 'dual_login', 'check_encryption');
                        foreach ($features as $f) {
                            $xml = $this->getElementByPath($this->manifest,$f);
	                        if ($xml instanceof JSimpleXMLElement || $xml instanceof JXMLElement) {
                                $$f = $this->filterInput->clean($this->getData($xml), 'integer');
	                        } elseif ($f == 'master' || $f == 'check_encryption') {
                                $$f = 0;
                            } else {
                                $$f = 3;
                            }
                        }
                        $db->setQuery('SELECT id ' . implode(', ', $features) . ' FROM #__jfusion WHERE name = ' . $db->Quote($new_jname));
                        $plugin = $db->loadObject();
                        if (!empty($plugin)) {
                            //enable/disable features and update the plugin files
                            $plugin_features = array();
                            foreach ($features as $f) {
                                if (($$f == 3 && $plugin->$f != 3) || ($$f != 3 && $plugin->$f == 3)) {
                                    $plugin_features[$f] = $$f;
                                }
                            }
                        } else {
                            $plugin_features = array();
                        }
                    }
                    foreach ($plugin_features as $key => $val) {
                        $query.= ', '.$key.' = '.$val;
                    }
                    $query.= ' WHERE name = ' . $db->Quote($new_jname);
                    $db->setQuery($query);
                    $db->query();
                } else {
                    //add the new entry in the JFusion plugin table
                    $db->setQuery('SELECT * FROM #__jfusion WHERE name = ' . $db->Quote($jname));
                    $plugin_entry = $db->loadObject();
                    $plugin_entry->name = $new_jname;
                    $plugin_entry->id = null;
                    $plugin_entry->master = ($plugin_entry->master == 3) ? 3 : 0;
                    $plugin_entry->slave = ($plugin_entry->slave == 3) ? 3 : 0;
                    $plugin_entry->plugin_files = $this->backup($new_jname);
                    //only change the original name if this is not a copy itself
                    if (empty($plugin_entry->original_name)) {
                        $plugin_entry->original_name = $jname;
                    }
                    if (!$db->insertObject('#__jfusion', $plugin_entry, 'id')) {
                        //return the error
                        $msg = 'Error while creating the plugin: ' . $db->stderr();
                        $this->parent->abort($msg);
                        $result['message'] = $msg;
                        return $result;
                    }
                }
                $result['message'] = JText::_('PLUGIN') . ' ' .$jname .' ' . JText::_('COPY') . ': ' . JText::_('SUCCESS');
                $result['status'] = true;
            }
        }
        return $result;
    }
    /**
     * load manifest file with installation information
     *
     * @param string $dir Directory
     *
     * @return simpleXML|JXMLElement object (or null)
     */
    function _getManifest($dir)
    {
        // Initialize variables

        // TODO: DISCUSS if we should allow flexible naming for installation file
        $file = $dir . DS . 'jfusion.xml';
        $this->parent->setPath('manifest', $file);
        // If we cannot load the xml file return null

		if(JFusionFunction::isJoomlaVersion('1.6')) {
            /**
             *  @ignore
             * @var $xml JXMLElement
             */
			$xml = JFactory::getXML($file);
		} else {
            /**
             * @ignore
             * @var $xml JSimpleXML
             */
        	$xml = JFactory::getXMLParser('Simple');
			if (!$xml->loadFile($file)) {
            	// Free up xml parser memory and return null
            	unset($xml);
                $xml = null;
        	} else {
                $xml = $xml->document;
            }
		}
    	/*
        * Check for a valid XML root tag.
        * @todo: Remove backwards compatibility in a future version
        * Should be 'install', but for backward compatibility we will accept 'mosinstall'.
        */
		if (!is_object($xml) || ($xml->name() != 'install' && $xml->name() != 'mosinstall')) {
            // Free up xml parser memory and return null
            unset($xml);
            $xml = null;
        } else {
            /**
             * Check that the plugin is an actual JFusion plugin
             */
            $type = $this->getAttribute($xml,'type');

            if ($type!=='jfusion') {
                //Free up xml parser memory and return null
                unset ($xml);
                $xml = null;
            }
        }

        // Valid manifest file return the object
        return $xml;
    }
    /**
     * handles JFusion plugin backups
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return backup zip file data or location
     */
    function backup($jname)
    {
        $config = JFactory::getConfig();
        $tmpDir = $config->getValue('config.tmp_path');
        //compress the files
        $filename = $tmpDir . DS . $jname . '.zip';
        //retrieve a list of files within the plugin directory
        $pluginPath = JFUSION_PLUGIN_PATH . DS . $jname;
        //check for zip creation
        $zipSuccess = false;
        //we need to chdir into the plugin path
        $cwd = getcwd();
        chdir($pluginPath);
        //get the contents of the files in the plugin dir
        $filesArray = $this->getFiles($pluginPath, $jname);
        if (extension_loaded('zlib')) {
            //use Joomla zip class to create the zip
            /**
             * @ignore
             * @var $zip JArchiveZip
             */
            $zip = JArchive::getAdapter('zip');
            if ($zip->create($filename, $filesArray)) {
                $zipSuccess = true;
            }
        } elseif (class_exists('ZipArchive')) {
            //use PECL ZipArchive to create the zip
            $zip = new ZipArchive();
            if ($zip->open($filename, ZIPARCHIVE::CREATE) === true) {
                foreach ($filesArray as $file) {
                    $zip->addFromString($file['name'], $file['data']);
                }
                $zip->close();
                $zipSuccess = true;
            }
        }
        chdir($cwd);
        $data = ($zipSuccess && file_exists($filename)) ? @file_get_contents($filename) : '';
	    JFile::delete($filename);
        return $data;
    }

    /**
     * get files function
     *
     *  @param string $folder folder name
     *  @param string $jname  jname
     *
     *  @return array files
     */
    function getFiles($folder, $jname)
    {
        $filesArray = array();
        $files = JFolder::files($folder, null, false, true);
        foreach ($files as $file) {
            $file = str_replace(JFUSION_PLUGIN_PATH . DS . $jname . DS, '', $file);
            $data = JFile::read($file);
            $filesArray[] = array('name' => $file, 'data' => $data);
        }
        $folders = JFolder::folders($folder, null, false, true);
        if (!empty($folders)) {
            foreach ($folders as $f) {
                $filesArray = array_merge($filesArray, $this->getFiles($f, $jname));
            }
        }
        return $filesArray;
    }

    /**
     * getElementByPath
     *
     *  @param JXMLElement|JSimpleXMLElement $xml xml object
     *  @param string $element element path
     *
     *  @return JXMLElement|JSimpleXMLElement elements
     */
    function getElementByPath($xml, $element)
    {
        $elements = explode('/',$element);
        foreach ($elements as $element) {
            if($xml instanceof JXMLElement) {
                $xml = $xml->$element;
            } elseif($xml instanceof JSimpleXMLElement) {
                $xml = $xml->getElementByPath($element);
            } else {
                $xml = null;
                break;
            }
        }
        return $xml;
    }

    /**
     * getAttribute
     *
     *  @param JXMLElement|JSimpleXMLElement $xml xml object
     *  @param string $attribute attribute name
     *
     *  @return string result
     */
    function getAttribute($xml, $attribute)
    {
        if($xml instanceof JXMLElement) {
            $xml = $xml->getAttribute($attribute);
        } elseif($xml instanceof JSimpleXMLElement) {
            $xml = $xml->attributes($attribute);
        } else {
            $xml = null;
        }
        return $xml;
    }

	/**
	 * getData
	 *
	 *  @param JXMLElement|JSimpleXMLElement $xml xml object
	 *
	 *  @return JXMLElement|string result
	 */
	function getData($xml)
	{
		if($xml instanceof JSimpleXMLElement) {
			$xml = $xml->data();
		}
		return $xml;
	}
}
