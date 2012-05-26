<?php

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
defined('_JEXEC') or die('Restricted access');

/**
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class phpbb_bbcode_parser {
    var $text = '';
    var $bbcode_uid = false;
    var $bbcodes = array();
    var $bbcode_bitfield = '';
    var $jname = '';
    var $db = '';
    var $source_url = '';
    //needed to parse the bbcode for phpbb
    function phpbb_bbcode_parser(&$text, $jname) {
        $this->text = $text;
        $this->jname = $jname;
        $params = & JFusionFactory::getParams($this->jname);
        $source_path = $params->get('source_path');
        $this->source_url = $params->get('source_url');
        $this->db = & JFusionFactory::getDatabase($this->jname);
        if (!defined('IN_PHPBB')) {
            define('IN_PHPBB', true);
        }
        $table_prefix = $params->get('database_prefix');
        include_once ($source_path . '/includes/constants.php');
        //get a bbcode_uid
        if (empty($this->bbcode_uid)) {
            $query = "SELECT config_value FROM #__config WHERE config_name = 'rand_seed'";
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
        $this->text = preg_replace($match, $replace, trim($this->text));
        //parse smilies phpbb's way
        $this->parse_smilies();
        //add phpbb's bbcode_uid to bbcode and generate bbcode_bitfield
        if (strpos($this->text, '[') !== false) {
            $this->bbcode_bitfield = base64_decode('');
            $this->parse_bbcode();
        }
    }
    function parse_smilies() {
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
        $this->text = trim(preg_replace(explode(chr(0), '#' . implode('#' . chr(0) . '#', $smilie_match) . '#'), $smilie_replace, $this->text));
    }
    function parse_bbcode() {
        if (!is_array($this->bbcodes)) {
            $this->bbcodes = array('code' => array('bbcode_id' => 8, 'regexp' => array('#\[code(?:=([a-z]+))?\](.+\[/code\])#ise' => "\$this->bbcode_code('\$1', '\$2')")), 'quote' => array('bbcode_id' => 0, 'regexp' => array('#\[quote(?:=&quot;(.*?)&quot;)?\](.+)\[/quote\]#ise' => "\$this->bbcode_quote('\$0')")), 'attachment' => array('bbcode_id' => 12, 'regexp' => array('#\[attachment=([0-9]+)\](.*?)\[/attachment\]#ise' => "\$this->bbcode_attachment('\$1', '\$2')")), 'b' => array('bbcode_id' => 1, 'regexp' => array('#\[b\](.*?)\[/b\]#ise' => "\$this->bbcode_strong('\$1')")), 'i' => array('bbcode_id' => 2, 'regexp' => array('#\[i\](.*?)\[/i\]#ise' => "\$this->bbcode_italic('\$1')")), 'url' => array('bbcode_id' => 3, 'regexp' => array('#\[url(=(.*))?\](.*)\[/url\]#iUe' => "\$this->validate_url('\$2', '\$3')")), 'img' => array('bbcode_id' => 4, 'regexp' => array('#\[img\](.*)\[/img\]#iUe' => "\$this->bbcode_img('\$1')")), 'size' => array('bbcode_id' => 5, 'regexp' => array('#\[size=([\-\+]?\d+)\](.*?)\[/size\]#ise' => "\$this->bbcode_size('\$1', '\$2')")), 'color' => array('bbcode_id' => 6, 'regexp' => array('!\[color=(#[0-9a-f]{6}|[a-z\-]+)\](.*?)\[/color\]!ise' => "\$this->bbcode_color('\$1', '\$2')")), 'u' => array('bbcode_id' => 7, 'regexp' => array('#\[u\](.*?)\[/u\]#ise' => "\$this->bbcode_underline('\$1')")), 'list' => array('bbcode_id' => 9, 'regexp' => array('#\[list(?:=(?:[a-z0-9]|disc|circle|square))?].*\[/list]#ise' => "\$this->bbcode_parse_list('\$0')")), 'email' => array('bbcode_id' => 10, 'regexp' => array('#\[email=?(.*?)?\](.*?)\[/email\]#ise' => "\$this->validate_email('\$1', '\$2')")), 'flash' => array('bbcode_id' => 11, 'regexp' => array('#\[flash=([0-9]+),([0-9]+)\](.*?)\[/flash\]#ie' => "\$this->bbcode_flash('\$1', '\$2', '\$3')")));
            $query = 'SELECT * FROM #__bbcodes';
            $this->db->setQuery($query);
            $results = $this->db->loadObjectList();
            foreach ($results as $r) {
                $this->bbcodes[$r->bbcode_tag] = array('bbcode_id' => (int)$r->bbcode_id, 'regexp' => array($r->first_pass_match => str_replace('$uid', $this->bbcode_uid, $r->first_pass_replace)));
            }
        }
        foreach ($this->bbcodes as $name => $data) {
            foreach ($data['regexp'] as $search => $replace) {
                if (preg_match($search, $this->text)) {
                    $this->text = preg_replace($search, $replace, $this->text);
                    $this->set_bbcode_bitfield($data['bbcode_id']);
                }
            }
        }
        $this->bbcode_bitfield = base64_encode($this->bbcode_bitfield);
    }
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
     * @return bool
     */
    function check_bbcode($bbcode, &$in) {
        // when using the /e modifier, preg_replace slashes double-quotes but does not
        // seem to slash anything else
        $in = str_replace("\r\n", "\n", str_replace('\"', '"', $in));
        // Trimming here to make sure no empty bbcodes are parsed accidently
        if (trim($in) == '') {
            return false;
        }
        return true;
    }
    /**
     * Transform some characters in valid bbcodes
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
                    $code = "<?php $code ?>";
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
                return "[code=$stx:" . $this->bbcode_uid . ']' . $code . '[/code:' . $this->bbcode_uid . ']';
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
        if (preg_match('#^' . $this->get_preg_expression('url') . '$#i', $url) || preg_match('#^' . $this->get_preg_expression('www_url') . '$#i', $url) || preg_match('#^' . preg_quote($this->source_url, '#') . $this->get_preg_expression('relative_url') . '$#i', $url)) {
            $valid = true;
        }
        if ($valid) {
            // if there is no scheme, then add http schema
            if (!preg_match('#^[a-z][a-z\d+\-.]*:/{2}#i', $url)) {
                $url = 'http://' . $url;
            }
            // Is this a link to somewhere inside this board? If so then remove the session id from the url
            if (strpos($url, $this->source_url) !== false && strpos($url, 'sid=') !== false) {
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
     */
    function get_preg_expression($mode) {
        switch ($mode) {
            case 'email':
                return '(?:[a-z0-9\'\.\-_\+\|]++|&amp;)+@[a-z0-9\-]+\.(?:[a-z0-9\-]+\.)*[a-z]+';
            break;
            case 'bbcode_htm':
                return array('#<!\-\- e \-\-><a href="mailto:(.*?)">.*?</a><!\-\- e \-\->#', '#<!\-\- l \-\-><a (?:class="[\w-]+" )?href="(.*?)(?:(&amp;|\?)sid=[0-9a-f]{32})?">.*?</a><!\-\- l \-\->#', '#<!\-\- ([mw]) \-\-><a (?:class="[\w-]+" )?href="(.*?)">.*?</a><!\-\- \1 \-\->#', '#<!\-\- s(.*?) \-\-><img src="\{SMILIES_PATH\}\/.*? \/><!\-\- s\1 \-\->#', '#<!\-\- .*? \-\->#s', '#<.*?>#s',);
            break;
                // Whoa these look impressive!
                // The code to generate the following two regular expressions which match valid IPv4/IPv6 addresses
                // can be found in the develop directory

            case 'ipv4':
                return '#^(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])$#';
            break;
            case 'ipv6':
                return '#^(?:(?:(?:[\dA-F]{1,4}:){6}(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:::(?:[\dA-F]{1,4}:){5}(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:):(?:[\dA-F]{1,4}:){4}(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:){1,2}:(?:[\dA-F]{1,4}:){3}(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:){1,3}:(?:[\dA-F]{1,4}:){2}(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:){1,4}:(?:[\dA-F]{1,4}:)(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:){1,5}:(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:){1,6}:[\dA-F]{1,4})|(?:(?:[\dA-F]{1,4}:){1,7}:))$#i';
            break;
            case 'url':
            case 'url_inline':
                $inline = ($mode == 'url') ? ')' : '';
                $scheme = ($mode == 'url') ? '[a-z\d+\-.]' : '[a-z\d+]'; // avoid automatic parsing of "word" in "last word.http://..."
                // generated with regex generation file in the develop folder
                return "[a-z]$scheme*:/{2}(?:(?:[a-z0-9\-._~!$&'($inline*+,;=:@|]+|%[\dA-F]{2})+|[0-9.]+|\[[a-z0-9.]+:[a-z0-9.]+:[a-z0-9.:]+\])(?::\d*)?(?:/(?:[a-z0-9\-._~!$&'($inline*+,;=:@|]+|%[\dA-F]{2})*)*(?:\?(?:[a-z0-9\-._~!$&'($inline*+,;=:@/?|]+|%[\dA-F]{2})*)?(?:\#(?:[a-z0-9\-._~!$&'($inline*+,;=:@/?|]+|%[\dA-F]{2})*)?";
            break;
            case 'www_url':
            case 'www_url_inline':
                $inline = ($mode == 'www_url') ? ')' : '';
                return "www\.(?:[a-z0-9\-._~!$&'($inline*+,;=:@|]+|%[\dA-F]{2})+(?::\d*)?(?:/(?:[a-z0-9\-._~!$&'($inline*+,;=:@|]+|%[\dA-F]{2})*)*(?:\?(?:[a-z0-9\-._~!$&'($inline*+,;=:@/?|]+|%[\dA-F]{2})*)?(?:\#(?:[a-z0-9\-._~!$&'($inline*+,;=:@/?|]+|%[\dA-F]{2})*)?";
            break;
            case 'relative_url':
            case 'relative_url_inline':
                $inline = ($mode == 'relative_url') ? ')' : '';
                return "(?:[a-z0-9\-._~!$&'($inline*+,;=:@|]+|%[\dA-F]{2})*(?:/(?:[a-z0-9\-._~!$&'($inline*+,;=:@|]+|%[\dA-F]{2})*)*(?:\?(?:[a-z0-9\-._~!$&'($inline*+,;=:@/?|]+|%[\dA-F]{2})*)?(?:\#(?:[a-z0-9\-._~!$&'($inline*+,;=:@/?|]+|%[\dA-F]{2})*)?";
            break;
        }
        return '';
    }
}
