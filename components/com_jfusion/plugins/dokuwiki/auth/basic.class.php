<?php

/**
 * Basic authentication class for dokuwiki
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
 * Basic authentication class for dokuwiki
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage DokuWiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class doku_auth_basic
{
    /**
     * @var $helper JFusionHelper_dokuwiki
     */
	var $helper = null;

    var $success = true;
    /**
     * Posible things an auth backend module may be able to
     * do. The things a backend can do need to be set to true
     * in the constructor.
     */
    var $cando = array('addUser' => false, // can Users be created?
    'delUser' => false, // can Users be deleted?
    'modLogin' => false, // can login names be changed?
    'modPass' => false, // can passwords be changed?
    'modName' => false, // can real names be changed?
    'modMail' => false, // can emails be changed?
    'modGroups' => false, // can groups be changed?
    'getUsers' => false, // can a (filtered) list of users be retrieved?
    'getUserCount' => false, // can the number of users be retrieved?
    'getGroups' => false, // can a list of available groups be retrieved?
    'external' => false, // does the module do external auth checking?
    'logoff' => false, // has the module some special logoff method?
    );

    /**
     * Encrypts a password using the given method and salt
     *
     * If the selected method needs a salt and none was given, a random one
     * is chosen.
     *
     * The following methods are understood:
     *
     *   smd5  - Salted MD5 hashing
     *   md5   - Simple MD5 hashing
     *   sha1  - SHA1 hashing
     *   ssha  - Salted SHA1 hashing
     *   crypt - Unix crypt
     *   mysql - MySQL password (old method)
     *   my411 - MySQL 4.1.1 password
     *
     * @param string $clear  clear password
     * @param string $method method to use
     * @param string $salt   salt added to password
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     * @return  string  The crypted password
     */
    function cryptPassword($clear, $method = '', $salt = '')
    {
        $conf = $this->helper->getConf();
        if (empty($method)) $method = $conf['passcrypt'];
        //prepare a salt
        if (empty($salt)) $salt = md5(uniqid(rand(), true));
        $magic = null;
        switch (strtolower($method)) {
	        case 'smd5':
	            if(defined('CRYPT_MD5') && CRYPT_MD5) return crypt($clear,'$1$'.substr($salt,0,8).'$');
	            // when crypt can't handle SMD5, falls through to pure PHP implementation
	            $magic = '1';
	        case 'apr1':
	            //from http://de.php.net/manual/en/function.crypt.php#73619 comment by <mikey_nich at hotmail dot com>
	            if(!$magic) $magic = 'apr1';
	            $salt = substr($salt,0,8);
	            $len = strlen($clear);
	            $text = $clear.'$'.$magic.'$'.$salt;
	            $bin = pack("H32", md5($clear.$salt.$clear));
	            for($i = $len; $i > 0; $i -= 16) { $text .= substr($bin, 0, min(16, $i)); }
	            for($i = $len; $i > 0; $i >>= 1) { $text .= ($i & 1) ? chr(0) : $clear{0}; }
	            $bin = pack("H32", md5($text));
	            for($i = 0; $i < 1000; $i++) {
	                $new = ($i & 1) ? $clear : $bin;
	                if ($i % 3) $new .= $salt;
	                if ($i % 7) $new .= $clear;
	                $new .= ($i & 1) ? $bin : $clear;
	                $bin = pack("H32", md5($new));
	            }
	            $tmp = '';
	            for ($i = 0; $i < 5; $i++) {
	                $k = $i + 6;
	                $j = $i + 12;
	                if ($j == 16) $j = 5;
	                $tmp = $bin[$i].$bin[$k].$bin[$j].$tmp;
	            }
            	$tmp = chr(0).chr(0).$bin[11].$tmp;
            	$tmp = strtr(strrev(substr(base64_encode($tmp), 2)),
                    "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",
                    "./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");
            	return '$'.$magic.'$'.$salt.'$'.$tmp;
            case 'md5':
                return md5($clear);
            case 'sha1':
                return sha1($clear);
            case 'ssha':
                $salt = substr($salt, 0, 4);
                return '{SSHA}' . base64_encode(pack("H*", sha1($clear . $salt)) . $salt);
            case 'crypt':
                return crypt($clear, substr($salt, 0, 2));
            case 'mysql':
                //from http://www.php.net/mysql comment by <soren at byu dot edu>
                $nr = 0x50305735;
                $nr2 = 0x12345671;
                $add = 7;
                $charArr = preg_split("//", $clear);
                foreach ($charArr as $char) {
                    if (($char == '') || ($char == ' ') || ($char == '\t')) continue;
                    $charVal = ord($char);
                    $nr^= ((($nr & 63) + $add) * $charVal) + ($nr << 8);
                    $nr2+= ($nr2 << 8) ^ $nr;
                    $add+= $charVal;
                }
                return sprintf("%08x%08x", ($nr & 0x7fffffff), ($nr2 & 0x7fffffff));
            case 'my411':
                return '*' . sha1(pack("H*", sha1($clear)));
            default:
                JError::raiseWarning(500, "Unsupported crypt method $method");
            }
        return false;
    }

    /**
     * Verifies a cleartext password against a crypted hash
     *
     * The method and salt used for the crypted hash is determined automatically
     * then the clear text password is crypted using the same method. If both hashs
     * match true is is returned else false
     *
     * @param string $clear username
     * @param string $crypt crupt
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     * @return  bool
     */
    function verifyPassword($clear, $crypt)
    {
        $method = '';
        $salt = '';
        //determine the used method and salt
        $len = strlen($crypt);
        if (preg_match('/^\$1\$([^\$]{0,8})\$/', $crypt, $m)) {
            $method = 'smd5';
            $salt = $m[1];
        } elseif (preg_match('/^\$apr1\$([^\$]{0,8})\$/', $crypt, $m)) {
            $method = 'apr1';
            $salt = $m[1];
        } elseif (substr($crypt, 0, 6) == '{SSHA}') {
            $method = 'ssha';
            $salt = substr(base64_decode(substr($crypt, 6)), 20);
        } elseif ($len == 32) {
            $method = 'md5';
        } elseif ($len == 40) {
            $method = 'sha1';
        } elseif ($len == 16) {
            $method = 'mysql';
        } elseif ($len == 41 && $crypt[0] == '*') {
            $method = 'my411';
        } else {
            $method = 'crypt';
            $salt = substr($crypt, 0, 2);
        }
        //crypt and compare
        if ($this->cryptPassword($clear, $method, $salt) === $crypt) {
            return true;
        }
        return false;
    }

    /**
     * Constructor.
     *
     * Carry out sanity checks to ensure the object is
     * able to operate. Set capabilities in $this->cando
     * array here
     *
     * Set $this->success to false if checks fail
     *
     * @author Christopher Smith <chris@jalakai.co.uk>
     * @return void
     */

    function doku_auth_basic()
    {
        // the base class constructor does nothing, derived class
        // constructors do the real work
    }

    /**
     * Capability check. [ DO NOT OVERRIDE ]
     *
     * Checks the capabilities set in the $this->cando array and
     * some pseudo capabilities (shortcutting access to multiple
     * ones)
     *
     * ususal capabilities start with lowercase letter
     * shortcut capabilities start with uppercase letter
     *
     * @param string $cap action
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @return bool
     */
    function canDo($cap)
    {
        switch ($cap) {
            case 'Profile':
                // can at least one of the user's properties be changed?
                return ($this->cando['modPass'] || $this->cando['modName'] || $this->cando['modMail']);
            break;
            case 'UserMod':
                // can at least anything be changed?
                return ($this->cando['modPass'] || $this->cando['modName'] || $this->cando['modMail'] || $this->cando['modLogin'] || $this->cando['modGroups'] || $this->cando['modMail']);
            break;
            default:
                // print a helping message for developers
                if (!isset($this->cando[$cap])) {
                    JError::raiseWarning(500, "Check for unknown capability '$cap' - Do you use an outdated Plugin?");
                    return false;
                }
                return $this->cando[$cap];
        }
    }

    /**
     * Log off the current user [ OPTIONAL ]
     *
     * Is run in addition to the ususal logoff method. Should
     * only be needed when trustExternal is implemented.
     *
     * @see    auth_logoff()
     * @author Andreas Gohr
     */
    function logOff()
    {
    }

    /**
     * Check user+password [ MUST BE OVERRIDDEN ]
     *
     * Checks if the given user exists and the given
     * plaintext password is correct
     *
     * May be ommited if trustExternal is used.
     *
     * @param string $user username
     * @param string $pass password
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @return bool
     */
    function checkPass($user, $pass)
    {
        JError::raiseWarning(500, "no valid authorisation system in use");
        return false;
    }

    /**
     * Return user info [ MUST BE OVERRIDDEN ]
     *
     * Returns info about the given user needs to contain
     * at least these fields:
     *
     * name string  full name of the user
     * mail string  email addres of the user
     * grps array   list of groups the user is in
     *
     * @param string $user username
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @return array containing user data or false
     */
    function getUserData($user)
    {
        if (!$this->cando['external']) JError::raiseWarning(500, "no valid authorisation system in use");
        return false;
    }

    /**
     * Create a new User [implement only where required/possible]
     *
     * Returns false if the user already exists, null when an error
     * occurred and true if everything went well.
     *
     * The new user HAS TO be added to the default group by this
     * function!
     *
     * Set addUser capability when implemented
     *
     * @param string $user username
     * @param string $pass password
     * @param string $name real name
     * @param string $mail email adress
     * @param string $grps groups
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     *
     * @return null
     */
    function createUser($user, $pass, $name, $mail, $grps = null)
    {
        JError::raiseWarning(500, "authorisation method does not allow creation of new users");
        return null;
    }

    /**
     * Modify user data [implement only where required/possible]
     *
     * Set the mod* capabilities according to the implemented features
     *
     * @param string $user    nick of the user to be changed
     * @param array  $changes array of field/value pairs to be changed (password will be clear text)
     *
     * @author Chris Smith <chris@jalakai.co.uk>
     * @return bool
     */
    function modifyUser($user, $changes)
    {
        JError::raiseWarning(500, "authorisation method does not allow modifying of user data");
        return false;
    }

    /**
     * Delete one or more users [implement only where required/possible]
     *
     * Set delUser capability when implemented
     *
     * @param array $users arrat of users to delete
     *
     * @author Chris Smith <chris@jalakai.co.uk>
     * @return int number of users deleted
     */
    function deleteUsers($users)
    {
        JError::raiseWarning(500, "authorisation method does not allow deleting of users");
        return false;
    }

    /**
     * Return a count of the number of user which meet $filter criteria
     * [should be implemented whenever retrieveUsers is implemented]
     *
     * Set getUserCount capability when implemented
     *
     * @param array $filter
     *
     * @author Chris Smith <chris@jalakai.co.uk>
     *
     * @return int
     */
    function getUserCount($filter = array())
    {
        JError::raiseWarning(500, "authorisation method does not provide user counts");
        return 0;
    }

    /**
     * Bulk retrieval of user data [implement only where required/possible]
     *
     * Set getUsers capability when implemented
     *
     * @param int $start  index of first user to be returned
     * @param int $limit  max number of users to be returned
     * @param string $filter array of field/pattern pairs, null for no filter
     *
     * @author Chris Smith <chris@jalakai.co.uk>
     * @return array of userinfo (refer getUserData for internal userinfo details)
     */
    function retrieveUsers($start = 0, $limit = - 1, $filter = null)
    {
        JError::raiseWarning(500, "authorisation method does not provide user counts");
        return array();
    }

    /**
     * Define a group [implement only where required/possible]
     *
     * Set addGroup capability when implemented
     *
     * @param string $group group
     *
     * @author Chris Smith <chris@jalakai.co.uk>
     * @return bool
     */
    function addGroup($group)
    {
        JError::raiseWarning(500, "authorisation method does not support independent group creation");
        return false;
    }

    /**
     * Retrieve groups [implement only where required/possible]
     *
     * Set getGroups capability when implemented
     *
     * @param int $start index of first user to be returned
     * @param int $limit max number of users to be returned
     *
     * @author Chris Smith <chris@jalakai.co.uk>
     * @return array
     */
    function retrieveGroups($start = 0, $limit = 0)
    {
        JError::raiseWarning(500, "authorisation method does not support group list retrieval");
        return array();
    }

    /**
     * Check Session Cache validity [implement only where required/possible]
     *
     * DokuWiki caches user info in the user's session for the timespan defined
     * in $conf['securitytimeout'].
     *
     * This makes sure slow authentication backends do not slow down DokuWiki.
     * This also means that changes to the user database will not be reflected
     * on currently logged in users.
     *
     * To accommodate for this, the user manager plugin will touch a reference
     * file whenever a change is submitted. This function compares the filetime
     * of this reference file with the time stored in the session.
     *
     * This reference file mechanism does not reflect changes done directly in
     * the backend's database through other means than the user manager plugin.
     *
     * Fast backends might want to return always false, to force rechecks on
     * each page load. Others might want to use their own checking here. If
     * unsure, do not override.
     *
     * @param string $user The username
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @return bool
     */
    function useSessionCache($user)
    {
        global $conf;
        return ($_SESSION[DOKU_COOKIE]['auth']['time'] >= @filemtime($conf['cachedir'] . '/sessionpurge'));
    }
}
//Setup VIM: ex: et ts=2 enc=utf-8 :
