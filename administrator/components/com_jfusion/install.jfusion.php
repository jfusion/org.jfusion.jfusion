<?php

/**
 * Installer file
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Install
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'defines.php';
/**
 * @return bool
 */
function com_install() {
	$return = true;

	//find out where we are
	$basedir = dirname(__FILE__);

	//load language file
	$lang = JFactory::getLanguage();
	$lang->load('com_jfusion', JPATH_BASE);

	//see if we need to create SQL tables
	$db = JFactory::getDBO();
	$table_list = $db->getTableList();
	$table_prefix = $db->getPrefix();
	//NOTE moved these before the jfusion table as some tables did not exist in old verions of JFusion thus leading to errors during the upgrade process
	//create the jfusion_users table if it does not exist already
	if (array_search($table_prefix . 'jfusion_users', $table_list) == false) {
		$query = 'CREATE TABLE #__jfusion_users (
      id int(11) NOT null,
      username varchar(50),
      PRIMARY KEY (id)
    ) DEFAULT CHARACTER SET utf8;';
		$db->setQuery($query);
		if (!$db->query()) {
			echo $db->stderr() . '<br />';
			$return = false;
			return $return;
		}
	}
	//create the jfusion_user_plugin table if it does not exist already
	if (array_search($table_prefix . 'jfusion_users_plugin', $table_list) == false) {
		$query = 'CREATE TABLE #__jfusion_users_plugin (
      autoid int(11) NOT null auto_increment,
      id int(11) NOT null,
      username varchar(50),
      userid varchar(50) NOT null,
      jname varchar(50) NOT null,
      PRIMARY KEY (autoid),
      UNIQUE `lookup` (id,jname)
    ) DEFAULT CHARACTER SET utf8;';
		$db->setQuery($query);
		if (!$db->query()) {
			echo $db->stderr() . '<br />';
			$return = false;
			return $return;
		}
	}
	//create the jfusion_discussion_bot table if it does not exist already
	if (array_search($table_prefix . 'jfusion_discussion_bot',$table_list) == false) {
		$query = 'CREATE TABLE IF NOT EXISTS #__jfusion_discussion_bot (
    	contentid int(11) NOT NULL,
    	component varchar(255) NOT NULL,
    	forumid int(11) NOT NULL,
    	threadid int(11) NOT NULL,
    	postid int(11) NOT NULL,
    	jname varchar(255) NOT NULL,
    	modified int(11) NOT NULL default 0,
    	published INT( 1 ) NOT NULL default 1,
    	manual INT( 1 ) NOT NULL DEFAULT 0,
    	UNIQUE `lookup` (contentid,jname)
    ) CHARSET=utf8;';
		$db->setQuery($query);
		if (!$db->query()){
			echo $db->stderr() . '<br/>';
			$return = false;
			return $return;
		}
	}
	//create the jfusion_sync table if it does not exist already
	if (array_search($table_prefix . 'jfusion_sync', $table_list) == false) {
		$query = 'CREATE TABLE #__jfusion_sync (
      syncid varchar(10),
      action varchar(255),
      active int(1) NOT NULL DEFAULT 0,
      syncdata longblob,
      time_start int(8),
      time_end int(8),
      PRIMARY KEY  (syncid)
    );';
		$db->setQuery($query);
		if (!$db->query()) {
			echo $db->stderr() . '<br />';
			$return = false;
			return $return;
		}
	}
	//create the jfusion_sync_log table if it does not exist already
	if (array_search($table_prefix . 'jfusion_sync_details',$table_list) == false) {
		$query = 'CREATE TABLE #__jfusion_sync_details (
      id int(11) NOT NULL auto_increment,
      syncid varchar(10),
      jname varchar(255),
      username varchar(255),
      email varchar(255),
      action varchar(255),
      message varchar(255),
      data longblob,
      PRIMARY KEY  (id)
    );';
		$db->setQuery($query);
		if (!$db->query()){
			echo $db->stderr() . '<br/>';
			$return = false;
			return $return;
		}
	}
	//create the jfusion table if it does not exist already
	if (array_search($table_prefix . 'jfusion', $table_list) == false) {
        $jfusionupgrade = 0;
		$batch_query = "CREATE TABLE #__jfusion (
        id int(11) NOT null auto_increment,
        name varchar(50) NOT null,
        params text,
        master tinyint(4) NOT null,
        slave tinyint(4) NOT null,
        status tinyint(4) NOT null,
        dual_login tinyint(4) NOT null,
        check_encryption tinyint(4) NOT null,
        activity tinyint(4) NOT null,
        search tinyint(4) NOT null DEFAULT 0,
        discussion tinyint(4) NOT null DEFAULT 0,
        plugin_files LONGBLOB,
        original_name varchar(50) null,
        ordering tinyint(4),
        PRIMARY KEY  (id)
      );

      INSERT INTO #__jfusion  (name , params,  slave, dual_login, status, check_encryption, activity, search, discussion) VALUES
      ('joomla_int', 0, 0, 0, 0, 0, 0, 0, 0);
      ";
		$db->setQuery($batch_query);
		if (!$db->queryBatch()) {
			echo $db->stderr() . '<br />';
			$return = false;
			return $return;
		}
	} else {
		//this is an upgrade
		$jfusionupgrade = 1;

		//list of default plugins
		$defaultPlugins = array('joomla_int');
		//make sure default plugins are installed
		$query = "SELECT name FROM #__jfusion";
		$db->setQuery($query);
		$installedPlugins = $db->loadResultArray();
		$pluginSql = array();
		foreach ($defaultPlugins as $plugin) {
			if (!in_array($plugin, $installedPlugins)) {
				if ($plugin == 'joomla_int') {
					$pluginSql[] = "('joomla_int', 0, 0,  0, 0,  0, 0, 0, 0)";
				}
			}
		}
		/***
		 * UPGRADES FOR 1.1.0 Patch 2
		 ***/
		//see if the columns exists
		$query = "SHOW COLUMNS FROM #__jfusion";
		$db->setQuery($query);
		$columns = $db->loadResultArray();
		//check to see if the description column exists, if it does remove all pre 1.1.0 Beta Patch 2 columns
		if (in_array('description', $columns)) {
			$query = "ALTER TABLE #__jfusion DROP COLUMN version, DROP COLUMN description, DROP COLUMN date, DROP COLUMN author, DROP COLUMN support";
			$db->setQuery($query);
			if (!$db->query()) {
				echo $db->stderr() . '<br />';
				$return = false;
				return $return;
			}
		}
		/***
		 * UPGRADES FOR 1.1.1 Beta
		 ***/
		//add the plugin_files and original columns if it does not exist
		if (!in_array('plugin_files', $columns)) {
			//add the column
			$query = "ALTER TABLE #__jfusion
              ADD COLUMN plugin_files LONGBLOB ";
			$db->setQuery($query);
			if (!$db->query()) {
				echo $db->stderr() . '<br />';
				$return = false;
				return $return;
			}
		}
		if (!in_array('original_name', $columns)) {
			//add the column
			$query = "ALTER TABLE #__jfusion ADD COLUMN original_name varchar(50) null";
			$db->setQuery($query);
			if (!$db->query()) {
				echo $db->stderr() . '<br />';
				$return = false;
				return $return;
			}
		}		
		
		/***
		 * UPGRADES FOR 1.1.2 Beta
		 ***/
		//add the search and discussion columns
		if (!in_array('search', $columns)) {
			$query = "ALTER TABLE #__jfusion
              ADD COLUMN search tinyint(4) NOT null DEFAULT 0,
                ADD COLUMN discussion tinyint(4) NOT null DEFAULT 0";
			$db->setQuery($query);
			if (!$db->query()) {
				echo $db->stderr() . '<br />';
				$return = false;
				return $return;
			}
		}

		$query = "SHOW INDEX FROM #__jfusion_users_plugin";
		$db->setQuery($query);
		$indexes = $db->loadObjectList("Key_name");
		if (!array_key_exists('lookup', $indexes)) {
			//we need to make sure that old jfusion_users_plugin table doesn't have duplicates
			//in prep of adding an unique index
			$query = "CREATE TABLE #__jfusion_users_plugin_backup AS
              SELECT * FROM  #__jfusion_users_plugin WHERE 1 GROUP BY id, jname";
			$db->setQuery($query);
			if ($db->query()) {
				$query = "DROP TABLE #__jfusion_users_plugin";
				$db->setQuery($query);
				if ($db->query()) {
					$query = "RENAME TABLE #__jfusion_users_plugin_backup TO #__jfusion_users_plugin";
					$db->setQuery($query);
					if (!$db->query()) {
						echo $db->stderr() . '<br />';
						$return = false;
						return $return;
					}
				} else {
					echo $db->stderr() . '<br />';
					$return = false;
					return $return;
				}
			} else {
				echo $db->stderr() . '<br />';
				$return = false;
				return $return;
			}
			//in addition the unique indexes we need to change the userid column to accept text as
			//plugins such as dokuwiki does not use int userids
			$query = "ALTER TABLE #__jfusion_users_plugin
              ADD UNIQUE `lookup` (id,jname),
              ADD PRIMARY KEY ( `autoid` ),
              CHANGE `autoid` `autoid` INT( 11 ) NOT null AUTO_INCREMENT,
              CHANGE `userid` `userid` VARCHAR(50) NOT null";
			$db->setQuery($query);
			if (!$db->query()) {
				echo $db->stderr() . '<br />';
				$return = false;
				return $return;
			}
		}
		//make sure that the slave and dual_login capabilties of the joomla_ext plugin is enabled
		$query = 'UPDATE #__jfusion SET slave = 0 WHERE name = \'joomla_ext\' AND slave = 3';
		$db->setQuery($query);
		$db->query();
		$query = 'UPDATE #__jfusion SET dual_login = 0 WHERE name = \'joomla_ext\' AND dual_login = 3';
		$db->setQuery($query);
		$db->query();
		//we need to remove a couple parameter files if they exists to prevent duplicates from showing up
		$files2delete = array(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'config.xml', JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'com_jfusion.xml');
		foreach ($files2delete as $f) {
			if (file_exists($f)) {
				if (!JFile::delete($f)) {
					JError::raiseWarning(500, JText::sprintf('UPGRADE_UNABLE_TO_REMOVE_FILE', $f));
				}
			}
		}
		/***
		 * UPGRADES FOR 1.1.4/1.2
		 */
		$query = "ALTER TABLE `#__jfusion_sync` CHANGE `syncdata` `syncdata` LONGBLOB null DEFAULT null ";
		$db->setQuery($query);
		if (!$db->query()) {
			echo $db->stderr() . '<br />';
			$return = false;
			return $return;
		}
		/***
		 * UPGRADES FOR 1.1.2 Stable
		 ***/
		//make id the primary key so that the username will be updated
		$query = "ALTER TABLE `#__jfusion_users` DROP PRIMARY KEY, ADD PRIMARY KEY ( `id` )";
		$db->setQuery($query);
		if (!$db->query()) {
			echo $db->stderr() . '<br />';
			$return = false;
			return $return;
		}

		/**
		 * UPGRADES FOR 1.5
		 */
		//add a active column for user sync
		$query = "SHOW COLUMNS FROM #__jfusion_sync";
		$db->setQuery($query);
		$columns = $db->loadResultArray();
		if (!in_array('active', $columns)) {
			$query = "ALTER TABLE #__jfusion_sync
              ADD COLUMN active int(1) NOT null DEFAULT 0";
			$db->setQuery($query);
			if (!$db->query()) {
				echo $db->stderr() . '<br />';
				$return = false;
				return $return;
			}
		}

		/**
		 * UPGRADES FOR 1.6
		 */

		//add a active column for user sync
		$query = "SHOW COLUMNS FROM #__jfusion";
		$db->setQuery($query);
		$columns = $db->loadResultArray();
		if (!in_array('ordering', $columns)) {
			$query = "ALTER TABLE #__jfusion
              ADD COLUMN ordering int(4)";
			$db->setQuery($query);
			if (!$db->query()) {
				echo $db->stderr() . '<br />';
				$return = false;
				return $return;
			}
		}
		
		/**
		 * UPGRADES FOR 1.8
		 */
		$dir = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'plugins';
		if (JFolder::exists($dir)) {
			$folders = JFolder::folders($dir);
			$results = true;
			foreach ($folders as $folder) {
				if ($folder != 'joomla_int' && !JFolder::exists(JFUSION_PLUGIN_PATH .DS. $folder) ) {
					$r = JFolder::copy($dir .DS. $folder, JFUSION_PLUGIN_PATH .DS. $folder);
					if ($results===true) {
						$results = $r;
					}
				}
			}
			if ($results===true) {
				JFolder::delete($dir);
			}
		}

		//migrate from #__jfusion_forum_plugin to #__jfusion_discussion_bot
		//check to see if #__jfusion_forum_plugin exists indicating that #__jfusion_discussion_bot has not been populated
		if(array_search($table_prefix . 'jfusion_forum_plugin',$table_list)) {
			$query = "SELECT * FROM #__jfusion_forum_plugin";
			$db->setQuery($query);
			$results = $db->loadObjectList();

			$query = "SHOW COLUMNS FROM #__jfusion_forum_plugin";
			$db->setQuery($query);
			$columns = $db->loadResultArray();

			$row_inserts = array();
			foreach($results as $result) {
				$col_inserts = array();
				foreach($columns as $column) {
					$col_inserts[] = $db->Quote($result->$column);
				}
				$row_inserts[] = "(" . implode(", ", $col_inserts) . ")";
			}

			if(!empty($row_inserts)) {
				$query = "REPLACE INTO #__jfusion_discussion_bot (" . implode(", ", $columns) . ") VALUES " . implode(", ", $row_inserts);
				$db->setQuery($query);
				if(!$db->query()) {
					echo $db->stderr() . '<br />';
					$migrate_success = false;
				} else {
					$migrate_success = true;
				}
			} else {
				$migrate_success = true;
			}

			if($migrate_success) {
				//add com_content to components column
				$query = "UPDATE #__jfusion_discussion_bot SET component = 'com_content'";
				$db->setQuery($query);
				if(!$db->query()) {
					echo $db->stderr() . '<br />';
				}

				$query = "DROP TABLE #__jfusion_forum_plugin";
				$db->setQuery($query);
				if(!$db->query()) {
					echo $db->stderr() . '<br />';
					$return = false;
					return $return;
				}
			} else {
				$return = false;
				return $return;
			}
		} else {
			//check to make sure there is a components column in the discussion_bot table
			$query = "SHOW COLUMNS FROM #__jfusion_discussion_bot";
			$db->setQuery($query);
			$columns = $db->loadResultArray();

			if (!in_array('component', $columns)) {
				$query = " ALTER TABLE #__jfusion_discussion_bot ADD COLUMN component varchar(255) NOT NULL";
				$db->setQuery($query);
				if (!$db->query()) {
					echo $db->stderr() . '<br />';
				} else {
					$query = "UPDATE #__jfusion_discussion_bot SET component = 'com_content'";
					$db->setQuery($query);
					if(!$db->query()) {
						echo $db->stderr() . '<br />';
					}
				}
			}
		}

		/****
		 * General for all upgrades
		 ***/
		//insert of missing plugins
		//#chris: moved after table modification to prevent errors
		if (count($pluginSql) > 0) {
			$query = "INSERT INTO #__jfusion  (name, params,  slave, dual_login, status,  check_encryption, activity, search, discussion) VALUES " . implode(', ', $pluginSql);
			$db->setQuery($query);
			if (!$db->query()) {
				echo $db->stderr() . '<br />';
				$return = false;
				return $return;
			}
		}
		//update plugins with search and discuss bot capabilities
		$query = "UPDATE #__jfusion SET search = 1, discussion = 1 WHERE name IN ('vbulletin','phpbb3','smf')";
		$db->setQuery($query);
		if (!$db->query()) {
			echo $db->stderr() . '<br />';
			$return = false;
			return $return;
		}

		//restore deleted plugins if possible and applicable
		//get a list of installed plugins
		$query = "SELECT name, original_name, plugin_files FROM #__jfusion";
		$db->setQuery($query);
		$installedPlugins = $db->loadObjectList();
		
		//stores the plugins that are to be removed from the database during the upgrade process
		$uninstallPlugin = array();
		//stores the reason why the plugin had to be unsinstalled
		$uninstallReason = array();
		//stores plugin names of plugins that was attempted to be restored
		$restorePlugins = array();
		//require the model.install.php file to recreate copied plugins
		include_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.install.php';
		$model = new JFusionModelInstaller();
		foreach ($installedPlugins as $plugin) {
			//attempt to restore missing plugins
			if (!file_exists(JFUSION_PLUGIN_PATH . DS . $plugin->name)) {
				//restore files for custom/copied plugins if available
				$restorePlugins[] = $plugin->name;
				$config = JFactory::getConfig();
				$tmpDir = $config->getValue('config.tmp_path');
				//check to see if this is a copy of a default plugin
				if (in_array($plugin->original_name, $defaultPlugins)) {
					//recreate the copy and update the database
					if (!$model->copy($plugin->original_name, $plugin->name, true)) {
						//the original plugin could not be copied so uninstall the plugin
						$uninstallPlugin[] = $plugin->name;
						$uninstallReason[$plugin->name] = JText::_('UPGRADE_CREATINGCOPY_FAILED');
					}
				} elseif (!empty($plugin->plugin_files)) {
					//save the compressed file to the tmp dir
					$zipfile = $tmpDir . DS . $plugin->name . '.zip';
					if (@JFile::write($zipfile, $plugin->plugin_files)) {
						//decompress the file
						if (!@JArchive::extract($zipfile, JFUSION_PLUGIN_PATH . DS . $plugin->name)) {
							//decompression failed
							$uninstallPlugin[] = $plugin->name;
							$uninstallReason[$plugin->name] = JText::_('UPGRADE_DECOMPRESS_FAILED');
							//remove the file
							unlink($zipfile);
						} else {
							//extra check to make sure the files were decompressed to prevent possible fatal errors
							if (!file_exists(JFUSION_PLUGIN_PATH . DS . $plugin->name)) {
								$uninstallPlugin[] = $plugin->name;
								$uninstallReason[$plugin->name] = JText::_('UPGRADE_DECOMPRESS_FAILED');
							}
							//remove the file
							unlink($zipfile);
						}
					} else {
						//the compressed file was not able to be written to the tmp dir so remove it
						$uninstallPlugin[] = $plugin->name;
						$uninstallReason[$plugin->name] = JText::_('UPGRADE_WRITEFILE_FAILED');
					}
				} else {
					//the backup file was missing so remove plugin
					$uninstallPlugin[] = $plugin->name;
					$uninstallReason[$plugin->name] = JText::_('UPGRADE_NO_BACKUP');
				}
			} elseif (in_array($plugin->original_name, $defaultPlugins)) {
				//we need to upgrade the files of copied plugins
				if (!$model->copy($plugin->original_name, $plugin->name, true)) {
					//the original plugin could not be copied so uninstall the plugin
					$uninstallPlugin[] = $plugin->name;
					$uninstallReason[$plugin->name] = JText::_('UPGRADE_CREATINGCOPY_FAILED');
				}
			}
		}
		//remove bad plugin entries from the table
		if (count($uninstallPlugin) > 0) {
			$query = "DELETE FROM #__jfusion WHERE name IN ('" . implode("', '", $uninstallPlugin) . "')";
			$db->setQuery($query);
			if (!$db->query()) {
				echo $db->stderr() . '<br />';
			}
		}
        $restorePluginOutput = '';
		foreach ($restorePlugins as $plugin) {
			if (!in_array($plugin, $uninstallPlugin)) {
                $color = '#d9f9e2';
                $text = JText::_('RESTORED') . ' ' . $plugin . ' ' . JText::_('SUCCESS');
			} else {
                $color = '#f9ded9';
                $text = JText::_('ERROR') . ' ' . JText::_('RESTORING') . ' ' . $plugin . '. ' . JText::_('UPGRADE_CUSTOM_PLUGIN_FAILED') . ': ' . $uninstallReason[$plugin];
			}

            $restorePluginOutput .= <<<HTML
            <table style="background-color: {$color};" width="100%">
                <tr style="height:30px">
                    <td width="50px">
			            <img src="components/com_jfusion/images/check_bad_small.png">
                    </td>
			        <td>
			            <font size="2">
			                <b>
			                    {$text}
			                </b>
                        </font>
                    </td>
                </tr>
            </table>
HTML;

		}

	    //cleanup unused plugins
	    $query = 'SELECT name from #__jfusion WHERE (params IS NULL OR params = \'\' OR params = \'0\') AND (master = 0 and slave = 0) AND (name NOT LIKE "joomla_int")';
        $db->setQuery($query );
        $rows = $db->loadObjectList();
        $ordering = 1;
        if(!empty($rows)) {
            foreach ($rows as $row) {
                $db->setQuery('DELETE FROM #__jfusion WHERE name = ' . $db->Quote($row->name));
                 if (!$db->query()) {
                     JError::raiseWarning(500,$db->stderr());
                 }
                 $db->setQuery('DELETE FROM #__jfusion_discussion_bot WHERE jname = ' . $db->Quote($row->name));
                 if (!$db->query()) {
                     JError::raiseWarning(500,$db->stderr());
                 }
                 $db->setQuery('DELETE FROM #__jfusion_users_plugin WHERE jname = ' . $db->Quote($row->name));
                 if (!$db->query()) {
                     JError::raiseWarning(500,$db->stderr());
                 }
                 if (JFolder::exists(JFUSION_PLUGIN_PATH . DS . $row->name)) {
                     JFolder::delete(JFUSION_PLUGIN_PATH . DS . $row->name);
                 }
            }
        }
	}

	//output some info to the user

    /**
     * @ignore
     * @var $parser JSimpleXML
     */
	$parser = JFactory::getXMLParser('Simple');
	$parser->loadFile($basedir . DS. 'jfusion.xml');
	?>
    <table>
        <tr>
            <td width="100px">
                <img src="components/com_jfusion/images/jfusion_large.png">
            </td>
            <td width="100px">
                <img src="components/com_jfusion/images/manager.png" height="75" width="75">
            </td>
            <td>
                <h2>
                    <?php echo JText::_('JFUSION') . ' '.$parser->document->getElementByPath('version')->data().' ' . JText::_('INSTALLATION'); ?>
                </h2>
            </td>
        </tr>
    </table>
    <h3>
        <?php echo JText::_('STARTING') . ' ' . JText::_('INSTALLATION') . ' ...' ?>
    </h3>

    <?php
    if(!empty($restorePluginOutput)) {
        echo $restorePluginOutput;
    }
    ?>

    <?php
    //install the JFusion packages
    jimport('joomla.installer.helper');
    $packages['Login Module'] = $basedir . DS . 'packages' . DS . 'jfusion_mod_login.zip';
    $packages['Activity Module'] = $basedir . DS . 'packages' . DS . 'jfusion_mod_activity.zip';
    $packages['User Activity Module'] = $basedir . DS . 'packages' . DS . 'jfusion_mod_user_activity.zip';
    $packages['Whos Online Module'] = $basedir . DS . 'packages' . DS . 'jfusion_mod_whosonline.zip';
    $packages['User Plugin'] = $basedir . DS . 'packages' . DS . 'jfusion_plugin_user.zip';
    $packages['Authentication Plugin'] = $basedir . DS . 'packages' . DS . 'jfusion_plugin_auth.zip';
    $packages['Search Plugin'] = $basedir . DS . 'packages' . DS . 'jfusion_plugin_search.zip';
    $packages['System Plugin'] = $basedir . DS . 'packages' . DS . 'jfusion_plugin_system.zip';
    $packages['Discussion Bot'] = $basedir . DS . 'packages' . DS . 'jfusion_plugin_content.zip';


    foreach ($packages as $name => $filename) {
        $package = JInstallerHelper::unpack($filename);
        $tmpInstaller = new JInstaller();
        if (!$tmpInstaller->install($package['dir'])) { ?>

    <table style="background-color:#f9ded9;width:100%;">
        <tr style="height: 30px">
            <td width="50px">
                <img src="components/com_jfusion/images/check_bad_small.png">
            </td>
            <td>
                <font size="2">
                    <b>
                        <?php echo JText::_('ERROR') . ' ' . JText::_('INSTALLING') . ' ' . JText::_('JFUSION') . ' ' . $name; ?>
                    </b>
                </font>
            </td>
        </tr>
    </table>
        <?php
        }
        unset($package, $tmpInstaller);
    }
    ?>
    <table style="background-color:#d9f9e2;width:100%;">
        <tr>
            <td width="50px">
                <img src="components/com_jfusion/images/check_good_small.png">
            </td>
            <td>
                <font size="2">
                    <b>
                        <?php
                        if ($jfusionupgrade == 1) {
                            echo JText::_('JFUSION') . ' ' . JText::_('UPDATE') . ' ' .JText::_('SUCCESS');
                        } else {
                            echo JText::_('JFUSION') . ' ' . JText::_('INSTALL') . ' ' .JText::_('SUCCESS');
                        }
                        ?>
                    </b>
                </font>
            </td>
        </tr>
    </table>

    <?php
    $jfusion_plugins = array();
    $jfusion_plugins['dokuwiki'] = 'A standards compliant, simple to use Wiki.';
    $jfusion_plugins['efront'] = 'A modern learning system, bundled with key enterprise functionality.';
    $jfusion_plugins['elgg'] = 'A leading open source social networking engine.';
    $jfusion_plugins['gallery2'] = 'An open source web based photo album organizer.';
    $jfusion_plugins['joomla_ext'] = 'Plugin to connect multiple Joomla sites.';
    $jfusion_plugins['magento'] = 'A open source based ecommerce web application.';
    $jfusion_plugins['mediawiki'] = 'Popular wiki that also powers wikipedia.';
    $jfusion_plugins['moodle'] = 'Open-source community-based tools for learning.';
    $jfusion_plugins['mybb'] = 'A free PHP and MySQL based discussion system.';
    $jfusion_plugins['oscommerce'] = 'Open source online shop e-commerce solution.';
    $jfusion_plugins['phpbb3'] = 'A free and open source forum software.';
    $jfusion_plugins['prestashop'] = 'A free open-source e-commerce software.';
    $jfusion_plugins['smf'] = 'All in one package, giving you an easy to use forum.';
    $jfusion_plugins['smf2'] = 'All in one package, giving you an easy to use forum.';
    $jfusion_plugins['vbulletin'] = 'The most powerful forum software.';
    $jfusion_plugins['wordpress'] = 'A semantic personal publishing platform.';

    //see if any plugins need upgrading

    //make sure default plugins are installed
    $query = "SELECT name FROM #__jfusion WHERE name != 'joomla_int'";
    $db->setQuery($query);
    $installedPlugins = $db->loadResultArray();
    $pluginSql = array();
    include_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.install.php';
    foreach ($installedPlugins as $plugin) :
        if (array_key_exists ($plugin, $jfusion_plugins)) :
            //install updates
            $packagename = $basedir . DS . 'packages' . DS . 'jfusion_' . $plugin . '.zip';
            $model = new JFusionModelInstaller();
            $result = $model->installZIP($packagename);
            //remove plugin from install list
            unset($jfusion_plugins[$plugin]);
            ?>
            <table style="background-color:#d9f9e2;width:100%;">
                <tr>
                    <td width="50px">
                        <img src="components/com_jfusion/images/check_good_small.png">
                    </td>
                    <td>
                        <font size="2">
                            <b>
                                <?php echo $result['message']; ?>
                            </b>
                        </font>
                    </td>
                </tr>
            </table>
        <?php
        endif;
    endforeach;
    ?>
    <br/>
    <?php echo JText::_('POST_INSTALL_PLUGIN_OPTIONS'); ?>
    <br/><br/>
    <?php
    //prepare toolbar
    $bar = new JToolBar('toolbar');
    $bar->appendButton('Link', 'apply', JText::_('INSTALL'), 'javascript: $(\'pluginForm\').submit();');
    $bar->appendButton( 'Link', 'options', 'CPanel', 'index.php?option=com_jfusion&task=plugindisplay' );
    echo $bar->render();?>
    <br/><br/><br/>
    <form method="post" action="index.php" name="pluginForm" id="pluginForm">
        <input type="hidden" name="option" value="com_jfusion" />
        <input type="hidden" name="task" value="installplugins" />
        <table class="adminlist" style="border-spacing:1px;" id="sortables">
            <thead>
                <tr>
                    <th class="title" width="20px;">
                    </th>
                    <th class="title" align="left">
                        <?php echo JText::_('NAME');?></th>
                    <th class="title" align="left">
                        <?php echo JText::_('DESCRIPTION');?>
                    </th>
                </tr>
            </thead>
            <tbody>
            <?php
            //loop through the JFusion plugins
            $rowcount = 0;
            foreach($jfusion_plugins as $name => $description) { ?>
                <tr id="<?php echo $name; ?>" class="row<? echo $rowcount; ?>">
                    <td width="20px;">
                        <input type="checkbox" name="jfusionplugins[]" value="<?php echo $name; ?>" />
                    </td>
                    <td>
                        <?php echo $name; ?>
                    </td>
                    <td>
                        <?php echo $description; ?>
                    </td>
                </tr>

                <?php
                if ($rowcount == 0) {
                    $rowcount = 1;
                } else {
                    $rowcount = 0;
                }
            } ?>
            </tbody>
        </table>
    </form>
    <?php
    //cleanup the packages directory
    $package_dir = $basedir . DS . 'packages';
    $folders = JFolder::folders($package_dir);
    foreach ($folders as $folder) {
        JFolder::delete($package_dir.DS.$folder);
    }

    //Make sure the status field in jos_jfusion has got either 0 or 1
    $query = 'SELECT status FROM #__jfusion WHERE status = 3';
    $db->setQuery($query);
    if ($db->loadResult()) {
        $query = 'UPDATE #__jfusion SET status = 0 WHERE status <> 3';
        $db->setQuery($query);
        $db->query();
        $query = 'UPDATE #__jfusion SET status = 1 WHERE status = 3';
        $db->setQuery($query);
        $db->query();
    }

    return $return;
}
