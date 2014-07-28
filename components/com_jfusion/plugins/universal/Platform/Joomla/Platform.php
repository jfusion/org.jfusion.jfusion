<?php namespace jfusion\plugins\universal\Platform\Joomla;

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
use JFusion\Factory;
use JFusion\Framework;

use Joomla\Language\Text;
use JFusion\Plugin\Platform\Joomla;
use Psr\Log\LogLevel;

defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Forum Class for phpBB3
 * For detailed descriptions on these functions please check the model.abstractforum.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Platform extends Joomla
{
	/**
	 * @param string $url
	 * @param int $itemid
	 *
	 * @return string
	 */
	function generateRedirectCode($url, $itemid)
	{
		$universal_url = $this->params->get('source_url');

		//create the new redirection code

		$redirect_code = '//JFUSION REDIRECT START
//SET SOME VARS
$joomla_url = \'' . $url . '\';
$universal_url \'' . $universal_url . '\';
$joomla_itemid = ' . $itemid . ';
	';
		$redirect_code .= '
if(!isset($_COOKIE[\'jfusionframeless\']))';

		$redirect_code .= '
{
	$list = explode(\'/\', $universal_url, 4);
	$jfile = ltrim (str_replace($list[3], \'\', $_SERVER[\'PHP_SELF\'] ), \'/\');
	$jfusion_url = $joomla_url . \'index.php?option=com_jfusion&Itemid=\' . $joomla_itemid . \'&jfile=\'.$jfile. \'&\' . $_SERVER[\'QUERY_STRING\'];
	header(\'Location: \' . $jfusion_url);
	exit;
}
//JFUSION REDIRECT END';
		return $redirect_code;
	}

	/**
	 * @param $name
	 * @param $value
	 * @param $node
	 * @param $control_name
	 * @return string
	 */
	function showRedirectMod($name, $value, $node, $control_name)
	{
		$action = Factory::getApplication()->input->get('action');
		if ($action == 'redirectcode') {
			$joomla_url = Factory::getParams('joomla_int')->get('source_url');
			$joomla_itemid = $this->params->get('redirect_itemid');

			//check to see if all vars are set
			if (empty($joomla_url)) {
				Framework::raise(LogLevel::WARNING, Text::_('MISSING') . ' Joomla URL', $this->getJname());
			} else if (empty($joomla_itemid) || !is_numeric($joomla_itemid)) {
				Framework::raise(LogLevel::WARNING, Text::_('MISSING') . ' ItemID', $this->getJname());
			} else if (!$this->isValidItemID($joomla_itemid)) {
				Framework::raise(LogLevel::WARNING, Text::_('MISSING') . ' ItemID ' . Text::_('MUST BE') . ' ' . $this->getJname(), $this->getJname());
			} else {
				header('Content-disposition: attachment; filename=jfusion_' . $this->getJname() . '_redirectcode.txt');
				header('Pragma: no-cache');
				header('Expires: 0');
				header ('content-type: text/html');

				echo $this->generateRedirectCode($joomla_url, $joomla_itemid);
				exit();
			}
		}

		$output = ' <a href="index.php?option=com_jfusion&amp;task=plugineditor&amp;jname=' . $this->getJname() . '&amp;action=redirectcode">' . Text::_('MOD_ENABLE_MANUALLY') . '</a>';
		return $output;
	}
}
