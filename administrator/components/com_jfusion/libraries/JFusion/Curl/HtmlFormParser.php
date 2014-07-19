<?php namespace JFusion\Curl;

/**
 * HTML Form Parser
 * This will extract all forms and his elements in an
 * big assoc Array.
 * Many modifications and bug repairs by Henk Wevers
 *
 * @category  JFusion
 * @package   Models
 * @author    Peter Valicek <Sonny2@gmx.DE>
 * @copyright 2004 Peter Valicek Peter Valicek <Sonny2@gmx.DE>: GPL-2
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class HtmlFormParser
{

	var $html_data = '';
	var $_return = array();
	var $_counter = '';
	var $button_counter = '';
	var $_unique_id = '';
	/**
	 * html form parser
	 *
	 * @param string $html_data the actual html string
	 *
	 * @return array html elements
	 */
	function JFusionCurlHtmlFormParser($html_data)
	{
		if (is_array($html_data)) {
			$this->html_data = join('', $html_data);
		} else {
			$this->html_data = $html_data;
		}
		$this->_return = array();
		$this->_counter = 0;
		$this->button_counter = 0;
		$this->_unique_id = md5(time());
	}

	/**
	 * Parses the forms
	 *
	 * @return string nothing
	 */
	function parseForms()
	{
		preg_match_all('/<form.*>.+<\/form>/isU', $this->html_data, $forms);
		foreach ($forms[0] as $form) {
			$this->button_counter = 0;

			//form details
			preg_match('/<form.*?name=["\']?([\w\s-]*)["\']?[\s>]/is', $form, $form_name);
			if ($form_name) {
				$this->_return[$this->_counter]['form_data']['name'] = preg_replace('/["\'<>]/', '', $form_name[1]);
			}
			preg_match('/<form.*?action=("([^"]*)"|\'([^\']*)\'|[^>\s]*)([^>]*)?>/is', $form, $action);
			if ($action) {
				$this->_return[$this->_counter]['form_data']['action'] = preg_replace('/["\'<>]/', '', $action[1]);
			}
			preg_match('/<form.*?method=["\']?([\w\s]*)["\']?[\s>]/is', $form, $method);
			if ($method) {
				$this->_return[$this->_counter]['form_data']['method'] = preg_replace('/["\'<>]/', '', $method[1]);
			}
			preg_match('/<form.*?enctype=("([^"]*)"|\'([^\']*)\'|[^>\s]*)([^>]*)?>/is', $form, $enctype);
			if ($enctype) {
				$this->_return[$this->_counter]['form_data']['enctype'] = preg_replace('/["\'<>]/', '', $enctype[1]);
			}
			preg_match('/<form.*?id=["\']?([\w\s-]*)["\']?[\s>]/is', $form, $id);
			if ($id) {
				$this->_return[$this->_counter]['form_data']['id'] = preg_replace('/["\'<>]/', '', $id[1]);
			}

			// form elements: input type = hidden
			if (preg_match_all('/<input[^<>]+type=["\']hidden["\'][^<>]*>/isU', $form, $hiddens)) {
				foreach ($hiddens[0] as $hidden) {
					$this->_return[$this->_counter]['form_elements'][$this->_getName($hidden)] = array(
						'type'  =>  'hidden',
						'value'  =>  $this->_getValue($hidden)
					);
				}
			}

			// form elements: input type = text
			if (preg_match_all('/<input[^<>]+type=["\']text["\'][^<>]*>/isU', $form, $texts)) {
				foreach ($texts[0] as $text) {
					$this->_return[$this->_counter]['form_elements'][$this->_getName($text)] = array(
						'type'  => 'text',
						'value'  =>  $this->_getValue($text),
						'id'  =>  $this->_getId($text),
						'class'  =>  $this->_getClass($text)
					);
				}
			}

            // form elements: input type = email
            if (preg_match_all('/<input[^<>]+type=["\']email["\'][^<>]*>/isU', $form, $texts)) {
                foreach ($texts[0] as $text) {
                    $this->_return[$this->_counter]['form_elements'][$this->_getName($text)] = array(
                        'type'  => 'email',
                        'value'  =>  $this->_getValue($text),
                        'id'  =>  $this->_getId($text),
                        'class'  =>  $this->_getClass($text)
                    );
                }
            }

			// form elements: input type = password
			if (preg_match_all('/<input[^<>]+type=["\']password["\'][^<>]*>/isU', $form, $passwords)) {
				foreach ($passwords[0] as $password) {
					$this->_return[$this->_counter]['form_elements'][$this->_getName($password)] = array(
						'type'  =>  'password',
						'value'  =>  $this->_getValue($password)
					);
				}
			}

			// form elements: textarea
			if (preg_match_all('/<textarea.*>.*<\/textarea>/isU', $form, $textareas)) {
				foreach ($textareas[0] as $textarea) {
					preg_match('/<textarea.*>(.*)<\/textarea>/isU', $textarea, $textarea_value);
					$this->_return[$this->_counter]['form_elements'][$this->_getName($textarea)] = array(
						'type'  =>  'textarea',
						'value'  =>  $textarea_value[1]
					);
				}
			}

			// form elements: input type = checkbox
			if (preg_match_all('/<input[^<>]+type=["\']checkbox["\'][^<>]*>/isU', $form, $checkboxes)) {
				foreach ($checkboxes[0] as $checkbox) {
					if (preg_match('/checked/is', $checkbox)) {
						$this->_return[$this->_counter]['form_elements'][$this->_getName($checkbox)] = array(
							'type'  =>  'checkbox',
							'value'  =>  'on'
						);
					} else {
						$this->_return[$this->_counter]['form_elements'][$this->_getName($checkbox)] = array(
							'type'  =>  'checkbox',
							'value'  =>  ''
						);
					}
				}
			}

			// form elements: input type = radio
			if (preg_match_all('/<input[^<>]+type=["\']radio["\'][^<>]*>/isU', $form, $radios)) {
				foreach ($radios[0] as $radio) {
					if (preg_match('/checked/i', $radio)) {
						$this->_return[$this->_counter]['form_elements'][$this->_getName($radio)] = array(
							'type'  =>  'radio',
							'value'  =>  $this->_getValue($radio)
						);
					}
				}
			}

			// form elements: input type = submit
			if (preg_match_all('/<input[^<>]+type=["\']submit["\'][^<>]*>/isU', $form, $submits)) {
				foreach ($submits[0] as $submit) {
					$this->_return[$this->_counter]['buttons'][$this->button_counter] = array(
						'type'  => 'submit',
						'name'  => $this->_getName($submit),
						'value'  => $this->_getValue($submit)
					);
					$this->button_counter++;
				}
			}

			// form elements: input type = button
			if (preg_match_all('/<input[^<>]+type=["\']button["\'][^<>]*>/isU', $form, $buttons)) {
				foreach ($buttons[0] as $button) {
					$this->_return[$this->_counter]['buttons'][$this->button_counter] = array(
						'type'  => 'button',
						'name'  => $this->_getName($button),
						'value'  => $this->_getValue($button)
					);
					$this->button_counter++;
				}
			}

			// form elements: input type = reset
			if (preg_match_all('/<input[^<>]+type=["\']reset["\'][^<>]*>/isU', $form, $resets)) {
				foreach ($resets[0] as $reset) {
					$this->_return[$this->_counter]['buttons'][$this->button_counter] = array(
						'type'  => 'reset',
						'name'  => $this->_getName($reset),
						'value'  => $this->_getValue($reset)
					);
					$this->button_counter++;
				}
			}

			// form elements: input type = image
			if (preg_match_all('/<input[^<>]+type=["\']image["\'][^<>]*>/isU', $form, $images)) {
				foreach ($images[0] as $image) {
					$this->_return[$this->_counter]['buttons'][$this->button_counter] = array(
						'type'  => 'image',
						'name'  => $this->_getName($image),
						'value'  => $this->_getValue($image)
					);
					$this->button_counter++;
				}
			}

			// input type=select entries
			// Here I have to go on step around to grep at first all select names and then
			// the content. Seems not to work in an other way
			if (preg_match_all('/<select.*>.+<\/select>/isU', $form, $selects)) {
				foreach ($selects[0] as $select) {
					if (preg_match_all('/<option.*>.+<\/option>/isU', $select, $all_options)) {
						$option_value = '';
						foreach ($all_options[0] as $option) {
							if (preg_match('/selected/i', $option)) {
								if (preg_match('/value=["\'](.*)["\']\s/isU', $option, $option_value)) {
									$option_value = $option_value[1];
									$found_selected = 1;
								} else {
									preg_match('/<option.*>(.*)<\/option>/isU', $option, $option_value);
									$option_value = $option_value[1];
									$found_selected = 1;
								}
							}
						}
						if (!isset($found_selected)) {
							if (preg_match('/value=["\'](.*)["\']/isU', $all_options[0][0], $option_value)) {
								$option_value = $option_value[1];
							} else {
								preg_match('/<option>(.*)<\/option>/isU', $all_options[0][0], $option_value);
								$option_value = $option_value[1];
							}
						} else {
							unset($found_selected);
						}
						$this->_return[$this->_counter]['form_elements'][$this->_getName($select)] = array(
							'type'  => 'select',
							'value'  => trim($option_value)
						);
					}
				}
			}

			# form elements: input type = --not defined--
			if ( preg_match_all('/<input[^<>]+name=["\'](.*)["\'][^<>]*>/isU', $form, $inputs)) {
				foreach ($inputs[0] as $input) {
					if ( !preg_match('/type=("([^"]*)"|\'([^\']*)\'|[^>\s]*)([^>]*)?>/is', $input) ) {
						if ( !isset($this->_return[$this->_counter]['form_elements'][$this->_getName($input)]) ) {
							$this->_return[$this->_counter]['form_elements'][$this->_getName($input)] =
								array(
									'type'  => 'text',
									'value'  =>  $this->_getValue($input),
									'id'  =>  $this->_getId($input),
									'class'  =>  $this->_getClass($input)
								);

						}
					}
				}
			}

			// Update the form counter if we have more then 1 form in the HTML table
			$this->_counter++;
		}

		return $this->_return;
	}

	/**
	 * gets the name
	 *
	 * @param string $string string
	 *
	 * @return string something
	 */
	function _getName($string)
	{
		if (preg_match('/name=("([^"]*)"|\'([^\']*)\'|[^>\s]*)([^>]*)?>/is', $string, $match)) {
			//preg_match('/name=["\']?([\w\s]*)["\']?[\s>]/i', $string, $match)) { -- did not work as expected
			$val_match = trim($match[1]);
			$val_match = trim($val_match, '"\'');
			unset($string);
			return trim($val_match, '"');
		}
		return false;
	}

	/**
	 * gets the value
	 *
	 * @param string $string string
	 *
	 * @return string something
	 */
	function _getValue($string)
	{
		if (preg_match('/value=("([^"]*)"|\'([^\']*)\'|[^>\s]*)([^>]*)?>/is', $string, $match)) {
			$val_match = trim($match[1]);
			$val_match = trim($val_match, '"\'');
			unset($string);
			return $val_match;
		}
		return false;
	}

	/**
	 * gets the id
	 *
	 * @param string $string string
	 *
	 * @return string something
	 */
	function _getId($string)
	{
		if (preg_match('/id=("([^"]*)"|\'([^\']*)\'|[^>\s]*)([^>]*)?>/is', $string, $match)) {
			//preg_match('/name=["\']?([\w\s]*)["\']?[\s>]/i', $string, $match)) { -- did not work as expected
			$val_match = trim($match[1]);
			$val_match = trim($val_match, '"\'');
			unset($string);
			return $val_match;
		}
		return false;
	}

	/**
	 * gets the class
	 *
	 * @param string $string string
	 *
	 * @return string something
	 */
	function _getClass($string)
	{
		if (preg_match('/class=("([^"]*)"|\'([^\']*)\'|[^>\s]*)([^>]*)?>/is', $string, $match)) {
			$val_match = trim($match[1]);
			$val_match = trim($val_match, '"\'');
			unset($string);
			return $val_match;
		}
		return false;
	}
}