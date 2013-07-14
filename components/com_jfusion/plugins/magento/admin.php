<?php

/**
 * file containing administrator function for the jfusion plugin
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Magento 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Admin class for Magento 1.1
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Magento 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class JFusionAdmin_magento extends JFusionAdmin 
{
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'magento';
    }

    /**
     * @return string
     */
    function getTablename() {
        return 'admin_user';
    }

	/**
	 * @param $version
	 *
	 * @return string
	 */public function normalize_version($version) {
    	/// 1.9 Beta 2 should be read 1.9 , not 1.9.2
    	/// we can discard everything after the first space
    	$version = trim($version);
    	$versionarr = explode(" ",$version);
    	if (!empty($versionarr)) {
    		$version = $versionarr[0];
    	}
    	/// Replace everything but numbers and dots by dots
    	$version = preg_replace('/[^\.\d]/', '.', $version);
    	/// Combine multiple dots in one
    	$version = preg_replace('/(\.{2,})/', '.', $version);
    	/// Trim possible leading and trailing dots
    	$version = trim($version, '.');
    	return $version;
    }
    
    
    // get the Magento version number
	/**
	 * @param $forumPath
	 *
	 * @return string
	 */function getMagentoVersion($forumPath) {
    	$file = file_get_contents(rtrim($forumPath,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Mage.php');
    	$pstart = strpos($file,'function getVersionInfo()');
    	$pend = strpos($file,'}',$pstart);
    	eval(substr($file,$pstart,$pend-$pstart+1));
    	$version = getVersionInfo();
    	return $version['major'].".".$version['minor'].".".$version['revision'];
    }
    
    

    /**
     * @param string $forumPath
     * @return array
     */
    function setupFromPath($forumPath) {
        //check for trailing slash and generate file path
        if (substr($forumPath, -1) != DIRECTORY_SEPARATOR) {
            $forumPath = $forumPath . DIRECTORY_SEPARATOR;
        }
        $xmlfile = $forumPath . 'app' . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'local.xml';
        $params = array();
        if (file_exists($xmlfile)) {
	        $xml = JFusionFunction::getXml($xmlfile);
            if (!$xml) {
                JFusionFunction::raiseWarning(JText::_('WIZARD_FAILURE') . " $xmlfile " . JText::_('WIZARD_MANUAL'));
            } else {
                //save the parameters into array
                $params['database_host'] = (string)$xml->global->resources->default_setup->connection->host;
                $params['database_name'] = (string)$xml->global->resources->default_setup->connection->dbname;
                $params['database_user'] = (string)$xml->global->resources->default_setup->connection->username;
                $params['database_password'] = (string)$xml->global->resources->default_setup->connection->password;
                $params['database_prefix'] = (string)$xml->global->resources->db->table_prefix;
                $params['database_type'] = 'mysql';
                $params['source_path'] = $forumPath;
            }
            unset($xml);
        } else {
            JFusionFunction::raiseWarning(JText::_('WIZARD_FAILURE') . " $xmlfile " . JText::_('WIZARD_MANUAL'));
        }
        
        $params['magento_version'] = $this->normalize_version($this->getMagentoVersion($forumPath));
        
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
        //getting the connection to the db
        $db = JFusionFactory::getDataBase($this->getJname());
        $query = 'SELECT email as username, email from #__customer_entity';

        $db->setQuery($query,$limitstart,$limit);
        //getting the results
        $userlist = $db->loadObjectList();
        return $userlist;
    }
    /**
     * @return int
     */
    function getUserCount() {
        //getting the connection to the db
        $db = JFusionFactory::getDataBase($this->getJname());
        $query = 'SELECT count(*) from #__customer_entity';
        $db->setQuery($query);
        //getting the results
        $no_users = $db->loadResult();
        return $no_users;
    }

    /**
     * @return array
     */
    function getUsergroupList() {
        //get the connection to the db
        $db = JFusionFactory::getDataBase($this->getJname());
        $query = 'SELECT customer_group_id as id, customer_group_code as name from #__customer_group;';
        $db->setQuery($query);
        //getting the results
        return $db->loadObjectList();
    }
    /**
     * @return string
     */
    function getDefaultUsergroup() {
        $params = JFusionFactory::getParams($this->getJname());
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),null);
        $usergroup_id = null;
        if(!empty($usergroups)) {
            $usergroup_id = $usergroups[0];
        }
        //we want to output the usergroup name
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT customer_group_code from #__customer_group WHERE customer_group_id = ' . (int)$usergroup_id;
        $db->setQuery($query);
        return $db->loadResult();
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

    function debugConfigExtra() {
        // see if we have an api user in Magento
        $jname = $this->getJname();
        $db = JFusionFactory::getDataBase($this->getJname());
        $query = 'SELECT count(*) from #__api_user';
        $db->setQuery($query);
        $no_users = $db->loadResult();
        if ($no_users <= 0) {
            JFusionFunction::raiseWarning($jname . ': ' . JText::_('MAGENTO_NEED_API_USER'));
        } else {
            // check if we have valid parameters  for apiuser and api key
            $params = JFusionFactory::getParams($this->getJname());
            $apiuser = $params->get('apiuser');
            $apikey = $params->get('apikey');
            if (!$apiuser || !$apikey) {
                JFusionFunction::raiseWarning($jname . '-plugin: ' . JText::_('MAGENTO_NO_API_DATA'));
            } else {
                //finally check if the apiuser and apikey are valid
                $query = 'SELECT api_key FROM #__api_user WHERE username = ' . $db->Quote($apiuser);
                $db->setQuery($query);
                $api_key = $db->loadResult();
                $hashArr = explode(':', $api_key);
                $api_key = $hashArr[0];
                $api_salt = $hashArr[1];
                if ($api_salt) {
                    $params_hash_md5 = md5($api_salt . $apikey);
                	$params_hash_sha256 = hash("sha256",$api_salt . $apikey);
                } else {
                    $params_hash_md5 = md5($apikey);
                	$params_hash_sha256 = hash("sha256",$apikey);
                }
                	if ($params_hash_md5 != $api_key && $params_hash_sha256 != $api_key) {
                    JFusionFunction::raiseWarning($jname . '-plugin: ' . JText::_('MAGENTO_WRONG_APIUSER_APIKEY_COMBINATION'));
                }
            }
        }
        // check the user_remote_addr security settings
        $query = 'SELECT  value FROM #__core_config_data WHERE path = \'web/session/use_remote_addr\'';
        $db->setQuery($query);
        if ($db->getErrorNum() == 0) {
            $value = $db->loadResult();
            if ($value) {
                JFusionFunction::raiseWarning($jname . ': ' . JText::_('MAGENTO_USE_REMOTE_ADDRESS_NOT_DISABLED'));
            }
        }
        // we need to have the curl library installed
        if (!extension_loaded('curl')) {
            JFusionFunction::raiseWarning($jname . ': ' . JText::_('CURL_NOTINSTALLED'));
        }
    }

    /**
     * @return bool
     */
    function allowRegistration() {
        $result = true;
        $params = JFusionFactory::getParams($this->getJname());
        $registration_disabled = $params->get('disabled_registration');
		if ($registration_disabled){$result = false;}
		return $result;
	}

    /**
     * @return string
     */
    public function moduleInstallation() {
        $jname = $this->getJname ();
        $params = JFusionFactory::getParams ( $jname );

        $db = JFusionFactory::getDatabase ( $jname );
        if (! JError::isError ( $db ) && ! empty ( $db )) {
            $source_path = $params->get ( 'source_path', '' );
            if (! file_exists ( $source_path . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Mage.php' )) {
                $html = JText::_ ( 'MAGE_CONFIG_SOURCE_PATH' );
            } else {
                $mod_exists = false;
                if (file_exists ( $source_path . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'Jfusion_All.xml' )) {
                    $mod_exists = true;
                }

                if ($mod_exists) {
                    $src = 'components/com_jfusion/images/tick.png';
                    $mod = 'uninstallModule';
                    $text = JText::_ ( 'MODULE_UNINSTALL_BUTTON' );
                } else {
                    $src = 'components/com_jfusion/images/cross.png';
                    $mod = 'installModule';
                    $text = JText::_ ( 'MODULE_INSTALL_BUTTON' );
                }

                $html = <<<HTML
                <div class="button2-left">
                    <div class="blank">
                        <a href="javascript:void(0);" onclick="return JFusion.module('{$mod}');">{$text}</a>
                    </div>
                </div>

                <img src="{$src}" style="margin-left:10px;" id="usergroups_img"/>
HTML;
            }
        } else {
            $html = JText::_ ( 'MAGE_CONFIG_FIRST' );
		}
        return $html;
	}

    /**
     * @return array
     */
    public function installModule() {
		
		$jname =  $this->getJname ();
		$db = JFusionFactory::getDatabase($jname);
		$params = JFusionFactory::getParams ( $jname );
		$source_path = $params->get ( 'source_path' );
		jimport ( 'joomla.filesystem.archive' );
		jimport ( 'joomla.filesystem.file' );
        $pear_path = realpath ( dirname ( __FILE__ ) ) . DIRECTORY_SEPARATOR .'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'models'.DIRECTORY_SEPARATOR. 'pear';
        require_once $pear_path.DIRECTORY_SEPARATOR.'PEAR.php';
        $pear_archive_path = $pear_path.DIRECTORY_SEPARATOR.archive_tar.DIRECTORY_SEPARATOR.'Archive_Tar.php';
        require_once $pear_archive_path;

        $status = array('error' => array(),'debug' => array());
		$archive_filename = 'magento_module_jfusion.tar.gz';
		$old_chdir = getcwd();
		$src_archive =  $src_path = realpath ( dirname ( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'install_module';
		$src_code =  $src_archive . DIRECTORY_SEPARATOR . 'source';
		$dest = $source_path;
		
		// Create an archive to facilitate the installation into the Magento installation while extracting
		chdir($src_code);
		$tar = new Archive_Tar( $archive_filename, 'gz' );
		$tar->setErrorHandling(PEAR_ERROR_PRINT);
		$tar->createModify( 'app' , '', '' );
		chdir($old_chdir);
		
		$ret = JArchive::extract ( $src_code . DIRECTORY_SEPARATOR . $archive_filename, $dest );
		JFile::delete($src_code . DIRECTORY_SEPARATOR . $archive_filename);
		
		// Initialize default data config in Magento database
		$joomla = JFusionFactory::getParams('joomla_int');
		$joomla_baseurl = $joomla->get('source_url');
		$joomla_secret = $joomla->get('secret');
		
		$query = 'REPLACE INTO #__core_config_data SET path = \'joomla/joomlaconfig/baseurl\', value = \''.$joomla_baseurl.'\';';
		$db->transactionStart();
        $db->setQuery($query);
        $db->execute();
		if ($db->getErrorNum() != 0) {
			$db->transactionRollback();
			$status['error'] = $db->stderr ();
		} else {
            $query = 'REPLACE INTO #__core_config_data SET path = \'joomla/joomlaconfig/installationpath\', value = \''.JPATH_SITE.'\';';
            $db->transactionStart();
            $db->setQuery($query);
            $db->execute();
            if ($db->getErrorNum() != 0) {
                $db->transactionRollback();
                $status['error'] = $db->stderr ();
            } else {
                $query = 'REPLACE INTO #__core_config_data SET path = \'joomla/joomlaconfig/secret_key\', value = \''.$joomla_secret.'\';';
                $db->transactionStart();
                $db->setQuery($query);
                $db->execute();
                if ($db->getErrorNum() != 0) {
                    $db->transactionRollback();
                    $status['error'] = $db->stderr ();
                } else {
                    if ($ret !== true) {
                        $status['error'] = $jname . ': ' . JText::sprintf('INSTALL_MODULE_ERROR', $src_archive, $dest);
                    } else {
                        $status['message'] = $jname .': ' . JText::_('INSTALL_MODULE_SUCCESS');
                    }
                }
            }
        }
		return $status;
	}

    /**
     * @return array
     */
    public function uninstallModule() {
        $status = array('error' => array(),'debug' => array());
		jimport ( 'joomla.filesystem.file' );
		jimport ( 'joomla.filesystem.folder' );
		
		$jname =  $this->getJname ();
		$db = JFusionFactory::getDatabase($jname);
		$params = JFusionFactory::getParams ( $jname );
		$source_path = $params->get ( 'source_path' );
		$xmlfile = realpath ( dirname ( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'install_module' . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR . 'listfiles.xml';

	    $listfiles = JFusionFunction::getXml($xmlfile);
		$files = $listfiles->file;
        /**
         * @ignore
         * @var $file JXMLElement
         */
		foreach($files as $file) {
			$file = (string)$file;
			$file = preg_replace('#/#', DIRECTORY_SEPARATOR, $file);
			@chmod($source_path . DIRECTORY_SEPARATOR . $file, 0777);
			if (!is_dir($source_path . DIRECTORY_SEPARATOR . $file)) {
				JFile::delete($source_path . DIRECTORY_SEPARATOR . $file);
			} else {
				JFolder::delete($source_path . DIRECTORY_SEPARATOR . $file);
			}
		}
		
		$paths = array();
		$paths[] = 'joomla/joomlaconfig/baseurl';
		$paths[] = 'joomla/joomlaconfig/installationpath';
		$paths[] = 'joomla/joomlaconfig/secret_key';
		
		foreach($paths as $path) {
			$query = 'DELETE FROM #__core_config_data WHERE path = ' . $db->Quote($path);
			$db->transactionStart();
			$db->setQuery($query);
			$db->execute();
			if ($db->getErrorNum() != 0) {
				$db->transactionRollback();
				$status['error'] = $db->stderr();
                break;
			}
		}
		
		/*
		$query = 'DELETE FROM #__core_config_data WHERE path = \'joomla/joomlaconfig/installationpath\'';
		$db->transactionStart();
		$db->setQuery($query);
        $db->execute();
		if ($db->getErrorNum() != 0) {
			$db->transactionRollback();
			$status['error'] = $db->stderr ();
			return $status;
		}
		
		$query = 'DELETE FROM #__core_config_data WHERE path = \'joomla/joomlaconfig/secret_key\'';
		$db->transactionStart();
		$db->setQuery($query);
        $db->execute();
		if ($db->getErrorNum() != 0) {
			$db->transactionRollback();
			$status['error'] = $db->stderr ();
			return $status;
		}
		*/

        if (empty($status['error'])) {
            $status['message'] = $jname .': ' . JText::_('UNINSTALL_MODULE_SUCCESS');
        }
        return $status;
	}

    /**
     * @return mixed|string
     */
    public function moduleActivation() {
		$jname =  $this->getJname ();
		$params = JFusionFactory::getParams ( $jname );
		$source_path = $params->get ( 'source_path' );
		
		$jfusion_mod_xml = $source_path . DIRECTORY_SEPARATOR .'app'. DIRECTORY_SEPARATOR .'etc'. DIRECTORY_SEPARATOR .'modules'. DIRECTORY_SEPARATOR .'Jfusion_All.xml';
		
		if(file_exists($jfusion_mod_xml)) {
			$xml = JFusionFunction::getXml($jfusion_mod_xml);

			$modules = $xml->modules->jfusion_joomla->active;
			$activated = (string)$modules;
			
			if($activated == 'false') {
				$activated = 0;
			} else {
				$activated = 1;
			}

            if ($activated) {
                $src = 'components/com_jfusion/images/tick.png';
                $text = JText::_ ( 'MODULE_DEACTIVATION_BUTTON' );
            } else {
                $src = 'components/com_jfusion/images/cross.png';
                $text = JText::_ ( 'MODULE_ACTIVATION_BUTTON' );
            }

            $html = <<<HTML
			    <div class="button2-left">
			        <div class="blank">
			            <a href="javascript:void(0);"  onclick="return JFusion.module('activateModule');">{$text}</a>
			        </div>
			    </div>
			    <input type="hidden" name="activation" id="activation" value="{$activated}"/>

			    <img src="{$src}" style="margin-left:10px;"/>
HTML;
		} else {
			$html =  JText::_ ( 'MAGE_CONFIG_FIRST' );
		}
        return $html;
	}
	
	public function activateModule(){
		
		jimport ( 'joomla.filesystem.file' );
		
		$activation = ((JFactory::getApplication()->input->get('activation', 1))?'true':'false');
		$jname =  $this->getJname ();
		$params = JFusionFactory::getParams ( $jname );
		$source_path = $params->get ( 'source_path' );
		$jfusion_mod_xml = $source_path . DIRECTORY_SEPARATOR .'app'. DIRECTORY_SEPARATOR .'etc'. DIRECTORY_SEPARATOR .'modules'. DIRECTORY_SEPARATOR .'Jfusion_All.xml';

		$xml = JFusionFunction::getXml($jfusion_mod_xml);

		$module = $xml->modules->jfusion_joomla->active;
			
		//$xml->document->modules->jfusion_joomla->active[0]->setData('false');
		$module->setData($activation);

		$buffer = '<?xml version="1.0"?'.'>';
		$buffer .= $xml->toString();
		JFile::write($jfusion_mod_xml, $buffer);
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

    /**
     * do plugin support multi usergroups
     *
     * @return string UNKNOWN or JNO or JYES or ??
     */
    function requireFileAccess()
	{
		return 'JNO';
	}	
}