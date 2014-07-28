<?php
use Psr\Log\LogLevel;

/**
 * Created by JetBrains PhpStorm.
 * User: fanno
 * Date: 17-12-12
 * Time: 16:18
 * To change this template use File | Settings | File Templates.
 */

class com_jfusionInstallerScript
{
	private $jfusionupgrade;

	/**
	 * method to install the component
	 *
	 * @param $parent
	 *
	 * @return void
	 */
	function install($parent)
	{
		$this->init();

		//see if we need to create SQL tables
		$db = JFactory::getDBO();
		$table_list = $db->getTableList();
		$table_prefix = $db->getPrefix();

		try {
			if (array_search($table_prefix . 'jfusion_users', $table_list) == false) {
				$query = 'CREATE TABLE #__jfusion_users (
				      id int(11) NOT null,
				      username varchar(50),
				      PRIMARY KEY (id)
				    ) DEFAULT CHARACTER SET utf8;';
				$db->setQuery($query);
				$db->execute();
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
				$db->execute();
			}
			//create the jfusion_discussion_bot table if it does not exist already
			if (array_search($table_prefix . 'jfusion_discussion_bot', $table_list) == false) {
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
				$db->execute();
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
				$db->execute();
			}
			//create the jfusion_sync_log table if it does not exist already
			if (array_search($table_prefix . 'jfusion_sync_details', $table_list) == false) {
				$query = 'CREATE TABLE #__jfusion_sync_details (
				      id int(11) NOT NULL auto_increment,
				      syncid varchar(10),
				      jname varchar(255),
				      username varchar(255),
				      email varchar(255),
				      action varchar(255),
				      `message` text,
				      data longblob,
				      PRIMARY KEY  (id)
				    );';
				$db->setQuery($query);
				$db->execute();
			}

			if (array_search($table_prefix . 'jfusion', $table_list) == false) {
				$this->jfusionupgrade = JText::_('JFUSION') . ' ' . JText::_('INSTALL') . ' ' . JText::_('SUCCESS');

				$query = 'CREATE TABLE #__jfusion (
				        id int(11) NOT null auto_increment,
				        name varchar(50) NOT null,
				        params text,
				        master tinyint(4) NOT null,
				        slave tinyint(4) NOT null,
				        status tinyint(4) NOT null,
				        dual_login tinyint(4) NOT null,
				        check_encryption tinyint(4) NOT null,
				        original_name varchar(50) null,
				        ordering tinyint(4),
				        PRIMARY KEY  (id)
				      );';

				$db->setQuery($query);
				$db->execute();

				JFolder::create(JFUSION_PLUGIN_PATH);
			}
			$this->display();
			// $parent is the class calling this method
			//	$parent->getParent()->setRedirectURL('index.php?option=com_helloworld');
		} catch (Exception $e ) {
			echo $e->getMessage() . '<br />';
		}
	}


	/**
	 * method to update the component
	 *
	 * @param $parent
	 *
	 * @return void
	 */
	function update($parent)
	{
		$this->init();
		//this is an upgrade
		$this->jfusionupgrade = JText::_('JFUSION') . ' ' . JText::_('UPDATE') . ' ' . JText::_('SUCCESS');

		$db = JFactory::getDBO();
		$table_list = $db->getTableList();
		$table_prefix = $db->getPrefix();

		try {
			/***
			 * UPGRADES FOR 1.1.0 Patch 2
			 ***/
			//see if the columns exists
			$query = 'SHOW COLUMNS FROM #__jfusion';
			$db->setQuery($query);
			$columns = $db->loadColumn();

			//check to see if the description column exists, if it does remove all pre 1.1.0 Beta Patch 2 columns
			if (in_array('description', $columns)) {
				$query = 'ALTER TABLE #__jfusion DROP COLUMN version, DROP COLUMN description, DROP COLUMN date, DROP COLUMN author, DROP COLUMN support';
				$db->setQuery($query);
				$db->execute();
			}
			/***
			 * UPGRADES FOR 1.1.1 Beta
			 ***/
			if (!in_array('original_name', $columns)) {
				//add the column
				$query = 'ALTER TABLE #__jfusion ADD COLUMN original_name varchar(50) null';
				$db->setQuery($query);
				$db->execute();
			}

			/***
			 * UPGRADES FOR 1.1.2 Beta
			 ***/
			//add the search and discussion columns
			if (!in_array('search', $columns)) {
				$query = 'ALTER TABLE #__jfusion
					ADD COLUMN search tinyint(4) NOT null DEFAULT 0,
					ADD COLUMN discussion tinyint(4) NOT null DEFAULT 0';
				$db->setQuery($query);
				$db->execute();
			}

			$query = 'SHOW INDEX FROM #__jfusion_users_plugin';
			$db->setQuery($query);
			$indexes = $db->loadObjectList('Key_name');
			if (!array_key_exists('lookup', $indexes)) {
				//we need to make sure that old jfusion_users_plugin table doesn't have duplicates
				//in prep of adding an unique index
				$query = 'CREATE TABLE #__jfusion_users_plugin_backup AS
					SELECT * FROM  #__jfusion_users_plugin WHERE 1 GROUP BY id, jname';
				$db->setQuery($query);
				$db->execute();

				$query = 'DROP TABLE #__jfusion_users_plugin';
				$db->setQuery($query);
				$db->execute();

				$query = 'RENAME TABLE #__jfusion_users_plugin_backup TO #__jfusion_users_plugin';
				$db->setQuery($query);
				$db->execute();

				//in addition the unique indexes we need to change the userid column to accept text as
				//plugins such as dokuwiki does not use int userids
				$query = 'ALTER TABLE #__jfusion_users_plugin
					ADD UNIQUE `lookup` (id,jname),
					ADD PRIMARY KEY ( `autoid` ),
					CHANGE `autoid` `autoid` INT( 11 ) NOT null AUTO_INCREMENT,
					CHANGE `userid` `userid` VARCHAR(50) NOT null';
				$db->setQuery($query);
				$db->execute();
			}
			//make sure that the slave and dual_login capabilties of the joomla_ext plugin is enabled
			$query = $db->getQuery(true)
				->update('#__jfusion')
				->set('slave = 0')
				->where('name = ' . $db->quote('joomla_ext'))
				->where('slave = 3');

			$db->setQuery($query);
			$db->execute();

			$query = $db->getQuery(true)
				->update('#__jfusion')
				->set('dual_login = 0')
				->where('name = ' . $db->quote('joomla_ext'))
				->where('dual_login = 3');
			$db->setQuery($query);
			$db->execute();

			//we need to remove a couple parameter files if they exists to prevent duplicates from showing up, and other unused files.
			$files2delete = array(JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'config.xml',
				JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'com_jfusion.xml',
				JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.abstractpublic.php',
				JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.abstractadmin.php',
				JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.abstractauth.php',
				JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.abstractuser.php',
				JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.abstractforum.php',
				JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jplugin.php',
				JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'mysql.php',
				JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'mysql.reconnect.php',
				JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'mysqli.php');

			foreach ($files2delete as $f) {
				if (file_exists($f)) {
					if (!JFile::delete($f)) {
						\JFusion\Framework::raise(LogLevel::WARNING, JText::sprintf('UPGRADE_UNABLE_TO_REMOVE_FILE', $f));
					}
				}
			}

			/***
			 * UPGRADES FOR 1.1.4/1.2
			 */
			$query = 'ALTER TABLE `#__jfusion_sync` CHANGE `syncdata` `syncdata` LONGBLOB null DEFAULT null';
			$db->setQuery($query);
			$db->execute();

			/***
			 * UPGRADES FOR 1.1.2 Stable
			 ***/
			/**
			 * TODO: REMOVE?
			//make id the primary key so that the username will be updated
			$query = 'ALTER TABLE `#__jfusion_users` DROP PRIMARY KEY, ADD PRIMARY KEY ( `id` )';
			$db->setQuery($query);
			$db->execute();
			*/

			/**
			 * UPGRADES FOR 1.5
			 */
			//add a active column for user sync
			$query = 'SHOW COLUMNS FROM #__jfusion_sync';
			$db->setQuery($query);
			$columns = $db->loadColumn();
			if (!in_array('active', $columns)) {
				$query = 'ALTER TABLE #__jfusion_sync
					ADD COLUMN active int(1) NOT null DEFAULT 0';
				$db->setQuery($query);
				$db->execute();
			}

			/**
			 * UPGRADES FOR 1.6
			 */

			//add a active column for user sync
			$query = 'SHOW COLUMNS FROM #__jfusion';
			$db->setQuery($query);
			$columns = $db->loadColumn();
			if (!in_array('ordering', $columns)) {
				$query = 'ALTER TABLE #__jfusion
            		ADD COLUMN ordering int(4)';
				$db->setQuery($query);
				$db->execute();
			}

			/**
			 * UPGRADES FOR 1.8
			 */
			$dir = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'plugins';
			if (JFolder::exists($dir)) {
				$folders = JFolder::folders($dir);
				$results = true;
				foreach ($folders as $folder) {
					if (!JFolder::exists(JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $folder) ) {
						$r = JFolder::copy($dir . DIRECTORY_SEPARATOR . $folder, JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $folder);
						if ($results === true) {
							$results = $r;
						}
					}
				}
				if ($results === true) {
					JFolder::delete($dir);
				}
			}

			//remove columns
			if (in_array('activity', $columns)) {
				$query = 'ALTER TABLE #__jfusion DROP column activity';
				$db->setQuery($query);
				try {
					$db->execute();
				} catch (Exception $e ) {
					echo $e->getMessage() . '<br />';
				}
			}
			if (in_array('search', $columns)) {
				$query = 'ALTER TABLE #__jfusion DROP column search';
				$db->setQuery($query);
				try {
					$db->execute();
				} catch (Exception $e ) {
					echo $e->getMessage() . '<br />';
				}
			}
			if (in_array('discussion', $columns)) {
				$query = 'ALTER TABLE #__jfusion DROP column discussion';
				$db->setQuery($query);
				try {
					$db->execute();
				} catch (Exception $e ) {
					echo $e->getMessage() . '<br />';
				}
			}

			//migrate from #__jfusion_forum_plugin to #__jfusion_discussion_bot
			//check to see if #__jfusion_forum_plugin exists indicating that #__jfusion_discussion_bot has not been populated
			if(array_search($table_prefix . 'jfusion_forum_plugin', $table_list)) {
				$query = $db->getQuery(true)
					->select('*')
					->from('#__jfusion_forum_plugin');

				$db->setQuery($query);
				$results = $db->loadObjectList();

				$query = 'SHOW COLUMNS FROM #__jfusion_forum_plugin';
				$db->setQuery($query);
				$columns = $db->loadColumn();

				$row_inserts = array();
				foreach($results as $result) {
					$col_inserts = array();
					foreach($columns as $column) {
						$col_inserts[] = $db->quote($result->$column);
					}
					$row_inserts[] = '(' . implode(', ', $col_inserts) . ')';
				}

				if(!empty($row_inserts)) {
					$query = 'REPLACE INTO #__jfusion_discussion_bot (' . implode(', ', $columns) . ') VALUES ' . implode(', ', $row_inserts);
					$db->setQuery($query);
					$db->execute();
				} else {
					$migrate_success = true;
				}

				//add com_content to components column
				$query = $db->getQuery(true)
					->update('#__jfusion_discussion_bot')
					->set('component = ' . $db->quote('com_content'));
				$db->setQuery($query);
				try {
					$db->execute();
				} catch (Exception $e ) {
					echo $e->getMessage() . '<br />';
				}

				$query = 'DROP TABLE #__jfusion_forum_plugin';
				$db->setQuery($query);
				$db->execute();
			} else {
				//check to make sure there is a components column in the discussion_bot table
				$query = 'SHOW COLUMNS FROM #__jfusion_discussion_bot';
				$db->setQuery($query);
				$columns = $db->loadColumn();

				if (!in_array('component', $columns)) {
					$query = 'ALTER TABLE #__jfusion_discussion_bot ADD COLUMN component varchar(255) NOT NULL';
					$db->setQuery($query);

					try {
						$db->execute();

						$query = $db->getQuery(true)
							->update('#__jfusion_discussion_bot')
							->set('component = ' . $db->quote('com_content'));

						$db->setQuery($query);
						$db->execute();
					} catch (Exception $e ) {
						echo $e->getMessage() . '<br />';
					}
				}
			}

			/**
			 * for 2.0
			 */
			try {
				$query = 'ALTER TABLE  #__jfusion_sync_details CHANGE  `message`  `message` TEXT';
				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e ) {
				echo $e->getMessage() . '<br />';
			}

			$query = 'SHOW COLUMNS FROM #__jfusion';
			$db->setQuery($query);
			$columns = $db->loadColumn();

			//remove the plugin_files if it exists
			if (in_array('plugin_files', $columns)) {
				//remove the column
				$query = 'ALTER TABLE #__jfusion DROP column plugin_files';
				$db->setQuery($query);
				$db->execute();
			}

			/**
			 * for 3.0
			 */
			$query = 'SHOW COLUMNS FROM #__jfusion_users_plugin';
			$db->setQuery($query);
			$columns = $db->loadColumn();
			if (!in_array('email', $columns)) {
				//remove the column
				$query = 'ALTER TABLE  `#__jfusion_users_plugin` ADD  `email` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `id`';
				$db->setQuery($query);
				$db->execute();

				$query = 'TRUNCATE #__jfusion_users_plugin';
				$db->setQuery($query);
				$db->execute();

				$query = 'DROP TABLE #__jfusion_users';
				$db->setQuery($query);
				$db->execute();
			}

			// let's update to json
			$query = $db->getQuery(true)
				->select('params, id')
				->from('#__jfusion');

			$db->setQuery($query);
			$rows = $db->loadObjectList();
			if(!empty($rows)) {
				foreach ($rows as $row) {
					if ($row->params) {
						$params = base64_decode($row->params);
						if (strpos($params, 'a:') === 0) {
							ob_start();
							$params = unserialize($params);
							ob_end_clean();
							if (is_array($params)) {
								$params = new JRegistry($params);
								$row->params  = $params->toString();
								$db->updateObject('#__jfusion', $row, 'id');
							}
						}
					}
				}
			}

			//cleanup unused plugins
			$query = $db->getQuery(true)
				->select('name')
				->from('#__jfusion')
				->where('(params IS NULL OR params = ' . $db->quote('') . ' OR params = ' . $db->quote('0') . ')')
				->where('status = 0')
				->where('master = 0')
				->where('slave = 0')
				->where('name NOT LIKE ' . $db->quote('joomla_int'));

			$db->setQuery($query);
			$rows = $db->loadObjectList();
			if(!empty($rows)) {
				foreach ($rows as $row) {
					$query = $db->getQuery(true)
						->select('count(*)')
						->from('#__jfusion')
						->where('original_name LIKE ' . $db->quote($row->name));

					$db->setQuery($query);
					$copys = $db->loadResult();
					if (!$copys) {
						$model = new JFusionModelInstaller();
						$model->uninstall($row->name);
					}
				}
			}
			$this->display();
		} catch (Exception $e ) {
			echo $e->getMessage() . '<br />';
		}
	}

	/**
	 * method to uninstall the component
	 *
	 * @param $parent
	 *
	 * @return void
	 */
	function uninstall($parent)
	{
		$this->init();
		echo '<h2>JFusion ' . JText::_('UNINSTALL') . '</h2><br/>';

		//restore the normal login behaviour
		$db = JFactory::getDBO();

		try {
			JFusionFunctionAdmin::changePluginStatus('joomla', 'authentication', 1);
			JFusionFunctionAdmin::changePluginStatus('joomla', 'user', 1);
			JFusionFunctionAdmin::changePluginStatus('jfusion', 'authentication', 0);
			JFusionFunctionAdmin::changePluginStatus('jfusion', 'user', 0);
		} catch (Exception $e ) {
			echo $e->getMessage() . '<br />';
		}

		echo '<table style="background-color:#dff0d8;" width ="100%"><tr style="height:30px">';
		echo '<td><font size="2"><b>' . JText::_('NORMAL_JOOMLA_BEHAVIOR_RESTORED') . '</b></font></td></tr></table>';

		//uninstall the JFusion plugins
		$this->_uninstallPlugin('plugin', 'jfusion', 'user', 'JFusion User Plugin');
		$this->_uninstallPlugin('plugin', 'jfusion', 'authentication', 'JFusion Authentication Plugin');
		$this->_uninstallPlugin('plugin', 'jfusion', 'search', 'JFusion Search Plugin');
		$this->_uninstallPlugin('plugin', 'jfusion', 'content', 'JFusion Discussion Bot Plugin');
		$this->_uninstallPlugin('plugin', 'jfusion', 'system', 'JFusion System Plugin');

		//uninstall the JFusion Modules
		$this->_uninstallPlugin('module', 'mod_jfusion_login', '', 'JFusion Login Module');
		$this->_uninstallPlugin('module', 'mod_jfusion_activity', '', 'JFusion Activity Module');
		$this->_uninstallPlugin('module', 'mod_jfusion_user_activity', '', 'JFusion User Activity Module');
		$this->_uninstallPlugin('module', 'mod_jfusion_whosonline', '', 'JFusion Whos Online Module');

		//see if any mods from jfusion plugins need to be removed
		$plugins = \JFusion\Factory::getPlugins('all', true, false);
		foreach($plugins as $plugin) {
			$model = new JFusionModelInstaller();
			$result = $model->uninstall($plugin->name);

			if (!$result['status']) {
				$color = '#f2dede';
				$description = JText::_('UNINSTALL') . ' ' . $plugin->name . ' ' . JText::_('FAILED');
			} else {
				$color = '#dff0d8';
				$description = JText::_('UNINSTALL') . ' ' . $plugin->name . ' ' . JText::_('SUCCESS');
			}
			$html = <<<HTML
        <table style="background-color:{$color}; width:100%;">
            <tr style="height:30px">
                <td>
                    <h3>
                        <strong>{$description}</strong>
                    </h3>
                </td>
             </tr>
        </table>
HTML;
			echo $html;
		}

		//remove the jfusion tables.
		$db = JFactory::getDBO();
		$query = 'DROP TABLE #__jfusion';
		$db->setQuery($query);
		try {
			$db->execute();
		} catch (Exception $e ) {
			echo $e->getMessage() . '<br />';
		}

		$query = 'DROP TABLE #__jfusion_sync';
		$db->setQuery($query);
		try {
			$db->execute();
		} catch (Exception $e ) {
			echo $e->getMessage() . '<br />';
		}

		$query = 'DROP TABLE #__jfusion_sync_details';
		$db->setQuery($query);
		try {
			$db->execute();
		} catch (Exception $e ) {
			echo $e->getMessage() . '<br />';
		}

		$query = 'DROP TABLE #__jfusion_users';
		$db->setQuery($query);
		try {
			$db->execute();
		} catch (Exception $e ) {
			echo $e->getMessage() . '<br />';
		}

		$query = 'DROP TABLE #__jfusion_users_plugin';
		$db->setQuery($query);
		try {
			$db->execute();
		} catch (Exception $e ) {
			echo $e->getMessage() . '<br />';
		}

		$query = 'DROP TABLE #__jfusion_discussion_bot';
		$db->setQuery($query);
		try {
			$db->execute();
		} catch (Exception $e ) {
			echo $e->getMessage() . '<br />';
		}
	}

	/**
	 * method to run before an install/update/uninstall method
	 *
	 * @param $type
	 * @param $parent
	 *
	 * @return void
	 */
	function preflight($type, $parent)
	{

	}

	/**
	 * method to run after an install/update/uninstall method
	 *
	 * @param $type
	 * @param $parent
	 *
	 * @return void
	 */
	function postflight($type, $parent)
	{
	}

	/**
	 * @param $type
	 * @param $id
	 * @param $group
	 * @param $description
	 */
	function _uninstallPlugin($type, $id, $group, $description)
	{
		$db = JFactory::getDBO();
		$result = $id;

		$query = $db->getQuery(true)
			->select('extension_id')
			->from('#__extensions')
			->where('element = ' . $db->quote($id));
		switch ($type) {
			case 'plugin':
				$query->where('folder = ' . $db->quote($group));
				$db->setQuery($query);
				$result = $db->loadResult();
				break;
			case 'module':
				$db->setQuery($query);
				$result = $db->loadResult();
				break;
		}

		if ($result) {
			$tmpinstaller = new JInstaller();
			$uninstall_result = $tmpinstaller->uninstall($type, $result, 0);

			if (!$uninstall_result) {
				$color = '#f2dede';
				$description = JText::_('UNINSTALL') . ' ' . $description . ' ' . JText::_('FAILED');
			} else {
				$color = '#dff0d8';
				$description = JText::_('UNINSTALL') . ' ' . $description . ' ' . JText::_('SUCCESS');
			}
			$html = <<<HTML
        <table style="background-color:{$color}; width:100%;">
            <tr style="height:30px">
                <td>
                    <h3>
                        <strong>{$description}</strong>
                    </h3>
                </td>
             </tr>
        </table>
HTML;
			echo $html;
		}
	}


	function display()
	{
		$this->init();

		//output some info to the user
		$db = JFactory::getDBO();

		$installer = JInstaller::getInstance();
		$manifest = $installer->getPath('manifest');

		$parser = \JFusion\Framework::getXML($manifest);

		if ($parser->version) {
			$version = $parser->version;
		} else {
			$version = JText::_('UNKNOWN');
		}
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
	                <?php echo JText::_('JFUSION'); ?>
                </h2>
            </td>
        </tr>
    </table>
    <h2>
		<?php echo JText::_('VERSION') . ' ' . $version . ' ' . JText::_('INSTALLATION'); ?>
    </h2>
    <h3>
		<?php echo JText::_('STARTING') . ' ' . JText::_('INSTALLATION') . ' ...' ?>
    </h3>

	<?php

		$html = <<<HTML
        <table style="background-color:#dff0d8;width:100%;">
            <tr>
                <td width="50px">
                    <img src="components/com_jfusion/images/check_good_small.png">
                </td>
                <td>
                    <h3>
                        <strong>
                            {$this->jfusionupgrade}
                        </strong>
                    </h3>
                </td>
            </tr>
        </table>
HTML;
		echo $html;

		$basedir = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion';
		if(!empty($restorePluginOutput)) {
			echo $restorePluginOutput;
		}

		$jfusion_plugins = array();
		$jfusion_plugins['joomla_int'] = 'jomla_int';
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
		$jfusion_plugins['universal'] = 'Universal plugin for JFusion';
		$jfusion_plugins['vbulletin'] = 'The most powerful forum software.';
		$jfusion_plugins['wordpress'] = 'A semantic personal publishing platform.';

		//see if any plugins need upgrading

		//make sure default plugins are installed
		$query = $db->getQuery(true)
			->select('original_name , name')
			->from('#__jfusion');

		$db->setQuery($query);
		$Plugins = $db->loadObjectList();

		$installedPlugins = array();
		foreach ($Plugins as $plugin) {
			if ($plugin->original_name) {
				$installedPlugins[$plugin->original_name] = $plugin->original_name;
			} else {
				$installedPlugins[$plugin->name] = $plugin->name;
			}
		}
		$installedPlugins['joomla_int'] = 'joomla_int';

		foreach ($installedPlugins as $plugin) {
			if (array_key_exists ($plugin, $jfusion_plugins)) {
				//install updates
				$model = new JFusionModelInstaller(false);
				try {
					$result = $model->installZIP($basedir . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'jfusion_' . $plugin . '.zip');

					$message = $result['message'];

					$color = '#dff0d8';
					$image = '<img src="components/com_jfusion/images/check_good_small.png">';
				} catch (Exception $e) {
					$message = $e->getMessage();

					$color = '#f2dede';
					$image = '<img src="components/com_jfusion/images/check_bad_small.png">';
				}

				//remove plugin from install list
				unset($jfusion_plugins[$plugin]);

				$html = <<<HTML
		            <table style="background-color:{$color}; width:100%;">
		                <tr>
		                    <td width="50px">
		                        {$image}
		                    </td>
		                    <td>
		                        <h3>
		                            <strong>
		                                {$message}
		                            </strong>
		                        </h3>
		                    </td>
		                </tr>
		            </table>
HTML;
				echo $html;
			}
		}
		?>
    <br/>

    <a class="btn" href="index.php?option=com_jfusion&task=plugindisplay">Configure Jfusion</a>
    <a class="btn" href="index.php?option=com_jfusion">CPanel</a>
    <br/><br/>
	<?php
		$basedir = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion';
		//install the JFusion packages
		jimport('joomla.installer.helper');
		$packages = array();

		$packages['Login Module'] = $basedir . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'jfusion_mod_login.zip';
		$packages['Activity Module'] = $basedir . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'jfusion_mod_activity.zip';
		$packages['User Activity Module'] = $basedir . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'jfusion_mod_user_activity.zip';
		$packages['Whos Online Module'] = $basedir . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'jfusion_mod_whosonline.zip';

		$packages['User Plugin'] = $basedir . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'jfusion_plugin_user.zip';
		$packages['Authentication Plugin'] = $basedir . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'jfusion_plugin_auth.zip';
		$packages['Search Plugin'] = $basedir . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'jfusion_plugin_search.zip';
		$packages['System Plugin'] = $basedir . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'jfusion_plugin_system.zip';
		$packages['Discussion Bot'] = $basedir . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'jfusion_plugin_content.zip';

		foreach ($packages as $name => $filename) {
			$package = JInstallerHelper::unpack($filename);
			$tmpInstaller = new JInstaller();
			if (!$tmpInstaller->install($package['dir'])) {
				$color = '#f2dede';
				$message = JText::_('ERROR') . ' ' . JText::_('INSTALLING') . ' ' . JText::_('JFUSION') . ' ' . $name;
				$image = '<img src="components/com_jfusion/images/check_bad_small.png">';
			} else {
				$color = '#dff0d8';
				$message = JText::_('SUCCESS') . ' ' . JText::_('INSTALLING') . ' ' . JText::_('JFUSION') . ' ' . $name;
				$image = '<img src="components/com_jfusion/images/check_good_small.png">';
			}

			$html = <<<HTML
	            <table style="background-color:{$color};width:100%;">
	                <tr style="height: 30px">
	                    <td width="50px">
	                        {$image}
	                    </td>
	                    <td>
	                        <h3>
	                            <strong>
	                                {$message}
	                            </strong>
	                        </h3>
	                    </td>
	                </tr>
	            </table>
HTML;
			echo $html;

			unset($package, $tmpInstaller);
		}
		echo '<br/><br/>';

		//cleanup the packages directory
		$package_dir = $basedir . DIRECTORY_SEPARATOR . 'packages';
		$folders = JFolder::folders($package_dir);
		if ($folders) {
			foreach ($folders as $folder) {
				JFolder::delete($package_dir . DIRECTORY_SEPARATOR . $folder);
			}
		}

		//Make sure the status field in jos_jfusion has got either 0 or 1
		$query = $db->getQuery(true)
			->select('status')
			->from('#__jfusion')
			->where('status = 3');

		$db->setQuery($query);
		if ($db->loadResult()) {
			$query = $db->getQuery(true)
				->update('#__jfusion')
				->set('status = 0')
				->where('status <> 3');

			$db->setQuery($query);
			$db->execute();

			$query = $db->getQuery(true)
				->update('#__jfusion')
				->set('status = 1')
				->where('status = 3');

			$db->setQuery($query);
			$db->execute();
		}
	}

	private function init() {
		JFactory::getLanguage()->load('com_jfusion', JPATH_BASE);

		$administrator = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR;

		require_once $administrator . DIRECTORY_SEPARATOR . 'import.php';
		require_once $administrator . 'models' . DIRECTORY_SEPARATOR . 'model.jfusionadmin.php';
		require_once $administrator . 'models' . DIRECTORY_SEPARATOR . 'model.install.php';
	}
}
