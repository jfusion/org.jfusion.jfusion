<?php

/**
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

/**
 * Main debugging class which is used for detailed outputs
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class debug {
	/**
	 *    Will get a globally defined JS-Function
	 *    -  change the name if it messes up with your own JavaScript functions
	 *    -  ..or set it to the empty-String to disable value-toggling
	 */
	static $toggleFunctionName = 'tns';
	static $colorScheme = array();
	static $colorSchemeInited = array();
	static $toggleScriptInited = false;

	static $callback = null;
	/**
	 *    initializes the colorScheme
	 *    would be a Constructors task if the debug would be instantated, since in debug all methods are static,
	 *    call this method before the first time you need color scheme
	 *
	 *    The meaning of the keys:
	 *        - vc = ValueColor, the background of shown Values
	 *        - akc = ArrayKeyColor, the background of array-Keys
	 *        - okc = ObjectKeyColor, the background of shown "object keys" i.e. the names of public accessible object/class-Variables
	 *        - tc = ValueColor, the background of the (optional) Title
	 *        - gc = ValueColor, the color of the structuring-grid, i.e. the background of the table
	 */
	private static function initColorScheme() {
		static::$colorScheme[] = array('vc' => "#ecf8fd", 'akc' => "#dbfede", 'okc' => "#dbfede", 'tc' => '#d6f2ff', 'gc' => '#fbfed6');
		static::$colorScheme[] = array('vc' => "#f2bb94", 'akc' => "#cc9e7c", 'okc' => "#a68065", 'tc' => '#CCCCCC', 'gc' => '#AAAAFF');
		static::$colorScheme[] = array('vc' => "#faea37", 'akc' => "#d4c62f", 'okc' => "#ada226", 'tc' => '#CCCCCC', 'gc' => '#AAAAFF');
		static::$colorScheme[] = array('vc' => "#4bf8d0", 'akc' => "#3fd1af", 'okc' => "#33ab8f", 'tc' => '#CCCCCC', 'gc' => '#AAAAFF');
		static::$colorScheme[] = array('vc' => "#7a7a7a", 'akc' => "#a1a1a1", 'okc' => "#c7c7c7", 'tc' => '#CCCCCC', 'gc' => '#AAAAFF');
		static::$colorScheme[] = array('vc' => "#0099cc", 'akc' => "#009999", 'okc' => "#00ff00", 'tc' => '#CCCCCC', 'gc' => '#AAAAFF');
		static::$colorScheme[] = array('vc' => "#cfc", 'akc' => "#cf6", 'okc' => "#cf0", 'tc' => '#CCCCCC', 'gc' => '#AAAAFF');
		static::$colorScheme[] = array('vc' => "#ffc", 'akc' => "#ff6", 'okc' => "#ff0", 'tc' => '#CCCCCC', 'gc' => '#AAAAFF');
		static::$colorScheme[] = array('vc' => "#f96", 'akc' => "#c66", 'okc' => "#966", 'tc' => '#CCCCCC', 'gc' => '#AAAAFF');
	}
	/**
	 * Creates and returns the JavaScript-Snippet used to toggle values by klicking on the keys
	 * returns the code only once, i.e. the first time, print it to the standard output if you get the snippet
	 *
	 * @return string
	 */
	private static function getToggleScript() {
		$script = '';
		if (static::$toggleScriptInited == false && static::$toggleFunctionName != '') {
			$toggleFunctionName = static::$toggleFunctionName;
			$script = '<script type="text/javascript">';
			$script .= <<<JS
            function {$toggleFunctionName}(event) {
                var evtSource;
                evtSource = window.event ? window.event.srcElement : event.target;
                while (evtSource.nextSibling === null) { evtSource = evtSource.parentNode;  }
                var tNode = evtSource.nextSibling;
                while (tNode.nodeType != 1) { tNode = tNode.nextSibling; }
                tNode.style.display = (tNode.style.display != 'none') ? 'none' : 'block';
            }
JS;
			$script .= '</script>';

			static::$toggleScriptInited = true;
		}
		return $script;
	}
	/**
	 *    Creates the style information for a color schema if needed
	 *    i.e. this schema is used the first time, print it to the standard output if you need it
	 *
	 *    @param int $schema the index of the desired color schema
	 *    @return string style-code if needed
	 */
	private static function setStylesForScheme($schema) {
		$style = '';
		if (count(static::$colorScheme) == 0) static::initColorScheme();
		if (!isset(static::$colorSchemeInited[$schema])) {

			$vc = static::$colorScheme[$schema]['vc'];
			$akc = static::$colorScheme[$schema]['akc'];
			$okc = static::$colorScheme[$schema]['okc'];
			$tc = static::$colorScheme[$schema]['tc'];
			$gc = static::$colorScheme[$schema]['gc'];


			$style = '<style  type="text/css">';
			$style .= <<<CSS
            div.debug_{$schema} .value {
                min-width: 100px;
                background-color: {$vc};
            }
            div.debug_{$schema} .a_key {
                min-width: 100px;
                background-color: {$akc};
            }
            div.debug_{$schema} .o_key {
                min-width: 100px;
                background-color: {$okc};
            }
            div.debug_{$schema} .title {
                background-color: {$tc};
            }
            div.debug_{$schema} .grid {
                font-family: arial, serif;
                background-color: {$gc};
                vertical-align:top;
            }
CSS;
			$style.= '</style>';
			static::$colorSchemeInited[$schema] = true;
			//print($style);

		}
		return $style;
	}

	/** *************************
	 * Craetes and returns a HTML-Code that shows nicely
	 * the Structure and Value(s) of any PHP-Variable, the given Value can be from a simple Integer
	 * to a complex object-structure. This function works recursively.
	 *
	 * @param mixed $arr   the PHP-Variable to look in
	 * @param mixed $start a title for the created structure-table
	 * @param bool|string $style
	 *
	 * @return string a HTML-Code Snippet (e.g. to be Viewed in a Browser)
	 ** ************************
	 */
	public static function get($arr, $start = true, $style = null) {
		$schema = 0;
		$str = '';
		$name = '';
		if (is_numeric($start)) { // All Arguments "move" 1 to the left
			$start = true;
		}
		if (is_string($start)) { // Indicates that we are on "root"-Level
			$name = $start;
			$start = true;
		}
		if (is_array($arr) || is_object($arr)) {
			if ($start == true) { // the "root" run
				$neededScriptCode = static::getToggleScript();
				$neededStyleCode = static::setStylesForScheme($schema);
				$str = $neededScriptCode . '<div class="debug_'.$schema.'">'."\n" . $neededStyleCode;
			}
			$emptyWhat = 'empty-array';
			$keyClass = 'a_key';
			if (is_object($arr)) {
				$keyClass = 'o_key';
				$emptyWhat = 'empty-object';
			}
			$empty = true;
			if (static::isOneDimensional($arr) && !$start) {
				foreach ($arr as $key => $value) {
					$empty = false;
					$temp = $style;
					if (static::$callback) {
						list($target, $function, $args) = static::$callback;
						if ($style === null) {
							list($style, $value) = $target->$function($key, $value, $args);
						} else {
							list(, $value) = $target->$function($key, $value, $args);
						}
					}
					$str.= '<span class="'.$keyClass.'" style="'.$style.'"> ' . static::decorateValue($key) . '</span> ';
					$str.= '<span class="value" style="'.$style.'" > ' . static::decorateValue($value) . '</span><br/>';
					$style = $temp;
				}
				if ($empty) {
					$str.= '<span class="'.$keyClass.'">'.$emptyWhat.'</span><br>'."\n";
				}
			} else {
				$onClick = '';
				if (static::$toggleFunctionName != '') $onClick = 'onclick="' . static::$toggleFunctionName . '(event)"';
				$str.= '<table class="grid" width="100%">';
				if ($name != '') {
					$str.= '<thead '.$onClick.'><tr><th colspan="2" class="title">'.$name.'</th></tr></thead>';
				}
				$str.= '<tbody>';
				foreach ($arr as $key => $value) {
					$temp = $style;
					$empty = false;
					if (static::$callback) {
						list($target, $function, $args) = static::$callback;
						if ($style === null) {
							list($style, $value) = $target->$function($key, $value, $args);
						} else {
							list(, $value) = $target->$function($key, $value, $args);
						}

					}
					$str.= '<tr>';
					$str.= '<td class="'.$keyClass.'" '.$onClick.' style="'.$style.'">'.static::decorateValue($key).'</td>';
					$str.= '<td class="value" style="'.$style.'">'.static::get($value, false, $style).'</td>';
					$str.= '</tr>';
					$style = $temp;
				}
				if ($empty) {
					$str.= '<tr><td colspan="2" class="'.$keyClass.'">'.$emptyWhat.'</td></tr>';
				}
				$str.= '</tbody></table>';
			}
			if ($start == true) { // the top-Level run
				$str.= '</div>';
			}
		} else { // the "leave"-run
			$str = static::decorateValue($arr);
			if ($name != '') $str = '<div class="debug_0"><table class="grid" width="100%"><thead onclick="'.static::$toggleFunctionName.'(event)"><tr><th class="title">'.$name.'</th></tr></thead><tbody><tr><td class="a_key" onclick="'.static::$toggleFunctionName.'(event)">'.$str.'</td></tr></tbody></table></div>';
			//flush();

		}
		return $str;
	}

	/**
	 * Craetes and returns a String in html code. that shows nicely in copy paste
	 *
	 * @param mixed $arr   : the PHP-Variable to look in
	 * @param mixed $start : a title for the created structure-table
	 * @param int   $level
	 *
	 * @return string a html string with no tags
	 */
	public static function getText($arr, $start = true, $level=1) {
		$levelText = '';
		for ($i = 0; $i < $level; $i++) {
			$levelText .= "\t";
		}
		$str = '';
		if (is_string($start)) {
			$str.= $start.' - &darr;'."\n";
		}
		if (is_array($arr) || is_object($arr)) {
			$emptyWhat = 'empty-array';
			if (is_object($arr)) {
				$emptyWhat = 'empty-object';
			}
			$empty = true;
			if (static::isOneDimensional($arr)) {
				foreach ($arr as $key => $value) {
					$empty = false;
					if (static::$callback) {
						list($target,$function,$args) = static::$callback;
						list(,$value) = $target->$function($key, $value, $args);
					}
					$str.= $levelText.$key.' &rarr; '.static::decorateValue($value, false)."\n";
				}
				if ($empty) {
					$str.= $levelText.$emptyWhat."\n";
				}
			} else {
				foreach ($arr as $key => $value) {
					$empty = false;
					$emptyWhat = 'empty-array';
					if (is_object($value)) {
						$emptyWhat = 'empty-object';
					}
					if (static::$callback) {
						list($target,$function,$args) = static::$callback;
						list(,$value) = $target->$function($key, $value, $args);
					}
					if ( is_array($value) || is_object($value) ) {
						if (count($value) == 0) {
							$str.= $emptyWhat."\n";
						}
						$str.= $levelText.$key.' - &darr; '."\n".static::getText($value, false, $level+1);
					} else {
						$str.= $levelText.$key.' &rarr; '.static::decorateValue($value, false)."\n";
					}
				}
				if ($empty) {
					$str.= $emptyWhat."\n";
				}
			}
		} else {
			$str = $levelText.$arr."\n";
		}
		return $str;
	}

	/**
	 *    Checks if an array is one-dimensional, i.e. if no one of the values is an array or abject again
	 *    The public version of this function is in arrayfunc, this here is just to use by debug::get
	 *    To avoid that the basic methods debug::get and debug::show have dependencies of other classes
	 *
	 *    @param array|object $arr: the array to check
	 *
	 *    @return boolean if it is one-dimensional
	 */
	private static function isOneDimensional($arr) {
		if (!is_array($arr) && !is_object($arr)) {
			$result = false;
			return $result;
		}
		foreach ($arr as $val) {
			if (is_array($val) || is_object($val)) {
				$result = false;
				return $result;
			}
		}
		$result = true;
		return $result;
	}
	/**
	 * Same as debug::get, but prints the created HTML-Code directly to the Standard Output.
	 * NOTE: This is the one and only debuging Tool!!
	 *
	 * @param mixed $arr the PHP-Variable to look in
	 * @param mixed $title a title for the created structure-table
	 *
	 * @return void
	 */
	public static function show($arr, $title = false) {
		print (static::get($arr, $title));
		//flush();

	}

	/**
	 *    Prepares Values to be used in debug::show / debug::get used to indicate a values type
	 *    - Strings will be
	 *        - in double-quotes if they are empty (to see something)
	 *        - Normal if not empty
	 *        - < and > will be replaced by "&lt;", "&gt;" to avoid tag-Interpretation by a Browser
	 *    - boolean and all numbers will be bold.
	 *    - the null-Value will be bold and italic
	 *
	 * @param mixed $value the Value to HTML-Encode
	 *
	 * @param bool  $html
	 *
	 * @return string the HTML-Encoded Value
	 */
	private static function decorateValue($value, $html = true) {
		if (is_string($value)) {
			if (trim($value) == '') $decValue = '\''.$value.'\'';
			else $decValue = str_replace(array('<', '>'), array('&lt;', '&gt;'), $value);
		} else if (is_bool($value)) {
			if ($value) $decValue = 'true';
			else $decValue = 'false';
			if ($html){
				$decValue = '<strong>'.$decValue.'</strong>';
			}
		} else if (is_null($value)) {
			$decValue = 'null';
			if ($html){
				$decValue = '<strong><i>'.$decValue.'</i></strong>';
			}
		} else {
			$decValue = $value;
			if ($html){
				$decValue = '<strong>'.$decValue.'</strong>';
			}
		}
		return $decValue;
	}
}