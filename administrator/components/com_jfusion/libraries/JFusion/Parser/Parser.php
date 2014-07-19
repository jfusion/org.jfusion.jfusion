<?php namespace JFusion\Parser;
/**
 * Model for all jfusion parse related function
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
use Joomla\Event\Event;

/**
 * Class for JFusionParse
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class Parser
{
    /**
     * Parses text from bbcode to html, html to bbcode, or html to plaintext
     * $options include:
     * strip_all_html - if $to==bbcode, strips all unsupported html from text (default is false)
     * bbcode_patterns - if $to==bbcode, adds additional html to bbcode rules; array [0] startsearch, [1] startreplace, [2] endsearch, [3] endreplace
     * parse_smileys - if $to==html, disables the bbcode smiley parsing; useful for plugins that do their own smiley parsing (default is true)
	 * custom_smileys - if $to==html, adds custom smileys to parser; array in the format of array[$smiley] => $path.  For example $options['custom_smileys'][':-)'] = 'http://mydomain.com/smileys/smile.png';
     * html_patterns - if $to==html, adds additional bbcode to html rules;
     *     Must be an array of elements with the custom bbcode as the key and the value in the format described at http://nbbc.sourceforge.net/readme.php?page=usage_add
     *     For example $options['html_patterns']['mono'] = array('simple_start' => '<tt>', 'simple_end' => '</tt>', 'class' => 'inline', 'allow_in' => array('listitem', 'block', 'columns', 'inline', 'link'));
     * character_limit - if $to==html OR $to==plaintext, limits the number of visible characters to the user
     * plaintext_line_breaks - if $to=='plaintext', should line breaks when converting to plaintext be replaced with <br /> (br) (default), converted to spaces (space), or left as \n (n)
     * plain_tags - if $to=='plaintext', array of custom bbcode tags (without brackets) that should be stripped
     *
     * @param string $text    the actual text
     * @param string $to      what to convert the text to; bbcode, html, or plaintext
     * @param mixed  $options array with parser options
     *
     * @return string with converted text
     */
    public function parseCode($text, $to, $options = array())
    {
        $options = !is_array($options) ? array() : $options;

        if ($to == 'plaintext') {
            if (!isset($options['plaintext_line_breaks'])) {
                $options['plaintext_line_breaks'] = 'br';
            }

	        $bbcode = new Nbbc();
            $bbcode->SetPlainMode(true);
            if (isset($options['plain_tags']) && is_array($options['plain_tags'])) {
                foreach ($options['plain_tags'] as $tag) {
                    $bbcode->AddRule($tag, array('class' => 'inline', 'allow_in' => array('block', 'inline', 'link', 'list', 'listitem', 'columns', 'image')));
                }
            }

            if (!empty($options['character_limit'])) {
                $bbcode->SetLimit($options['character_limit']);
            }

            //first thing is to protect our code blocks
            $text = preg_replace('#\[code\](.*?)\[\/code\]#si', '[code]<!-- CODE BLOCK -->$1<!-- END CODE BLOCK -->[/code]', $text, '-1', $code_count);

            $text = $bbcode->Parse($text);
            $text = $bbcode->UnHTMLEncode(strip_tags($text));

            //re-encode our code blocks
            if (!empty($code_count)) {
				$text = preg_replace_callback('#<!-- CODE BLOCK -->(.*?)<!-- END CODE BLOCK -->#si', array($this, '__htmlspecialchars'), $text);
            }

            //catch newly unencoded tags
            $text = strip_tags($text);

            if ($options['plaintext_line_breaks'] == 'br') {
                $text = $bbcode->nl2br($text);
            } elseif ($options['plaintext_line_breaks'] == 'space') {
                $text = str_replace("\n", '  ', $text);
            }
        } elseif ($to == 'html') {
            //Encode html entities added by the plugin prepareText function
            $text = htmlentities($text);

            $bbcode = new Nbbc();

            //do not parse & into &amp;
            $bbcode->SetAllowAmpersand(true);

            if (isset($options['html_patterns']) && is_array($options['html_patterns'])) {
                foreach ($options['html_patterns'] as $name => $rule) {
                    $bbcode->AddRule($name, $rule);
                }
            }

            if (!empty($options['parse_smileys'])) {
                $bbcode->SetSmileyURL($options['parse_smileys']);
            } else {
                $bbcode->SetEnableSmileys(false);
            }

            if (!empty($options['custom_smileys'])) {
                foreach ($options['custom_smileys'] AS $smiley => $path) {
                    $bbcode->AddSmiley($smiley, $path);
                }
            }

            if (!empty($options['character_limit'])) {
                $bbcode->SetLimit($options['character_limit']);
            }

            //disabled this as it caused issues with images and youtube links
            //$bbcode->SetDetectURLs(true);
            //$bbcode->SetURLPattern('<a href="{$url/h}" target="_blank">{$text/h}</a>');

            //first thing is to protect our code blocks
            $text = preg_replace('#\[code\](.*?)\[\/code\]#si', '[code]<!-- CODE BLOCK -->$1<!-- END CODE BLOCK -->[/code]', $text, '-1', $code_count);

            $text = $bbcode->Parse($text);

            //Decode for output
            $text = html_entity_decode($text);

            //re-encode our code blocks
            if (!empty($code_count)) {
                $text = preg_replace_callback('#<!-- CODE BLOCK -->(.*?)<!-- END CODE BLOCK -->#si', array($this, '__htmlspecialchars'), $text);
            }
        } elseif ($to == 'bbcode') {
            if (!isset($options['bbcode_patterns'])) {
                $options['bbcode_patterns'] = '';
            }
            if (!isset($options['strip_all_html'])) {
                $options['strip_all_html'] = true;
            }

            //remove all line breaks to prevent massive empty space in bbcode
	        $text = preg_replace('/(?:(?:\r\n|\r|\n)\s*){2}/s', "\n\n", $text);

            static $search, $replace;
            if (!is_array($search)) {
                $search = $replace = array();
                $search[] = '#<(blockquote|cite)[^>]*>(.*?)<\/\\1>#si';
                $replace[] = '[quote]$2[/quote]';
                $search[] = '#<ol[^>]*>(.*?)<\/ol>#si';
                $replace[] = '[list=1]$1[/list]';
                $search[] = '#<ul[^>]*>(.*?)<\/ul>#si';
                $replace[] = '[list]$1[/list]';
                $search[] = '#<li[^>]*>(.*?)<\/li>#si';
                $replace[] = '[*]$1';
                $search[] = '#<img [^>]*src=[\|"](?!\w{0,10}://)(.*?)[\'|"][^>]*>#si';
                $replace[] = array($this, '__parseTag_img');
                $search[] = '#<img [^>]*src=[\'|"](.*?)[\'|"][^>]*>#sim';
                $replace[] = '[img]$1[/img]';
                $search[] = '#<a [^>]*href=[\'|"]mailto:(.*?)[\'|"][^>]*>(.*?)<\/a>#si';
                $replace[] = '[email=$1]$2[/email]';
                $search[] = '#<a [^>]*href=[\'|"](?!\w{0,10}://|\#)(.*?)[\'|"][^>]*>(.*?)</a>#si';
                $replace[] = array($this, '__url');
                $search[] = '#<a [^>]*href=[\'|"](.*?)[\'|"][^>]*>(.*?)<\/a>#si';
                $replace[] = '[url=$1]$2[/url]';
                $search[] = '#<(b|i|u)>(.*?)<\/\\1>#si';
                $replace[] = '[$1]$2[/$1]';
                $search[] = '#<font [^>]*color=[\'|"](.*?)[\'|"][^>]*>(.*?)<\/font>#si';
                $replace[] = '[color=$1]$2[/color]';
                $search[] = '#<p>(.*?)<\/p>#si';
                $replace[] = array($this, '__parseTag_p');
            }
            $searchNS = $replaceNS = array();
            //convert anything between code or pre tags to html entities to prevent conversion
            $searchNS[] = '#<(code|pre)[^>]*>(.*?)<\/\\1>#si';
            $replaceNS[] = array($this, '__code');
            $morePatterns = $options['bbcode_patterns'];
            if (is_array($morePatterns) && isset($morePatterns[0]) && isset($morePatterns[1])) {
                $searchNS = array_merge($searchNS, $morePatterns[0]);
                $replaceNS = array_merge($replaceNS, $morePatterns[1]);
            }
            $searchNS = array_merge($searchNS, $search);
            $replaceNS = array_merge($replaceNS, $replace);
            if (is_array($morePatterns) && isset($morePatterns[2]) && isset($morePatterns[3])) {
                $searchNS = array_merge($searchNS, $morePatterns[2]);
                $replaceNS = array_merge($replaceNS, $morePatterns[3]);
            }
            $text = str_ireplace(array('<br />', '<br>', '<br/>'), "\n", $text);

			foreach ($searchNS as $k => $v) {
	            //check if we need to use callback
	            if(is_array($replaceNS[$k])) {
	                $text = preg_replace_callback($searchNS[$k], $replaceNS[$k], $text);
	            } else {
	                $text = preg_replace($searchNS[$k], $replaceNS[$k], $text);
	            }
	        }

            //decode html entities that we converted for code and pre tags
            $text = preg_replace_callback('#\[code\](.*?)\[\/code\]#si', array($this, '__code_decode'), $text);

	        $text = preg_replace('/(?:(?:\r\n|\r|\n)\s*){2}/s', "\n\n", $text);

            //Change to ensure that the discussion bot posts the article to the forums when there
            //is an issue with preg_replace( '/\p{Z}/u', ' ', $text ) returning an empty string
            //or a series of whitespace.
            //Change to code David Coutts 03/08/2009
            $text_utf8space_to_space = preg_replace('/\p{Z}/u', ' ', $text);
            //Check to see if the returned function is not empty or purely spaces/
            if (strlen(rtrim($text_utf8space_to_space)) > 0) {
                //function returned properly set the output text to be the right trimmed output of the string
                $text = rtrim($text_utf8space_to_space);
            }

            if ($options['strip_all_html']) {
                $text = strip_tags($text);
            }
        }
        return $text;
    }

    /**
     * Used by the JFusionParse::parseCode function to parse various tags when parsing to bbcode.
     * For example, some Joomla editors like to use an empty paragraph tag for line breaks which gets
     * parsed into a lot of unnecessary line breaks
     *
     * @param mixed $matches mixed values from preg functions
     * @param string $tag
     *
     * @return string to replace search subject with
     */
    public function parseTag($matches, $tag = 'p')
    {
        $return = false;
        if ($tag == 'p') {
            $text = trim($matches);
            //remove the slash added to double quotes and slashes added by the e modifier
            $text = str_replace('\"', '"', $text);
            if(empty($text) || ord($text) == 194) {
                //p tags used simply as a line break
                $return = "\n";
            } else {
                $return = $text . "\n\n";
            }
        } elseif ($tag == 'img') {
	        $event = new Event('onPlatformRoute');
	        $event->addArgument('url', $matches);

	        Factory::getDispatcher()->triggerEvent($event);

	        $url = $event->getArgument('url', null);
            $return = $url;
        }
        return $return;
    }

    /**
     * @param $matches
     *
     * @return string
     */
    public function __htmlspecialchars($matches)
    {
        return htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param $matches
     *
     * @return string
     */
    public function __code($matches)
    {
        return '[code]' . htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8') . '[/code]';
    }

    /**
     * @param $matches
     *
     * @return string
     */
    public function __code_decode($matches)
    {
        return '[code]' . htmlspecialchars_decode($matches[1], ENT_QUOTES) . '[/code]';
    }

    /**
     * @param $matches
     *
     * @return string
     */
    public function __parseTag_img($matches)
    {
        return '[img]' . $this->parseTag($matches[1], 'img') . '[/img]';
    }

    /**
     * @param $matches
     *
     * @return string
     */
    public function __parseTag_p($matches)
    {
        return $this->parseTag($matches[1], 'p');
    }

    /**
     * @param $matches
     *
     * @return string
     */
    public function __url($matches)
    {
	    $event = new Event('onPlatformRoute');
	    $event->addArgument('url', $matches[1]);

	    Factory::getDispatcher()->triggerEvent($event);

	    $url = $event->getArgument('url', null);

    	return '[url=' . $url . ']' . $matches[2] . '[/url]';
    }
}