<?php namespace JFusion\Plugin;

/**
 * Abstract public class for JFusion
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

use JFusion\Factory;

/**
 * Abstract interface for all JFusion functions that are accessed through the Joomla front-end
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class Plugin_Front extends Plugin
{
	var $helper;

	/**
	 * @param string $instance instance name of this plugin
	 */
	function __construct($instance)
	{
		parent::__construct($instance);
		//get the helper object
		$this->helper = & Factory::getHelper($this->getJname(), $this->getName());
	}

    /**
     * extends JFusion's parseRoute function to reconstruct the SEF URL
     *
     * @param array &$vars vars already parsed by JFusion's router.php file
     *
     */
    function parseRoute(&$vars)
    {
    }

    /**
     * extends JFusion's buildRoute function to build the SEF URL
     *
     * @param array &$segments query already prepared by JFusion's router.php file
     */
    function buildRoute(&$segments)
    {
    }

    /**
     * Returns the registration URL for the integrated software
     *
     * @return string registration URL
     */
    function getRegistrationURL()
    {
        return '';
    }

    /**
     * Returns the lost password URL for the integrated software
     *
     * @return string lost password URL
     */
    function getLostPasswordURL()
    {
        return '';
    }

    /**
     * Returns the lost username URL for the integrated software
     *
     * @return string lost username URL
     */
    function getLostUsernameURL()
    {
        return '';
    }

    /**
     * Parses custom BBCode defined in $this->prepareText() and called by the nbbc parser via Framework::parseCode()
     *
     * @param mixed $bbcode
     * @param int $action
     * @param string $name
     * @param string $default
     * @param mixed $params
     * @param string $content
     *
     * @return mixed bbcode converted to html
     */
    function parseCustomBBCode($bbcode, $action, $name, $default, $params, $content)
    {
        if ($action == 1) {
            $return = true;
        } else {
            $return = $content;
            switch ($name) {
                case 'size':
                    $return = '<span style="font-size:' . $default . '">' . $content . '</span>';
                    break;
                case 'glow':
                    $temp = explode(',', $default);
                    $color = (!empty($temp[0])) ? $temp[0] : 'red';
                    $return = '<span style="background-color:' . $color . '">' . $content . '</span>';
                    break;
                case 'shadow':
                    $temp = explode(',', $default);
                    $color = (!empty($temp[0])) ? $temp[0] : '#6374AB';
                    $dir = (!empty($temp[1])) ? $temp[1] : 'left';
                    $x = ($dir == 'left') ? '-0.2em' : '0.2em';
                    $return = '<span style="text-shadow: ' . $color . ' ' . $x . ' 0.1em 0.2em;">' . $content . '</span>';
                    break;
                case 'move':
                    $return = '<marquee>' . $content . '</marquee>';
                    break;
                case 'pre':
                    $return = '<pre>' . $content . '</pre>';
                    break;
                case 'hr':
	                $return = '<hr>';
                    break;
                case 'flash':
                    $temp = explode(',', $default);
                    $width = (!empty($temp[0])) ? $temp[0] : '200';
                    $height = (!empty($temp[1])) ? $temp[1] : '200';
                    $return = <<<HTML
                        <object classid="clsid:D27CDB6E-AE6D-11CF-96B8-444553540000" codebase="http://active.macromedia.com/flash2/cabs/swflash.cab#version=5,0,0,0" width="{$width}" height="{$height}">
                            <param name="movie" value="{$content}" />
                            <param name="play" value="false" />
                            <param name="loop" value="false" />
                            <param name="quality" value="high" />
                            <param name="allowScriptAccess" value="never" />
                            <param name="allowNetworking" value="internal" />
                            <embed src="{$content}" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash" width="{$width}" height="{$height}" play="false" loop="false" quality="high" allowscriptaccess="never" allownetworking="internal">
                            </embed>
                        </object>
HTML;
                    break;
                case 'ftp':
                    if (empty($default)) {
                        $default = $content;
                    }
                    $return = '<a href="' . $content . '">' . $default . '</a>';
                    break;
                case 'table':
                    $return = '<table>' . $content . '</table>';
                    break;
                case 'tr':
                    $return = '<tr>' . $content . '</tr>';
                    break;
                case 'td':
                    $return = '<td>' . $content . '</td>';
                    break;
                case 'tt';
                    $return = '<tt>' . $content . '</tt>';
                    break;
                case 'o':
                case 'O':
                case '0':
                    $return = '<li type="circle">' . $content . '</li>';
                    break;
                case '*':
                case '@':
                    $return = '<li type="disc">' . $content . '</li>';
                    break;
                case '+':
                case 'x':
                case '#':
                    $return = '<li type="square">' . $content . '</li>';
                    break;
                case 'abbr':
                    if (empty($default)) {
                        $default = $content;
                    }
                    $return = '<abbr title="' . $default . '">' . $content . '</abbr>';
                    break;
                case 'anchor':
                    if (!empty($default)) {
                        $return = '<span id="' . $default . '">' . $content . '</span>';
                    } else {
                        $return = $content;
                    }
                    break;
                case 'black':
                case 'blue':
                case 'green':
                case 'red':
                case 'white':
                    $return = '<span style="color: ' . $name . ';">' . $content . '</span>';
                    break;
                case 'iurl':
                    if (empty($default)) {
                        $default = $content;
                    }
                    $return = '<a href="' . htmlspecialchars($default) . '" class="bbcode_url" target="_self">' . $content . '</a>';
                    break;
                case 'html':
                case 'nobbc':
                case 'php':
                    $return = $content;
                    break;
                case 'ltr':
                    $return = '<div style="text-align: left;" dir="$name">' . $content . '</div>';
                    break;
                case 'rtl':
                    $return = '<div style="text-align: right;" dir="$name">' . $content . '</div>';
                    break;
                case 'me':
                    $return = '<div style="color: red;">* ' . $default . ' ' . $content . '</div>';
                    break;
                case 'time':
                    $return = date('Y-m-d H:i', $content);
                    break;
                default:
                    break;
            }
        }
        return $return;
    }
}
