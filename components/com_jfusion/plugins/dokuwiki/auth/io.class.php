<?php
/**
 * Class for dokuwiki file access
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

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Class for dokuwiki file access
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage DokuWiki 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionDokuwiki_Io {
    var $jname = null;
    
    function JFusionDokuwiki_Io($jname) {
		$this->jname = $jname;
    } 		
    /**
     * Saves $content to $file.
     *
     * If the third parameter is set to true the given content
     * will be appended.
     *
     * Uses gzip if extension is .gz
     * and bz2 if extension is .bz2
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     * @return bool true on success
     */
    function saveFile($file, $content, $append = false) {
        $share = Dokuwiki::getInstance($this->jname);
        $conf = $share->getConf();
        $mode = ($append) ? 'ab' : 'wb';
        $fileexists = @file_exists($file);
        $this->makefiledir($file);
        $this->lock($file);
        if (substr($file, -3) == '.gz') {
            $fh = @gzopen($file, $mode . '9');
            if (!$fh) {
                JError::raiseWarning(500, "Writing $file failed");
                $this->unlock($file);
                return false;
            }
            gzwrite($fh, $content);
            gzclose($fh);
        } else if (substr($file, -4) == '.bz2') {
            $fh = @bzopen($file, $mode{0});
            if (!$fh) {
                JError::raiseWarning(500, "Writing $file failed");
                $this->unlock($file);
                return false;
            }
            bzwrite($fh, $content);
            bzclose($fh);
        } else {
            $fh = @fopen($file, $mode);
            if ($fh === false) {
                JError::raiseWarning(500, "Writing $file failed");
                $this->unlock($file);
                return false;
            }
            fwrite($fh, $content);
            fclose($fh);
        }
        if (!$fileexists and !empty($conf['fperm'])) chmod($file, $conf['fperm']);
        $this->unlock($file);
        return true;
    }
    /**
     * Delete exact linematch for $badline from $file.
     *
     * Be sure to include the trailing newline in $badline
     *
     * Uses gzip if extension is .gz
     *
     * 2005-10-14 : added regex option -- Christopher Smith <chris@jalakai.co.uk>
     *
     * @author Steven Danz <steven-danz@kc.rr.com>
     * @return bool true on success
     */
    function deleteFromFile($file, $badline, $regex = false) {
        if (!@file_exists($file)) return true;
        $this->lock($file);
        // load into array
        if (substr($file, -3) == '.gz') {
            $lines = gzfile($file);
        } else {
            $lines = file($file);
        }
        // remove all matching lines
        if ($regex) {
            $lines = preg_grep($badline, $lines, PREG_GREP_INVERT);
        } else {
            $pos = array_search($badline, $lines); //return null or false if not found
            while (is_int($pos)) {
                unset($lines[$pos]);
                $pos = array_search($badline, $lines);
            }
        }
        if (count($lines)) {
            $content = join('', $lines);
            if (substr($file, -3) == '.gz') {
                $fh = @gzopen($file, 'wb9');
                if (!$fh) {
                    JError::raiseWarning(500, "Removing content from $file failed");
                    $this->unlock($file);
                    return false;
                }
                gzwrite($fh, $content);
                gzclose($fh);
            } else {
                $fh = @fopen($file, 'wb');
                if (!$fh) {
                    JError::raiseWarning(500, "Removing content from $file failed");
                    $this->unlock($file);
                    return false;
                }
                fwrite($fh, $content);
                fclose($fh);
            }
        } else {
            @unlink($file);
        }
        $this->unlock($file);
        return true;
    }
    /**
     * Tries to lock a file
     *
     * Locking is only done for io_savefile and uses directories
     * inside $conf['lockdir']
     *
     * It waits maximal 3 seconds for the lock, after this time
     * the lock is assumed to be stale and the function goes on
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function lock($file) {
        $share = Dokuwiki::getInstance($this->jname);
        $conf = $share->getConf();
        // no locking if safemode hack
        if (@$conf['safemodehack']) return;
        $lockDir = @$conf['lockdir'] . '/' . md5($file);
        @ignore_user_abort(1);
        $timeStart = time();
        do {
            //waited longer than 3 seconds? -> stale lock
            if ((time() - $timeStart) > 3) break;
            $locked = @mkdir($lockDir, @$conf['dmode']);
            if ($locked) {
                if (!empty($conf['dperm'])) chmod($lockDir, $conf['dperm']);
                break;
            }
            usleep(50);
        }
        while ($locked === false);
    }
    /**
     * JFusionDokuwiki_Io::unlocks a file
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function unlock($file) {
        $share = Dokuwiki::getInstance($this->jname);
        $conf = $share->getConf();;
        // no locking if safemode hack
        if (@$conf['safemodehack']) return;
        $lockDir = @$conf['lockdir'] . '/' . md5($file);
        @rmdir($lockDir);
        @ignore_user_abort(0);
    }
    /**
     * Create the directory needed for the given file
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     */
    function makeFileDir($file) {
        $dir = dirname($file);
        if (!@is_dir($dir)) {
            $this->mkdir_p($dir) || JError::raiseWarning(500, "Creating directory $dir failed");
        }
    }
    /**
     * Creates a directory hierachy.
     *
     * @link    http://www.php.net/manual/en/function.mkdir.php
     * @author  <saint@corenova.com>
     * @author  Andreas Gohr <andi@splitbrain.org>
     */
    function mkdir_p($target) {
        $share = Dokuwiki::getInstance($this->jname);
        $conf = $share->getConf();
        if (@is_dir($target) || empty($target)) return 1; // best case check first
        if (@file_exists($target) && !is_dir($target)) return 0;
        //recursion
        if ($this->mkdir_p(substr($target, 0, strrpos($target, '/')))) {
            if ($conf['safemodehack']) {
                $dir = preg_replace('/^' . preg_quote($this->fullpath($conf['ftp']['root']), '/') . '/', '', $target);
                return $this->mkdir_ftp($dir);
            } else {
                $ret = @mkdir($target, $conf['dmode']); // crawl back up & create dir tree
                if ($ret && $conf['dperm']) chmod($target, $conf['dperm']);
                return $ret;
            }
        }
        return 0;
    }
    /**
     * Creates a directory using FTP
     *
     * This is used when the safemode workaround is enabled
     *
     * @author <andi@splitbrain.org>
     */
    function mkdir_ftp($dir) {
        $share = Dokuwiki::getInstance($this->jname);
        $conf = $share->getConf();
        if (!function_exists('ftp_connect')) {
            JError::raiseWarning(500, "FTP support not found - safemode workaround not usable");
            return false;
        }
        $conn = @ftp_connect($conf['ftp']['host'], $conf['ftp']['port'], 10);
        if (!$conn) {
            JError::raiseWarning(500, "FTP connection failed");
            return false;
        }
        if (!@ftp_login($conn, $conf['ftp']['user'], $conf['ftp']['pass'])) {
            JError::raiseWarning(500, "FTP login failed");
            return false;
        }
        //create directory
        $ok = @ftp_mkdir($conn, $dir);
        //set permissions
        @ftp_site($conn, sprintf("CHMOD %04o %s", $conf['dmode'], $dir));
        @ftp_close($conn);
        return $ok;
    }
    /**
     * A realpath() replacement
     *
     * This function behaves similar to PHP's realpath() but does not resolve
     * symlinks or accesses upper directories
     *
     * @author <richpageau at yahoo dot co dot uk>
     * @link   http://de3.php.net/manual/en/function.realpath.php#75992
     */
    function fullpath($path) {
        $iswin = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
        if ($iswin) $path = str_replace('\\', '/', $path); // windows compatibility
        // check if path begins with "/" or "c:" ie. is absolute
        // if it isnt concat with script path
        if ((!$iswin && $path{0} !== '/') || ($iswin && $path{1} !== ':')) {
            $base = dirname($_SERVER['SCRIPT_FILENAME']);
            $path = $base . "/" . $path;
        }
        // canonicalize
        $path = explode('/', $path);
        $newpath = array();
        foreach ($path as $p) {
            if ($p === '' || $p === '.') continue;
            if ($p === '..') {
                array_pop($newpath);
                continue;
            }
            array_push($newpath, $p);
        }
        $finalpath = implode('/', $newpath);
        if (!$iswin) $finalpath = '/' . $finalpath;
        // check then return valid path or filename
        if (file_exists($finalpath)) {
            return ($finalpath);
        } else return false;
    }
}
