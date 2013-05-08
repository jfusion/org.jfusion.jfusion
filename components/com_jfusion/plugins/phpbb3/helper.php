<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpbb
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Helper Class for Dokuwiki
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpbb
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionHelper_phpbb3
{
    var $bbcode_uid = false;
    var $bbcodes = array();
    var $warn_msg = array();
    var $bbcode_bitfield = '';
    var $db = '';

    /**
     *
     */
    function __construct()
    {
    }

    /**
     * Returns the name for this plugin
     *
     * @return string
     */
    function getJname() {
        return 'phpbb3';
    }

    /**
     * This function is to emulate phpbb set_var used needed to proper encode "clean" password, and other variables.
     *
     * @param $var
     * @return string
     */
    function clean_string($var)
    {
        $var = trim(htmlspecialchars(str_replace(array("\r\n", "\r", "\0"), array("\n", "\n", ''), $var), ENT_COMPAT, 'UTF-8'));

        if (!empty($var)) {
            // Make sure multibyte characters are well formed
            if (!preg_match('/^./u', $var)) {
                $var = '';
            }
        }

        // Register globals and magic quotes have been dropped in PHP 5.4
        if (version_compare(PHP_VERSION, '5.4.0-dev', '>=')) {
            $strip = false;
        } else {
            @set_magic_quotes_runtime(0);
            $strip = (get_magic_quotes_gpc()) ? true : false;
        }
        $var = ($strip) ? stripslashes($var) : $var;
        return $var;
    }
    /**
     * This function is used to generate a "clean" version of a string.
     * Clean means that it is a case insensitive form (case folding) and that it is normalized (NFC).
     * Additionally a homographs of one character are transformed into one specific character (preferably ASCII
     * if it is an ASCII character).
     *
     * Please be aware that if you change something within this function or within
     * functions used here you need to rebuild/update the username_clean column in the users table. And all other
     * columns that store a clean string otherwise you will break this functionality.
     *
     * @param string $text An unclean string, maybe user input (has to be valid UTF-8!)
     * @return string Cleaned up version of the input string
     */
    function utf8_clean_string($text) {
        static $homographs = array();
        if (empty($homographs)) {
            $homographs = include (JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'utf' . DS . 'confusables.php');
        }
        $text = $this->utf8_case_fold_nfkc($text);
        $text = strtr($text, $homographs);
        // Other control characters
        $text = preg_replace('#(?:[\x00-\x1F\x7F]+|(?:\xC2[\x80-\x9F])+)#', '', $text);
        // we need to reduce multiple spaces to a single one
        $text = preg_replace('# {2,}#', ' ', $text);
        // we can use trim here as all the other space characters should have been turned
        // into normal ASCII spaces by now
        return trim($text);
    }

    /**
     * Takes the input and does a "special" case fold. It does minor normalization
     * and returns NFKC compatible text
     *
     * @param string $text text to be case folded
     * @param string $option determines how we will fold the cases
     * @return string case folded text
     */
    function utf8_case_fold_nfkc($text, $option = 'full') {
        static $fc_nfkc_closure = array("\xCD\xBA" => "\x20\xCE\xB9", "\xCF\x92" => "\xCF\x85", "\xCF\x93" => "\xCF\x8D", "\xCF\x94" => "\xCF\x8B", "\xCF\xB2" => "\xCF\x83", "\xCF\xB9" => "\xCF\x83", "\xE1\xB4\xAC" => "\x61", "\xE1\xB4\xAD" => "\xC3\xA6", "\xE1\xB4\xAE" => "\x62", "\xE1\xB4\xB0" => "\x64", "\xE1\xB4\xB1" => "\x65", "\xE1\xB4\xB2" => "\xC7\x9D", "\xE1\xB4\xB3" => "\x67", "\xE1\xB4\xB4" => "\x68", "\xE1\xB4\xB5" => "\x69", "\xE1\xB4\xB6" => "\x6A", "\xE1\xB4\xB7" => "\x6B", "\xE1\xB4\xB8" => "\x6C", "\xE1\xB4\xB9" => "\x6D", "\xE1\xB4\xBA" => "\x6E", "\xE1\xB4\xBC" => "\x6F", "\xE1\xB4\xBD" => "\xC8\xA3", "\xE1\xB4\xBE" => "\x70", "\xE1\xB4\xBF" => "\x72", "\xE1\xB5\x80" => "\x74", "\xE1\xB5\x81" => "\x75", "\xE1\xB5\x82" => "\x77", "\xE2\x82\xA8" => "\x72\x73", "\xE2\x84\x82" => "\x63", "\xE2\x84\x83" => "\xC2\xB0\x63", "\xE2\x84\x87" => "\xC9\x9B", "\xE2\x84\x89" => "\xC2\xB0\x66", "\xE2\x84\x8B" => "\x68", "\xE2\x84\x8C" => "\x68", "\xE2\x84\x8D" => "\x68", "\xE2\x84\x90" => "\x69", "\xE2\x84\x91" => "\x69", "\xE2\x84\x92" => "\x6C", "\xE2\x84\x95" => "\x6E", "\xE2\x84\x96" => "\x6E\x6F", "\xE2\x84\x99" => "\x70", "\xE2\x84\x9A" => "\x71", "\xE2\x84\x9B" => "\x72", "\xE2\x84\x9C" => "\x72", "\xE2\x84\x9D" => "\x72", "\xE2\x84\xA0" => "\x73\x6D", "\xE2\x84\xA1" => "\x74\x65\x6C", "\xE2\x84\xA2" => "\x74\x6D", "\xE2\x84\xA4" => "\x7A", "\xE2\x84\xA8" => "\x7A", "\xE2\x84\xAC" => "\x62", "\xE2\x84\xAD" => "\x63", "\xE2\x84\xB0" => "\x65", "\xE2\x84\xB1" => "\x66", "\xE2\x84\xB3" => "\x6D", "\xE2\x84\xBB" => "\x66\x61\x78", "\xE2\x84\xBE" => "\xCE\xB3", "\xE2\x84\xBF" => "\xCF\x80", "\xE2\x85\x85" => "\x64", "\xE3\x89\x90" => "\x70\x74\x65", "\xE3\x8B\x8C" => "\x68\x67", "\xE3\x8B\x8E" => "\x65\x76", "\xE3\x8B\x8F" => "\x6C\x74\x64", "\xE3\x8D\xB1" => "\x68\x70\x61", "\xE3\x8D\xB3" => "\x61\x75", "\xE3\x8D\xB5" => "\x6F\x76", "\xE3\x8D\xBA" => "\x69\x75", "\xE3\x8E\x80" => "\x70\x61", "\xE3\x8E\x81" => "\x6E\x61", "\xE3\x8E\x82" => "\xCE\xBC\x61", "\xE3\x8E\x83" => "\x6D\x61", "\xE3\x8E\x84" => "\x6B\x61", "\xE3\x8E\x85" => "\x6B\x62", "\xE3\x8E\x86" => "\x6D\x62", "\xE3\x8E\x87" => "\x67\x62", "\xE3\x8E\x8A" => "\x70\x66", "\xE3\x8E\x8B" => "\x6E\x66", "\xE3\x8E\x8C" => "\xCE\xBC\x66", "\xE3\x8E\x90" => "\x68\x7A", "\xE3\x8E\x91" => "\x6B\x68\x7A", "\xE3\x8E\x92" => "\x6D\x68\x7A", "\xE3\x8E\x93" => "\x67\x68\x7A", "\xE3\x8E\x94" => "\x74\x68\x7A", "\xE3\x8E\xA9" => "\x70\x61", "\xE3\x8E\xAA" => "\x6B\x70\x61", "\xE3\x8E\xAB" => "\x6D\x70\x61", "\xE3\x8E\xAC" => "\x67\x70\x61", "\xE3\x8E\xB4" => "\x70\x76", "\xE3\x8E\xB5" => "\x6E\x76", "\xE3\x8E\xB6" => "\xCE\xBC\x76", "\xE3\x8E\xB7" => "\x6D\x76", "\xE3\x8E\xB8" => "\x6B\x76", "\xE3\x8E\xB9" => "\x6D\x76", "\xE3\x8E\xBA" => "\x70\x77", "\xE3\x8E\xBB" => "\x6E\x77", "\xE3\x8E\xBC" => "\xCE\xBC\x77", "\xE3\x8E\xBD" => "\x6D\x77", "\xE3\x8E\xBE" => "\x6B\x77", "\xE3\x8E\xBF" => "\x6D\x77", "\xE3\x8F\x80" => "\x6B\xCF\x89", "\xE3\x8F\x81" => "\x6D\xCF\x89", "\xE3\x8F\x83" => "\x62\x71", "\xE3\x8F\x86" => "\x63\xE2\x88\x95\x6B\x67", "\xE3\x8F\x87" => "\x63\x6F\x2E", "\xE3\x8F\x88" => "\x64\x62", "\xE3\x8F\x89" => "\x67\x79", "\xE3\x8F\x8B" => "\x68\x70", "\xE3\x8F\x8D" => "\x6B\x6B", "\xE3\x8F\x8E" => "\x6B\x6D", "\xE3\x8F\x97" => "\x70\x68", "\xE3\x8F\x99" => "\x70\x70\x6D", "\xE3\x8F\x9A" => "\x70\x72", "\xE3\x8F\x9C" => "\x73\x76", "\xE3\x8F\x9D" => "\x77\x62", "\xE3\x8F\x9E" => "\x76\xE2\x88\x95\x6D", "\xE3\x8F\x9F" => "\x61\xE2\x88\x95\x6D", "\xF0\x9D\x90\x80" => "\x61", "\xF0\x9D\x90\x81" => "\x62", "\xF0\x9D\x90\x82" => "\x63", "\xF0\x9D\x90\x83" => "\x64", "\xF0\x9D\x90\x84" => "\x65", "\xF0\x9D\x90\x85" => "\x66", "\xF0\x9D\x90\x86" => "\x67", "\xF0\x9D\x90\x87" => "\x68", "\xF0\x9D\x90\x88" => "\x69", "\xF0\x9D\x90\x89" => "\x6A", "\xF0\x9D\x90\x8A" => "\x6B", "\xF0\x9D\x90\x8B" => "\x6C", "\xF0\x9D\x90\x8C" => "\x6D", "\xF0\x9D\x90\x8D" => "\x6E", "\xF0\x9D\x90\x8E" => "\x6F", "\xF0\x9D\x90\x8F" => "\x70", "\xF0\x9D\x90\x90" => "\x71", "\xF0\x9D\x90\x91" => "\x72", "\xF0\x9D\x90\x92" => "\x73", "\xF0\x9D\x90\x93" => "\x74", "\xF0\x9D\x90\x94" => "\x75", "\xF0\x9D\x90\x95" => "\x76", "\xF0\x9D\x90\x96" => "\x77", "\xF0\x9D\x90\x97" => "\x78", "\xF0\x9D\x90\x98" => "\x79", "\xF0\x9D\x90\x99" => "\x7A", "\xF0\x9D\x90\xB4" => "\x61", "\xF0\x9D\x90\xB5" => "\x62", "\xF0\x9D\x90\xB6" => "\x63", "\xF0\x9D\x90\xB7" => "\x64", "\xF0\x9D\x90\xB8" => "\x65", "\xF0\x9D\x90\xB9" => "\x66", "\xF0\x9D\x90\xBA" => "\x67", "\xF0\x9D\x90\xBB" => "\x68", "\xF0\x9D\x90\xBC" => "\x69", "\xF0\x9D\x90\xBD" => "\x6A", "\xF0\x9D\x90\xBE" => "\x6B", "\xF0\x9D\x90\xBF" => "\x6C", "\xF0\x9D\x91\x80" => "\x6D", "\xF0\x9D\x91\x81" => "\x6E", "\xF0\x9D\x91\x82" => "\x6F", "\xF0\x9D\x91\x83" => "\x70", "\xF0\x9D\x91\x84" => "\x71", "\xF0\x9D\x91\x85" => "\x72", "\xF0\x9D\x91\x86" => "\x73", "\xF0\x9D\x91\x87" => "\x74", "\xF0\x9D\x91\x88" => "\x75", "\xF0\x9D\x91\x89" => "\x76", "\xF0\x9D\x91\x8A" => "\x77", "\xF0\x9D\x91\x8B" => "\x78", "\xF0\x9D\x91\x8C" => "\x79", "\xF0\x9D\x91\x8D" => "\x7A", "\xF0\x9D\x91\xA8" => "\x61", "\xF0\x9D\x91\xA9" => "\x62", "\xF0\x9D\x91\xAA" => "\x63", "\xF0\x9D\x91\xAB" => "\x64", "\xF0\x9D\x91\xAC" => "\x65", "\xF0\x9D\x91\xAD" => "\x66", "\xF0\x9D\x91\xAE" => "\x67", "\xF0\x9D\x91\xAF" => "\x68", "\xF0\x9D\x91\xB0" => "\x69", "\xF0\x9D\x91\xB1" => "\x6A", "\xF0\x9D\x91\xB2" => "\x6B", "\xF0\x9D\x91\xB3" => "\x6C", "\xF0\x9D\x91\xB4" => "\x6D", "\xF0\x9D\x91\xB5" => "\x6E", "\xF0\x9D\x91\xB6" => "\x6F", "\xF0\x9D\x91\xB7" => "\x70", "\xF0\x9D\x91\xB8" => "\x71", "\xF0\x9D\x91\xB9" => "\x72", "\xF0\x9D\x91\xBA" => "\x73", "\xF0\x9D\x91\xBB" => "\x74", "\xF0\x9D\x91\xBC" => "\x75", "\xF0\x9D\x91\xBD" => "\x76", "\xF0\x9D\x91\xBE" => "\x77", "\xF0\x9D\x91\xBF" => "\x78", "\xF0\x9D\x92\x80" => "\x79", "\xF0\x9D\x92\x81" => "\x7A", "\xF0\x9D\x92\x9C" => "\x61", "\xF0\x9D\x92\x9E" => "\x63", "\xF0\x9D\x92\x9F" => "\x64", "\xF0\x9D\x92\xA2" => "\x67", "\xF0\x9D\x92\xA5" => "\x6A", "\xF0\x9D\x92\xA6" => "\x6B", "\xF0\x9D\x92\xA9" => "\x6E", "\xF0\x9D\x92\xAA" => "\x6F", "\xF0\x9D\x92\xAB" => "\x70", "\xF0\x9D\x92\xAC" => "\x71", "\xF0\x9D\x92\xAE" => "\x73", "\xF0\x9D\x92\xAF" => "\x74", "\xF0\x9D\x92\xB0" => "\x75", "\xF0\x9D\x92\xB1" => "\x76", "\xF0\x9D\x92\xB2" => "\x77", "\xF0\x9D\x92\xB3" => "\x78", "\xF0\x9D\x92\xB4" => "\x79", "\xF0\x9D\x92\xB5" => "\x7A", "\xF0\x9D\x93\x90" => "\x61", "\xF0\x9D\x93\x91" => "\x62", "\xF0\x9D\x93\x92" => "\x63", "\xF0\x9D\x93\x93" => "\x64", "\xF0\x9D\x93\x94" => "\x65", "\xF0\x9D\x93\x95" => "\x66", "\xF0\x9D\x93\x96" => "\x67", "\xF0\x9D\x93\x97" => "\x68", "\xF0\x9D\x93\x98" => "\x69", "\xF0\x9D\x93\x99" => "\x6A", "\xF0\x9D\x93\x9A" => "\x6B", "\xF0\x9D\x93\x9B" => "\x6C", "\xF0\x9D\x93\x9C" => "\x6D", "\xF0\x9D\x93\x9D" => "\x6E", "\xF0\x9D\x93\x9E" => "\x6F", "\xF0\x9D\x93\x9F" => "\x70", "\xF0\x9D\x93\xA0" => "\x71", "\xF0\x9D\x93\xA1" => "\x72", "\xF0\x9D\x93\xA2" => "\x73", "\xF0\x9D\x93\xA3" => "\x74", "\xF0\x9D\x93\xA4" => "\x75", "\xF0\x9D\x93\xA5" => "\x76", "\xF0\x9D\x93\xA6" => "\x77", "\xF0\x9D\x93\xA7" => "\x78", "\xF0\x9D\x93\xA8" => "\x79", "\xF0\x9D\x93\xA9" => "\x7A", "\xF0\x9D\x94\x84" => "\x61", "\xF0\x9D\x94\x85" => "\x62", "\xF0\x9D\x94\x87" => "\x64", "\xF0\x9D\x94\x88" => "\x65", "\xF0\x9D\x94\x89" => "\x66", "\xF0\x9D\x94\x8A" => "\x67", "\xF0\x9D\x94\x8D" => "\x6A", "\xF0\x9D\x94\x8E" => "\x6B", "\xF0\x9D\x94\x8F" => "\x6C", "\xF0\x9D\x94\x90" => "\x6D", "\xF0\x9D\x94\x91" => "\x6E", "\xF0\x9D\x94\x92" => "\x6F", "\xF0\x9D\x94\x93" => "\x70", "\xF0\x9D\x94\x94" => "\x71", "\xF0\x9D\x94\x96" => "\x73", "\xF0\x9D\x94\x97" => "\x74", "\xF0\x9D\x94\x98" => "\x75", "\xF0\x9D\x94\x99" => "\x76", "\xF0\x9D\x94\x9A" => "\x77", "\xF0\x9D\x94\x9B" => "\x78", "\xF0\x9D\x94\x9C" => "\x79", "\xF0\x9D\x94\xB8" => "\x61", "\xF0\x9D\x94\xB9" => "\x62", "\xF0\x9D\x94\xBB" => "\x64", "\xF0\x9D\x94\xBC" => "\x65", "\xF0\x9D\x94\xBD" => "\x66", "\xF0\x9D\x94\xBE" => "\x67", "\xF0\x9D\x95\x80" => "\x69", "\xF0\x9D\x95\x81" => "\x6A", "\xF0\x9D\x95\x82" => "\x6B", "\xF0\x9D\x95\x83" => "\x6C", "\xF0\x9D\x95\x84" => "\x6D", "\xF0\x9D\x95\x86" => "\x6F", "\xF0\x9D\x95\x8A" => "\x73", "\xF0\x9D\x95\x8B" => "\x74", "\xF0\x9D\x95\x8C" => "\x75", "\xF0\x9D\x95\x8D" => "\x76", "\xF0\x9D\x95\x8E" => "\x77", "\xF0\x9D\x95\x8F" => "\x78", "\xF0\x9D\x95\x90" => "\x79", "\xF0\x9D\x95\xAC" => "\x61", "\xF0\x9D\x95\xAD" => "\x62", "\xF0\x9D\x95\xAE" => "\x63", "\xF0\x9D\x95\xAF" => "\x64", "\xF0\x9D\x95\xB0" => "\x65", "\xF0\x9D\x95\xB1" => "\x66", "\xF0\x9D\x95\xB2" => "\x67", "\xF0\x9D\x95\xB3" => "\x68", "\xF0\x9D\x95\xB4" => "\x69", "\xF0\x9D\x95\xB5" => "\x6A", "\xF0\x9D\x95\xB6" => "\x6B", "\xF0\x9D\x95\xB7" => "\x6C", "\xF0\x9D\x95\xB8" => "\x6D", "\xF0\x9D\x95\xB9" => "\x6E", "\xF0\x9D\x95\xBA" => "\x6F", "\xF0\x9D\x95\xBB" => "\x70", "\xF0\x9D\x95\xBC" => "\x71", "\xF0\x9D\x95\xBD" => "\x72", "\xF0\x9D\x95\xBE" => "\x73", "\xF0\x9D\x95\xBF" => "\x74", "\xF0\x9D\x96\x80" => "\x75", "\xF0\x9D\x96\x81" => "\x76", "\xF0\x9D\x96\x82" => "\x77", "\xF0\x9D\x96\x83" => "\x78", "\xF0\x9D\x96\x84" => "\x79", "\xF0\x9D\x96\x85" => "\x7A", "\xF0\x9D\x96\xA0" => "\x61", "\xF0\x9D\x96\xA1" => "\x62", "\xF0\x9D\x96\xA2" => "\x63", "\xF0\x9D\x96\xA3" => "\x64", "\xF0\x9D\x96\xA4" => "\x65", "\xF0\x9D\x96\xA5" => "\x66", "\xF0\x9D\x96\xA6" => "\x67", "\xF0\x9D\x96\xA7" => "\x68", "\xF0\x9D\x96\xA8" => "\x69", "\xF0\x9D\x96\xA9" => "\x6A", "\xF0\x9D\x96\xAA" => "\x6B", "\xF0\x9D\x96\xAB" => "\x6C", "\xF0\x9D\x96\xAC" => "\x6D", "\xF0\x9D\x96\xAD" => "\x6E", "\xF0\x9D\x96\xAE" => "\x6F", "\xF0\x9D\x96\xAF" => "\x70", "\xF0\x9D\x96\xB0" => "\x71", "\xF0\x9D\x96\xB1" => "\x72", "\xF0\x9D\x96\xB2" => "\x73", "\xF0\x9D\x96\xB3" => "\x74", "\xF0\x9D\x96\xB4" => "\x75", "\xF0\x9D\x96\xB5" => "\x76", "\xF0\x9D\x96\xB6" => "\x77", "\xF0\x9D\x96\xB7" => "\x78", "\xF0\x9D\x96\xB8" => "\x79", "\xF0\x9D\x96\xB9" => "\x7A", "\xF0\x9D\x97\x94" => "\x61", "\xF0\x9D\x97\x95" => "\x62", "\xF0\x9D\x97\x96" => "\x63", "\xF0\x9D\x97\x97" => "\x64", "\xF0\x9D\x97\x98" => "\x65", "\xF0\x9D\x97\x99" => "\x66", "\xF0\x9D\x97\x9A" => "\x67", "\xF0\x9D\x97\x9B" => "\x68", "\xF0\x9D\x97\x9C" => "\x69", "\xF0\x9D\x97\x9D" => "\x6A", "\xF0\x9D\x97\x9E" => "\x6B", "\xF0\x9D\x97\x9F" => "\x6C", "\xF0\x9D\x97\xA0" => "\x6D", "\xF0\x9D\x97\xA1" => "\x6E", "\xF0\x9D\x97\xA2" => "\x6F", "\xF0\x9D\x97\xA3" => "\x70", "\xF0\x9D\x97\xA4" => "\x71", "\xF0\x9D\x97\xA5" => "\x72", "\xF0\x9D\x97\xA6" => "\x73", "\xF0\x9D\x97\xA7" => "\x74", "\xF0\x9D\x97\xA8" => "\x75", "\xF0\x9D\x97\xA9" => "\x76", "\xF0\x9D\x97\xAA" => "\x77", "\xF0\x9D\x97\xAB" => "\x78", "\xF0\x9D\x97\xAC" => "\x79", "\xF0\x9D\x97\xAD" => "\x7A", "\xF0\x9D\x98\x88" => "\x61", "\xF0\x9D\x98\x89" => "\x62", "\xF0\x9D\x98\x8A" => "\x63", "\xF0\x9D\x98\x8B" => "\x64", "\xF0\x9D\x98\x8C" => "\x65", "\xF0\x9D\x98\x8D" => "\x66", "\xF0\x9D\x98\x8E" => "\x67", "\xF0\x9D\x98\x8F" => "\x68", "\xF0\x9D\x98\x90" => "\x69", "\xF0\x9D\x98\x91" => "\x6A", "\xF0\x9D\x98\x92" => "\x6B", "\xF0\x9D\x98\x93" => "\x6C", "\xF0\x9D\x98\x94" => "\x6D", "\xF0\x9D\x98\x95" => "\x6E", "\xF0\x9D\x98\x96" => "\x6F", "\xF0\x9D\x98\x97" => "\x70", "\xF0\x9D\x98\x98" => "\x71", "\xF0\x9D\x98\x99" => "\x72", "\xF0\x9D\x98\x9A" => "\x73", "\xF0\x9D\x98\x9B" => "\x74", "\xF0\x9D\x98\x9C" => "\x75", "\xF0\x9D\x98\x9D" => "\x76", "\xF0\x9D\x98\x9E" => "\x77", "\xF0\x9D\x98\x9F" => "\x78", "\xF0\x9D\x98\xA0" => "\x79", "\xF0\x9D\x98\xA1" => "\x7A", "\xF0\x9D\x98\xBC" => "\x61", "\xF0\x9D\x98\xBD" => "\x62", "\xF0\x9D\x98\xBE" => "\x63", "\xF0\x9D\x98\xBF" => "\x64", "\xF0\x9D\x99\x80" => "\x65", "\xF0\x9D\x99\x81" => "\x66", "\xF0\x9D\x99\x82" => "\x67", "\xF0\x9D\x99\x83" => "\x68", "\xF0\x9D\x99\x84" => "\x69", "\xF0\x9D\x99\x85" => "\x6A", "\xF0\x9D\x99\x86" => "\x6B", "\xF0\x9D\x99\x87" => "\x6C", "\xF0\x9D\x99\x88" => "\x6D", "\xF0\x9D\x99\x89" => "\x6E", "\xF0\x9D\x99\x8A" => "\x6F", "\xF0\x9D\x99\x8B" => "\x70", "\xF0\x9D\x99\x8C" => "\x71", "\xF0\x9D\x99\x8D" => "\x72", "\xF0\x9D\x99\x8E" => "\x73", "\xF0\x9D\x99\x8F" => "\x74", "\xF0\x9D\x99\x90" => "\x75", "\xF0\x9D\x99\x91" => "\x76", "\xF0\x9D\x99\x92" => "\x77", "\xF0\x9D\x99\x93" => "\x78", "\xF0\x9D\x99\x94" => "\x79", "\xF0\x9D\x99\x95" => "\x7A", "\xF0\x9D\x99\xB0" => "\x61", "\xF0\x9D\x99\xB1" => "\x62", "\xF0\x9D\x99\xB2" => "\x63", "\xF0\x9D\x99\xB3" => "\x64", "\xF0\x9D\x99\xB4" => "\x65", "\xF0\x9D\x99\xB5" => "\x66", "\xF0\x9D\x99\xB6" => "\x67", "\xF0\x9D\x99\xB7" => "\x68", "\xF0\x9D\x99\xB8" => "\x69", "\xF0\x9D\x99\xB9" => "\x6A", "\xF0\x9D\x99\xBA" => "\x6B", "\xF0\x9D\x99\xBB" => "\x6C", "\xF0\x9D\x99\xBC" => "\x6D", "\xF0\x9D\x99\xBD" => "\x6E", "\xF0\x9D\x99\xBE" => "\x6F", "\xF0\x9D\x99\xBF" => "\x70", "\xF0\x9D\x9A\x80" => "\x71", "\xF0\x9D\x9A\x81" => "\x72", "\xF0\x9D\x9A\x82" => "\x73", "\xF0\x9D\x9A\x83" => "\x74", "\xF0\x9D\x9A\x84" => "\x75", "\xF0\x9D\x9A\x85" => "\x76", "\xF0\x9D\x9A\x86" => "\x77", "\xF0\x9D\x9A\x87" => "\x78", "\xF0\x9D\x9A\x88" => "\x79", "\xF0\x9D\x9A\x89" => "\x7A", "\xF0\x9D\x9A\xA8" => "\xCE\xB1", "\xF0\x9D\x9A\xA9" => "\xCE\xB2", "\xF0\x9D\x9A\xAA" => "\xCE\xB3", "\xF0\x9D\x9A\xAB" => "\xCE\xB4", "\xF0\x9D\x9A\xAC" => "\xCE\xB5", "\xF0\x9D\x9A\xAD" => "\xCE\xB6", "\xF0\x9D\x9A\xAE" => "\xCE\xB7", "\xF0\x9D\x9A\xAF" => "\xCE\xB8", "\xF0\x9D\x9A\xB0" => "\xCE\xB9", "\xF0\x9D\x9A\xB1" => "\xCE\xBA", "\xF0\x9D\x9A\xB2" => "\xCE\xBB", "\xF0\x9D\x9A\xB3" => "\xCE\xBC", "\xF0\x9D\x9A\xB4" => "\xCE\xBD", "\xF0\x9D\x9A\xB5" => "\xCE\xBE", "\xF0\x9D\x9A\xB6" => "\xCE\xBF", "\xF0\x9D\x9A\xB7" => "\xCF\x80", "\xF0\x9D\x9A\xB8" => "\xCF\x81", "\xF0\x9D\x9A\xB9" => "\xCE\xB8", "\xF0\x9D\x9A\xBA" => "\xCF\x83", "\xF0\x9D\x9A\xBB" => "\xCF\x84", "\xF0\x9D\x9A\xBC" => "\xCF\x85", "\xF0\x9D\x9A\xBD" => "\xCF\x86", "\xF0\x9D\x9A\xBE" => "\xCF\x87", "\xF0\x9D\x9A\xBF" => "\xCF\x88", "\xF0\x9D\x9B\x80" => "\xCF\x89", "\xF0\x9D\x9B\x93" => "\xCF\x83", "\xF0\x9D\x9B\xA2" => "\xCE\xB1", "\xF0\x9D\x9B\xA3" => "\xCE\xB2", "\xF0\x9D\x9B\xA4" => "\xCE\xB3", "\xF0\x9D\x9B\xA5" => "\xCE\xB4", "\xF0\x9D\x9B\xA6" => "\xCE\xB5", "\xF0\x9D\x9B\xA7" => "\xCE\xB6", "\xF0\x9D\x9B\xA8" => "\xCE\xB7", "\xF0\x9D\x9B\xA9" => "\xCE\xB8", "\xF0\x9D\x9B\xAA" => "\xCE\xB9", "\xF0\x9D\x9B\xAB" => "\xCE\xBA", "\xF0\x9D\x9B\xAC" => "\xCE\xBB", "\xF0\x9D\x9B\xAD" => "\xCE\xBC", "\xF0\x9D\x9B\xAE" => "\xCE\xBD", "\xF0\x9D\x9B\xAF" => "\xCE\xBE", "\xF0\x9D\x9B\xB0" => "\xCE\xBF", "\xF0\x9D\x9B\xB1" => "\xCF\x80", "\xF0\x9D\x9B\xB2" => "\xCF\x81", "\xF0\x9D\x9B\xB3" => "\xCE\xB8", "\xF0\x9D\x9B\xB4" => "\xCF\x83", "\xF0\x9D\x9B\xB5" => "\xCF\x84", "\xF0\x9D\x9B\xB6" => "\xCF\x85", "\xF0\x9D\x9B\xB7" => "\xCF\x86", "\xF0\x9D\x9B\xB8" => "\xCF\x87", "\xF0\x9D\x9B\xB9" => "\xCF\x88", "\xF0\x9D\x9B\xBA" => "\xCF\x89", "\xF0\x9D\x9C\x8D" => "\xCF\x83", "\xF0\x9D\x9C\x9C" => "\xCE\xB1", "\xF0\x9D\x9C\x9D" => "\xCE\xB2", "\xF0\x9D\x9C\x9E" => "\xCE\xB3", "\xF0\x9D\x9C\x9F" => "\xCE\xB4", "\xF0\x9D\x9C\xA0" => "\xCE\xB5", "\xF0\x9D\x9C\xA1" => "\xCE\xB6", "\xF0\x9D\x9C\xA2" => "\xCE\xB7", "\xF0\x9D\x9C\xA3" => "\xCE\xB8", "\xF0\x9D\x9C\xA4" => "\xCE\xB9", "\xF0\x9D\x9C\xA5" => "\xCE\xBA", "\xF0\x9D\x9C\xA6" => "\xCE\xBB", "\xF0\x9D\x9C\xA7" => "\xCE\xBC", "\xF0\x9D\x9C\xA8" => "\xCE\xBD", "\xF0\x9D\x9C\xA9" => "\xCE\xBE", "\xF0\x9D\x9C\xAA" => "\xCE\xBF", "\xF0\x9D\x9C\xAB" => "\xCF\x80", "\xF0\x9D\x9C\xAC" => "\xCF\x81", "\xF0\x9D\x9C\xAD" => "\xCE\xB8", "\xF0\x9D\x9C\xAE" => "\xCF\x83", "\xF0\x9D\x9C\xAF" => "\xCF\x84", "\xF0\x9D\x9C\xB0" => "\xCF\x85", "\xF0\x9D\x9C\xB1" => "\xCF\x86", "\xF0\x9D\x9C\xB2" => "\xCF\x87", "\xF0\x9D\x9C\xB3" => "\xCF\x88", "\xF0\x9D\x9C\xB4" => "\xCF\x89", "\xF0\x9D\x9D\x87" => "\xCF\x83", "\xF0\x9D\x9D\x96" => "\xCE\xB1", "\xF0\x9D\x9D\x97" => "\xCE\xB2", "\xF0\x9D\x9D\x98" => "\xCE\xB3", "\xF0\x9D\x9D\x99" => "\xCE\xB4", "\xF0\x9D\x9D\x9A" => "\xCE\xB5", "\xF0\x9D\x9D\x9B" => "\xCE\xB6", "\xF0\x9D\x9D\x9C" => "\xCE\xB7", "\xF0\x9D\x9D\x9D" => "\xCE\xB8", "\xF0\x9D\x9D\x9E" => "\xCE\xB9", "\xF0\x9D\x9D\x9F" => "\xCE\xBA", "\xF0\x9D\x9D\xA0" => "\xCE\xBB", "\xF0\x9D\x9D\xA1" => "\xCE\xBC", "\xF0\x9D\x9D\xA2" => "\xCE\xBD", "\xF0\x9D\x9D\xA3" => "\xCE\xBE", "\xF0\x9D\x9D\xA4" => "\xCE\xBF", "\xF0\x9D\x9D\xA5" => "\xCF\x80", "\xF0\x9D\x9D\xA6" => "\xCF\x81", "\xF0\x9D\x9D\xA7" => "\xCE\xB8", "\xF0\x9D\x9D\xA8" => "\xCF\x83", "\xF0\x9D\x9D\xA9" => "\xCF\x84", "\xF0\x9D\x9D\xAA" => "\xCF\x85", "\xF0\x9D\x9D\xAB" => "\xCF\x86", "\xF0\x9D\x9D\xAC" => "\xCF\x87", "\xF0\x9D\x9D\xAD" => "\xCF\x88", "\xF0\x9D\x9D\xAE" => "\xCF\x89", "\xF0\x9D\x9E\x81" => "\xCF\x83", "\xF0\x9D\x9E\x90" => "\xCE\xB1", "\xF0\x9D\x9E\x91" => "\xCE\xB2", "\xF0\x9D\x9E\x92" => "\xCE\xB3", "\xF0\x9D\x9E\x93" => "\xCE\xB4", "\xF0\x9D\x9E\x94" => "\xCE\xB5", "\xF0\x9D\x9E\x95" => "\xCE\xB6", "\xF0\x9D\x9E\x96" => "\xCE\xB7", "\xF0\x9D\x9E\x97" => "\xCE\xB8", "\xF0\x9D\x9E\x98" => "\xCE\xB9", "\xF0\x9D\x9E\x99" => "\xCE\xBA", "\xF0\x9D\x9E\x9A" => "\xCE\xBB", "\xF0\x9D\x9E\x9B" => "\xCE\xBC", "\xF0\x9D\x9E\x9C" => "\xCE\xBD", "\xF0\x9D\x9E\x9D" => "\xCE\xBE", "\xF0\x9D\x9E\x9E" => "\xCE\xBF", "\xF0\x9D\x9E\x9F" => "\xCF\x80", "\xF0\x9D\x9E\xA0" => "\xCF\x81", "\xF0\x9D\x9E\xA1" => "\xCE\xB8", "\xF0\x9D\x9E\xA2" => "\xCF\x83", "\xF0\x9D\x9E\xA3" => "\xCF\x84", "\xF0\x9D\x9E\xA4" => "\xCF\x85", "\xF0\x9D\x9E\xA5" => "\xCF\x86", "\xF0\x9D\x9E\xA6" => "\xCF\x87", "\xF0\x9D\x9E\xA7" => "\xCF\x88", "\xF0\x9D\x9E\xA8" => "\xCF\x89", "\xF0\x9D\x9E\xBB" => "\xCF\x83", "\xF0\x9D\x9F\x8A" => "\xCF\x9D",);
        // do the case fold
        $text = $this->utf8_case_fold($text, $option);
        // convert to NFKC
        $params = JFusionFactory::getParams($this->getJname());
        $utf8_advanced = $params->get('utf8_advanced');
        if ($utf8_advanced == 'enabled') {
            if (!class_exists('utf_normalizer_phpbb')) {
                require_once JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'utf' . DS . 'utf_normalizer.php';
            }
            utf_normalizer_phpbb::nfkc($text);
        }
        // FC_NFKC_Closure, http://www.unicode.org/Public/5.0.0/ucd/DerivedNormalizationProps.txt
        $text = strtr($text, $fc_nfkc_closure);
        return $text;
    }
    /**
     * Case folds a unicode string as per Unicode 5.0, section 3.13
     *
     * @param    string    $text    text to be case folded
     * @param    string    $option    determines how we will fold the cases
     * @return    string            case folded text
     */
    function utf8_case_fold($text, $option = 'full') {
        static $uniarray = array();
        $option = 'not full';
        // common is always set
        if (!isset($uniarray['c'])) {
            $uniarray['c'] = include (JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'utf' . DS . 'case_fold_c.php');
        }
        // only set full if we need to
        if ($option === 'full' && !isset($uniarray['f'])) {
            $uniarray['f'] = include (JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'utf' . DS . 'case_fold_f.php');
        }
        // only set simple if we need to
        if ($option !== 'full' && !isset($uniarray['s'])) {
            $uniarray['s'] = include (JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'utf' . DS . 'case_fold_s.php');
        }
        // common is always replaced
        $text = strtr($text, $uniarray['c']);
        if ($option === 'full') {
            // full replaces a character with multiple characters
            $text = strtr($text, $uniarray['f']);
        } else {
            // simple replaces a character with another character
            $text = strtr($text, $uniarray['s']);
        }
        return $text;
    }

    /**
     * needed to parse the bbcode for phpbb
     *
     * @param string &$text
     * @return stdClass
     */
    function bbcode_parser(&$text) {
        $this->warn_msg = array();
        $this->bbcode_uid = false;
        $this->bbcodes = array();
        $this->bbcode_bitfield = '';

        $params = JFusionFactory::getParams($this->getJname());
        $source_path = $params->get('source_path');
        $this->db = JFusionFactory::getDatabase($this->getJname());
        if (!defined('IN_PHPBB')) {
            define('IN_PHPBB', true);
        }
        $table_prefix = $params->get('database_prefix');
        include_once ($source_path . '/includes/constants.php');
        //get a bbcode_uid
        if (empty($this->bbcode_uid)) {
            $query = 'SELECT config_value FROM #__config WHERE config_name = \'rand_seed\'';
            $this->db->setQuery($query);
            $rand_seed = $this->db->loadResult();
            $val = $rand_seed . microtime();
            $val = md5($val);
            $uniqueid = substr($val, 4, 16);
            $this->bbcode_uid = substr(base_convert($uniqueid, 16, 36), 0, BBCODE_UID_LEN);
        }
        //remove unwanted stuff
        $match = array('#(script|about|applet|activex|chrome):#i');
        $replace = array("\\1&#058;");
        $text = preg_replace($match, $replace, trim($text));
        //parse smilies phpbb way
        $this->parse_smilies($text);
        //add phpbb bbcode_uid to bbcode and generate bbcode_bitfield
        if (strpos($text, '[') !== false) {
            $this->bbcode_bitfield = base64_decode('');
            $this->parse_bbcode($text);
        }
        $bbcode = new stdClass;
        $bbcode->text = $text;
        $bbcode->warn_msg = $this->warn_msg;
        $bbcode->bbcode_bitfield = $this->bbcode_bitfield;
        $bbcode->bbcode_uid = $this->bbcode_uid;
        $bbcode->bbcodes = $this->bbcodes;
        return $bbcode;
    }

    /**
     * @param string &$text
     */
    function parse_smilies(&$text) {
        static $smilie_match, $smilie_replace;
        if (!is_array($smilie_match)) {
            $smilie_match = $smilie_replace = array();
            $query = 'SELECT * FROM #__smilies ORDER BY LENGTH(code) DESC';
            $this->db->setQuery($query);
            $results = $this->db->loadObjectList();
            foreach ($results as $r) {
                $smilie_match[] = '(?<=^|[\n .])' . preg_quote($r->code, '#') . '(?![^<>]*>)';
                $smilie_replace[] = '<!-- s' . $r->code . ' --><img src="{SMILIES_PATH}/' . $r->smiley_url . '" alt="' . $r->code . '" title="' . $r->emotion . '" /><!-- s' . $r->code . ' -->';
            }
        }
        $text = trim(preg_replace(explode(chr(0), '#' . implode('#' . chr(0) . '#', $smilie_match) . '#'), $smilie_replace, $text));
    }

    /**
     * @param string &$text
     */
    function parse_bbcode(&$text) {
        if (empty($this->bbcodes)) {
            $this->bbcodes = array(
	            'code'          => array('bbcode_id' => 8, 'regexp' => array('#\[code(?:=([a-z]+))?\](.+\[/code\])#ise' => "\$this->bbcode_code('\$1', '\$2')")),
	            'quote'         => array('bbcode_id' => 0, 'regexp' => array('#\[quote(?:=&quot;(.*?)&quot;)?\](.+)\[/quote\]#ise' => "\$this->bbcode_quote('\$0')")),
	            'attachment'    => array('bbcode_id' => 12, 'regexp' => array('#\[attachment=([0-9]+)\](.*?)\[/attachment\]#ise' => "\$this->bbcode_attachment('\$1', '\$2')")),
	            'b'             => array('bbcode_id' => 1, 'regexp' => array('#\[b\](.*?)\[/b\]#ise' => "\$this->bbcode_strong('\$1')")),
	            'i'             => array('bbcode_id' => 2, 'regexp' => array('#\[i\](.*?)\[/i\]#ise' => "\$this->bbcode_italic('\$1')")),
	            'url'           => array('bbcode_id' => 3, 'regexp' => array('#\[url(=(.*))?\](.*)\[/url\]#iUe' => "\$this->validate_url('\$2', '\$3')")),
	            'img'           => array('bbcode_id' => 4, 'regexp' => array('#\[img\](.*)\[/img\]#iUe' => "\$this->bbcode_img('\$1')")),
	            'size'          => array('bbcode_id' => 5, 'regexp' => array('#\[size=([\-\+]?\d+)\](.*?)\[/size\]#ise' => "\$this->bbcode_size('\$1', '\$2')")),
	            'color'         => array('bbcode_id' => 6, 'regexp' => array('!\[color=(#[0-9a-f]{6}|[a-z\-]+)\](.*?)\[/color\]!ise' => "\$this->bbcode_color('\$1', '\$2')")),
	            'u'             => array('bbcode_id' => 7, 'regexp' => array('#\[u\](.*?)\[/u\]#ise' => "\$this->bbcode_underline('\$1')")),
	            'list'          => array('bbcode_id' => 9, 'regexp' => array('#\[list(?:=(?:[a-z0-9]|disc|circle|square))?].*\[/list]#ise' => "\$this->bbcode_parse_list('\$0')")),
	            'email'         => array('bbcode_id' => 10, 'regexp' => array('#\[email=?(.*?)?\](.*?)\[/email\]#ise' => "\$this->validate_email('\$1', '\$2')")),
	            'flash'         => array('bbcode_id' => 11, 'regexp' => array('#\[flash=([0-9]+),([0-9]+)\](.*?)\[/flash\]#ie' => "\$this->bbcode_flash('\$1', '\$2', '\$3')")));

            $query = 'SELECT * FROM #__bbcodes';
            $this->db->setQuery($query);
            $results = $this->db->loadObjectList();
            foreach ($results as $r) {
                $this->bbcodes[$r->bbcode_tag] = array('bbcode_id' => (int)$r->bbcode_id, 'regexp' => array($r->first_pass_match => str_replace('$uid', $this->bbcode_uid, $r->first_pass_replace)));
            }
        }
        foreach ($this->bbcodes as $name => $data) {
            foreach ($data['regexp'] as $search => $replace) {
                if (preg_match($search, $text)) {
                    $text = preg_replace($search, $replace, $text);
                    $this->set_bbcode_bitfield($data['bbcode_id']);
                }
            }
        }
        $this->bbcode_bitfield = base64_encode($this->bbcode_bitfield);
    }

    /**
     * @param $id
     */
    function set_bbcode_bitfield($id) {
        $byte = $id >> 3;
        $bit = 7 - ($id & 7);
        if (strlen($this->bbcode_bitfield) >= $byte + 1) {
            $this->bbcode_bitfield[$byte] = $this->bbcode_bitfield[$byte] | chr(1 << $bit);
        } else {
            $this->bbcode_bitfield.= str_repeat("\0", $byte - strlen($this->bbcode_bitfield));
            $this->bbcode_bitfield.= chr(1 << $bit);
        }
    }
    /**
     * The following functions were taken from phpBB 3.0.4 to parse bbcode the way phpBB wants it.  It has been adapted to fit JFusion's needs
     * Original copyright (c) 2005 phpBB Group
     * Original license http://opensource.org/licenses/gpl-license.php GNU Public License
     *
     */
    /**
     * Making some pre-checks for bbcodes as well as increasing the number of parsed items
     *
     * @param $bbcode
     * @param string &$in
     *
     * @return bool
     */
    function check_bbcode($bbcode, &$in) {
        // when using the /e modifier, preg_replace slashes double-quotes but does not
        // seem to slash anything else
        $in = str_replace("\r\n", "\n", str_replace('\"', '"', $in));
        // Trimming here to make sure no empty bbcodes are parsed accidentally
        if (trim($in) == '') {
            return false;
        }
        return true;
    }
    /**
     * Transform some characters in valid bbcodes
     *
     * @param string $text
     *
     * @return string
     */
    function bbcode_specialchars($text) {
        $str_from = array('<', '>', '[', ']', '.', ':');
        $str_to = array('&lt;', '&gt;', '&#91;', '&#93;', '&#46;', '&#58;');
        return str_replace($str_from, $str_to, $text);
    }
    /**
     * Parse size tag
     *
     * @param string $stx
     * @param string $in
     *
     * @return string
     */
    function bbcode_size($stx, $in) {
        global $user, $config;
        if (!$this->check_bbcode('size', $in)) {
            return $in;
        }
        // Do not allow size=0
        if ($stx <= 0) {
            return '[size=' . $stx . ']' . $in . '[/size]';
        }
        return '[size=' . $stx . ':' . $this->bbcode_uid . ']' . $in . '[/size:' . $this->bbcode_uid . ']';
    }
    /**
     * Parse color tag
     *
     * @param string $stx
     * @param string $in
     *
     * @return string
     */
    function bbcode_color($stx, $in) {
        if (!$this->check_bbcode('color', $in)) {
            return $in;
        }
        return '[color=' . $stx . ':' . $this->bbcode_uid . ']' . $in . '[/color:' . $this->bbcode_uid . ']';
    }
    /**
     * Parse u tag
     *
     * @param string $in
     *
     * @return string
     */
    function bbcode_underline($in) {
        if (!$this->check_bbcode('u', $in)) {
            return $in;
        }
        return '[u:' . $this->bbcode_uid . ']' . $in . '[/u:' . $this->bbcode_uid . ']';
    }
    /**
     * Parse b tag
     *
     * @param string $in
     *
     * @return string
     */
    function bbcode_strong($in) {
        if (!$this->check_bbcode('b', $in)) {
            return $in;
        }
        return '[b:' . $this->bbcode_uid . ']' . $in . '[/b:' . $this->bbcode_uid . ']';
    }
    /**
     * Parse i tag
     *
     * @param string $in
     *
     * @return string
     */
    function bbcode_italic($in) {
        if (!$this->check_bbcode('i', $in)) {
            return $in;
        }
        return '[i:' . $this->bbcode_uid . ']' . $in . '[/i:' . $this->bbcode_uid . ']';
    }
    /**
     * Parse img tag
     *
     * @param string $in
     *
     * @return string
     */
    function bbcode_img($in) {
        global $user, $config;
        if (!$this->check_bbcode('img', $in)) {
            return $in;
        }
        $in = trim($in);
        $in = str_replace(' ', '%20', $in);
        // Checking urls
        if (!preg_match('#^' . $this->get_preg_expression('url') . '$#i', $in) && !preg_match('#^' . $this->get_preg_expression('www_url') . '$#i', $in)) {
            return '[img]' . $in . '[/img]';
        }
        // Try to cope with a common user error... not specifying a protocol but only a subdomain
        if (!preg_match('#^[a-z0-9]+://#i', $in)) {
            $in = 'http://' . $in;
        }
        return '[img:' . $this->bbcode_uid . ']' . $this->bbcode_specialchars($in) . '[/img:' . $this->bbcode_uid . ']';
    }
    /**
     * Parse flash tag
     *
     * @param int $width
     * @param int $height
     * @param string $in
     *
     * @return string
     */
    function bbcode_flash($width, $height, $in) {
        global $user, $config;
        if (!$this->check_bbcode('flash', $in)) {
            return $in;
        }
        $in = trim($in);
        // Do not allow 0-sizes generally being entered
        if ($width <= 0 || $height <= 0) {
            return '[flash=' . $width . ',' . $height . ']' . $in . '[/flash]';
        }
        return '[flash=' . $width . ',' . $height . ':' . $this->bbcode_uid . ']' . $this->bbcode_specialchars($in) . '[/flash:' . $this->bbcode_uid . ']';
    }
    /**
     * Parse inline attachments [ia]
     *
     * @param string $stx
     * @param string $in
     *
     * @return string
     */
    function bbcode_attachment($stx, $in) {
        if (!$this->check_bbcode('attachment', $in)) {
            return $in;
        }
        return '[attachment=' . $stx . ':' . $this->bbcode_uid . ']<!-- ia' . $stx . ' -->' . trim($in) . '<!-- ia' . $stx . ' -->[/attachment:' . $this->bbcode_uid . ']';
    }
    /**
     * Parse code text from code tag
     * @access private
     *
     * @param string $stx
     * @param string &$code
     *
     * @return string
     */
    function bbcode_parse_code($stx, &$code) {
        switch (strtolower($stx)) {
            case 'php':
                $remove_tags = false;
                $str_from = array('&lt;', '&gt;', '&#91;', '&#93;', '&#46;', '&#58;', '&#058;');
                $str_to = array('<', '>', '[', ']', '.', ':', ':');
                $code = str_replace($str_from, $str_to, $code);
                if (!preg_match('/\<\?.*?\?\>/is', $code)) {
                    $remove_tags = true;
                    $code = '<?php '.$code.' ?>';
                }
                $conf = array('highlight.bg', 'highlight.comment', 'highlight.default', 'highlight.html', 'highlight.keyword', 'highlight.string');
                foreach ($conf as $ini_var) {
                    @ini_set($ini_var, str_replace('highlight.', 'syntax', $ini_var));
                }
                // Because highlight_string is specialcharing the text (but we already did this before), we have to reverse this in order to get correct results
                $code = htmlspecialchars_decode($code);
                $code = highlight_string($code, true);
                $str_from = array('<span style="color: ', '<font color="syntax', '</font>', '<code>', '</code>', '[', ']', '.', ':');
                $str_to = array('<span class="', '<span class="syntax', '</span>', '', '', '&#91;', '&#93;', '&#46;', '&#58;');
                if ($remove_tags) {
                    $str_from[] = '<span class="syntaxdefault">&lt;?php </span>';
                    $str_to[] = '';
                    $str_from[] = '<span class="syntaxdefault">&lt;?php&nbsp;';
                    $str_to[] = '<span class="syntaxdefault">';
                }
                $code = str_replace($str_from, $str_to, $code);
                $code = preg_replace('#^(<span class="[a-z_]+">)\n?(.*?)\n?(</span>)$#is', '$1$2$3', $code);
                if ($remove_tags) {
                    $code = preg_replace('#(<span class="[a-z]+">)?\?&gt;(</span>)#', '$1&nbsp;$2', $code);
                }
                $code = preg_replace('#^<span class="[a-z]+"><span class="([a-z]+)">(.*)</span></span>#s', '<span class="$1">$2</span>', $code);
                $code = preg_replace('#(?:\s++|&nbsp;)*+</span>$#u', '</span>', $code);
                // remove newline at the end
                if (!empty($code) && substr($code, -1) == "\n") {
                    $code = substr($code, 0, -1);
                }
                return '[code='.$stx.':' . $this->bbcode_uid . ']' . $code . '[/code:' . $this->bbcode_uid . ']';
                break;
            default:
                return '[code:' . $this->bbcode_uid . ']' . $this->bbcode_specialchars($code) . '[/code:' . $this->bbcode_uid . ']';
                break;
        }
    }
    /**
     * Parse code tag
     * Expects the argument to start right after the opening [code] tag and to end with [/code]
     *
     * @param string $stx
     * @param string $in
     *
     * @return string
     */
    function bbcode_code($stx, $in) {
        if (!$this->check_bbcode('code', $in)) {
            return $in;
        }
        // We remove the hardcoded elements from the code block here because it is not used in code blocks
        // Having it here saves us one preg_replace per message containing [code] blocks
        // Additionally, magic url parsing should go after parsing bbcodes, but for safety those are stripped out too...
        $htm_match = $this->get_preg_expression('bbcode_htm');
        unset($htm_match[4], $htm_match[5]);
        $htm_replace = array('\1', '\1', '\2', '\1');
        $out = $code_block = '';
        $open = 1;
        while ($in) {
            // Determine position and tag length of next code block
            preg_match('#(.*?)(\[code(?:=([a-z]+))?\])(.+)#is', $in, $buffer);
            $pos = (isset($buffer[1])) ? strlen($buffer[1]) : false;
            $tag_length = (isset($buffer[2])) ? strlen($buffer[2]) : false;
            // Determine position of ending code tag
            $pos2 = stripos($in, '[/code]');
            // Which is the next block, ending code or code block
            if ($pos !== false && $pos < $pos2) {
                // Open new block
                if (!$open) {
                    $out.= substr($in, 0, $pos);
                    $in = substr($in, $pos);
                    $stx = (isset($buffer[3])) ? $buffer[3] : '';
                    $code_block = '';
                } else {
                    // Already opened block, just append to the current block
                    $code_block.= substr($in, 0, $pos) . ((isset($buffer[2])) ? $buffer[2] : '');
                    $in = substr($in, $pos);
                }
                $in = substr($in, $tag_length);
                $open++;
            } else {
                // Close the block
                if ($open == 1) {
                    $code_block.= substr($in, 0, $pos2);
                    $code_block = preg_replace($htm_match, $htm_replace, $code_block);
                    // Parse this code block
                    $out.= $this->bbcode_parse_code($stx, $code_block);
                    $code_block = '';
                    $open--;
                } else if ($open) {
                    // Close one open tag... add to the current code block
                    $code_block.= substr($in, 0, $pos2 + 7);
                    $open--;
                } else {
                    // end code without opening code... will be always outside code block
                    $out.= substr($in, 0, $pos2 + 7);
                }
                $in = substr($in, $pos2 + 7);
            }
        }
        // if now $code_block has contents we need to parse the remaining code while removing the last closing tag to match up.
        if ($code_block) {
            $code_block = substr($code_block, 0, -7);
            $code_block = preg_replace($htm_match, $htm_replace, $code_block);
            $out.= $this->bbcode_parse_code($stx, $code_block);
        }
        return $out;
    }
    /**
     * Parse list bbcode
     * Expects the argument to start with a tag
     *
     * @param string $in
     *
     * @return string
     */
    function bbcode_parse_list($in) {
        if (!$this->check_bbcode('list', $in)) {
            return $in;
        }
        // $tok holds characters to stop at. Since the string starts with a '[' we'll get everything up to the first ']' which should be the opening [list] tag
        $tok = ']';
        $out = '[';
        // First character is [
        $in = substr($in, 1);
        $list_end_tags = $item_end_tags = array();
        do {
            $pos = strlen($in);
            for ($i = 0, $tok_len = strlen($tok);$i < $tok_len;++$i) {
                $tmp_pos = strpos($in, $tok[$i]);
                if ($tmp_pos !== false && $tmp_pos < $pos) {
                    $pos = $tmp_pos;
                }
            }
            $buffer = substr($in, 0, $pos);
            $tok = $in[$pos];
            $in = substr($in, $pos + 1);
            if ($tok == ']') {
                // if $tok is ']' the buffer holds a tag
                if (strtolower($buffer) == '/list' && sizeof($list_end_tags)) {
                    // valid [/list] tag, check nesting so that we don't hit false positives
                    if (sizeof($item_end_tags) && sizeof($item_end_tags) >= sizeof($list_end_tags)) {
                        // current li tag has not been closed
                        $out = preg_replace('/\n?\[$/', '[', $out) . array_pop($item_end_tags) . '][';
                    }
                    $out.= array_pop($list_end_tags) . ']';
                    $tok = '[';
                } else if (preg_match('#^list(=[0-9a-z]+)?$#i', $buffer, $m)) {
                    // sub-list, add a closing tag
                    if (empty($m[1]) || preg_match('/^=(?:disc|square|circle)$/i', $m[1])) {
                        array_push($list_end_tags, '/list:u:' . $this->bbcode_uid);
                    } else {
                        array_push($list_end_tags, '/list:o:' . $this->bbcode_uid);
                    }
                    $out.= 'list' . substr($buffer, 4) . ':' . $this->bbcode_uid . ']';
                    $tok = '[';
                } else {
                    if (($buffer == '*' || substr($buffer, -2) == '[*') && sizeof($list_end_tags)) {
                        // the buffer holds a bullet tag and we have a [list] tag open
                        if (sizeof($item_end_tags) >= sizeof($list_end_tags)) {
                            if (substr($buffer, -2) == '[*') {
                                $out.= substr($buffer, 0, -2) . '[';
                            }
                            // current li tag has not been closed
                            if (preg_match('/\n\[$/', $out, $m)) {
                                $out = preg_replace('/\n\[$/', '[', $out);
                                $buffer = array_pop($item_end_tags) . "]\n[*:" . $this->bbcode_uid;
                            } else {
                                $buffer = array_pop($item_end_tags) . '][*:' . $this->bbcode_uid;
                            }
                        } else {
                            $buffer = '*:' . $this->bbcode_uid;
                        }
                        $item_end_tags[] = '/*:m:' . $this->bbcode_uid;
                    } else if ($buffer == '/*') {
                        array_pop($item_end_tags);
                        $buffer = '/*:' . $this->bbcode_uid;
                    }
                    $out.= $buffer . $tok;
                    $tok = '[]';
                }
            } else {
                // Not within a tag, just add buffer to the return string
                $out.= $buffer . $tok;
                $tok = ($tok == '[') ? ']' : '[]';
            }
        }
        while ($in);
        // do we have some tags open? close them now
        if (sizeof($item_end_tags)) {
            $out.= '[' . implode('][', $item_end_tags) . ']';
        }
        if (sizeof($list_end_tags)) {
            $out.= '[' . implode('][', $list_end_tags) . ']';
        }
        return $out;
    }
    /**
     * Parse quote bbcode
     * Expects the argument to start with a tag
     *
     * @param string $in
     *
     * @return string
     */
    function bbcode_quote($in) {
        global $config, $user;
        /**
         * If you change this code, make sure the cases described within the following reports are still working:
         * #3572 - [quote="[test]test"]test [ test[/quote] - (correct: parsed)
         * #14667 - [quote]test[/quote] test ] and [ test [quote]test[/quote] (correct: parsed)
         * #14770 - [quote="["]test[/quote] (correct: parsed)
         * [quote="[i]test[/i]"]test[/quote] (correct: parsed)
         * [quote="[quote]test[/quote]"]test[/quote] (correct: parsed - Username displayed as [quote]test[/quote])
         * #20735 - [quote]test[/[/b]quote] test [/quote][/quote] test - (correct: quoted: "test[/[/b]quote] test" / non-quoted: "[/quote] test" - also failed if layout distorted)
         */
        $in = str_replace("\r\n", "\n", str_replace('\"', '"', trim($in)));
        if (!$in) {
            return '';
        }
        // To let the parser not catch tokens within quote_username quotes we encode them before we start this...
        $in = preg_replace('#quote=&quot;(.*?)&quot;\]#ie', "'quote=&quot;' . str_replace(array('[', ']'), array('&#91;', '&#93;'), '\$1') . '&quot;]'", $in);
        $tok = ']';
        $out = '[';
        $in = substr($in, 1);
        $close_tags = $error_ary = array();
        $buffer = '';
        do {
            $pos = strlen($in);
            for ($i = 0, $tok_len = strlen($tok);$i < $tok_len;++$i) {
                $tmp_pos = strpos($in, $tok[$i]);
                if ($tmp_pos !== false && $tmp_pos < $pos) {
                    $pos = $tmp_pos;
                }
            }
            $buffer.= substr($in, 0, $pos);
            $tok = $in[$pos];
            $in = substr($in, $pos + 1);
            if ($tok == ']') {
                if (strtolower($buffer) == '/quote' && sizeof($close_tags) && substr($out, -1, 1) == '[') {
                    // we have found a closing tag
                    $out.= array_pop($close_tags) . ']';
                    $tok = '[';
                    $buffer = '';
                    /* Add space at the end of the closing tag if not happened before to allow following urls/smilies to be parsed correctly
                    * Do not try to think for the user. :/ Do not parse urls/smilies if there is no space - is the same as with other bbcodes too.
                    * Also, we won't have any spaces within $in anyway, only adding up spaces -> #10982
                    if (!$in || $in[0] !== ' ')
                    {
                    $out .= ' ';
                    }*/
                } else if (preg_match('#^quote(?:=&quot;(.*?)&quot;)?$#is', $buffer, $m) && substr($out, -1, 1) == '[') {
                    // the buffer holds a valid opening tag
                    if ($config['max_quote_depth'] && sizeof($close_tags) >= $config['max_quote_depth']) {
                        // there are too many nested quotes
                        $error_ary['quote_depth'] = sprintf($user->lang['QUOTE_DEPTH_EXCEEDED'], $config['max_quote_depth']);
                        $out.= $buffer . $tok;
                        $tok = '[]';
                        $buffer = '';
                        continue;
                    }
                    array_push($close_tags, '/quote:' . $this->bbcode_uid);
                    if (isset($m[1]) && $m[1]) {
                        $username = str_replace(array('&#91;', '&#93;'), array('[', ']'), $m[1]);
                        $username = preg_replace('#\[(?!b|i|u|color|url|email|/b|/i|/u|/color|/url|/email)#iU', '&#91;$1', $username);
                        $end_tags = array();
                        $error = false;
                        preg_match_all('#\[((?:/)?(?:[a-z]+))#i', $username, $tags);
                        foreach ($tags[1] as $tag) {
                            if ($tag[0] != '/') {
                                $end_tags[] = '/' . $tag;
                            } else {
                                $end_tag = array_pop($end_tags);
                                $error = ($end_tag != $tag) ? true : false;
                            }
                        }
                        if ($error) {
                            $username = $m[1];
                        }
                        $out.= 'quote=&quot;' . $username . '&quot;:' . $this->bbcode_uid . ']';
                    } else {
                        $out.= 'quote:' . $this->bbcode_uid . ']';
                    }
                    $tok = '[';
                    $buffer = '';
                } else if (preg_match('#^quote=&quot;(.*?)#is', $buffer, $m)) {
                    // the buffer holds an invalid opening tag
                    $buffer.= ']';
                } else {
                    $out.= $buffer . $tok;
                    $tok = '[]';
                    $buffer = '';
                }
            } else {
                /**
                 *                Old quote code working fine, but having errors listed in bug #3572
                 *
                 *                $out .= $buffer . $tok;
                 *                $tok = ($tok == '[') ? ']' : '[]';
                 *                $buffer = '';
                 */
                $out.= $buffer . $tok;
                if ($tok == '[') {
                    // Search the text for the next tok... if an ending quote comes first, then change tok to []
                    $pos1 = stripos($in, '[/quote');
                    // If the token ] comes first, we change it to ]
                    $pos2 = strpos($in, ']');
                    // If the token [ comes first, we change it to [
                    $pos3 = strpos($in, '[');
                    if ($pos1 !== false && ($pos2 === false || $pos1 < $pos2) && ($pos3 === false || $pos1 < $pos3)) {
                        $tok = '[]';
                    } else if ($pos3 !== false && ($pos2 === false || $pos3 < $pos2)) {
                        $tok = '[';
                    } else {
                        $tok = ']';
                    }
                } else {
                    $tok = '[]';
                }
                $buffer = '';
            }
        }
        while ($in);
        if (sizeof($close_tags)) {
            $out.= '[' . implode('][', $close_tags) . ']';
        }
        foreach ($error_ary as $error_msg) {
            $this->warn_msg[] = $error_msg;
        }
        return $out;
    }
    /**
     * Validate email
     *
     * @param string $var1
     * @param string $var2
     *
     * @return string
     */
    function validate_email($var1, $var2) {
        $var1 = str_replace("\r\n", "\n", str_replace('\"', '"', trim($var1)));
        $var2 = str_replace("\r\n", "\n", str_replace('\"', '"', trim($var2)));
        $txt = $var2;
        $email = ($var1) ? $var1 : $var2;
        $validated = true;
        if (!preg_match('/^' . $this->get_preg_expression('email') . '$/i', $email)) {
            $validated = false;
        }
        if (!$validated) {
            return '[email' . (($var1) ? "=$var1" : '') . ']' . $var2 . '[/email]';
        }
        if ($var1) {
            $retval = '[email=' . $this->bbcode_specialchars($email) . ':' . $this->bbcode_uid . ']' . $txt . '[/email:' . $this->bbcode_uid . ']';
        } else {
            $retval = '[email:' . $this->bbcode_uid . ']' . $this->bbcode_specialchars($email) . '[/email:' . $this->bbcode_uid . ']';
        }
        return $retval;
    }
    /**
     * Validate url
     *
     * @param string $var1 optional url parameter for url bbcode: [url(=$var1)]$var2[/url]
     * @param string $var2 url bbcode content: [url(=$var1)]$var2[/url]
     *
     * @return string
     */
    function validate_url($var1, $var2) {
        global $config;
        $var1 = str_replace("\r\n", "\n", str_replace('\"', '"', trim($var1)));
        $var2 = str_replace("\r\n", "\n", str_replace('\"', '"', trim($var2)));
        $url = ($var1) ? $var1 : $var2;
        if ($var1 && !$var2) {
            $var2 = $var1;
        }
        if (!$url) {
            return '[url' . (($var1) ? '=' . $var1 : '') . ']' . $var2 . '[/url]';
        }
        $valid = false;
        $url = str_replace(' ', '%20', $url);
        // Checking urls
        $params = JFusionFactory::getParams($this->getJname());
        $source_url = $params->get('source_url');

        if (preg_match('#^' . $this->get_preg_expression('url') . '$#i', $url) || preg_match('#^' . $this->get_preg_expression('www_url') . '$#i', $url) || preg_match('#^' . preg_quote($source_url, '#') . $this->get_preg_expression('relative_url') . '$#i', $url)) {
            $valid = true;
        }
        if ($valid) {
            // if there is no scheme, then add http schema
            if (!preg_match('#^[a-z][a-z\d+\-.]*:/{2}#i', $url)) {
                $url = 'http://' . $url;
            }
            // Is this a link to somewhere inside this board? If so then remove the session id from the url
            if (strpos($url, $source_url) !== false && strpos($url, 'sid=') !== false) {
                $url = preg_replace('/(&amp;|\?)sid=[0-9a-f]{32}&amp;/', '\1', $url);
                $url = preg_replace('/(&amp;|\?)sid=[0-9a-f]{32}$/', '', $url);
                $url = append_sid($url);
            }
            return ($var1) ? '[url=' . $this->bbcode_specialchars($url) . ':' . $this->bbcode_uid . ']' . $var2 . '[/url:' . $this->bbcode_uid . ']' : '[url:' . $this->bbcode_uid . ']' . $this->bbcode_specialchars($url) . '[/url:' . $this->bbcode_uid . ']';
        }
        return '[url' . (($var1) ? '=' . $var1 : '') . ']' . $var2 . '[/url]';
    }
    /**
     * This function returns a regular expression pattern for commonly used expressions
     * Use with / as delimiter for email mode and # for url modes
     * mode can be: email|bbcode_htm|url|url_inline|www_url|www_url_inline|relative_url|relative_url_inline|ipv4|ipv6
     *
     * @param string $mode
     *
     * @return string
     */
    function get_preg_expression($mode) {
        $return = '';
        switch ($mode) {
            case 'email':
                $return = '(?:[a-z0-9\'\.\-_\+\|]++|&amp;)+@[a-z0-9\-]+\.(?:[a-z0-9\-]+\.)*[a-z]+';
                break;
            case 'bbcode_htm':
                $return = array('#<!\-\- e \-\-><a href="mailto:(.*?)">.*?</a><!\-\- e \-\->#', '#<!\-\- l \-\-><a (?:class="[\w-]+" )?href="(.*?)(?:(&amp;|\?)sid=[0-9a-f]{32})?">.*?</a><!\-\- l \-\->#', '#<!\-\- ([mw]) \-\-><a (?:class="[\w-]+" )?href="(.*?)">.*?</a><!\-\- \1 \-\->#', '#<!\-\- s(.*?) \-\-><img src="\{SMILIES_PATH\}\/.*? \/><!\-\- s\1 \-\->#', '#<!\-\- .*? \-\->#s', '#<.*?>#s',);
                break;
            // Whoa these look impressive!
            // The code to generate the following two regular expressions which match valid IPv4/IPv6 addresses
            // can be found in the develop directory

            case 'ipv4':
                $return = '#^(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])$#';
                break;
            case 'ipv6':
                $return = '#^(?:(?:(?:[\dA-F]{1,4}:){6}(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:::(?:[\dA-F]{1,4}:){5}(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:):(?:[\dA-F]{1,4}:){4}(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:){1,2}:(?:[\dA-F]{1,4}:){3}(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:){1,3}:(?:[\dA-F]{1,4}:){2}(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:){1,4}:(?:[\dA-F]{1,4}:)(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:){1,5}:(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:){1,6}:[\dA-F]{1,4})|(?:(?:[\dA-F]{1,4}:){1,7}:))$#i';
                break;
            case 'url':
            case 'url_inline':
                $inline = ($mode == 'url') ? ')' : '';
                $scheme = ($mode == 'url') ? '[a-z\d+\-.]' : '[a-z\d+]'; // avoid automatic parsing of "word" in "last word.http://..."
                // generated with regex generation file in the develop folder
                $return = "[a-z]$scheme*:/{2}(?:(?:[a-z0-9\-._~!$&'($inline*+,;=:@|]+|%[\dA-F]{2})+|[0-9.]+|\[[a-z0-9.]+:[a-z0-9.]+:[a-z0-9.:]+\])(?::\d*)?(?:/(?:[a-z0-9\-._~!$&'($inline*+,;=:@|]+|%[\dA-F]{2})*)*(?:\?(?:[a-z0-9\-._~!$&'($inline*+,;=:@/?|]+|%[\dA-F]{2})*)?(?:\#(?:[a-z0-9\-._~!$&'($inline*+,;=:@/?|]+|%[\dA-F]{2})*)?";
                break;
            case 'www_url':
            case 'www_url_inline':
                $inline = ($mode == 'www_url') ? ')' : '';
                $return = "www\.(?:[a-z0-9\-._~!$&'($inline*+,;=:@|]+|%[\dA-F]{2})+(?::\d*)?(?:/(?:[a-z0-9\-._~!$&'($inline*+,;=:@|]+|%[\dA-F]{2})*)*(?:\?(?:[a-z0-9\-._~!$&'($inline*+,;=:@/?|]+|%[\dA-F]{2})*)?(?:\#(?:[a-z0-9\-._~!$&'($inline*+,;=:@/?|]+|%[\dA-F]{2})*)?";
                break;
            case 'relative_url':
            case 'relative_url_inline':
                $inline = ($mode == 'relative_url') ? ')' : '';
                $return = "(?:[a-z0-9\-._~!$&'($inline*+,;=:@|]+|%[\dA-F]{2})*(?:/(?:[a-z0-9\-._~!$&'($inline*+,;=:@|]+|%[\dA-F]{2})*)*(?:\?(?:[a-z0-9\-._~!$&'($inline*+,;=:@/?|]+|%[\dA-F]{2})*)?(?:\#(?:[a-z0-9\-._~!$&'($inline*+,;=:@/?|]+|%[\dA-F]{2})*)?";
                break;
        }
        return $return;
    }
}