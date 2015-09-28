<?php
use JFusion\Installer\Framework as InstallerFramework;
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
			InstallerFramework::install();

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

			JFolder::create(JFUSION_PLUGIN_PATH);
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
			InstallerFramework::update();

			$adminpath = JPATH_ADMINISTRATOR . '/components/com_jfusion/';
			//we need to remove a couple parameter files if they exists to prevent duplicates from showing up, and other unused files.
			$files2delete = array($adminpath . 'config.xml',
				$adminpath . 'com_jfusion.xml',
				$adminpath . 'models/model.abstractpublic.php',
				$adminpath . 'models/model.abstractadmin.php',
				$adminpath . 'models/model.abstractauth.php',
				$adminpath . 'models/model.abstractuser.php',
				$adminpath . 'models/model.abstractforum.php',
				$adminpath . 'models/model.jplugin.php',
				$adminpath . 'models/mysql.php',
				$adminpath . 'models/mysql.reconnect.php',
				$adminpath . 'models/mysqli.php',
				$adminpath . 'models/recaptchalib.php');

			foreach ($files2delete as $f) {
				if (file_exists($f)) {
					if (!JFile::delete($f)) {
						\JFusion\Framework::raise(LogLevel::WARNING, JText::sprintf('UPGRADE_UNABLE_TO_REMOVE_FILE', $f));
					}
				}
			}

			/**
			 * UPGRADES FOR 1.8
			 */
			$dir = JPATH_ADMINISTRATOR . '/components/com_jfusion/plugins';
			if (JFolder::exists($dir)) {
				$folders = JFolder::folders($dir);
				$results = true;
				foreach ($folders as $folder) {
					if (!JFolder::exists(JFUSION_PLUGIN_PATH . '/' . $folder) ) {
						$r = JFolder::copy($dir . '/' . $folder, JFUSION_PLUGIN_PATH . '/' . $folder);
						if ($results === true) {
							$results = $r;
						}
					}
				}
				if ($results === true) {
					JFolder::delete($dir);
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

		InstallerFramework::uninstall();

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
                <img src="components/com_jfusion/images/jfusion.png">
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
                	<span style="font-size: 25pt; color: green;">&#x2714;</span>
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

		$basedir = JPATH_ADMINISTRATOR . '/components/com_jfusion';
		if(!empty($restorePluginOutput)) {
			echo $restorePluginOutput;
		}

		$jfusion_plugins = array();
		$jfusion_plugins['joomla_int'] = 'joomla_int';
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
					$result = $model->installZIP($basedir . '/packages/jfusion_' . $plugin . '.zip');

					$message = $result['message'];

					$color = '#dff0d8';

					$check = '<span style="font-size: 25pt; color: green;">&#x2714;</span>';
				} catch (Exception $e) {
					$message = $e->getMessage();

					$color = '#f2dede';
					$check = '<span style="font-size: 25pt; color: red;">&#x2716;</span>';
				}

				//remove plugin from install list
				unset($jfusion_plugins[$plugin]);

				$html = <<<HTML
		            <table style="background-color:{$color}; width:100%;">
		                <tr>
		                    <td width="50px">
		                        {$check}
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
		$basedir = JPATH_ADMINISTRATOR . '/components/com_jfusion';
		//install the JFusion packages
		jimport('joomla.installer.helper');
		$packages = array();

		$packages['Login Module'] = $basedir . '/packages/jfusion_mod_login.zip';
		$packages['Activity Module'] = $basedir . '/packages/jfusion_mod_activity.zip';
		$packages['User Activity Module'] = $basedir . '/packages/jfusion_mod_user_activity.zip';
		$packages['Whos Online Module'] = $basedir . '/packages/jfusion_mod_whosonline.zip';

		$packages['User Plugin'] = $basedir . '/packages/jfusion_plugin_user.zip';
		$packages['Authentication Plugin'] = $basedir . '/packages/jfusion_plugin_auth.zip';
		$packages['Search Plugin'] = $basedir . '/packages/jfusion_plugin_search.zip';
		$packages['System Plugin'] = $basedir . '/packages/jfusion_plugin_system.zip';
		$packages['Discussion Bot'] = $basedir . '/packages/jfusion_plugin_content.zip';

		foreach ($packages as $name => $filename) {
			$package = JInstallerHelper::unpack($filename);
			$tmpInstaller = new JInstaller();
			if (!$tmpInstaller->install($package['dir'])) {
				$color = '#f2dede';
				$message = JText::_('ERROR') . ' ' . JText::_('INSTALLING') . ' ' . JText::_('JFUSION') . ' ' . $name;

				$check = '<span style="font-size: 25pt; color: red;">&#x2716;</span>';
			} else {
				$color = '#dff0d8';
				$message = JText::_('SUCCESS') . ' ' . JText::_('INSTALLING') . ' ' . JText::_('JFUSION') . ' ' . $name;

				$check = '<span style="font-size: 25pt; color: green;">&#x2714;</span>';
			}

			$html = <<<HTML
	            <table style="background-color:{$color};width:100%;">
	                <tr style="height: 30px">
	                    <td width="50px">
	                        {$check}
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
		$package_dir = $basedir . '/packages';
		$folders = JFolder::folders($package_dir);
		if ($folders) {
			foreach ($folders as $folder) {
				JFolder::delete($package_dir . '/' . $folder);
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

		$administrator = JPATH_ADMINISTRATOR . '/components/com_jfusion/';

		require_once $administrator . '/import.php';
		require_once $administrator . 'models/model.jfusionadmin.php';
		require_once $administrator . 'models/model.install.php';
	}
}
