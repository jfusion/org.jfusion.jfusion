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

	/**
	 * @param string $key
	 * @param $value
	 */
	public function add($key, $value) {
		if (isset($this->data[$key]) && is_array($this->data[$key])) {
			$this->data[$key][] = $value;
		} else {
			$this->data[$key] = array();
			$this->data[$key][] = $value;
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
	 * @param string|null $key
	 * @param      $value
	 */
	public function set($key = null, $value) {
		if ($key === null) {
			$this->data = $value;
		} else {
			$this->data[$key] = $value;
		}
	}
}