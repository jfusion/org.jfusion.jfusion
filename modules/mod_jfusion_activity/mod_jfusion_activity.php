<?php
/**
 * This is the activity module helper file
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    Modules
 * @subpackage Activity
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
*/

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

/**
* load the helper file
*/
require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'helper.php');

//check if the JFusion component is installed
$model_file = JPATH_ADMINISTRATOR .DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_jfusion'.DIRECTORY_SEPARATOR.'models'.DIRECTORY_SEPARATOR.'model.factory.php';
$factory_file = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jfusion.php';
try {
	if (file_exists($model_file) && file_exists($factory_file)) {

		/**
		* require the JFusion libraries
		*/
		require_once $model_file;
		require_once $factory_file;
	    /**
	     * @ignore
	     * @var $params JRegistry
	     */
	    $pluginParamValue = $params->get('JFusionPluginParam');

		$parametersInstance = new JRegistry('');
	    if ($pluginParamValue) {
			$data = unserialize(base64_decode($pluginParamValue));
			if (is_array($data)) {
				foreach ($data as $key => $value) {
					$parametersInstance->set($key, $value);
				}
			}
		}
		$jname = $parametersInstance->get('jfusionplugin');

		if(JFusionFunction::validPlugin($jname)) {
			$pluginParam = new JRegistry('');
			$data = $parametersInstance->get($jname);
			if (is_array($data)) {
				foreach ($data as $key => $value) {
					$pluginParam->set($key, $value);
				}
			}

			$view = $pluginParam->get('view', 'auto');
	        $forum = JFusionFactory::getForum($jname);

			defined('_DATE_FORMAT_LC2') or define('_DATE_FORMAT_LC2','%A, %d %B %Y %H:%M');
			defined('LAT') or define('LAT', 0);
			defined('LCT') or define('LCT', 1);
			defined('LCP') or define('LCP', 2);
			defined('LINKTHREAD') or define('LINKTHREAD', 0);
			defined('LINKPOST') or define('LINKPOST', 1);

			// configuration
		    $config['mode'] = $params->get('mode', 0);
			$config['lat_mode'] = $params->get('lat_mode', 0);
	        $config['show_reply_num'] = $params->get('show_reply_num', 0);
	        $config['linktype'] = $params->get('linktype', 0);
	        $config['display_body'] = $params->get('display_body', 0);
	        $config['replace_subject'] = $params->get('replace_subject', 0);
			$config['new_window'] = $params->get('new_window', 0);
			$config['forum_mode'] = $params->get('forum_mode', 0);
	        $config['character_limit'] = $params->get('character_limit', 150);
	        $config['character_limit_subject'] = $params->get('character_limit_subject', 50);
			$config['result_limit'] = $params->get('result_limit', 5);
	        $config['date_format'] = $params->get('custom_date', _DATE_FORMAT_LC2);
	        $config['result_order'] = ($params->get('result_order', 0)) ? 'DESC' : 'ASC';
	        $config['showdate'] = $params->get('showdate', 1);
	        $config['showuser'] = $params->get('showuser', 1);
	        $config['display_name'] = $params->get('display_name', 0);
	        $config['shownew'] = $params->get('shownew', 0);
	        $config['userlink'] = $params->get('userlink', 0);
	        $config['userlink_software'] = $params->get('userlink_software', false);
	        $config['userlink_custom'] = $params->get('userlink_custom', false);
	        $config['avatar'] = $params->get('avatar', 0);
	        $config['avatar_software'] = $params->get('avatar_software', 'jfusion');
	        $config['avatar_keep_proportional'] = $params->get('avatar_keep_proportional', '1');
	        $config['avatar_height'] = $params->get('avatar_height', 53);
	        $config['avatar_width'] = $params->get('avatar_width', 40);
	        $config['debug'] = $params->get('debug');
	        $config['itemid'] = $params->get('itemid');
			$config['selected_forums'] = $params->get('selected_forums');
			if ($params->get('new_window')) {
				$config['new_window'] = '_blank';
			} else {
			    $config['new_window'] = '_self';
			}

			//can be used in plugins filterActivityResults
			defined('ACTIVITY_MODE') or define('ACTIVITY_MODE', $config['mode']);

			if($view == 'auto') {
				$db = JFusionFactory::getDatabase($jname);

	            if ($config['forum_mode'] == 0 || empty($config['selected_forums'])) {
	                $selectedforumssql = '';
	            } else if (is_array($config['selected_forums'])) {
	                $selectedforumssql = implode(',', $config['selected_forums']);
	            } else {
	                $selectedforumssql = $config['selected_forums'];
	            }

	            //define some other JFusion specific parameters
	            $query = $forum->getActivityQuery($selectedforumssql, $config['result_order'], $config['result_limit']);
	            if (!empty($query)) {
	                // load
	                if($config['mode']==LAT) {
	                    $db->setQuery($query[$config['mode'].$config['lat_mode']]);
	                } else {
	                    $db->setQuery($query[$config['mode']]);
	                }

		            try {
			            $results = $db->loadObjectList();
			            $error = JText::_('JLIB_DATABASE_FUNCTION_NOERROR');
		            } catch( Exception $e ) {
			            $error = $e->getMessage();
			            $results = array();
		            }

	                if($config['debug']) {
	                    $resultBeforeFiltering = $results;
	                } else {
	                    $resultBeforeFiltering = '';
	                }
	                if (!empty($results)) {
	                    $forum->filterActivityResults($results, $config['result_limit']);
	                }
	                //reorder the keys for the for loop
	                if(is_array($results)) {
	                    $results = array_values($results);
	                }
	                if ($config['debug']) {
	                    $queryMode = ($config['mode']==LAT) ? $config['mode'].$config['lat_mode'] : $config['mode'];
	                    $debug  = 'Query mode: ' . $queryMode . '<br><br>';
	                    $sqlQuery = ($config['mode']==LAT) ? $query[$config['mode'].$config['lat_mode']] : $query[$config['mode']];
	                    $debug .= 'SQL Query: ' . $sqlQuery .'<br><br>';
	                    $debug .= 'Error: ' . $error . '<br><br>';
	                    $debug .= 'Results Before Filtering:<br><pre>'.print_r($resultBeforeFiltering,true).'</pre><br><br>';
	                    $debug .= 'Results After Filtering:<br><pre>'.print_r($results,true).'</pre><br><br>';
	                    die($debug);
	                } else {
	                    modjfusionActivityHelper::appendAutoOutput($results, $jname, $config, $params);
	                    require(JModuleHelper::getLayoutPath('mod_jfusion_activity'));
	                }
	            }
			} else {
				if (JFusionFunction::methodDefined($forum, 'renderActivityModule')) {
					$output = $forum->renderActivityModule($config,$view, $pluginParam);
					echo $output;
				} else {
					throw new Exception(JText::_('NOT_IMPLEMENTED_YET'));
				}
			}
		} else {
			if (empty($jname)) {
				throw new Exception(JText::_('MODULE_NOT_CONFIGURED'));
			} else {
				throw new Exception(JText::_('NO_PLUGIN'));
			}
		}
	} else {
		throw new Exception(JText::_('NO_COMPONENT'));
	}
} catch (Exception $e) {
	echo $e->getMessage();
}