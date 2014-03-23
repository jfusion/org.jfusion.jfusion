<?php namespace JFusion\Plugins\dokuwiki;


/**
 * file containing search function for dokuwiki
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage DokuWiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 *
 * @return array
 */
use JFusion\Factory;

/**
 * Dokuwiki Search class
 */
class Search
{
	var $path;
	var $conf;

	/**
	 * @param string $instance instance name of this plugin
	 */
	function __construct($instance)
	{
		/**
		 * @ignore
		 * @var $helper Helper
		 */
		$helper = Factory::getHelper($instance);
		$this->path = $helper->params->get('source_path');
		if (substr($this->path, -1) != DIRECTORY_SEPARATOR) {
			$this->path .= DIRECTORY_SEPARATOR;
		}

		$this->conf = $helper->getConf($this->path);
	}
	/**
	 * ft_pageSearch
	 *
	 * @param string $query
	 * @param array &$highlight
	 *
	 * @return array
	 */
	function ft_pageSearch($query, &$highlight) {
		$q = $this->ft_queryParser($query);
		$highlight = array();
		// remember for hilighting later
		foreach ($q['words'] as $wrd) {
			$highlight[] = str_replace('*', '', $wrd);
		}
		// lookup all words found in the query
		$words = array_merge($q['and'], $q['not']);
		if (!count($words)) return array();
		$result = $this->idx_lookup($words);
		if (!count($result)) return array();
		// merge search results with query
		foreach ($q['and'] as $pos => $w) {
			$q['and'][$pos] = $result[$w];
		}
		// create a list of unwanted docs
		$not = array();
		foreach ($q['not'] as $w) {
			$not = array_merge($not, array_keys($result[$w]));
		}
		// combine and-words
		if (count($q['and']) > 1) {
			$docs = $this->ft_resultCombine($q['and']);
		} else {
			$docs = $q['and'][0];
		}
		if (!count($docs)) return array();
		// create a list of hidden pages in the result
		$hidden = array_filter(array_keys($docs), array($this, 'isHiddenPage'));
		$not = array_merge($not, $hidden);
		// filter unmatched namespaces
		if (!empty($q['ns'])) {
			$pattern = implode('|^', $q['ns']);
			foreach ($docs as $key => $val) {
				if (!preg_match('/^' . $pattern . '/', $key)) {
					unset($docs[$key]);
				}
			}
		}
		// remove negative matches
		foreach ($not as $n) {
			unset($docs[$n]);
		}
		if (!count($docs)) return array();
		// handle phrases
		if (count($q['phrases'])) {
			$q['phrases'] = array_map('utf8_strtolower', $q['phrases']);
			// use this for highlighting later:
			$highlight = array_merge($highlight, $q['phrases']);
			$q['phrases'] = array_map('preg_quote_cb', $q['phrases']);
			// check the source of all documents for the exact phrases
			foreach (array_keys($docs) as $id) {
				$text = utf8_strtolower($this->getPage($id));
				foreach ($q['phrases'] as $phrase) {
					if (!preg_match('/' . $phrase . '/usi', $text)) {
						unset($docs[$id]); // no hit - remove
						break;
					}
				}
			}
		}
		if (!count($docs)) return array();
		// check ACL permissions
		/*
		foreach (array_keys($docs) as $doc)
		{
			if (auth_quickaclcheck($doc) < AUTH_READ)
			{
				unset($docs[$doc]);
			}
		}
		*/
		if (!count($docs)) return array();
		// if there are any hits left, sort them by count
		arsort($docs);
		return $docs;
	}
	/**
	 * Combine found documents and sum up their scores
	 *
	 * This function is used to combine searched words with a logical
	 * AND. Only documents available in all arrays are returned.
	 *
	 * based upon PEAR's PHP_Compat function for array_intersect_key()
	 *
	 * @param array $args An array of page arrays
	 *
	 * @return array
	 */
	function ft_resultCombine($args) {
		$array_count = count($args);
		if ($array_count == 1) {
			return $args[0];
		}
		$result = array();
		if ($array_count > 1) {
			foreach ($args[0] as $key => $value) {
				$result[$key] = $value;
				for ($i = 1;$i !== $array_count;$i++) {
					if (!isset($args[$i][$key])) {
						unset($result[$key]);
						break;
					}
					$result[$key]+= $args[$i][$key];
				}
			}
		}
		return $result;
	}
	/**
	 * Builds an array of search words from a query
	 *
	 * @TODO support OR and parenthesises?
	 *
	 * @param string $query
	 *
	 * @return array
	 */
	function ft_queryParser($query) {
		$conf = $this->conf;
		$swfile = $this->path . 'inc/lang/' . $conf['lang'] . '/stopwords.txt';
		if (file_exists($swfile)) {
			$stopwords = file($swfile);
		} else {
			$stopwords = array();
		}
		$q = array();
		$q['query'] = $query;
		$q['ns'] = array();
		$q['phrases'] = array();
		$q['words'] = array();
		$q['and'] = array();
		$q['not'] = array();
		// strip namespace from query
		if (preg_match('/([^@]*)@(.*)/', $query, $match)) {
			$query = $match[1];
			$q['ns'] = explode('@', preg_replace('/ /', '', $match[2]));
		}
		// handle phrase searches
		while (preg_match('/"(.*?)"/', $query, $match)) {
			$q['phrases'][] = $match[1];
			$q['and'] = array_merge($q['and'], $this->idx_tokenizer($match[0], $stopwords));
			$query = preg_replace('/"(.*?)"/', '', $query, 1);
		}
		$words = explode(' ', $query);
		foreach ($words as $w) {
			if ($w{0} == '-') {
				$token = $this->idx_tokenizer($w, $stopwords, true);
				if (count($token)) $q['not'] = array_merge($q['not'], $token);
			} else {
				// asian "words" need to be searched as phrases
				if (@preg_match_all('/((' . IDX_ASIAN . ')+)/u', $w, $matches)) {
					$q['phrases'] = array_merge($q['phrases'], $matches[1]);
				}
				$token = $this->idx_tokenizer($w, $stopwords, true);
				if (count($token)) {
					$q['and'] = array_merge($q['and'], $token);
					$q['words'] = array_merge($q['words'], $token);
				}
			}
		}
		return $q;
	}

	/**
	 * @param $string
	 * @param $stopwords
	 * @param bool $wc
	 * @return array
	 */
	function idx_tokenizer($string, &$stopwords, $wc = false) {
		$words = array();
		$wc = ($wc) ? '' : $wc = '\*';
		if (preg_match('/[^0-9A-Za-z]/u', $string)) {
			// handle asian chars as single words (may fail on older PHP version)
			$asia = @preg_replace('/(' . IDX_ASIAN . ')/u', ' \1 ', $string);
			if (!is_null($asia)) $string = $asia; //recover from regexp failure
			$arr = explode(' ', $this->utf8_stripspecials($string, ' ', '\._\-:' . $wc));
			foreach ($arr as $w) {
				if (!is_numeric($w) && strlen($w) < 3) continue;
				$w = utf8_strtolower($w);
				if ($stopwords && is_int(array_search("$w\n", $stopwords))) continue;
				$words[] = $w;
			}
		} else {
			$w = $string;
			if (!is_numeric($w) && strlen($w) < 3) return $words;
			$w = strtolower($w);
			if (is_int(array_search("$w\n", $stopwords))) return $words;
			$words[] = $w;
		}
		return $words;
	}

	/**
	 * @param $words
	 * @return array
	 */
	function idx_lookup($words) {
		$conf = $this->conf;
		$result = array();
		$wids = $this->idx_getIndexWordsSorted($words, $result);
		if (empty($wids)) return array();
		// load known words and documents
		$page_idx = $this->idx_getIndex('page', '');
		$docs = array(); // hold docs found
		foreach (array_keys($wids) as $wlen) {
			$wids[$wlen] = array_unique($wids[$wlen]);
			$index = $this->idx_getIndex('i', $wlen);
			foreach ($wids[$wlen] as $ixid) {
				if ($ixid < count($index)) $docs["$wlen*$ixid"] = $this->idx_parseIndexLine($page_idx, $index[$ixid]);
			}
		}
		// merge found pages into final result array
		$final = array();
		foreach (array_keys($result) as $word) {
			$final[$word] = array();
			foreach ($result[$word] as $wid) {
				$hits = & $docs[$wid];
				foreach ($hits as $hitkey => $hitcnt) {
					if (isset($final[$word][$hitkey])) {
						$final[$word][$hitkey] = $hitcnt + $final[$word][$hitkey];
					} else {
						$final[$word][$hitkey] = $hitcnt;
					}
				}
			}
		}
		return $final;
	}

	/**
	 * @param $words
	 * @param $result
	 * @return array
	 */
	function idx_getIndexWordsSorted($words, &$result) {
		// parse and sort tokens
		$tokens = array();
		$tokenlength = array();
		$tokenwild = array();
		foreach ($words as $word) {
			$result[$word] = array();
			$wild = 0;
			$xword = $word;
			$wlen = $this->wordlen($word);
			// check for wildcards
			if (substr($xword, 0, 1) == '*') {
				$xword = substr($xword, 1);
				$wild|= 1;
				$wlen-= 1;
			}
			if (substr($xword, -1, 1) == '*') {
				$xword = substr($xword, 0, -1);
				$wild|= 2;
				$wlen-= 1;
			}
			if ($wlen < 3 && $wild == 0 && !is_numeric($xword)) continue;
			if (!isset($tokens[$xword])) {
				$tokenlength[$wlen][] = $xword;
			}
			if ($wild) {
				$ptn = preg_quote($xword, '/');
				if (($wild & 1) == 0) $ptn = '^' . $ptn;
				if (($wild & 2) == 0) $ptn = $ptn . '$';
				$tokens[$xword][] = array($word, '/' . $ptn . '/');
				if (!isset($tokenwild[$xword])) $tokenwild[$xword] = $wlen;
			} else $tokens[$xword][] = array($word, null);
		}
		asort($tokenwild);
		// $tokens = array( base word => array( [ query word , grep pattern ] ... ) ... )
		// $tokenlength = array( base word length => base word ... )
		// $tokenwild = array( base word => base word length ... )
		$length_filter = empty($tokenwild) ? $tokenlength : min(array_keys($tokenlength));
		$indexes_known = $this->idx_indexLengths($length_filter);
		if (!empty($tokenwild)) sort($indexes_known);
		// get word IDs
		$wids = array();
		foreach ($indexes_known as $ixlen) {
			$word_idx = $this->idx_getIndex('w', $ixlen);
			// handle exact search
			if (isset($tokenlength[$ixlen])) {
				foreach ($tokenlength[$ixlen] as $xword) {
					$wid = array_search("$xword\n", $word_idx);
					if (is_int($wid)) {
						$wids[$ixlen][] = $wid;
						foreach ($tokens[$xword] as $w) $result[$w[0]][] = "$ixlen*$wid";
					}
				}
			}
			// handle wildcard search
			foreach ($tokenwild as $xword => $wlen) {
				if ($wlen >= $ixlen) break;
				foreach ($tokens[$xword] as $w) {
					if (is_null($w[1])) continue;
					foreach (array_keys(preg_grep($w[1], $word_idx)) as $wid) {
						$wids[$ixlen][] = $wid;
						$result[$w[0]][] = "$ixlen*$wid";
					}
				}
			}
		}
		return $wids;
	}

	/**
	 * @param $w
	 * @return int
	 */
	function wordlen($w) {
		defined('IDX_ASIAN2') OR define('IDX_ASIAN2', '[' .
			'\x{2E80}-\x{3040}' .  // CJK -> Hangul
			'\x{309D}-\x{30A0}' .
			'\x{30FD}-\x{31EF}\x{3200}-\x{D7AF}' .
			'\x{F900}-\x{FAFF}' .  // CJK Compatibility Ideographs
			'\x{FE30}-\x{FE4F}' .  // CJK Compatibility Forms
			']');

		$l = strlen($w);
		// If left alone, all chinese "words" will get put into w3.idx
		// So the "length" of a "word" is faked
		if (preg_match('/' . IDX_ASIAN2 . '/u', $w)) $l+= ord($w) - 0xE1; // Lead bytes from 0xE2-0xEF
		return $l;
	}

	/**
	 * @param $filter
	 * @return array
	 */
	function idx_indexLengths(&$filter) {
		$conf = $this->conf;
		$dir = @opendir($this->path . 'data/index');
		if ($dir === false) return array();
		$idx = array();
		if (is_array($filter)) {
			while (($f = readdir($dir)) !== false) {
				if (substr($f, 0, 1) == 'i' && substr($f, -4) == '.idx') {
					$i = substr($f, 1, -4);
					if (is_numeric($i) && isset($filter[(int)$i])) $idx[] = (int)$i;
				}
			}
		} else {
			// Exact match first.
			if (file_exists($this->path . 'data/index' . "/i$filter.idx")) $idx[] = $filter;
			while (($f = readdir($dir)) !== false) {
				if (substr($f, 0, 1) == 'i' && substr($f, -4) == '.idx') {
					$i = substr($f, 1, -4);
					if (is_numeric($i) && $i > $filter) $idx[] = (int)$i;
				}
			}
		}
		closedir($dir);
		return $idx;
	}

	/**
	 * @param $string
	 * @param string $repl
	 * @param string $additional
	 * @return mixed
	 */
	function utf8_stripspecials($string, $repl = '', $additional = '') {
		global $UTF8_SPECIAL_CHARS;
		global $UTF8_SPECIAL_CHARS2;
		static $specials = null;
		if (is_null($specials)) {
			#    $specials = preg_quote(unicode_to_utf8($UTF8_SPECIAL_CHARS), '/');
			$specials = preg_quote($UTF8_SPECIAL_CHARS2, '/');
		}
		return preg_replace('/[' . $additional . '\x00-\x19' . $specials . ']/u', $repl, $string);
	}

	/**
	 * @param $pre
	 * @param $wlen
	 * @return array
	 */
	function idx_getIndex($pre, $wlen) {
		$conf = $this->conf;
		$fn = $this->path . 'data/index' . '/' . $pre . $wlen . '.idx';
		if (!file_exists($fn)) return array();
		return file($fn);
	}

	/**
	 * @param $page_idx
	 * @param $line
	 * @return array
	 */
	function idx_parseIndexLine(&$page_idx, $line) {
		$result = array();
		$line = trim($line);
		if ($line == '') return $result;
		$parts = explode(':', $line);
		foreach ($parts as $part) {
			if ($part == '') continue;
			list($doc, $cnt) = explode('*', $part);
			if (!$cnt) continue;
			$doc = trim($page_idx[$doc]);
			if (!$doc) continue;
			// make sure the document still exists
			if (!$this->page_exists($doc, '', false)) continue;
			$result[$doc] = $cnt;
		}
		return $result;
	}

	/**
	 * @param $id
	 * @param string $rev
	 * @param bool $clean
	 * @return bool
	 */
	function page_exists($id, $rev = '', $clean = true) {
		return file_exists($this->wikiFN($id, $rev, $clean));
	}

	/**
	 * @param $raw_id
	 * @param string $rev
	 * @param bool $clean
	 * @return string
	 */
	function wikiFN($raw_id, $rev = '', $clean = true) {
		$conf = $this->conf;
		global $cache_wikifn;
		$cache = & $cache_wikifn;
		if (isset($cache[$raw_id]) && isset($cache[$raw_id][$rev])) {
			return $cache[$raw_id][$rev];
		}
		$id = $raw_id;
		if ($clean) $id = $this->cleanID($id);
		$id = str_replace(':', '/', $id);
		if (empty($rev)) {
			$fn = $this->path . 'data/pages' . '/' . $this->utf8_encodeFN($id) . '.txt';
		} else {
			$fn = $conf['olddir'] . '/' . $this->utf8_encodeFN($id) . '.' . $rev . '.txt';
			if ($conf['compression']) {
				//test for extensions here, we want to read both compressions
				if (file_exists($fn . '.gz')) {
					$fn .= '.gz';
				} else if (file_exists($fn . '.bz2')) {
					$fn .= '.bz2';
				} else {
					//file doesn't exist yet, so we take the configured extension
					$fn .= '.' . $conf['compression'];
				}
			}
		}
		if (!isset($cache[$raw_id])) {
			$cache[$raw_id] = array();
		}
		$cache[$raw_id][$rev] = $fn;
		return $fn;
	}

	/**
	 * @param $file
	 * @param bool $safe
	 * @return mixed|string
	 */
	function utf8_encodeFN($file, $safe = true) {
		if ($safe && preg_match('#^[a-zA-Z0-9/_\-.%]+$#', $file)) {
			return $file;
		}
		$file = urlencode($file);
		$file = str_replace('%2F', '/', $file);
		return $file;
	}

	/**
	 * @param $id
	 * @return bool
	 */
	function isHiddenPage($id) {
		$conf = $this->conf;
		if (empty($conf['hidepages'])) return false;
		if (preg_match('/' . $conf['hidepages'] . '/ui', ':' . $id)) {
			return true;
		}
		return false;
	}

	/**
	 * Remove unwanted chars from ID
	 *
	 * Cleans a given ID to only use allowed characters. Accented characters are
	 * converted to unaccented ones
	 *
	 * @author Andreas Gohr <andi@splitbrain.org>
	 * @param  string  $raw_id    The pageid to clean
	 * @param  boolean $ascii     Force ASCII
	 * @param  boolean $media     Allow leading or trailing _ for media files
	 *
	 * @return string
	 */
	function cleanID($raw_id, $ascii = false, $media = false) {
		$conf = $this->conf;
		static $sepcharpat = null;

		global $cache_cleanid;
		$cache = & $cache_cleanid;

		// check if it's already in the memory cache
		if (isset($cache[(string)$raw_id])) {
			return $cache[(string)$raw_id];
		}

		$sepchar = $conf['sepchar'];
		if($sepcharpat == null) // build string only once to save clock cycles
			$sepcharpat = '#\\' . $sepchar . '+#';

		$id = trim((string)$raw_id);
		$id = utf8_strtolower($id);

		//alternative namespace separator
		$id = strtr($id, ';', ':');
		if($conf['useslash']) {
			$id = strtr($id, '/', ':');
		} else {
			$id = strtr($id, '/', $sepchar);
		}
		/* commented becuase it is unused
					if($conf['deaccent'] == 2 || $ascii) $id = utf8_romanize($id);
					if($conf['deaccent'] || $ascii) $id = utf8_deaccent($id,-1);

					//remove specials
					$id = utf8_stripspecials($id, $sepchar, '\*');

					if($ascii) $id = utf8_strip($id);
		*/
		//clean up
		$id = preg_replace($sepcharpat, $sepchar, $id);
		$id = preg_replace('#:+#', ':', $id);
		$id = ($media ? trim($id, ':.-') : trim($id, ':._-'));
		$id = preg_replace('#:[:\._\-]+#', ':', $id);
		$id = preg_replace('#[:\._\-]+:#', ':', $id);

		$cache[(string)$raw_id] = $id;
		return ($id);
	}

	/**
	 * @param $page
	 * @return string
	 */
	function getPage($page) {
		$file = $this->path . 'data' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . str_replace(':', DIRECTORY_SEPARATOR, $page) . '.txt';
		$text = '';
		if (file_exists($file)) {
			$handle = fopen($file, "r");
			while (!feof($handle)) {
				$text.= fgets($handle, 4096);
			}
			fclose($handle);
		}
		return $text ? $text : 'Please, follow the given link to get the DokuWiki article where we found one or more keyword(s).';
	}

	/**
	 * @param $page
	 * @return string
	 */
	function getPageModifiedDateTime($page) {
		$datetime = '';
		$file = $this->path . 'data' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . str_replace(':', DIRECTORY_SEPARATOR, $page) . '.txt';
		if (file_exists($file)) {
			$datetime = date ('Y-m-d h:i:s', filemtime($file));
		}
		return $datetime;
	}
}