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
use JFusion\Installer\Plugin;
use Psr\Log\LogLevel;

defined('_JEXEC') or die('Restricted access');

/**
 * Require the Joomla Installer model
 */
require_once JPATH_ADMINISTRATOR . '/components/com_jfusion/import.php';
require_once JPATH_ADMINISTRATOR . '/components/com_installer/models/install.php';
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
					\JFusion\Framework::raise(LogLevel::INFO, $msg, $jname);
					break;
				case 'error':
					\JFusion\Framework::raise(LogLevel::ERROR, $msg, $jname);
					break;
			}
		}
		return $msg;
	}

	/**
	 * Replaces original Install() method.
	 *
	 * @throws Exception
	 * @return array|bool Result of the JFusion plugin install
	 */
    function install()
    {
	    $result = array('status' => false);
	    $this->setState('action', 'install');
	    $package = null;
	    try {
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
			    $installer = new Plugin($this);

			    // Install the package
			    $installer->install($package['dir'], $result);

			    // Cleanup the install files
			    if (!is_file($package['packagefile'])) {
				    $config = JFactory::getConfig();
				    $package['packagefile'] = $config->get('tmp_path') . '/' . $package['packagefile'];
			    }
			    if ($result['status'] && is_file($package['packagefile'])) {
				    //save a copy of the plugin for safe keeping
				    $dest = JPATH_ADMINISTRATOR . '/components/com_jfusion/packages/' . basename($package['packagefile']);
				    if ($package['packagefile'] != $dest) {
					    JFile::copy($package['packagefile'], $dest);
				    }
			    }
		    }

		    if ( isset($package['packagefile']) && $package['extractdir'] ) {
			    JInstallerHelper::cleanupInstall($package['packagefile'], $package['extractdir']);
		    }
	    } catch (Exception $e) {
		    if ( isset($package['packagefile']) && $package['extractdir'] ) {
			    JInstallerHelper::cleanupInstall($package['packagefile'], $package['extractdir']);
		    }
		    throw $e;
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
        $installer = new Plugin($this);

        $package = JInstallerHelper::unpack($filename);

        // Install the package
        $installer->install($package['dir'], $result);

        // Cleanup the install files
        if (!is_file($package['packagefile'])) {
            $config = JFactory::getConfig();
            $package['packagefile'] = $config->get('tmp_path') . '/' . $package['packagefile'];
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
            $installer = new Plugin($this);
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
            $installer = new Plugin($this);
            // Install the package
	        $result = $installer->copy($jname, $new_jname, $update);
        }
        return $result;
    }
}