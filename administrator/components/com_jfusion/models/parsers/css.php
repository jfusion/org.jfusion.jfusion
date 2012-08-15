<?php
/**
 * cssparser class
 *
 * @category   JFusion
 * @package    Parser
 * @subpackage cssparser
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class cssparser {
	var $css = array();
	var $media = array();
	var $url = null;
	var $thisUrl = null;
	var $html = null;
	var $prefix = null;

	var $regex = array();
	var $replace = array();

    /**
     * @param null $prefix
     */
    function cssparser($prefix = null) {
		$this->prefix = $prefix;
	    $this->Clear();
	}

	function Clear() {
    	unset($this->css);
    	$this->css = array();
    	unset($this->media);
    	$this->media = array();
	}

    /**
     * @param $url
     */
    function SetUrl($url) {
		$this->url = $url;
	}

    /**
     * @param $key
     * @param $codestr
     * @return mixed
     */
    function Add($key, $codestr) {
		if(!isset($this->css[$key])) {
			$this->css[$key] = array();
		}
		if( strpos($key,'@') !== false ) {
			$this->media[$key] = $codestr;
		} else {
            $codes = explode(';',$codestr);
            if(count($codes) > 0) {
                foreach($codes as $code) {
                    $code = trim($code);
                    if(strlen($code)) {
                        $code = explode(':',$code,2);
                        if (count($code) == 2) {
                            list($codekey, $codevalue) = $code;
                            $codevalue = trim($codevalue);
                            if(strlen($codekey) > 0 && strlen($codevalue) > 0) {
                                $this->css[$key][trim($codekey)] = $codevalue;
                            }
                        }
                    }
                }
            }
        }
	}

    /**
     * @param $key
     * @param $property
     * @return string
     */
    function Get($key, $property) {
		$key = strtolower($key);
		$property = strtolower($property);

		list($tag, $subtag) = explode(':',$key);
		list($tag, $class) = explode('.',$tag);
		list($tag, $id) = explode('#',$tag);
		$result = '';
		foreach($this->css as $_tag => $value) {
			list($_tag, $_subtag) = explode(':',$_tag);
			list($_tag, $_class) = explode('.',$_tag);
			list($_tag, $_id) = explode('#',$_tag);

			$tagmatch = (strcmp($tag, $_tag) == 0) | (strlen($_tag) == 0);
			$subtagmatch = (strcmp($subtag, $_subtag) == 0) | (strlen($_subtag) == 0);
			$classmatch = (strcmp($class, $_class) == 0) | (strlen($_class) == 0);
			$idmatch = (strcmp($id, $_id) == 0);

			if($tagmatch & $subtagmatch & $classmatch & $idmatch) {
		    	$temp = $_tag;
				if((strlen($temp) > 0) & (strlen($_class) > 0)) {
					$temp .= '.'.$_class;
				} elseif(strlen($temp) == 0) {
					$temp = '.'.$_class;
				}
				if((strlen($temp) > 0) & (strlen($_subtag) > 0)) {
					$temp .= ':'.$_subtag;
				} elseif(strlen($temp) == 0) {
					$temp = ':'.$_subtag;
				}
				if(isset($this->css[$temp][$property])) {
					$result = $this->css[$temp][$property];
				}
			}
		}
		return $result;
	}

    /**
     * @param $key
     * @return array
     */
    function GetSection($key) {
    	$key = strtolower($key);

		list($tag, $subtag) = explode(':',$key);
		list($tag, $class) = explode('.',$tag);
		list($tag, $id) = explode('#',$tag);
		$result = array();
		foreach($this->css as $_tag => $value) {
			list($_tag, $_subtag) = explode(':',$_tag);
			list($_tag, $_class) = explode('.',$_tag);
			list($_tag, $_id) = explode('#',$_tag);

			$tagmatch = (strcmp($tag, $_tag) == 0) | (strlen($_tag) == 0);
			$subtagmatch = (strcmp($subtag, $_subtag) == 0) | (strlen($_subtag) == 0);
			$classmatch = (strcmp($class, $_class) == 0) | (strlen($_class) == 0);
			$idmatch = (strcmp($id, $_id) == 0);

			if($tagmatch & $subtagmatch & $classmatch & $idmatch) {
				$temp = $_tag;
				if((strlen($temp) > 0) & (strlen($_class) > 0)) {
					$temp .= ".".$_class;
				} elseif(strlen($temp) == 0) {
					$temp = ".".$_class;
				}
				if((strlen($temp) > 0) & (strlen($_subtag) > 0)) {
					$temp .= ":".$_subtag;
				} elseif(strlen($temp) == 0) {
					$temp = ":".$_subtag;
				}
				foreach($this->css[$temp] as $property => $property_value) {
					$result[$property] = $property_value;
				}
			}
		}
		return $result;
	}

    /**
     * @param $str
     * @return bool
     */
    function ParseStr($str) {
		$this->Clear();

		$this->modifyContent($str);

		$pos = strpos($str, '@');
		while ($pos !== false) {
			$start = $pos;
			$pos++;
			$media = '';
			$count = -1;

			while(true) {
				if ( $str[$pos] == '{' ) {
					if ( $count == -1 ) {
						$media = substr  ( $str , $start , ($pos-$start) );
						$count = 0;
					}
					$count++;
				}
				if ( $str[$pos] == '}' ) $count--;

				if ( $count == 0) {
					$end = $pos;

					$fullmedia = substr  ( $str , $start , ($end-$start)+1 );

					$subparse = substr($fullmedia , strpos($fullmedia, '{')+1 );

					$subparse = substr($subparse , 0,-1);

					$cssparser = new cssparser($this->prefix);
					$cssparser->SetUrl($this->thisUrl);
					$cssparser->ParseStr($subparse);
					$codestr = $cssparser->GetCSS();

					$str = str_replace($fullmedia,'',$str);
					$this->Add($media, $codestr);
					$pos = 0;
					break;
				}
				$pos++;
				if ( $pos == strlen($str) ) break;
			}
			$pos = strpos($str, '@',$pos);
		}

		if (preg_match_all( '#([^}]*){([^}]*)}#Sis', $str, $parts)) {
			foreach($parts[1] as $key => $keystr) {
				$codestr = trim($parts[2][$key]);
				$keys = explode(',',trim($keystr));
				if(count($keys)) {
					foreach($keys as $value) {
						$value = trim($value);
						if(strlen($value)) {
							$this->Add($value, $codestr);
						}
					}
				}
			}
		}
		return (count($this->css) > 0);
	}

    /**
     * @param $filename
     * @return bool
     */
    function Parse($filename) {
    	$this->Clear();
		if(file_exists($filename)) {
			return $this->ParseStr(file_get_contents($filename));
		} else {
			return false;
		}
	}

    /**
     * @param $url
     * @return bool
     */
    function ParseUrl($url) {
    	$this->Clear();

		$this->url = htmlspecialchars_decode($url);
		require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusionadmin.php';
		$content = JFusionFunctionAdmin::getFileData($this->url);

		if(strlen($content)) {
			$this->modifyContent($content);
			$this->ParseStr($content);
			return true;
		} else {
			return false;
		}
	}

    /**
     * @return string
     */
    function GetCSS() {
		$result = '';
		foreach($this->css as $key => $values) {
			if (strpos($key, '@') === false ) {
				if (isset($this->prefix) && strpos($key, $this->prefix) === false ) {
					if ($key == 'body' || $key == 'html' || strpos($key, 'html body') !== false){
						$result .= $key.' '.$this->prefix.' {
';
					} else {
						$result .= $this->prefix.' '.$key.' {
';
					}
				} else {
					$result .= $key.' {
';
				}
				foreach($values as $key2 => $value) {
					$result .= '  '.$key2.': '.$value.';
';
				}
				$result .= '}

';
			}
		}
		foreach($this->media as $key => $value) {
			$result .= $key.' {
';
			$result .= ' '.$value;
			$result .= '}
';
		}
		return $result;
	}

    /**
     * @param $content
     * @return mixed|string
     */
    function modifyContent(&$content) {
    	//Remove comments
		$content = preg_replace("#\/\*(?!\*\/)(.*?)\*\/#si", "", $content);
		$content = str_replace('<!--', '', $content);
		$content = str_replace('-->', '', $content);
		$content = trim($content);

		if (!isset($this->url)) return $content;
		$this->regex = array();
		$this->replace = array();

		$pathinfo = parse_url  ( $this->url   );

		$sorceurl = $pathinfo['scheme'].'://'.$pathinfo['host'].'/';

		$sorcepath = explode('/',$pathinfo['path']);
		array_shift($sorcepath);
		array_pop($sorcepath);

		$sorcepathoriginal = $sorcepath;

		while(count($sorcepath)) {
			$temp = $sorcepathoriginal;
			$path ='';
			foreach($sorcepath as $key => $values) {
				$path .= '\.\.\/';
				array_pop($temp);
			}

			$turl = $sorceurl;
			if (count($temp)) {
				$turl .= implode('/', $temp).'/';
			}
			$this->regex[] = '#'.$path.'#iSs';
			$this->replace[] = $turl;
			array_pop($sorcepath);
		}

		$this->thisUrl = $sorceurl.implode('/', $sorcepathoriginal).'/';

		$this->regex[] = '#\.\/#is';
		$this->replace[] = $sorceurl.implode('/', $sorcepathoriginal).'/';

		$this->regex[] = '#url\(([^"\'\)]*)\)#is';
		$this->replace[] = 'url("$1")';

		$this->regex[] = '#url\(["\']/([^"\']*)["\']\)#Sis';
		$this->replace[] = 'url("'.$sorceurl.'$1")';

		$this->regex[] = '#url\(["\'](?!\w{0,10}://)([^"\']*)["\']\)#Sis';
		$this->replace[] = 'url("'.$sorceurl.implode("/", $sorcepathoriginal).'/$1")';

		$regexall = $this->regex;
		$replaceall = $this->replace;

		$content = preg_replace($regexall, $replaceall, $content);

		if (preg_match_all( '#@import.*?[\'"]([^\'"]*)[\'"].*?;#Sis', $content, $imports)) {
			foreach ($imports[1] as $key => $import) {
				$cssparser = new cssparser($this->prefix);
				$cssparser->ParseUrl($import);
				$temp = $cssparser->GetCSS();

				$content = str_replace($imports[0][$key]  , ''  , $content );

				$content = $temp.$content;
			}
		}
		return $content;
	}
}