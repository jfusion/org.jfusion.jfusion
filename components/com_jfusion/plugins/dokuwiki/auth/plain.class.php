<?php

/**
 * Plaintext authentication backend
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Chris Smith <chris@jalakai.co.uk>
 * @author     Jan Schumann <js@schumann-it.com>
 */
require_once 'basic.class.php';

if (!class_exists('Jfusion_DokuWiki_Plain')) {
	/**
	 * Class Jfusion_DokuWiki_Plain
	 */
	class Jfusion_DokuWiki_Plain extends Jfusion_DokuWiki_Basic {
		/** @var array user cache */
		protected $users = null;

		/** @var array filter pattern */
		protected $_pattern = array();

		protected $file = null;

		/**
		 * Constructor
		 *
		 * @param JFusionHelper_dokuwiki $helper
		 */
		public function __construct($helper) {
			parent::__construct($helper);

			$params = JFusionFactory::getParams($this->helper->getJname());
			$this->file = $params->get('source_path');
			if (substr($this->file, -1) == DIRECTORY_SEPARATOR) {
				$this->file = $this->file . 'conf/users.auth.php';
			} else {
				$this->file = $this->file . DIRECTORY_SEPARATOR . 'conf/users.auth.php';
			}
		}

		/**
		 * Check user+password
		 *
		 * Checks if the given user exists and the given
		 * plaintext password is correct
		 *
		 * @author  Andreas Gohr <andi@splitbrain.org>
		 * @param string $user
		 * @param string $pass
		 * @return  bool
		 */
		public function checkPass($user, $pass) {
			$userinfo = $this->getUserData($user);
			if($userinfo === false) return false;

			return $this->verifyPassword($pass, $this->users[$user]['pass']);
		}

		/**
		 * Return user info
		 *
		 * Returns info about the given user needs to contain
		 * at least these fields:
		 *
		 * name string  full name of the user
		 * mail string  email addres of the user
		 * grps array   list of groups the user is in
		 *
		 * @author  Andreas Gohr <andi@splitbrain.org>
		 * @param string $user
		 * @return array|bool
		 */
		public function getUserData($user) {
			if($this->users === null) $this->_loadUserData();
			return isset($this->users[$user]) ? $this->users[$user] : false;
		}

		/**
		 * Create a new User
		 *
		 * Returns false if the user already exists, null when an error
		 * occurred and true if everything went well.
		 *
		 * The new user will be added to the default group by this
		 * function if grps are not specified (default behaviour).
		 *
		 * @author  Andreas Gohr <andi@splitbrain.org>
		 * @author  Chris Smith <chris@jalakai.co.uk>
		 *
		 * @param string $user
		 * @param string $pwd
		 * @param string $name
		 * @param string $mail
		 * @param array  $grps
		 *
		 * @return bool|null|string
		 */
		public function createUser($user, $pwd, $name, $mail, $grps) {
			// user mustn't already exist
			if($this->getUserData($user) !== false) return false;

			$pass = $this->cryptPassword($pwd);

			// prepare user line
			$groups   = join(',', $grps);
			$userline = join(':', array($user, $pass, $name, $mail, $groups))."\n";

			if($this->saveFile($this->file, $userline, true)) {
				$this->users[$user] = compact('pass', 'name', 'mail', 'grps');
				return $pwd;
			}
			$this->debug('The '.$this->file.' file is not writable. Please inform the Wiki-Admin',-1);
			return null;
		}

		/**
		 * Modify user data
		 *
		 * @author  Chris Smith <chris@jalakai.co.uk>
		 * @param   string $user      nick of the user to be changed
		 * @param   array  $changes   array of field/value pairs to be changed (password will be clear text)
		 * @return  bool
		 */
		public function modifyUser($user, $changes) {
			// sanity checks, user must already exist and there must be something to change
			if(($userinfo = $this->getUserData($user)) === false) return false;
			if(!is_array($changes) || !count($changes)) return true;

			// update userinfo with new data, remembering to encrypt any password
			$newuser = $user;
			foreach($changes as $field => $value) {
				if($field == 'user') {
					$newuser = $value;
					continue;
				}
				if($field == 'pass') $value = $this->cryptPassword($value);
				$userinfo[$field] = $value;
			}

			$groups   = join(',', $userinfo['grps']);
			$userline = join(':', array($newuser, $userinfo['pass'], $userinfo['name'], $userinfo['mail'], $groups))."\n";

			if(!$this->deleteUsers(array($user))) {
				$this->debug('Unable to modify user data. Please inform the Wiki-Admin',-1);
				return false;
			}

			if(!$this->saveFile($this->file, $userline, true)) {
				$this->debug('There was an error modifying your user data. You should register again.',-1);
				return false;
			}

			$this->users[$newuser] = $userinfo;
			return true;
		}

		/**
		 * Remove one or more users from the list of registered users
		 *
		 * @author  Christopher Smith <chris@jalakai.co.uk>
		 * @param   array  $users   array of users to be deleted
		 * @return  int             the number of users deleted
		 */
		public function deleteUsers($users) {
			if(!is_array($users) || empty($users)) return 0;

			if($this->users === null) $this->_loadUserData();

			$deleted = array();
			foreach($users as $user) {
				if(isset($this->users[$user])) $deleted[] = preg_quote($user, '/');
			}

			if(empty($deleted)) return 0;

			$pattern = '/^('.join('|', $deleted).'):/';

			if($this->deleteFromFile($this->file, $pattern, true)) {
				foreach($deleted as $user) unset($this->users[$user]);
				return count($deleted);
			}

			// problem deleting, reload the user list and count the difference
			$count = count($this->users);
			$this->_loadUserData();
			$count -= count($this->users);
			return $count;
		}

		/**
		 * Return a count of the number of user which meet $filter criteria
		 *
		 * @author  Chris Smith <chris@jalakai.co.uk>
		 *
		 * @param array $filter
		 * @return int
		 */
		public function getUserCount($filter = array()) {

			if($this->users === null) $this->_loadUserData();

			if(!count($filter)) return count($this->users);

			$count = 0;
			$this->_constructPattern($filter);

			foreach($this->users as $user => $info) {
				$count += $this->_filter($user, $info);
			}

			return $count;
		}

		/**
		 * Bulk retrieval of user data
		 *
		 * @author  Chris Smith <chris@jalakai.co.uk>
		 *
		 * @param   int   $start index of first user to be returned
		 * @param   int   $limit max number of users to be returned
		 * @param   array $filter array of field/pattern pairs
		 * @return  array userinfo (refer getUserData for internal userinfo details)
		 */
		public function retrieveUsers($start = 0, $limit = 0, $filter = array()) {

			if($this->users === null) $this->_loadUserData();

			ksort($this->users);

			$i     = 0;
			$count = 0;
			$out   = array();
			$this->_constructPattern($filter);

			foreach($this->users as $user => $info) {
				if($this->_filter($user, $info)) {
					if($i >= $start) {
						$out[$user] = $info;
						$count++;
						if(($limit > 0) && ($count >= $limit)) break;
					}
					$i++;
				}
			}

			return $out;
		}

		/**
		 * Load all user data
		 *
		 * loads the user file into a datastructure
		 *
		 * @author  Andreas Gohr <andi@splitbrain.org>
		 */
		protected function _loadUserData() {
			$this->users = array();

			if(!@file_exists($this->file)) return;

			$lines = file($this->file);
			foreach($lines as $line) {
				$line = preg_replace('/#.*$/', '', $line); //ignore comments
				$line = trim($line);
				if(empty($line)) continue;

				$row    = explode(":", $line, 5);
				$groups = array_values(array_filter(explode(",", $row[4])));

				$this->users[$row[0]]['pass'] = $row[1];
				$this->users[$row[0]]['name'] = urldecode($row[2]);
				$this->users[$row[0]]['mail'] = $row[3];
				$this->users[$row[0]]['grps'] = $groups;
			}
		}

		/**
		 * return true if $user + $info match $filter criteria, false otherwise
		 *
		 * @author   Chris Smith <chris@jalakai.co.uk>
		 *
		 * @param string $user User login
		 * @param array  $info User's userinfo array
		 * @return bool
		 */
		protected function _filter($user, $info) {
			foreach($this->_pattern as $item => $pattern) {
				if($item == 'user') {
					if(!preg_match($pattern, $user)) return false;
				} else if($item == 'grps') {
					if(!count(preg_grep($pattern, $info['grps']))) return false;
				} else {
					if(!preg_match($pattern, $info[$item])) return false;
				}
			}
			return true;
		}

		/**
		 * construct a filter pattern
		 *
		 * @param array $filter
		 */
		protected function _constructPattern($filter) {
			$this->_pattern = array();
			foreach($filter as $item => $pattern) {
				$this->_pattern[$item] = '/'.str_replace('/', '\/', $pattern).'/i'; // allow regex characters
			}
		}



		/**
		 * @param $string
		 */
		function debug($string) {
			JFusionFunction::raiseWarning($string, $this->helper->getJname());
		}



		/**
		 * BASIC DOKUWIKI FUNCTIONS RECREATED
		 */

		/**
		 * Saves $content to $file.
		 *
		 * If the third parameter is set to true the given content
		 * will be appended.
		 *
		 * Uses gzip if extension is .gz
		 * and bz2 if extension is .bz2
		 *
		 * @param string $file
		 * @param string $content
		 * @param bool $append
		 *
		 * @author  Andreas Gohr <andi@splitbrain.org>
		 * @return bool true on success
		 */
		function saveFile($file, $content, $append = false) {
			$conf = $this->helper->getConf();
			$mode = ($append) ? 'ab' : 'wb';
			$fileexists = @file_exists($file);
			$this->makefiledir($file);
			$this->lock($file);
			if (substr($file, -3) == '.gz') {
				$fh = @gzopen($file, $mode . '9');
				if (!$fh) {
					$this->debug("Writing $file failed",-1);
					$this->unlock($file);
					return false;
				}
				gzwrite($fh, $content);
				gzclose($fh);
			} else if (substr($file, -4) == '.bz2') {
				$fh = @bzopen($file, $mode{0});
				if (!$fh) {
					$this->debug("Writing $file failed",-1);
					$this->unlock($file);
					return false;
				}
				bzwrite($fh, $content);
				bzclose($fh);
			} else {
				$fh = @fopen($file, $mode);
				if ($fh === false) {
					$this->debug("Writing $file failed",-1);
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
		 * @param string $file
		 * @param string $badline
		 * @param bool $regex
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
						$this->debug("Removing content from $file failed",-1);
						$this->unlock($file);
						return false;
					}
					gzwrite($fh, $content);
					gzclose($fh);
				} else {
					$fh = @fopen($file, 'wb');
					if (!$fh) {
						$this->debug("Removing content from $file failed",-1);
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
		 *
		 * @param string $file
		 */
		function lock($file) {
			$conf = $this->helper->getConf();
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
		 * @param string $file
		 *
		 * @author Andreas Gohr <andi@splitbrain.org>
		 */
		function unlock($file) {
			$conf = $this->helper->getConf();;
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
		 *
		 * @param string $file
		 */
		function makeFileDir($file) {
			$dir = dirname($file);
			if (!@is_dir($dir)) {
				$this->mkdir_p($dir) || $this->debug("Creating directory $dir failed",-1);
			}
		}
		/**
		 * Creates a directory hierarchy.
		 *
		 * @link    http://www.php.net/manual/en/function.mkdir.php
		 * @author  <saint@corenova.com>
		 * @author  Andreas Gohr <andi@splitbrain.org>
		 *
		 * @param string $target
		 *
		 * @return int
		 */
		function mkdir_p($target) {
			$conf = $this->helper->getConf();
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
		 *
		 * @param string $dir
		 *
		 * @return bool
		 */
		function mkdir_ftp($dir) {
			$conf = $this->helper->getConf();
			if (!function_exists('ftp_connect')) {
				$this->debug("FTP support not found - safemode workaround not usable",-1);
				return false;
			}
			$conn = @ftp_connect($conf['ftp']['host'], $conf['ftp']['port'], 10);
			if (!$conn) {
				$this->debug("FTP connection failed",-1);
				return false;
			}
			if (!@ftp_login($conn, $conf['ftp']['user'], $conf['ftp']['pass'])) {
				$this->debug("FTP login failed",-1);
				return false;
			}
			//create directory
			$ok = @ftp_mkdir($conn, $dir);
			//set permissions
			@ftp_site($conn, sprintf('CHMOD %04o %s', $conf['dmode'], $dir));
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
		 *
		 * @param string $path
		 *
		 * @return bool
		 */
		function fullpath($path) {
			$iswin = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
			if ($iswin) $path = str_replace('\\', '/', $path); // windows compatibility
			// check if path begins with "/" or "c:" ie. is absolute
			// if it isn't concat with script path
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
}