<?php

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
 */

function ft_pageSearch($query, &$highlight) {
    $q = ft_queryParser($query);
    $highlight = array();
    // remember for hilighting later
    foreach ($q['words'] as $wrd) {
        $highlight[] = str_replace('*', '', $wrd);
    }
    // lookup all words found in the query
    $words = array_merge($q['and'], $q['not']);
    if (!count($words)) return array();
    $result = idx_lookup($words);
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
        $docs = ft_resultCombine($q['and']);
    } else {
        $docs = $q['and'][0];
    }
    if (!count($docs)) return array();
    // create a list of hidden pages in the result
    $hidden = array();
    $hidden = array_filter(array_keys($docs), 'isHiddenPage');
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
        // use this for higlighting later:
        $highlight = array_merge($highlight, $q['phrases']);
        $q['phrases'] = array_map('preg_quote_cb', $q['phrases']);
        // check the source of all documents for the exact phrases
        foreach (array_keys($docs) as $id) {
            $text = utf8_strtolower(rawWiki($id));
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
 * Returns the backlinks for a given page
 *
 * Does a quick lookup with the fulltext index, then
 * evaluates the instructions of the found pages
 */
function ft_backlinks($id) {
    global $conf;
    $swfile = DOKU_INC . 'inc/lang/' . $conf['lang'] . '/stopwords.txt';
    $stopwords = @file_exists($swfile) ? file($swfile) : array();
    $result = array();
    // quick lookup of the pagename
    $page = noNS($id);
    $matches = idx_lookup(idx_tokenizer($page, $stopwords)); // pagename may contain specials (_ or .)
    $docs = array_keys(ft_resultCombine(array_values($matches)));
    $docs = array_filter($docs, 'isVisiblePage'); // discard hidden pages
    if (!count($docs)) return $result;
    require_once DOKU_INC . 'inc/parserutils.php';
    // check metadata for matching links
    foreach ($docs as $match) {
        // metadata relation reference links are already resolved
        $links = p_get_metadata($match, 'relation references');
        if (isset($links[$id])) $result[] = $match;
    }
    if (!count($result)) return $result;
    // check ACL permissions
    foreach (array_keys($result) as $idx) {
        if (auth_quickaclcheck($result[$idx]) < AUTH_READ) {
            unset($result[$idx]);
        }
    }
    sort($result);
    return $result;
}
/**
 * Returns the pages that use a given media file
 *
 * Does a quick lookup with the fulltext index, then
 * evaluates the instructions of the found pages
 *
 * Aborts after $max found results
 */
function ft_mediause($id, $max) {
    global $conf;
    $swfile = DOKU_INC . 'inc/lang/' . $conf['lang'] . '/stopwords.txt';
    $stopwords = @file_exists($swfile) ? file($swfile) : array();
    if (!$max) $max = 1; // need to find at least one
    $result = array();
    // quick lookup of the mediafile
    $media = noNS($id);
    $matches = idx_lookup(idx_tokenizer($media, $stopwords));
    $docs = array_keys(ft_resultCombine(array_values($matches)));
    if (!count($docs)) return $result;
    // go through all found pages
    $found = 0;
    $pcre = preg_quote($media, '/');
    foreach ($docs as $doc) {
        $ns = getNS($doc);
        preg_match_all('/\{\{([^|}]*' . $pcre . '[^|}]*)(|[^}]+)?\}\}/i', rawWiki($doc), $matches);
        foreach ($matches[1] as $img) {
            $img = trim($img);
            if (preg_match('/^https?:\/\//i', $img)) continue; // skip external images
            list($img) = explode('?', $img); // remove any parameters
            $exists = null;
            resolve_mediaid($ns, $img, $exists); // resolve the possibly relative img
            if ($img == $id) { // we have a match
                $result[] = $doc;
                $found++;
                break;
            }
        }
        if ($found >= $max) break;
    }
    sort($result);
    return $result;
}
/**
 * Quicksearch for pagenames
 *
 * By default it only matches the pagename and ignores the
 * namespace. This can be changed with the second parameter
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function ft_pageLookup($id, $pageonly = true) {
    global $conf, $rootFolder;
    $id = preg_quote($id, '/');
    $pages = file($rootFolder . '/data/index' . '/page.idx');
    if ($id) $pages = array_values(preg_grep('/' . $id . '/', $pages));
    $cnt = count($pages);
    for ($i = 0;$i < $cnt;$i++) {
        if ($pageonly) {
            if (!preg_match('/' . $id . '/', noNS($pages[$i]))) {
                unset($pages[$i]);
                continue;
            }
        }
        if (!page_exists($pages[$i])) {
            unset($pages[$i]);
            continue;
        }
    }
    $pages = array_filter($pages, 'isVisiblePage'); // discard hidden pages
    if (!count($pages)) return array();
    // check ACL permissions
    foreach (array_keys($pages) as $idx) {
        if (auth_quickaclcheck($pages[$idx]) < AUTH_READ) {
            unset($pages[$idx]);
        }
    }
    $pages = array_map('trim', $pages);
    sort($pages);
    return $pages;
}
/**
 * Creates a snippet extract
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function ft_snippet($id, $highlight) {
    $text = rawWiki($id);
    $match = array();
    $snippets = array();
    $utf8_offset = $offset = $end = 0;
    $len = utf8_strlen($text);
    // build a regexp from the phrases to highlight
    $re = join('|', array_map('preg_quote_cb', array_filter((array)$highlight)));
    for ($cnt = 3;$cnt--;) {
        if (!preg_match('#(' . $re . ')#iu', $text, $match, PREG_OFFSET_CAPTURE, $offset)) break;
        list($str, $idx) = $match[0];
        // convert $idx (a byte offset) into a utf8 character offset
        $utf8_idx = utf8_strlen(substr($text, 0, $idx));
        $utf8_len = utf8_strlen($str);
        // establish context, 100 bytes surrounding the match string
        // first look to see if we can go 100 either side,
        // then drop to 50 adding any excess if the other side can't go to 50,
        $pre = min($utf8_idx - $utf8_offset, 100);
        $post = min($len - $utf8_idx - $utf8_len, 100);
        if ($pre > 50 && $post > 50) {
            $pre = $post = 50;
        } else if ($pre > 50) {
            $pre = min($pre, 100 - $post);
        } else if ($post > 50) {
            $post = min($post, 100 - $pre);
        } else {
            // both are less than 50, means the context is the whole string
            // make it so and break out of this loop - there is no need for the
            // complex snippet calculations
            $snippets = array($text);
            break;
        }
        // establish context start and end points, try to append to previous
        // context if possible
        $start = $utf8_idx - $pre;
        $append = ($start < $end) ? $end : false; // still the end of the previous context snippet
        $end = $utf8_idx + $utf8_len + $post; // now set it to the end of this context
        if ($append) {
            $snippets[count($snippets) - 1].= utf8_substr($text, $append, $end - $append);
        } else {
            $snippets[] = utf8_substr($text, $start, $end - $start);
        }
        // set $offset for next match attempt
        //   substract strlen to avoid splitting a potential search success,
        //   this is an approximation as the search pattern may match strings
        //   of varying length and it will fail if the context snippet
        //   boundary breaks a matching string longer than the current match
        $utf8_offset = $utf8_idx + $post;
        $offset = $idx + strlen(utf8_substr($text, $utf8_idx, $post));
        $offset = utf8_correctIdx($text, $offset);
    }
    $m = "\1";
    $snippets = preg_replace('#(' . $re . ')#iu', $m . '$1' . $m, $snippets);
    $snippet = preg_replace('#' . $m . '([^' . $m . ']*?)' . $m . '#iu', '<strong class="search_hit">$1</strong>', hsc(join('... ', $snippets)));
    return $snippet;
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
 * @todo support OR and parenthesises?
 */
function ft_queryParser($query) {
    global $conf;
    $swfile = DOKU_INC . 'inc/lang/' . $conf['lang'] . '/stopwords.txt';
    if (@file_exists($swfile)) {
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
        $q['ns'] = explode('@', preg_replace("/ /", '', $match[2]));
    }
    // handle phrase searches
    while (preg_match('/"(.*?)"/', $query, $match)) {
        $q['phrases'][] = $match[1];
        $q['and'] = array_merge($q['and'], idx_tokenizer($match[0], $stopwords));
        $query = preg_replace('/"(.*?)"/', '', $query, 1);
    }
    $words = explode(' ', $query);
    foreach ($words as $w) {
        if ($w{0} == '-') {
            $token = idx_tokenizer($w, $stopwords, true);
            if (count($token)) $q['not'] = array_merge($q['not'], $token);
        } else {
            // asian "words" need to be searched as phrases
            if (@preg_match_all('/((' . IDX_ASIAN . ')+)/u', $w, $matches)) {
                $q['phrases'] = array_merge($q['phrases'], $matches[1]);
            }
            $token = idx_tokenizer($w, $stopwords, true);
            if (count($token)) {
                $q['and'] = array_merge($q['and'], $token);
                $q['words'] = array_merge($q['words'], $token);
            }
        }
    }
    return $q;
}
function idx_tokenizer($string, &$stopwords, $wc = false) {
    $words = array();
    $wc = ($wc) ? '' : $wc = '\*';
    if (preg_match('/[^0-9A-Za-z]/u', $string)) {
        // handle asian chars as single words (may fail on older PHP version)
        $asia = @preg_replace('/(' . IDX_ASIAN . ')/u', ' \1 ', $string);
        if (!is_null($asia)) $string = $asia; //recover from regexp failure
        $arr = explode(' ', utf8_stripspecials($string, ' ', '\._\-:' . $wc));
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
function idx_lookup($words) {
    global $conf;
    $result = array();
    $wids = idx_getIndexWordsSorted($words, $result);
    if (empty($wids)) return array();
    // load known words and documents
    $page_idx = idx_getIndex('page', '');
    $docs = array(); // hold docs found
    foreach (array_keys($wids) as $wlen) {
        $wids[$wlen] = array_unique($wids[$wlen]);
        $index = idx_getIndex('i', $wlen);
        foreach ($wids[$wlen] as $ixid) {
            if ($ixid < count($index)) $docs["$wlen*$ixid"] = idx_parseIndexLine($page_idx, $index[$ixid]);
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
function idx_getIndexWordsSorted($words, &$result) {
    // parse and sort tokens
    $tokens = array();
    $tokenlength = array();
    $tokenwild = array();
    foreach ($words as $word) {
        $result[$word] = array();
        $wild = 0;
        $xword = $word;
        $wlen = wordlen($word);
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
    $indexes_known = idx_indexLengths($length_filter);
    if (!empty($tokenwild)) sort($indexes_known);
    // get word IDs
    $wids = array();
    foreach ($indexes_known as $ixlen) {
        $word_idx = idx_getIndex('w', $ixlen);
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
function wordlen($w) {
    defined('IDX_ASIAN2') OR define('IDX_ASIAN2','['.
       '\x{2E80}-\x{3040}'.  // CJK -> Hangul
       '\x{309D}-\x{30A0}'.
       '\x{30FD}-\x{31EF}\x{3200}-\x{D7AF}'.
       '\x{F900}-\x{FAFF}'.  // CJK Compatibility Ideographs
       '\x{FE30}-\x{FE4F}'.  // CJK Compatibility Forms
       ']');

    $l = strlen($w);
    // If left alone, all chinese "words" will get put into w3.idx
    // So the "length" of a "word" is faked
    if (preg_match('/' . IDX_ASIAN2 . '/u', $w)) $l+= ord($w) - 0xE1; // Lead bytes from 0xE2-0xEF
    return $l;
}
function idx_indexLengths(&$filter) {
    global $conf, $rootFolder;
    $dir = @opendir($rootFolder . '/data/index');
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
        if (@file_exists($rootFolder . '/data/index' . "/i$filter.idx")) $idx[] = $filter;
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
function idx_getIndex($pre, $wlen) {
    global $conf, $rootFolder;
    $fn = $rootFolder . '/data/index' . '/' . $pre . $wlen . '.idx';
    if (!@file_exists($fn)) return array();
    return file($fn);
}
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
        if (!page_exists($doc, '', false)) continue;
        $result[$doc] = $cnt;
    }
    return $result;
}
function page_exists($id, $rev = '', $clean = true) {
    return @file_exists(wikiFN($id, $rev, $clean));
}
function wikiFN($raw_id, $rev = '', $clean = true) {
    global $conf, $rootFolder;
    global $cache_wikifn;
    $cache = & $cache_wikifn;
    if (isset($cache[$raw_id]) && isset($cache[$raw_id][$rev])) {
        return $cache[$raw_id][$rev];
    }
    $id = $raw_id;
    if ($clean) $id = cleanID($id);
    $id = str_replace(':', '/', $id);
    if (empty($rev)) {
        $fn = $rootFolder . '/data/pages' . '/' . utf8_encodeFN($id) . '.txt';
    } else {
        $fn = $conf['olddir'] . '/' . utf8_encodeFN($id) . '.' . $rev . '.txt';
        if ($conf['compression']) {
            //test for extensions here, we want to read both compressions
            if (@file_exists($fn . '.gz')) {
                $fn.= '.gz';
            } else if (@file_exists($fn . '.bz2')) {
                $fn.= '.bz2';
            } else {
                //file doesnt exist yet, so we take the configured extension
                $fn.= '.' . $conf['compression'];
            }
        }
    }
    if (!isset($cache[$raw_id])) {
        $cache[$raw_id] = array();
    }
    $cache[$raw_id][$rev] = $fn;
    return $fn;
}
function utf8_encodeFN($file, $safe = true) {
    if ($safe && preg_match('#^[a-zA-Z0-9/_\-.%]+$#', $file)) {
        return $file;
    }
    $file = urlencode($file);
    $file = str_replace('%2F', '/', $file);
    return $file;
}
function isHiddenPage($id) {
    global $conf;
    if (empty($conf['hidepages'])) return false;
    if (preg_match('/' . $conf['hidepages'] . '/ui', ':' . $id)) {
        return true;
    }
    return false;
}
