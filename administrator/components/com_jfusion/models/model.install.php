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
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.factory.php';
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_installer' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'install.php';
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

	var $raise = true;

    /**
     * Overridden constructor
     *
     * @access    protected
     */
    function __construct($raise=true)
    {
	    $this->raise = $raise;
        // Load the language file
	    JFactory::getLanguage()->load('com_installer');
        parent::__construct();
    }

	/**
	 * @param        $type
	 * @param        $msg
	 *
	 * @param string $jname
	 *
	 * @return mixed
	 */
	function raise($type, $msg, $jname = '') {
		$this->setState('message', $msg);
		if ($this->raise) {
			switch($type) {
				case 'message':
					JFusionFunction::raiseMessage($msg, $jname);
					break;
				case 'error':
					JFusionFunction::raiseError($msg, $jname);
					break;
			}
		}
		return $msg;
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
	    try {
		    $package = null;
		    switch (JFactory::getApplication()->input->getWord('installtype')) {
			    case 'folder':
				    $package = $this->_getPackageFromFolder();
				    break;
			    case 'upload':
				    $package = $this->_getPackageFromUpload();
				    break;
			    case 'url':
				    // Get the URL of the package to install
				    $url = JFactory::getApplication()->input->getString('install_url');
				    if(filter_var($url, FILTER_VALIDATE_URL) !== FALSE) {
					    $package = $this->_getPackageFromUrl();
				    } else {
					    throw new RuntimeException(JText::_('INVALID_URL') . ': ' . $url);
				    }
				    break;
			    default:
					throw new RuntimeException(JText::_('NO_INSTALL_TYPE'));
				    break;
		    }
		    // Was the package unpacked?
		    if ($package == false) {
			    throw new RuntimeException(JText::_('NO_PACKAGE_FOUND'));
		    } else {
			    // custom installer
			    $installer = new JfusionPluginInstaller($this);

			    // Install the package
			    $installer->install($package['dir'], $result);

			    // Cleanup the install files
			    if (!is_file($package['packagefile'])) {
				    $config = JFactory::getConfig();
				    $package['packagefile'] = $config->get('tmp_path') . DIRECTORY_SEPARATOR . $package['packagefile'];
			    }
			    if ($result['status'] && is_file($package['packagefile'])) {
				    //save a copy of the plugin for safe keeping
				    $dest = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . basename($package['packagefile']);
				    if ($package['packagefile'] != $dest) {
					    JFile::copy($package['packagefile'], $dest);
				    }
			    }

			    JInstallerHelper::cleanupInstall($package['packagefile'], $package['extractdir']);
		    }
	    } catch (Exception $e) {
		    $result['message'] = $this->raise('error', $e->getMessage());
	    }
        //return the results array
        return $result;
    }

    /**
     * Replaces original Install() method.
     *
     * @param string $filename
     *
     * @return boolean Result of the JFusion plugin install
     */
    function installZIP($filename)
    {
    	$result = array();

        $this->setState('action', 'install');

        // custom installer
        $installer = new JfusionPluginInstaller($this);

        $package = JInstallerHelper::unpack($filename);

        // Install the package
        $installer->install($package['dir'], $result);

        // Cleanup the install files
        if (!is_file($package['packagefile'])) {
            $config = JFactory::getConfig();
            $package['packagefile'] = $config->get('tmp_path') . DIRECTORY_SEPARATOR . $package['packagefile'];
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
     * @return boolean Result of the JFusion plugin uninstall
     */
    function uninstall($jname)
    {
        $db = JFactory::getDBO();

	    $query = $db->getQuery(true)
		    ->select('id')
		    ->from('#__jfusion')
		    ->where('name = ' . $db->quote($jname));

        $db->setQuery($query);
        $myId = $db->loadResult();
        $result['status'] = false;
        if (!$myId) {
	        $result['message'] = $this->raise('error', 'JFusion ' . JText::_('PLUGIN') . ' ' . JText::_('UNINSTALL') . ' ' . JText::_('FAILED'), $jname);
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
     * @return boolean Result of the JFusion plugin uninstall
     */
    function copy($jname, $new_jname, $update = false)
    {
        $db = JFactory::getDBO();

	    $query = $db->getQuery(true)
		    ->select('id')
		    ->from('#__jfusion')
		    ->where('name = ' . $db->quote($jname));

        $db->setQuery($query);
        $myId = $db->loadResult();
        $result['status'] = false;
        if (!$myId) {
	        $result['message'] = $this->raise('error', 'JFusion ' . JText::_('PLUGIN') . ' ' . JText::_('COPY') . ' ' . JText::_('FAILED'), $new_jname);
        } else {
            $installer = new JfusionPluginInstaller($this);
            // Install the package
	        $result = $installer->copy($jname, $new_jname, $update);
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

	var $module;
    /**
     * Overridden constructor
     *
     * @param object &$module parent object
     *
     * @access    protected
     */
    function __construct(&$module)
    {
	    $this->module = $module;
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
	        $result['message'] = $this->module->raise('error', JText::_('INSTALL_INVALID_PATH'));
        } else {
            $this->parent->setPath('source', $dir);

            // Get the extension manifest object
            $manifest = $this->_getManifest($dir);
            if (is_null($manifest)) {
                $this->parent->abort(JText::_('INSTALL_NOT_VALID_PLUGIN'));
	            $result['message'] = $this->module->raise('error', JText::_('INSTALL_NOT_VALID_PLUGIN'));
            } else {
                $this->manifest = $manifest;

	            $file = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'jfusion.xml';
	            if (file_exists($file)) {
		            $jfusionxml = JFusionFunction::getXml($file);
	            } else {
		            $jfusionxml = false;
	            }

	            $version = $this->getAttribute($this->manifest, 'version');

	            /**
	             * ---------------------------------------------------------------------------------------------
	             * Manifest Document Setup Section
	             * ---------------------------------------------------------------------------------------------
	             */
	            // Set the extensions name
	            /**
	             * Check that the plugin is an actual JFusion plugin
	             */
	            $name = $this->manifest->name;
	            $name = $this->filterInput->clean($name, 'string');

	            if (!$jfusionxml || !$version || version_compare($jfusionxml->version, $version) >= 0) {
		            $result['jname'] = $name;
		            $this->set('name', $name);

		            // installation path
		            $this->parent->setPath('extension_root', JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $name);
		            // get files to copy

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
				            $result['message'] = $this->module->raise('error', $msg, $name);
				            return $result;
			            }
		            }
		            /**
		             * If we created the plugin directory and will want to remove it if we
		             * have to roll back the installation, lets add it to the installation
		             * step stack
		             */
		            if ($created) {
			            $this->parent->pushStep(array('type' => 'folder', 'path' => $this->parent->getPath('extension_root')));
		            }
		            // Copy all necessary files
		            if ($this->parent->parseFiles($this->manifest->files[0], -1) === false) {
			            // Install failed, roll back changes
			            $this->parent->abort();
			            $result['message'] = $this->module->raise('error', JText::_('PLUGIN') . ' ' . $name . ' ' . JText::_('INSTALL') . ': ' . JText::_('FAILED'), $name);
		            } else {
			            /**
			             * ---------------------------------------------------------------------------------------------
			             * Language files Processing Section
			             * ---------------------------------------------------------------------------------------------
			             */
			            $languageFolder = $dir . DIRECTORY_SEPARATOR  .  'language';
			            if (JFolder::exists($languageFolder)) {
				            $files = JFolder::files($languageFolder);
				            foreach ($files as $file) {
					            $dest = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR . substr($file, 0, 5);
					            JFolder::create($dest);
					            JFile::copy($languageFolder . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
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
				            $xml = $this->manifest->$f;

				            if ($xml instanceof SimpleXMLElement) {
					            $$f = $this->filterInput->clean($xml, 'integer');
				            } elseif ($f == 'master' || $f == 'check_encryption') {
					            $$f = 0;
				            } else {
					            $$f = 3;
				            }
			            }
			            //let's check to see if a plugin with the same name is already installed
			            $query = $db->getQuery(true)
				            ->select('id, ' . implode(', ', $features))
				            ->from('#__jfusion')
				            ->where('name = ' . $db->quote($name));

			            $db->setQuery($query);

			            $plugin = $db->loadObject();
			            if (!empty($plugin)) {
				            if (!$this->parent->isOverwrite()) {
					            // Install failed, roll back changes
					            $msg = JText::_('PLUGIN') . ' ' . JText::_('INSTALL') . ': ' . JText::_('PLUGIN') . ' "' . $name . '" ' . JText::_('ALREADY_EXISTS');
					            $this->parent->abort($msg);
					            $result['message'] = $this->module->raise('error', $msg, $name);
					            return $result;
				            } else {
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
				            //now append the new plugin data
				            try {
					            $db->insertObject('#__jfusion', $plugin_entry, 'id');
				            } catch (Exception $e) {
					            // Install failed, roll back changes
					            $msg = JText::_('PLUGIN') . ' ' . JText::_('INSTALL') . ' ' . JText::_('ERROR') . ': ' . $e->getMessage();
					            $this->parent->abort($msg);
					            $result['message'] = $this->module->raise('error', $msg);
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
			            $query = $db->getQuery(true)
				            ->select('name')
				            ->from('#__jfusion')
				            ->where('original_name = ' . $db->quote($name));

			            $db->setQuery($query);
			            $copiedPlugins = $db->loadObjectList();
			            foreach ($copiedPlugins as $plugin) {
				            //update the copied version with the new files
				            $this->copy($name, $plugin->name, true);
			            }

			            if ($result['overwrite'] == 1) {
				            $msg = JText::_('PLUGIN') . ' ' . $name . ' ' . JText::_('UPDATE') . ': ' . JText::_('SUCCESS');
			            } else {
				            $msg = JText::_('PLUGIN') . ' ' . $name . ' ' . JText::_('INSTALL') . ': ' . JText::_('SUCCESS');
			            }
			            $result['message'] = $this->module->raise('message', $msg, $name);
			            $result['status'] = true;
		            }
	            } else {
		            $msg = JText::_('PLUGIN') . ' ' . $name . ': ' . JText::_('FAILED') . ' ' . JText::_('NEED_JFUSION_VERSION') . ' "' . $version . '" ' . JText::_('OR_HIGHER');
		            $this->parent->abort($msg);
		            $result['message'] = $this->module->raise('error', $msg, $name);
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
	    try {
		    $JFusionAdmin = JFusionFactory::getAdmin($jname);
		    if ($JFusionAdmin->isConfigured()) {
			    //if this plugin had been valid, call its uninstall function if it exists
			    $result = $JFusionAdmin->uninstall();
			    $reason = '';
			    if (is_array($result)) {
				    $success = $result[0];
				    if (is_array($result[1])) {
					    $reason = implode('</li><li>' . $jname . ': ', $result[1]);
				    } elseif (!empty($result[1])) {
					    $reason = $result[1];
				    }
			    } else {
				    $success = $jname . ': ' . $result;
			    }
			    if (!$success) {
				    throw new RuntimeException(JText::_('PLUGIN') . ' ' . $jname . ' ' . JText::_('UNINSTALL') . ' ' . JText::_('FAILED') . ': ' . $reason);
			    }
		    }
		    $db = JFactory::getDBO();

		    $query = $db->getQuery(true)
			    ->select('name , original_name')
			    ->from('#__jfusion')
			    ->where('name = ' . $db->quote($jname));

		    $db->setQuery($query);
		    $plugin = $db->loadObject();
		    $removeLanguage = true;
		    if (!$plugin || $plugin->original_name) {
			    $removeLanguage = false;
		    }

		    // delete raw

		    $query = $db->getQuery(true)
			    ->delete('#__jfusion')
			    ->where('name = ' . $db->quote($jname));
		    $db->setQuery($query);
		    $db->execute();

		    $query = $db->getQuery(true)
			    ->delete('#__jfusion_discussion_bot')
			    ->where('jname = ' . $db->quote($jname));
		    $db->setQuery($query);
		    $db->execute();

		    $query = $db->getQuery(true)
			    ->delete('#__jfusion_users_plugin')
			    ->where('jname = ' . $db->quote($jname));
		    $db->setQuery($query);
		    $db->execute();

		    $dir = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $jname;
		    if (!$jname || !JFolder::exists($dir)) {
			    throw new RuntimeException(JText::_('UNINSTALL_ERROR_PATH'));
		    } else {
			    /**
			     * ---------------------------------------------------------------------------------------------
			     * Remove Language files Processing Section
			     * ---------------------------------------------------------------------------------------------
			     */
			    // Get the extension manifest object
			    $manifest = $this->_getManifest($dir);
			    if (is_null($manifest)) {
				    throw new RuntimeException(JText::_('INSTALL_NOT_VALID_PLUGIN'));
			    } else {
				    $this->manifest = $manifest;

				    if ($removeLanguage) {
					    $languageFolder = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'language';
					    if (JFolder::exists($languageFolder)) {
						    $files = JFolder::files(JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'language',  'com_jfusion.plg_' . $jname . '.ini', true);
						    foreach ($files as $file) {
							    $file = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR . substr($file, 0, 5) . DIRECTORY_SEPARATOR . $file;
							    JFile::delete($file);
						    }
					    }
				    }

				    // remove files
				    if (!JFolder::delete($dir)) {
					    throw new RuntimeException(JText::_('UNINSTALL_ERROR_DELETE'));
				    } else {
					    //return success
					    $msg = JText::_('PLUGIN') . ' ' . $jname . ' ' . JText::_('UNINSTALL') . ': ' . JText::_('SUCCESS');
					    $result['message'] = $this->module->raise('message', $msg);
					    $result['status'] = true;
					    $result['jname'] = $jname;
				    }
			    }
		    }
	    } catch (Exception $e) {
		    $result['message'] = $this->module->raise('error', $e->getMessage(), $jname);
		    $this->parent->abort($e->getMessage());
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
        $dir = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $jname;
        $new_dir = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $new_jname;
        $result['status'] = false;
        if (!$jname || !JFolder::exists($dir)) {
            $this->parent->abort(JText::_('COPY_ERROR_PATH'));
	        $result['message'] = $this->module->raise('error', JText::_('COPY_ERROR_PATH'), $new_jname);
        } else if (!JFolder::copy($dir, $new_dir, null, $update)) {
            //copy the files
            $this->parent->abort(JText::_('COPY_ERROR'));
	        $result['message'] = $this->module->raise('error', JText::_('COPY_ERROR'), $new_jname);
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
             * @TODO - This section may be improved but works actually
             * ---------------------------------------------------------------------------------------------
             */
            $manifest = $this->_getManifest($dir);
            if (is_null($manifest)) {
                $this->parent->abort(JText::_('INSTALL_NOT_VALID_PLUGIN'));
	            $result['message'] = $this->module->raise('error', JText::_('INSTALL_NOT_VALID_PLUGIN'), $new_jname);
            } else {
                $this->manifest = $manifest;

                /**
                 * ---------------------------------------------------------------------------------------------
                 * Rename class files and xml file of the new plugin create
                 * ---------------------------------------------------------------------------------------------
                 */
                //define which files need parsing
                $parse_files = array($new_dir . DIRECTORY_SEPARATOR . 'auth.php', $new_dir . DIRECTORY_SEPARATOR . 'admin.php', $new_dir . DIRECTORY_SEPARATOR . 'user.php', $new_dir . DIRECTORY_SEPARATOR . 'jfusion.xml', $new_dir . DIRECTORY_SEPARATOR . 'forum.php', $new_dir . DIRECTORY_SEPARATOR . 'public.php', $new_dir . DIRECTORY_SEPARATOR . 'helper.php');
                foreach ($parse_files as $parse_file) {
                    if (file_exists($parse_file)) {
                        $file_data = file_get_contents($parse_file);
                        $file_data = preg_replace($regex, $replace, $file_data);
                        JFile::write($parse_file, $file_data);
                    }
                }
                $db = JFactory::getDBO();
                if (!$update) {
                    //add the new entry in the JFusion plugin table
	                $query = $db->getQuery(true)
		                ->select('*')
		                ->from('#__jfusion')
		                ->where('name = ' . $db->quote($jname));

                    $db->setQuery($query);
                    $plugin_entry = $db->loadObject();
                    $plugin_entry->name = $new_jname;
                    $plugin_entry->id = null;
                    $plugin_entry->master = ($plugin_entry->master == 3) ? 3 : 0;
                    $plugin_entry->slave = ($plugin_entry->slave == 3) ? 3 : 0;
                    //only change the original name if this is not a copy itself
                    if (empty($plugin_entry->original_name)) {
                        $plugin_entry->original_name = $jname;
                    }
	                try {
		                $db->insertObject('#__jfusion', $plugin_entry, 'id');
	                } catch (Exception $e) {
		                //return the error
		                $msg = 'Error while creating the plugin: ' . $e->getMessage();
		                $this->parent->abort($msg);
		                $result['message'] = $this->module->raise('error', $msg, $new_jname);
		                return $result;
	                }
                }
	            $result['message'] = $this->module->raise('message', JText::_('PLUGIN') . ' ' . $jname . ' ' . JText::_('COPY') . ' ' . JText::_('SUCCESS'), $new_jname);
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
     * @return SimpleXMLElement object (or null)
     */
    function _getManifest($dir)
    {
        // Initialize variables

        /**
         * @TODO DISCUSS if we should allow flexible naming for installation file
         */
        $file = $dir . DIRECTORY_SEPARATOR . 'jfusion.xml';
        $this->parent->setPath('manifest', $file);
        // If we cannot load the xml file return null

	    $xml = JFusionFunction::getXml($file);
    	/*
        * Check for a valid XML root tag.
        * @TODO Remove backwards compatibility in a future version
        * Should be 'install', but for backward compatibility we will accept 'mosinstall'.
        */
		if (!($xml instanceof SimpleXMLElement) || ($xml->getName() != 'extension')) {
            // Free up xml parser memory and return null
            unset($xml);
            $xml = null;
        } else {
            /**
             * Check that the plugin is an actual JFusion plugin
             */
            $type = $this->getAttribute($xml, 'type');

            if ($type !== 'jfusion') {
                //Free up xml parser memory and return null
                unset ($xml);
                $xml = null;
            }
        }

        // Valid manifest file return the object
        return $xml;
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
            $file = str_replace(JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $jname . DIRECTORY_SEPARATOR, '', $file);
            $data = file_get_contents($file);
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
     * getAttribute
     *
     *  @param SimpleXMLElement $xml xml object
     *  @param string $attribute attribute name
     *
     *  @return string result
     */
    function getAttribute($xml, $attribute)
    {
        if($xml instanceof SimpleXMLElement) {
	        $attributes = $xml->attributes();
	        if (isset($attributes[$attribute])) {
		        $xml = (string)$attributes[$attribute];
	        } else {
		        $xml = null;
	        }
        } else {
            $xml = null;
        }
        return $xml;
    }
}
