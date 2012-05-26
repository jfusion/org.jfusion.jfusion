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
    function getTablename() {
        return 'admin_user';
    }
    function setupFromPath($forumPath) {
        //check for trailing slash and generate file path
        if (substr($forumPath, -1) != DS) {
            $forumPath = $forumPath . DS;
        }
        $xmlfile = $forumPath . 'app' . DS . 'etc' . DS . 'local.xml';
        if (file_exists($xmlfile)) {
            $xml = JFactory::getXMLParser('Simple');
            if (!$xml->loadFile($xmlfile)) {
                unset($xml);
                JError::raiseWarning(500, JText::_('WIZARD_FAILURE') . " $xmlfile " . JText::_('WIZARD_MANUAL'));
                $result = false;
                return $result;
            }
            //save the parameters into array
            $params = array();
            $params['database_host'] = (string)$xml->document->global[0]->resources[0]->default_setup[0]->connection[0]->host[0]->data();
            $params['database_name'] = (string)$xml->document->global[0]->resources[0]->default_setup[0]->connection[0]->dbname[0]->data();
            $params['database_user'] = (string)$xml->document->global[0]->resources[0]->default_setup[0]->connection[0]->username[0]->data();
            $params['database_password'] = (string)$xml->document->global[0]->resources[0]->default_setup[0]->connection[0]->password[0]->data();
            $params['database_prefix'] = (string)$xml->document->global[0]->resources[0]->db[0]->table_prefix[0]->data();
            $params['database_type'] = "mysql";
            $params['source_path'] = $forumPath;
            unset($xml);
            return $params;
        } else {
            JError::raiseWarning(500, JText::_('WIZARD_FAILURE') . " $xmlfile " . JText::_('WIZARD_MANUAL'));
            $result = false;
            return $result;
        }
    }
    function getUserList($start = 0, $count = '') {
        //getting the connection to the db
        $db = JFusionFactory::getDataBase($this->getJname());
        $query = 'SELECT email as username, email from #__customer_entity';
        if (!empty($count)) {
            $query.= ' LIMIT ' . $start . ', ' . $count;
        }
        $db->setQuery($query);
        //getting the results
        $userlist = $db->loadObjectList();
        return $userlist;
    }
    function getUserCount() {
        //getting the connection to the db
        $db = JFusionFactory::getDataBase($this->getJname());
        $query = 'SELECT count(*) from #__customer_entity';
        $db->setQuery($query);
        //getting the results
        $no_users = $db->loadResult();
        return $no_users;
    }
    function getUsergroupList() {
        //get the connection to the db
        $db = JFusionFactory::getDataBase($this->getJname());
        $query = 'SELECT customer_group_id as id, customer_group_code as name from #__customer_group;';
        $db->setQuery($query);
        //getting the results
        return $db->loadObjectList();
    }
    function getDefaultUsergroup() {
        $params = JFusionFactory::getParams($this->getJname());
        $usergroup_id = $params->get('usergroup');
        //we want to output the usergroup name
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT customer_group_code from #__customer_group WHERE customer_group_id = ' . (int)$usergroup_id;
        $db->setQuery($query);
        return $db->loadResult();
    }
    function allowEmptyCookiePath() {
        return true;
    }
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
            JError::raiseWarning(0, $jname . ': ' . JText::_('MAGENTO_NEED_API_USER'));
        } else {
            // check if we have valid parameters  for apiuser and api key
            $params = JFusionFactory::getParams($this->getJname());
            $apiuser = $params->get('apiuser');
            $apikey = $params->get('apikey');
            if (!$apiuser || !$apikey) {
                JError::raiseWarning(0, $jname . '-plugin: ' . JText::_('MAGENTO_NO_API_DATA'));
            } else {
                //finally check if the apiuser and apikey are valid
                $query = 'SELECT api_key FROM #__api_user WHERE username = ' . $db->Quote($apiuser);
                $db->setQuery($query);
                $api_key = $db->loadResult();
                $hashArr = explode(':', $api_key);
                $api_key = $hashArr[0];
                $api_salt = $hashArr[1];
                if ($api_salt) {
                    $params_hash = md5($api_salt . $apikey);
                } else {
                    $params_hash = md5($apikey);
                }
                if ($params_hash != $api_key) {
                    JError::raiseWarning(0, $jname . '-plugin: ' . JText::_('MAGENTO_WRONG_APIUSER_APIKEY_COMBINATION'));
                }
            }
        }
        // check the user_remote_addr security settings
        $query = "SELECT  value FROM #__core_config_data WHERE path = 'web/session/use_remote_addr'";
        $db->setQuery($query);
        if ($db->getErrorNum() == 0) {
            $value = $db->loadResult();
            if ($value) {
                JError::raiseWarning(0, $jname . ': ' . JText::_('MAGENTO_USE_REMOTE_ADDRESS_NOT_DISABLED'));
            }
        }
        // we need to have the curl library installed
        if (!extension_loaded('curl')) {
            JError::raiseWarning(0, $jname . ': ' . JText::_('CURL_NOTINSTALLED'));
        }
    }
    function allowRegistration() {
        $result = true;
        $params = JFusionFactory::getParams($this->getJname());
        $registration_disabled = $params->get('disabled_registration');
		if ($registration_disabled){$result = false;}
		return $result;
	}
	/**
	 * Put specific javascript script 
	 */
	public function outputJavascript(){
$js = <<< EOD
<script language="javascript" type="text/javascript">
<!--
function module(action) {
var form = document.adminForm;
form.customcommand.value = action;
form.action.value = 'apply';
submitform('saveconfig');
return;
}

//-->
</script>
EOD;
	return $js;
	}
	
	public function moduleInstallation() {
			$jname = $this->getJname ();
			$params = & JFusionFactory::getParams ( $jname );
			
			$db = & JFusionFactory::getDatabase ( $jname );
			if (! JError::isError ( $db ) && ! empty ( $db )) {
				
				$source_path = $params->get ( 'source_path', '' );
				if (! file_exists ( $source_path . DS . 'app' . DS . 'Mage.php' )) {
					return JText::_ ( 'MAGE_CONFIG_SOURCE_PATH' );
				}
				
				$js = $this->outputJavascript ();
				$document = &JFactory::getDocument ();
				$document->addCustomTag ( $js );
				
				$mod_exists = false;
				if (file_exists ( $source_path . DS . 'app' . DS . 'etc' . DS . 'modules' . DS . 'Jfusion_All.xml' )) {
					$mod_exists = true;
				}
				
				$html = '<div class="button2-left"><div class="blank"><a href="javascript:void(0);" onclick="return module(\'' . (($mod_exists) ? 'uninstallModule' : 'installModule') . '\');">' . ((! $mod_exists) ? JText::_ ( 'MODULE_UNINSTALL_BUTTON' ) : JText::_ ( 'MODULE_INSTALL_BUTTON' )) . '</a></div></div>' . "\n";
				
				if ($mod_exists) {
					$src = "components/com_jfusion/images/tick.png";
				} else {
					$src = "components/com_jfusion/images/cross.png";
				}
				$html .= "<img src='$src' style='margin-left:10px;' id='usergroups_img'/>";
				return $html;
			} else {
				return JText::_ ( 'MAGE_CONFIG_FIRST' );
		}
	}
	
public function installModule() {
		
		$jname =  $this->getJname ();
		$db = JFusionFactory::getDatabase($jname);
		$params = JFusionFactory::getParams ( $jname );
		$source_path = $params->get ( 'source_path' );
		jimport ( 'joomla.filesystem.archive' );
		jimport ( 'joomla.filesystem.file' );
        $pear_path = realpath ( dirname ( __FILE__ ) ) . DS .'..'.DS.'..'.DS.'models'.DS. 'pear';
        require_once $pear_path.DS.'PEAR.php';
        $pear_archive_path = $pear_path.DS.archive_tar.DS.'Archive_Tar.php';
        require_once $pear_archive_path;
 
        $status = array();
		$archive_filename = 'magento_module_jfusion.tar.gz';
		$old_chdir = getcwd();
		$src_archive =  $src_path = realpath ( dirname ( __FILE__ ) ) . DS . 'install_module';
		$src_code =  $src_archive . DS . 'source';
		$dest = $source_path;
		
		// Create an archive to facilitate the installation into the Magento installation while extracting
		chdir($src_code);
		$tar = new Archive_Tar( $archive_filename, 'gz' );
		$tar->setErrorHandling(PEAR_ERROR_PRINT);
		$tar->createModify( 'app' , '', '' );
		chdir($old_chdir);
		
		$ret = JArchive::extract ( $src_code . DS . $archive_filename, $dest );
		JFile::delete($src_code . DS . $archive_filename);
		
		// Initialize default data config in Magento database
		$joomla = JFusionFactory::getParams('joomla_int');
		$joomla_baseurl = $joomla->get('source_url');
		$joomla_secret = $joomla->get('secret');
		
		$query = "REPLACE INTO #__core_config_data SET path = 'joomla/joomlaconfig/baseurl', value = '".$joomla_baseurl."';";
		$db->BeginTrans();
		$db->Execute($query);
		if ($db->getErrorNum() != 0) {
			$db->RollbackTrans();
			$status['error'] = $db->stderr ();
			return $status;
		}
		
		$query = "REPLACE INTO #__core_config_data SET path = 'joomla/joomlaconfig/installationpath', value = '".JPATH_SITE."';";
		$db->BeginTrans();
		$db->Execute($query);
		if ($db->getErrorNum() != 0) {
			$db->RollbackTrans();
			$status['error'] = $db->stderr ();
			return $status;
		}
		
		$query = "REPLACE INTO #__core_config_data SET path = 'joomla/joomlaconfig/secret_key', value = '".$joomla_secret."';";
		$db->BeginTrans();
		$db->Execute($query);
		if ($db->getErrorNum() != 0) {
			$db->RollbackTrans();
			$status['error'] = $db->stderr ();
			return $status;
		}
		
		$status = array();
		if ($ret !== true) {
			$status['error'] = $jname . ': ' . JText::sprintf('INSTALL_MODULE_ERROR', $src_archive, $dest);
		}else{
			$status['message'] = $jname .': ' . JText::_('INSTALL_MODULE_SUCCESS');
		}
		
		return $status;
	}
	
	public function uninstallModule(){
		
		jimport ( 'joomla.filesystem.file' );
		jimport ( 'joomla.filesystem.folder' );
		
		$jname =  $this->getJname ();
		$db = JFusionFactory::getDatabase($jname);
		$params = JFusionFactory::getParams ( $jname );
		$source_path = $params->get ( 'source_path' );
		$xmlfile = realpath ( dirname ( __FILE__ ) ) . DS . 'install_module' . DS . 'source' . DS . 'listfiles.xml';
		
		$listfiles = JFactory::getXMLParser('simple');
		$listfiles->loadFile($xmlfile);
		$files = $listfiles->document->file;
		
		foreach($files as $file){
			$file = $file->data();
			$file = preg_replace('#/#', DS, $file);
			@chmod($source_path . DS . $file, 0777);
			if(!is_dir($source_path . DS . $file)){
				JFile::delete($source_path . DS . $file);
			}else{
				JFolder::delete($source_path . DS . $file);
			}
		}
		
		$paths = array();
		$paths[] = 'joomla/joomlaconfig/baseurl';
		$paths[] = 'joomla/joomlaconfig/installationpath';
		$paths[] = 'joomla/joomlaconfig/secret_key';
		
		foreach($paths as $path)
		{
			$query = "DELETE FROM #__core_config_data WHERE path = " . $db->Quote($path);
			$db->BeginTrans ();
			$db->Execute ( $query );
			if ($db->getErrorNum() != 0) {
				$db->RollbackTrans ();
				$status ['error'] = $db->stderr ();
				return $status;
			}
		}
		
		/*$query = "DELETE FROM #__core_config_data WHERE path = 'joomla/joomlaconfig/installationpath'";
		$db->BeginTrans();
		$db->Execute($query);
		if ($db->getErrorNum() != 0) {
			$db->RollbackTrans();
			$status['error'] = $db->stderr ();
			return $status;
		}
		
		$query = "DELETE FROM #__core_config_data WHERE path = 'joomla/joomlaconfig/secret_key'";
		$db->BeginTrans();
		$db->Execute($query);
		if ($db->getErrorNum() != 0) {
			$db->RollbackTrans();
			$status['error'] = $db->stderr ();
			return $status;
		}*/
		
		$status = array();
		if ($ret !== true) {
			$status['error'] = $jname . ': ' . JText::sprintf('UNINSTALL_MODULE_ERROR', $src_archive, $dest);
		}else{
			$status['message'] = $jname .': ' . JText::_('UNINSTALL_MODULE_SUCCESS');
		}
        return $status;
	}

public function moduleActivation() {
		$jname =  $this->getJname ();
		$params = JFusionFactory::getParams ( $jname );
		$source_path = $params->get ( 'source_path' );
		
		$jfusion_mod_xml = $source_path . DS .'app'. DS .'etc'. DS .'modules'. DS .'Jfusion_All.xml';
		
		if(file_exists($jfusion_mod_xml)){
			$xml = JFactory::getXMLParser ( 'simple' );
			$xml->loadfile ( $jfusion_mod_xml );
			$modules = $xml->document->getElementByPath ( 'modules/jfusion_joomla' );
			$activated = $modules->active [0]->data ();
			
			if($activated == 'false'){
				$activated = 0;
			}else{
				$activated = 1;
			}
			
			$js = $this->outputJavascript();
			$document = &JFactory::getDocument();
			$document->addCustomTag($js);
			
			$html = '<div class="button2-left"><div class="blank"><a href="javascript:void(0);"  onclick="return module(\'activateModule\');">' . ((!$activated)?JText::_ ( 'MODULE_DEACTIVATION_BUTTON' ):JText::_ ( 'MODULE_ACTIVATION_BUTTON' )) . '</a></div></div>' . "\n";
			$html .= '<input type="hidden" name="activation" id="activation" value="'.(($activated)?0:1).'"/>';
			
			if ($activated ) {
				$src = "components/com_jfusion/images/tick.png";
			} else {
				$src = "components/com_jfusion/images/cross.png";
			}
			$html .= "<img src='$src' style='margin-left:10px;'/>";

			return $html;
		} else {
			return JText::_ ( 'MAGE_CONFIG_FIRST' );
		}
	}
	
	public function activateModule(){
		
		jimport ( 'joomla.filesystem.file' );
		
		$activation = ((JRequest::getVar('activation', 1))?'true':'false');
		$jname =  $this->getJname ();
		$params = JFusionFactory::getParams ( $jname );
		$source_path = $params->get ( 'source_path' );
		$jfusion_mod_xml = $source_path . DS .'app'. DS .'etc'. DS .'modules'. DS .'Jfusion_All.xml';
		
		$xml = JFactory::getXMLParser ( 'simple' );
		$xml->loadfile ( $jfusion_mod_xml );
		$module = $xml->document->getElementByPath ( 'modules/jfusion_joomla' );
			
		//$xml->document->modules->jfusion_joomla->active[0]->setData('false');
		$module->active[0]->setData($activation);

		$buffer = '<?xml version="1.0"?'.'>';
		$buffer .= $xml->document->toString();
		JFile::write($jfusion_mod_xml, $buffer);
	}

	/*
	 * do plugin support multi usergroups
	 * return UNKNOWN for unknown
	 * return JNO for NO
	 * return JYES for YES
	 * return ... ??
	 */
	function requireFileAccess()
	{
		return 'JNO';
	}	
}