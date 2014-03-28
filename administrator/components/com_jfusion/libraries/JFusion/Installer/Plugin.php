<?php namespace JFusion\Installer;

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

use Exception;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use JFusion\Factory;
use JFusion\Framework;
use Joomla\Event\Event;
use Joomla\Filesystem\Path;
use Joomla\Language\Text;
use JFusion\Object\Object;
use Joomla\Filter\InputFilter;
use RuntimeException;
use SimpleXMLElement;
use stdClass;

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
class Plugin extends Object
{
    var $manifest;

    /**
     * Overridden constructor
     *
     * @access    protected
     */
    function __construct()
    {
        $this->installer = new Installer;
        $this->installer->setOverwrite(true);
        $this->filterInput = new InputFilter;
    }

	/**
	 * handles JFusion plugin installation
	 *
	 * @param mixed $dir install path
	 * @param array &$result
	 *
	 * @throws \RuntimeException
	 * @return array
	 */
    function install($dir = null, &$result)
    {
        // Get a database connector object
        $db = Factory::getDBO();
        $result['status'] = false;
        $result['jname'] = null;
        if (!$dir && !is_dir(Path::clean($dir))) {
            $this->installer->abort(Text::_('INSTALL_INVALID_PATH'));
	        throw new RuntimeException(Text::_('INSTALL_INVALID_PATH'));
        } else {
            $this->installer->setPath('source', $dir);

            // Get the extension manifest object
            $manifest = $this->getManifest($dir);
            if (is_null($manifest)) {
                $this->installer->abort(Text::_('INSTALL_NOT_VALID_PLUGIN'));
	            throw new RuntimeException(Text::_('INSTALL_NOT_VALID_PLUGIN'));
            } else {
                $this->manifest = $manifest;

	            $file = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'jfusion.xml';
	            if (file_exists($file)) {
		            $jfusionxml = Framework::getXml($file);
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
		            $this->installer->setPath('extension_root', JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $name);
		            // get files to copy

		            /**
		             * ---------------------------------------------------------------------------------------------
		             * Filesystem Processing Section
		             * ---------------------------------------------------------------------------------------------
		             */

		            // If the plugin directory does not exist, lets create it
		            $created = false;
		            if (!file_exists($this->installer->getPath('extension_root'))) {
			            if (!$created = Folder::create($this->installer->getPath('extension_root'))) {
				            $msg = Text::_('PLUGIN') . ' ' . Text::_('INSTALL') . ': ' . Text::_('INSTALL_FAILED_DIRECTORY') . ': "' . $this->installer->getPath('extension_root') . '"';
				            $this->installer->abort($msg);
				            throw new RuntimeException($name . ': ' . $msg);
			            }
		            }
		            /**
		             * If we created the plugin directory and will want to remove it if we
		             * have to roll back the installation, lets add it to the installation
		             * step stack
		             */
		            if ($created) {
			            $this->installer->pushStep(array('type' => 'folder', 'path' => $this->installer->getPath('extension_root')));
		            }
		            // Copy all necessary files
		            if ($this->installer->parseFiles($this->manifest->files[0], -1) === false) {
			            // Install failed, roll back changes
			            $this->installer->abort();
			            throw new RuntimeException($name . ': ' . Text::_('PLUGIN') . ' ' . $name . ' ' . Text::_('INSTALL') . ': ' . Text::_('FAILED'));
		            } else {
			            /**
			             * ---------------------------------------------------------------------------------------------
			             * Language files Processing Section
			             * ---------------------------------------------------------------------------------------------
			             */
			            $languageFolder = $dir . DIRECTORY_SEPARATOR  .  'language';
			            if (is_dir(Path::clean($languageFolder))) {
				            $files = Folder::files($languageFolder);
				            foreach ($files as $file) {
					            $dest = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR . substr($file, 0, 5);
					            Folder::create($dest);
					            File::copy($languageFolder . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
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
				            if (!$this->installer->isOverwrite()) {
					            // Install failed, roll back changes
					            $msg = Text::_('PLUGIN') . ' ' . Text::_('INSTALL') . ': ' . Text::_('PLUGIN') . ' "' . $name . '" ' . Text::_('ALREADY_EXISTS');
					            $this->installer->abort($msg);
					            throw new RuntimeException($name . ': ' . $msg);
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
					            $msg = Text::_('PLUGIN') . ' ' . Text::_('INSTALL') . ' ' . Text::_('ERROR') . ': ' . $e->getMessage();
					            $this->installer->abort($msg);
					            throw new RuntimeException($name . ': ' . $msg);
				            }
				            $this->installer->pushStep(array('type' => 'plugin', 'id' => $plugin_entry->id));
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
//				            $this->copy($name, $plugin->name, true);
			            }

			            if ($result['overwrite'] == 1) {
				            $msg = Text::_('PLUGIN') . ' ' . $name . ' ' . Text::_('UPDATE') . ': ' . Text::_('SUCCESS');
			            } else {
				            $msg = Text::_('PLUGIN') . ' ' . $name . ' ' . Text::_('INSTALL') . ': ' . Text::_('SUCCESS');
			            }
			            $result['message'] = $name . ': ' . $msg;
			            $result['status'] = true;
		            }
	            } else {
		            $msg = Text::_('PLUGIN') . ' ' . $name . ': ' . Text::_('FAILED') . ' ' . Text::_('NEED_JFUSION_VERSION') . ' "' . $version . '" ' . Text::_('OR_HIGHER');
		            $this->installer->abort($msg);
		            throw new RuntimeException($name . ': ' . $msg);
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
     * @return array
     */
    function uninstall($jname)
    {
	    $result = array();
    	$result['status'] = false;
	    try {
		    $JFusionAdmin = Factory::getAdmin($jname);
		    if ($JFusionAdmin->isConfigured()) {
			    //if this plugin had been valid, call its uninstall function if it exists
			    $success = 0;
			    try {
				    list($success, $reasons) = $JFusionAdmin->uninstall();

				    $reason = '';
				    if (is_array($reasons)) {
					    $reason = implode('</li><li>' . $jname . ': ', $reasons);
				    } else {
					    $reason = $jname . ': ' . $reasons;
				    }
			    } catch (Exception $e) {
				    $reason = $e->getMessage();
			    }
			    if (!$success) {
				    throw new RuntimeException(Text::_('PLUGIN') . ' ' . $jname . ' ' . Text::_('UNINSTALL') . ' ' . Text::_('FAILED') . ': ' . $reason);
			    }
		    }
		    $db = Factory::getDBO();

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
			    ->delete('#__jfusion_users_plugin')
			    ->where('jname = ' . $db->quote($jname));
		    $db->setQuery($query);
		    $db->execute();

		    $event = new Event('onInstallerPluginUninstall');
		    $event->addArgument('jname', $jname);
		    Factory::getDispatcher()->triggerEvent($event);

		    $dir = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $jname;

		    if (!$jname || !is_dir(Path::clean($dir))) {
			    throw new RuntimeException(Text::_('UNINSTALL_ERROR_PATH'));
		    } else {
			    /**
			     * ---------------------------------------------------------------------------------------------
			     * Remove Language files Processing Section
			     * ---------------------------------------------------------------------------------------------
			     */
			    // Get the extension manifest object
			    $manifest = $this->getManifest($dir);
			    if (is_null($manifest)) {
				    throw new RuntimeException(Text::_('INSTALL_NOT_VALID_PLUGIN'));
			    } else {
				    $this->manifest = $manifest;

				    if ($removeLanguage) {
					    $languageFolder = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'language';
					    if (is_dir(Path::clean($languageFolder))) {
						    $files = Folder::files(JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'language',  'com_jfusion.plg_' . $jname . '.ini', true);
						    foreach ($files as $file) {
							    $file = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR . substr($file, 0, 5) . DIRECTORY_SEPARATOR . $file;
							    File::delete($file);
						    }
					    }
				    }

				    // remove files
				    if (!Folder::delete($dir)) {
					    throw new RuntimeException(Text::_('UNINSTALL_ERROR_DELETE'));
				    } else {
					    //return success
					    $msg = Text::_('PLUGIN') . ' ' . $jname . ' ' . Text::_('UNINSTALL') . ': ' . Text::_('SUCCESS');
					    $result['message'] = $msg;
					    $result['status'] = true;
					    $result['jname'] = $jname;
				    }
			    }
		    }
	    } catch (Exception $e) {
		    $result['message'] = $jname . ' ' . $e->getMessage();
		    $this->installer->abort($e->getMessage());
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
	 * @throws RuntimeException
	 * @return boolean
	 */
    function copy($jname, $new_jname, $update = false)
    {
	    //replace not-allowed characters with _
	    $new_jname = preg_replace('/([^a-zA-Z0-9_])/', '_', $new_jname);

	    //initialise response element
	    $result = array();
	    $result['status'] = false;

	    //check to see if an integration was selected
	    $db = Factory::getDBO();

	    $query = $db->getQuery(true)
		    ->select('count(*)')
		    ->from('#__jfusion')
		    ->where('original_name IS NULL')
		    ->where('name LIKE ' . $db->quote($jname));

	    $db->setQuery($query);
	    $record = $db->loadResult();

	    $query = $db->getQuery(true)
		    ->select('id')
		    ->from('#__jfusion')
		    ->where('name = ' . $db->quote($new_jname));

	    $db->setQuery($query);
	    $exsist = $db->loadResult();
	    if ($exsist) {
		    throw new RuntimeException($new_jname . ' ' . Text::_('ALREADY_IN_USE'));
	    } else if ($jname && $new_jname && $record) {
		    $JFusionPlugin = Factory::getAdmin($jname);
		    if ($JFusionPlugin->multiInstance()) {
			    $db = Factory::getDBO();
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
				    $db->insertObject('#__jfusion', $plugin_entry, 'id');
			    }
			    $result['message'] = $new_jname . ': ' . Text::_('PLUGIN') . ' ' . $jname . ' ' . Text::_('COPY') . ' ' . Text::_('SUCCESS');
			    $result['status'] = true;
		    } else {
			    throw new RuntimeException(Text::_('CANT_COPY'));
		    }
	    } else {
		    throw new RuntimeException(Text::_('NONE_SELECTED'));
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
    function getManifest($dir)
    {
        // Initialize variables

        /**
         * @TODO DISCUSS if we should allow flexible naming for installation file
         */
        $file = $dir . DIRECTORY_SEPARATOR . 'jfusion.xml';
        $this->installer->setPath('manifest', $file);
        // If we cannot load the xml file return null

	    $xml = Framework::getXml($file);

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
        $files = Folder::files($folder, null, false, true);
        foreach ($files as $file) {
            $file = str_replace(JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $jname . DIRECTORY_SEPARATOR, '', $file);
            $data = file_get_contents($file);
            $filesArray[] = array('name' => $file, 'data' => $data);
        }
        $folders = Folder::folders($folder, null, false, true);
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
