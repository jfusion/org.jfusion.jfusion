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
class JFusionDebugger {
	private $data = array();
	private $title = '';
	private $callback = null;

	/**
	 * @param string $key
	 * @param $value
	 */
	public function add($key, $value) {
		if ($key === null) {
			if (!is_array($this->data)) {
				$this->data = array();
			}
			$this->data[] = $value;
		} else {
			if (isset($this->data[$key]) && is_array($this->data[$key])) {
				$this->data[$key][] = $value;
			} else {
				$this->data[$key] = array();
				$this->data[$key][] = $value;
			}
		}
	}

	/**
	 * @param string|null $key
	 *
	 * @return array|null
	 */
	public function get($key = null) {
		if ($key === null) {
			return $this->data;
		} else if (isset($this->data[$key])) {
			return $this->data[$key];
		}
		return null;
	}

	/**
	 * @param string $key
	 * @param      $value
	 */
	public function set($key, $value) {
		if ($key === null) {
			$this->data = $value;
		} else {
			$this->data[$key] = $value;
		}
	}

	/**
	 * @param string|null $key
	 *
	 * @return boolean
	 */
	public function isEmpty($key = null) {
		$result = true;
		if ($key !== null) {
			if (isset($this->data[$key])) {
				$result = empty($this->data[$key]);
			} else {
				$result = true;
			}
		} else {
			$result = empty($this->data);
		}
		return $result;
	}

	/**
	 * @param array $debugger
	 */
	public function merge($debugger) {
		$this->data = array_merge_recursive($this->data, $debugger);
	}

	/**
	 * @param string $title
	 *
	 * @return boolean
	 */
	public function setTitle($title) {
		$this->title = $title;
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
	public function getHtml($arr, $start = true, $style = null) {
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
			$emptyWhat = 'empty-array';
			$keyClass = 'a_key';
			if (is_object($arr)) {
				$keyClass = 'o_key';
				$emptyWhat = 'empty-object';
			}
			$empty = true;
			if ($this->isOneDimensional($arr) && !$start) {
				foreach ($arr as $key => $value) {
					$empty = false;
					$temp = $style;
					if ($this->callback) {
						list($target, $function, $args) = $this->callback;
						if ($style === null) {
							list($style, $value) = $target->$function($key, $value, $args);
						} else {
							list(, $value) = $target->$function($key, $value, $args);
						}
					}

					$key = $this->decorateValue($key);
					$value = $this->decorateValue($value);

					$str .=<<<HTML
					<span class="{$keyClass}" style="{$style}">{$key}</span>
					<span class="value" style="{$style}">{$value}</span>
					<br/>
HTML;
					$style = $temp;
				}
				if ($empty) {
					$str .=<<<HTML
					<span class="{$keyClass}">{$emptyWhat}</span>
					<br>
HTML;
				}
			} else {
				$head = '';
				if ($name != '') {
					$head =<<<HTML
						<thead onclick="JFusion.toggleDebugger(event);">
							<tr>
								<th colspan="2" class="title">
									{$name}
								</th>
							</tr>
						</thead>
HTML;
				}
				$body = '';
				foreach ($arr as $key => $value) {
					$temp = $style;
					$empty = false;
					if ($this->callback) {
						list($target, $function, $args) = $this->callback;
						if ($style === null) {
							list($style, $value) = $target->$function($key, $value, $args);
						} else {
							list(, $value) = $target->$function($key, $value, $args);
						}
					}

					$key = $this->decorateValue($key);
					$value = $this->getHtml($value, false, $style);
					$body .=<<<HTML
					<tr>
						<td class="{$keyClass}" onclick="JFusion.toggleDebugger(event);" style="{$style}">{$key}</td>
						<td class="value" style="{$style}">{$value}</td>
					</tr>
HTML;
					$style = $temp;
				}
				if ($empty) {
					$body .=<<<HTML
					<tr>
						<td colspan="2" class="{$keyClass}">
							{$emptyWhat}
						</td>
					</tr>
HTML;
					$body .= '';
				}

				$str .=<<<HTML
				<table class="grid" width="100%">
					{$head}
					<tbody>
						{$body}
					</tbody>
				</table>
HTML;
			}
		} else { // the "leave"-run
			$value = $this->decorateValue($arr);
			if ($name != '') {
				$str =<<<HTML
					<div class="debug">
						<table class="grid" width="100%">
							<thead onclick="JFusion.toggleDebugger(event);">
								<tr>
									<th class="title">{$name}</th>
								</tr>
							</thead>
						<tbody>
							<tr>
								<td class="a_key" onclick="JFusion.toggleDebugger(event);">{$value}</td>
							</tr>
						</tbody>
						</table>
					</div>
HTML;
			} else {
				$str = $value;
			}
		}
		return $str;
	}

	/**
	 * Craetes and returns a String in html code. that shows nicely in copy paste
	 *
	 * @param mixed $arr   : the PHP-Variable to look in
	 * @param string|null $title : a title for the created structure-table
	 * @param int   $level
	 *
	 * @return string a html string with no tags
	 */
	private function getText($arr, $title = null, $level = 1) {
		$lines = array();
		$levelText = '';

		for ($i = 0; $i < $level; $i++) {
			$levelText .= "\t";
		}

		if ($title) {
			$lines[] = $title . ' - &darr;';
		}
		if (is_array($arr) || is_object($arr)) {
			$emptyWhat = 'empty-array';
			if (is_object($arr)) {
				$emptyWhat = 'empty-object';
			}
			$empty = true;
			if ($this->isOneDimensional($arr)) {
				foreach ($arr as $key => $value) {
					$empty = false;
					if ($this->callback) {
						list($target, $function, $args) = $this->callback;
						list(, $value) = $target->$function($key, $value, $args);
					}
					$lines[] = $levelText . $key . ' &rarr; ' . $this->decorateValue($value, false);
				}
				if ($empty) {
					$lines[] = $levelText . $emptyWhat;
				}
			} else {
				foreach ($arr as $key => $value) {
					$empty = false;
					$emptyWhat = 'empty-array';
					if (is_object($value)) {
						$emptyWhat = 'empty-object';
					}
					if ($this->callback) {
						list($target, $function, $args) = $this->callback;
						list(, $value) = $target->$function($key, $value, $args);
					}
					if ( is_array($value) || is_object($value) ) {
						if (count($value) == 0) {
							$lines[] = $emptyWhat;
						}
						$lines[] = $levelText . $key . ' - &darr;';
						$lines[] = $this->getText($value, null, $level+1);
					} else {
						$lines[] = $levelText . $key . ' &rarr; ' . $this->decorateValue($value, false);
					}
				}
				if ($empty) {
					$lines[] = $emptyWhat;
				}
			}
		} else {
			$lines[] = $levelText . $arr;
		}
		$str = implode("\n", $lines);
		return $str;
	}

	/**
	 *    @param array|object $arr: the array to check
	 *
	 *    @return boolean if it is one-dimensional
	 */
	private function isOneDimensional($arr) {
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
	 * render the debugging info as HTML-Code directly to the Standard Output.
	 *
	 * @param string|null $key
	 * @param bool $loadresources
	 *
	 * @return void
	 */
	public function displayHtml($key = null, $loadresources = true) {
		echo $this->getAsHtml($key, $loadresources);
	}


	/**
	 * @param string|null $key
	 * @param bool $loadresources
	 *
	 * @return string
	 */
	public function getAsHtml($key = null, $loadresources = true) {
		if ($loadresources) {
			$document = JFactory::getDocument();
			$document->addStyleSheet(JUri::root(true) . '/components/com_jfusion/css/debugger.css');
			$document->addScript(JUri::root(true) . '/components/com_jfusion/js/debugger.js');
		}

		if ($key === null) {
			$data = $this->data;
		} else {
			$data = $this->data[$key];
		}

		$html =<<<HTML
			<div class="debugger">
				<div class="debug">
					{$this->getHtml($data, $this->title)}
				</div>
			</div>
HTML;
		return $html;
	}

	/**
	 * @param string|null $key
	 *
	 * @return string
	 */
	public function getAsText($key = null) {
		if ($key === null) {
			$data = $this->data;
		} else {
			$data = $this->data[$key];
		}

		return $this->getText($data, $this->title);
	}

	/**
	 * @param mixed $value the Value to HTML-Encode
	 *
	 * @param bool  $html
	 *
	 * @return string the HTML-Encoded Value
	 */
	private function decorateValue($value, $html = true) {
		if (is_string($value)) {
			if (trim($value) == '') {
				$decValue = '\'' . $value . '\'';
			} else {
				$decValue = htmlspecialchars($value);
			}
		} else if (is_bool($value)) {
			if ($value) $decValue = 'true';
			else $decValue = 'false';
			if ($html) {
				$decValue = '<strong>' . $decValue . '</strong>';
			}
		} else if (is_null($value)) {
			$decValue = 'null';
			if ($html){
				$decValue = '<strong><i>' . $decValue . '</i></strong>';
			}
		} else {
			$decValue = $value;
			if ($html) {
				$decValue = '<strong>' . $decValue . '</strong>';
			}
		}
		return $decValue;
	}


	/**
	 * @param $callback
	 */
	public function setCallback($callback) {
		$this->callback = $callback;
	}
}