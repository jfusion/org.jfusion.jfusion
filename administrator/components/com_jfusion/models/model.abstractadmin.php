<?php

/**
 * Abstract admin file
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

require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'defines.php';

/**
 * Abstract interface for all JFusion functions that are accessed through the Joomla administrator interface
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionAdmin
{
	var $helper;

	/**
	 * @var JRegistry
	 */
	var $params;

	/**
	 *
	 */
	function __construct()
	{
		//get the params object
		$this->params = JFusionFactory::getParams($this->getJname());
		//get the helper object
		$this->helper = JFusionFactory::getHelper($this->getJname());
	}
    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return '';
    }

    /**
     * Returns the a list of users of the integrated software
     * @param int $limitstart optional
     * @param int $limit optional
     * @return array List of usernames/emails
     */
    function getUserList($limitstart = 0, $limit = 0)
    {
        return array();
    }

    /**
     * Returns the the number of users in the integrated software. Allows for fast retrieval total number of users for the usersync
     *
     * @return integer Number of registered users
     */
    function getUserCount()
    {
        return 0;
    }

    /**
     * Returns the a list of usersgroups of the integrated software
     *
     * @return array List of usergroups
     */
    function getUsergroupList()
    {
        return array();
    }

    /**
     * Function used to display the default usergroup in the JFusion plugin overview
     *
     * @return string Default usergroup name
     */
    function getDefaultUsergroup()
    {
        return '';
    }

    /**
     * Checks if the software allows new users to register
     *
     * @return boolean True if new user registration is allowed, otherwise returns false
     */
    function allowRegistration()
    {
        return true;
    }

    /**
     * returns the name of user table of integrated software
     *
     * @return string table name
     */
    function getTablename()
    {
        return '';
    }

    /**
     * Function finds config file of integrated software and automatically configures the JFusion plugin
     *
     * @param string $softwarePath path to root of integrated software
     *
     * @return array array with ne newly found configuration
     */
    function setupFromPath($softwarePath)
    {
        return array();
    }

    /**
     * Function that checks if the plugin has a valid config
     *
     * @return array result of the config check
     */
    function checkConfig()
    {
        $status = array();
	    $status['config'] = 0;
	    $status['message'] = JText::_('UNKNOWN');
        $jname = $this->getJname();
        //for joomla_int check to see if the source_url does not equal the default
	    try {
		    if ($jname == 'joomla_int') {
			    $source_url = $this->params->get('source_url');
			    if (empty($source_url)) {
				    throw new RuntimeException(JText::_('GOOD_CONFIG'));
			    }
		    } else {
			    try {
				    $db = JFusionFactory::getDatabase($jname);
			    } catch (Exception $e) {
				    throw new RuntimeException(JText::_('NO_DATABASE').' : '.$e->getMessage());
			    }

			    try {
				    $jdb = JFactory::getDBO();
			    } catch (Exception $e) {
				    throw new RuntimeException(' -> joomla_int '. JText::_('NO_DATABASE').' : '.$e->getMessage());
			    }

			    if (!$db->connected()) {
				    throw new RuntimeException(JText::_('NO_DATABASE'));
			    } elseif (!$jdb->connected()) {
				    throw new RuntimeException(' -> joomla_int '. JText::_('NO_DATABASE'));
			    } else {
				    //added check for missing files of copied plugins after upgrade
				    $path = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $jname . DIRECTORY_SEPARATOR;
				    if (!file_exists($path.'admin.php')) {
					    throw new RuntimeException(JText::_('NO_FILES').' admin.php');
				    } else if (!file_exists($path.'user.php')) {
					    throw new RuntimeException(JText::_('NO_FILES').' user.php');
				    } else {
					    $cookie_domain = $this->params->get('cookie_domain');
					    $jfc = JFusionFactory::getCookies();
					    list($url) = $jfc->getApiUrl($cookie_domain);
					    if ($url) {
						    require_once(JPATH_SITE.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_jfusion'.DIRECTORY_SEPARATOR.'jfusionapi.php');

						    $joomla_int = JFusionFactory::getParams('joomla_int');
						    $api = new JFusionAPI($url,$joomla_int->get('secret'));
						    if (!$api->ping()) {
							    list ($message) = $api->getError();

							    throw new RuntimeException($api->url. ' ' .$message);
						    }
					    }
					    $source_path = $this->params->get('source_path');
					    if ($source_path && (strpos($source_path, 'http://') === 0 || strpos($source_path, 'https://') === 0)) {
						    throw new RuntimeException(JText::_('ERROR_SOURCE_PATH'). ' : '.$source_path);
					    } else {
						    //get the user table name
						    $tablename = $this->getTablename();
						    // lets check if the table exists, now using the Joomla API
						    $table_list = $db->getTableList();
						    $table_prefix = $db->getPrefix();
						    if (!is_array($table_list)) {
							    throw new RuntimeException($table_prefix . $tablename . ': ' . JText::_('NO_TABLE'));
						    } else {
							    if (array_search($table_prefix . $tablename, $table_list) === false) {
								    //do a final check for case insensitive windows servers
								    if (array_search(strtolower($table_prefix . $tablename), $table_list) === false) {
									    throw new RuntimeException($table_prefix . $tablename . ': ' . JText::_('NO_TABLE'));
								    }
							    }
						    }
					    }
				    }
			    }
		    }
		    $status['config'] = 1;
		    $status['message'] = JText::_('GOOD_CONFIG');
	    } catch (Exception $e) {
		    $status['message'] = $e->getMessage();
	    }
        return $status;
    }

    /**
     * Function that checks if the plugin has a valid config
     * jerror is used for output
     *
     * @return void
     */
    function debugConfig()
    {
	    $jname = $this->getJname();
	    try {
		    //get registration status
		    $new_registration = $this->allowRegistration();

		    //get the data about the JFusion plugins
		    $db = JFactory::getDBO();

		    $query = $db->getQuery(true)
			    ->select('*')
			    ->from('#__jfusion')
			    ->where('name = ' . $db->Quote($jname));

		    $db->setQuery($query);
		    $plugin = $db->loadObject();
		    //output a warning to the administrator if the allowRegistration setting is wrong
		    if ($new_registration && $plugin->slave == 1) {
			    JFusionFunction::raiseNotice(JText::_('DISABLE_REGISTRATION'), $jname);
		    }
		    if (!$new_registration && $plugin->master == 1) {
			    JFusionFunction::raiseNotice(JText::_('ENABLE_REGISTRATION'), $jname);
		    }
		    //most dual login problems are due to incorrect cookie domain settings
		    //therefore we should check it and output a warning if needed.

		    $cookie_domain = $this->params->get('cookie_domain',-1);
		    if ($cookie_domain!==-1) {
			    $cookie_domain = str_replace(array('http://', 'https://'), array('', ''), $cookie_domain);
			    $correct_array = explode('.', html_entity_decode($_SERVER['SERVER_NAME']));

			    //check for domain names with double extentions
			    if (isset($correct_array[count($correct_array) - 2]) && isset($correct_array[count($correct_array) - 1])) {
				    //domain array
				    $domain_array = array('com', 'net', 'org', 'co', 'me');
				    if (in_array($correct_array[count($correct_array) - 2], $domain_array)) {
					    $correct_domain = '.' . $correct_array[count($correct_array) - 3] . '.' . $correct_array[count($correct_array) - 2] . '.' . $correct_array[count($correct_array) - 1];
				    } else {
					    $correct_domain = '.' . $correct_array[count($correct_array) - 2] . '.' . $correct_array[count($correct_array) - 1];
				    }
				    if ($correct_domain != $cookie_domain && !$this->allowEmptyCookieDomain()) {
					    JFusionFunction::raiseNotice(JText::_('BEST_COOKIE_DOMAIN') . ' ' . $correct_domain, $jname);
				    }
			    }
		    }

		    //also check the cookie path as it can interfere with frameless
		    $cookie_path = $this->params->get('cookie_path',-1);
		    if ($cookie_path!==-1) {
			    if ($cookie_path != '/' && !$this->allowEmptyCookiePath()) {
				    JFusionFunction::raiseNotice(JText::_('BEST_COOKIE_PATH') . ' /', $jname);
			    }
		    }

		    //check that master plugin does not have advanced group mode data stored
		    $master = JFusionFunction::getMaster();
		    if (!empty($master) && $master->name == $jname && JFusionFunction::isAdvancedUsergroupMode($jname)) {
			    JFusionFunction::raiseWarning(JText::_('ADVANCED_GROUPMODE_ONLY_SUPPORTED_FORSLAVES'), $jname);
		    }

		    // allow additional checking of the configuration
		    $this->debugConfigExtra();
	    } catch (Exception $e) {
		    JFusionFunction::raiseWarning($e, $jname);
	    }
    }

    /**
     * Function that determines if the empty cookie path is allowed
     *
     * @return bool
     */
    function allowEmptyCookiePath()
    {
        return false;
    }

    /**
     * Function that determines if the empty cookie domain is allowed
     *
     * @return bool
     */
    function allowEmptyCookieDomain()
    {
        return false;
    }

    /**
     * Function to implement any extra debug checks for plugins
     *
     * @return void
     */
    function debugConfigExtra()
    {
    }

    /**
     * Get an usergroup element
     *
     * @param string $name         name of element
     * @param string $value        value of element
     * @param string $node         node of element
     * @param string $control_name name of controller
     *
     * @return string html
     */
    function usergroup($name, $value, $node, $control_name)
    {
        $jname = $this->getJname();
        //get the master plugin to be throughout
        $master = JFusionFunction::getMaster();
        $advanced = 0;

	    JHTML::setFormatOptions(array('format.eol' => "", 'format.indent' => ""));

        //detect is value is a serialized array
        if (substr($value, 0, 2) == 'a:') {
            $value = unserialize($value);
            //use advanced only if this plugin is not set as master
            if (!empty($master) && $master->name != $this->getJname()) {
                $advanced = 1;
            }
        }
        if (JFusionFunction::validPlugin($this->getJname())) {
            $usergroups = $this->getUsergroupList();
            if (!empty($usergroups)) {
                $simple_usergroup = '<table style="width:100%; border:0">';
                $simple_usergroup.= '<tr><td>' . JText::_('DEFAULT_USERGROUP') . '</td><td>' . JHTML::_('select.genericlist', $usergroups, $control_name . '[' . $name . ']', '', 'id', 'name', $value) . '</td></tr>';
                $simple_usergroup.= '</table>';
                //escape single quotes to prevent JS errors
                $simple_usergroup = str_replace("'", "\'", $simple_usergroup);
            } else {
                $simple_usergroup = '';
            }
        } else {
            return JText::_('SAVE_CONFIG_FIRST');
        }
        //check to see if current plugin is a slave
        $db = JFactory::getDBO();

	    $query = $db->getQuery(true)
		    ->select('slave')
		    ->from('#__jfusion')
		    ->where('name = '.$db->Quote($jname));

        $db->setQuery($query);
        $slave = $db->loadResult();
        $list_box = '<select onchange="JFusion.Plugin.usergroupSelect(this.selectedIndex);">';
        if ($advanced == 1) {
            $list_box.= '<option value="0">'.JText::_('SIMPLE').'</option>';
        } else {
            $list_box.= '<option value="0" selected="selected">'.JText::_('SIMPLE').'</option>';
        }
        if ($slave == 1 && !empty($master) && JFusionFunction::hasFeature($jname,'updateusergroup')) {
            //allow usergroup sync
            if ($advanced == 1) {
                $list_box.= '<option selected="selected" value="1">'.JText::_('ADVANCED').'</option>';
            } else {
                $list_box.= '<option value="1">'.JText::_('ADVANCED').'</option>';
            }
            //prepare the advanced options
            $JFusionMaster = JFusionFactory::getAdmin($master->name);
            $master_usergroups = $JFusionMaster->getUsergroupList();
            $advanced_usergroup = '<table class="usergroups">';
            if ($advanced == 1) {
                foreach ($master_usergroups as $master_usergroup) {
                    $select_value = (!isset($value[$master_usergroup->id])) ? '' : $value[$master_usergroup->id];
                    $advanced_usergroup.= '<tr><td>' . $master_usergroup->name . '</td>';
                    $advanced_usergroup.= '<td>' . JHTML::_('select.genericlist', $usergroups, $control_name . '[' . $name . '][' . $master_usergroup->id . ']', '', 'id', 'name', $select_value) . '</td></tr>';
                }
            } else {
                foreach ($master_usergroups as $master_usergroup) {
                    $advanced_usergroup.= '<tr><td>' . $master_usergroup->name . '</td>';
                    $advanced_usergroup.= '<td>' . JHTML::_('select.genericlist', $usergroups, $control_name . '[' . $name . '][' . $master_usergroup->id . ']', '', 'id', 'name', '') . '</td></tr>';
                }
            }
            $advanced_usergroup.= '</table>';
            //escape single quotes to prevent JS errors
            $advanced_usergroup = str_replace("'", "\'", $advanced_usergroup);
        } else {
            $advanced_usergroup = '';
        }
        $list_box.= '</select>';

        $document = JFactory::getDocument();

        $js = <<<JS
        JFusion.Plugin.groupDataArray[0] = '{$simple_usergroup}';
        JFusion.Plugin.groupDataArray[1] = '{$advanced_usergroup}';
JS;
        $document->addScriptDeclaration($js);

        $return = '<table><tr><td>'.JText::_('USERGROUP'). ' '. JText::_('MODE').'</td><td>'.$list_box.'</td></tr><tr><td COLSPAN=2><div id="JFusionUsergroup">';
        if ($advanced == 1) {
            $return .= $advanced_usergroup;
        } else {
            $return .= $simple_usergroup;
        }
        $return .= '</div></td></tr></table>';
        return $return;
    }

    /**
     * Get an multiusergroup element
     *
     * @param string $name         name of element
     * @param string $value        value of element
     * @param string $node         node of element
     * @param string $control_name name of controller
     *
     * @return string html
     */
    function multiusergroup($name, $value, $node, $control_name)
    {
        $jname = $this->getJname();
        //get the master plugin to be throughout
        $master = JFusionFunction::getMaster();
        $advanced = 0;

	    JHTML::setFormatOptions(array('format.eol' => '', 'format.indent' => ''));

        //detect is value is a serialized array
        if (substr($value, 0, 2) == 'a:') {
            $value = unserialize($value);
            //use advanced only if this plugin is not set as master
            if (!empty($master) && $master->name != $this->getJname()) {
                $advanced = 1;
            }
        }
        if (JFusionFunction::validPlugin($this->getJname())) {
            $usergroups = $this->getUsergroupList();
            if (!empty($usergroups)) {
                $simple_usergroup = '<table style="width:100%; border:0">';
                $simple_usergroup.= '<tr><td>' . JText::_('DEFAULT_USERGROUP') . '</td><td>' . JHTML::_('select.genericlist', $usergroups, $control_name . '[' . $name . ']', '', 'id', 'name', $value) . '</td></tr>';
                $simple_usergroup.= '</table>';
                //escape single quotes to prevent JS errors
                $simple_usergroup = str_replace("'", "\'", $simple_usergroup);
            } else {
                $simple_usergroup = '';
            }
        } else {
            return JText::_('SAVE_CONFIG_FIRST');
        }

        $multiusergroupdefault = $this->params->get('multiusergroupdefault');
        $master_usergroups = array();
        $JFusionMaster = null;
        if ( !empty($master) ) {
            $JFusionMaster = JFusionFactory::getAdmin($master->name);
            $master_usergroups = $JFusionMaster->getUsergroupList();
        }

        //check to see if current plugin is a slave
        $db = JFactory::getDBO();

	    $query = $db->getQuery(true)
		    ->select('slave')
		    ->from('#__jfusion')
		    ->where('name = '.$db->Quote($jname));
	    
        $db->setQuery($query);
        $slave = $db->loadResult();
        $list_box = '<select onchange="JFusion.Plugin.multiUsergroupSelect(this.selectedIndex);">';
        if ($advanced == 1) {
            $list_box.= '<option value="0">'.JText::_('SIMPLE').'</option>';
        } else {
            $list_box.= '<option value="0" selected="selected">'.JText::_('SIMPLE').'</option>';
        }

        $jfGroupCount = 0;
        if ($slave == 1 && $JFusionMaster && JFusionFunction::hasFeature($jname,'updateusergroup')) {
            //allow usergroup sync
            if ($advanced == 1) {
                $list_box.= '<option selected="selected" value="1">'.JText::_('ADVANCED').'</option>';
            } else {
                $list_box.= '<option value="1">'.JText::_('ADVANCED').'</option>';
            }
            //prepare the advanced options
            $advanced_usergroup = '<table id="usergroups" class="usergroups">';
            $advanced_usergroup.= '<tr><td>'.$JFusionMaster->getJname().'</td><td>'.$this->getJname().'</td><td>Default Group</td><td></td></tr>';

            $master_control_name = $control_name . '[' . $name . ']['.$JFusionMaster->getJname().']';
            $this_control_name = $control_name . '[' . $name . ']['.$this->getJname().']';
            if ($advanced == 1) {
                if ( isset($value[$JFusionMaster->getJname()]) && isset($value[$this->getJname()]) && count($value[$JFusionMaster->getJname()]) < count($value[$this->getJname()])) {
                    $groups = isset($value[$this->getJname()]) ? $value[$this->getJname()] : array();
                } else {
                    $groups = isset($value[$JFusionMaster->getJname()]) ? $value[$JFusionMaster->getJname()] : array();
                }

                foreach ($groups as $key => $select_value) {
                    $jfGroupCount++;
                    $select_value =  isset($value[$JFusionMaster->getJname()][$key]) ? $value[$JFusionMaster->getJname()][$key] : array();
                    $advanced_usergroup.= '<tr id="usergroups_row'.$jfGroupCount.'">';
                    if ($JFusionMaster->isMultiGroup()) {
                        $advanced_usergroup.= '<td>' . JHTML::_('select.genericlist', $master_usergroups , $master_control_name.'['.$jfGroupCount.'][]', 'MULTIPLE SIZE="10"', 'id', 'name', $select_value) . '</td>';
                    } else {
                        $advanced_usergroup.= '<td>' . JHTML::_('select.genericlist', $master_usergroups , $master_control_name.'['.$jfGroupCount.'][]', '', 'id', 'name', $select_value) . '</td>';
                    }

                    $select_value = isset($value[$this->getJname()][$key]) ? $value[$this->getJname()][$key] : array();
                    if ($this->isMultiGroup()) {
                        $advanced_usergroup.= '<td>' . JHTML::_('select.genericlist', $usergroups, $this_control_name.'['.$jfGroupCount.'][]', 'MULTIPLE SIZE="10"', 'id', 'name', $select_value) . '</td>';
                    } else {
                        $advanced_usergroup.= '<td>' . JHTML::_('select.genericlist', $usergroups, $this_control_name.'['.$jfGroupCount.'][]', '', 'id', 'name', $select_value) . '</td>';
                    }

                    $checked = '';
                    if ($multiusergroupdefault == $key) {
                        $checked = 'checked';
                    }
                    $advanced_usergroup.= '<td><input type="radio" '.$checked.' name="'.$control_name . '[' . $name . 'default]" value="'.$jfGroupCount.'"></td>';
                    $advanced_usergroup.= '<td><a href="javascript:JFusion.removeRow('.$jfGroupCount.')">'. JText::_('REMOVE').'</a></td>';

                    $advanced_usergroup.= '</tr>';
                }
            } else {
                $jfGroupCount++;
                $select_value = '';
                $advanced_usergroup.= '<tr id="usergroups_row'.$jfGroupCount.'">';
                if ($JFusionMaster->isMultiGroup()) {
                    $advanced_usergroup.= '<td>' . JHTML::_('select.genericlist', $master_usergroups , $master_control_name.'['.$jfGroupCount.'][]', 'MULTIPLE SIZE="10"', 'id', 'name', $select_value) . '</td>';
                } else {
                    $advanced_usergroup.= '<td>' . JHTML::_('select.genericlist', $master_usergroups , $master_control_name.'['.$jfGroupCount.'][]', '', 'id', 'name', $select_value) . '</td>';
                }

                $select_value = '';
                if ($this->isMultiGroup()) {
                    $advanced_usergroup.= '<td>' . JHTML::_('select.genericlist', $usergroups, $this_control_name.'['.$jfGroupCount.'][]', 'MULTIPLE SIZE="10"', 'id', 'name', $select_value) . '</td>';
                } else {
                    $advanced_usergroup.= '<td>' . JHTML::_('select.genericlist', $usergroups, $this_control_name.'['.$jfGroupCount.'][]', '', 'id', 'name', $select_value) . '</td>';
                }
                $checked = 'checked';
                $advanced_usergroup.= '<td><input type="radio" '.$checked.' name="'.$control_name . '[' . $name . 'default]" value="'.$jfGroupCount.'"></td>';
                $advanced_usergroup.= '<td><a href="javascript:JFusion.removeRow('.$jfGroupCount.')">'.JText::_('REMOVE').'</a></td>';

                $advanced_usergroup.= '</tr>';
            }

            $advanced_usergroup.= '</table>';
            //escape single quotes to prevent JS errors
            $advanced_usergroup = str_replace("'", "\'", $advanced_usergroup);
        } else {
            $advanced_usergroup = '';
        }
        $list_box.= '</select>';
        $plugin = array();
        if ($JFusionMaster) {
            $plugin['name'] = $this->getJname();
            $plugin['master'] = $JFusionMaster->getJname();
            $plugin['count'] = $jfGroupCount;
            $plugin[$this->getJname()]['groups'] = $usergroups;
            $plugin[$this->getJname()]['type'] = $this->isMultiGroup() ? 'multi':'single';
            $plugin[$JFusionMaster->getJname()]['groups'] = $master_usergroups;
            $plugin[$JFusionMaster->getJname()]['type'] = $JFusionMaster->isMultiGroup() ? 'multi':'single';
        }

        $document = JFactory::getDocument();
        $plugin = json_encode($plugin);
        $js = <<<JS
			JFusion.plugin = {$plugin};

	        JFusion.Plugin.groupDataArray[0] = '{$simple_usergroup}';
	        JFusion.Plugin.groupDataArray[1] = '{$advanced_usergroup}';

	        JFusion.addRow = function() {
	        	JFusion.plugin['count']++;
	        	var count = JFusion.plugin['count'];

	        	var master = JFusion.plugin['master'];
	        	var name = JFusion.plugin['name'];

	        	var elTrNew = new Element('tr', {
					'id' : 'usergroups_row'+count
	        	});

	        	elTrNew.appendChild(new Element('td')).appendChild(JFusion.createSelect(master));
	        	elTrNew.appendChild(new Element('td')).appendChild(JFusion.createSelect(name));
	        	elTrNew.appendChild(new Element('td')).appendChild(new Element('input', {
	        		'type': 'radio',
	        		'name': 'params[multiusergroupdefault]',
	        		'value': count
	        	}));
	        	elTrNew.appendChild(new Element('td')).appendChild(new Element('a', {
					'href': 'javascript:JFusion.removeRow('+count+')',
					'html': JFusion.JText('REMOVE')
	        	}));

	        	var divEls = $('usergroups');
	        	divEls.appendChild(elTrNew);
	        };

	        JFusion.removeRow = function (row) {
	        	var trEl = $('usergroups_row'+row);
	        	trEl.style.display = 'none';
	        	trEl.empty();
	        };

	        JFusion.createSelect = function(name) {
	        	var count = JFusion.plugin['count'];
	        	var type = JFusion.plugin[name]['type'];
	        	var groups = JFusion.plugin[name]['groups'];

				if (type == 'multi') {
					var elSelNew = new Element('select', {
						'name': 'params[multiusergroup]['+name+']['+count+'][]',
						'size': 10,
						'multiple': 'multiple'
					});
				} else {
					var elSelNew = new Element('select', {
						'name': 'params[multiusergroup]['+name+']['+count+'][]'
					});
				}
				Object.each(groups, function (value) {
					elSelNew.appendChild(new Element('option', {
						'text': value.name,
						'value': value.id
					}));
				});
				return elSelNew;
	        };
JS;
        $document->addScriptDeclaration($js);

        $addbutton='<a id="addgroupset"></a>';
        $return = '<table><tr><td>'.JText::_('USERGROUP'). ' '. JText::_('MODE').'</td><td>'.$list_box.'</td></tr><tr><td colspan="2"><div id="JFusionUsergroup">';
        if ($advanced == 1) {
            if (($JFusionMaster && $JFusionMaster->isMultiGroup()) || $this->isMultiGroup()) {
                $addbutton = '<a id="addgroupset" href="javascript:JFusion.addRow()">'. JText::_('ADD_GROUP_PAIR').'</a>';
            }
            $return .= $advanced_usergroup;
        } else {
            if (($JFusionMaster && $JFusionMaster->isMultiGroup()) || $this->isMultiGroup()) {
                $addbutton = '<a id="addgroupset" style="display: none;" href="javascript:JFusion.addRow()">'. JText::_('ADD_GROUP_PAIR').'</a>';
            }
            $return .= $simple_usergroup;
        }
        $return .= '</div>'.$addbutton.'</td></tr></table>';
        return $return;
    }

    /**
     * Function returns the path to the modfile
     *
     * @param string $filename file name
     * @param int    &$error   error number
     * @param string &$reason  error reason
     *
     * @return string $mod_file path and file of the modfile.
     */
    function getModFile($filename, &$error, &$reason)
    {
        //check to see if a path is defined
        $path = $this->params->get('source_path');
        if (empty($path)) {
            $error = 1;
            $reason = JText::_('SET_PATH_FIRST');
        }
        //check for trailing slash and generate file path
        if (substr($path, -1) == DIRECTORY_SEPARATOR) {
            $mod_file = $path . $filename;
        } else {
            $mod_file = $path . DIRECTORY_SEPARATOR . $filename;
        }
        //see if the file exists
        if (!file_exists($mod_file) && $error == 0) {
            $error = 1;
            $reason = JText::_('NO_FILE_FOUND');
        }
        return $mod_file;
    }

    /**
     * Called when JFusion is uninstalled so that plugins can run uninstall processes such as removing auth mods
     * @return array    [0] boolean true if successful uninstall
     *                  [1] mixed reason(s) why uninstall was unsuccessful
     */
    function uninstall()
    {
        return array(true, '');
    }

    /**
     * do plugin support multi usergroups
     *
     * @return bool
     */
    function isMultiGroup()
    {
        static $muiltisupport;
        if (!isset($muiltisupport)) {
            $multiusergroup = $this->params->get('multiusergroup',null);
            if ($multiusergroup !== null) {
                $muiltisupport = true;
            } else {
                $muiltisupport = false;
            }
        }
        return $muiltisupport;
    }

    /**
     * This function is used to display to the user if the software requires file access to work
     *
     * @return string UNKNOWN or JNO or JYES or ??
     */
    function requireFileAccess()
    {
        return 'UNKNOWN';
    }

	/**
	 * This function tells if the software supports more than one instance
	 *
	 * @return bool do the plugin support multi instance
	 */
	function multiInstance()
	{
		return true;
	}

    /**
     * Function to check if a given itemid is configured for the plugin in question.
     *
     * @param int $itemid
     * @return bool
     */
    function isValidItemID($itemid)
    {
        $result = false;
        if ($itemid) {
            $app = JFactory::getApplication();
            $menus = $app->getMenu('site');
            $item = $menus->getItem($itemid);
            if ($item) {
                $jPluginParam = unserialize(base64_decode($item->params->get('JFusionPluginParam')));
                if (is_array($jPluginParam) && $jPluginParam['jfusionplugin'] == $this->getJname()) {
                    $result = true;
                }
            }
        }
        return $result;
    }
}