<?php

/**
 * Auth Plugin Prototype
 *
 * foundation authorisation class
 * all auth classes should inherit from this class
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Chris Smith <chris@jalakai.co.uk>
 * @author     Jan Schumann <js@jschumann-it.com>
 */
if (!class_exists('Jfusion_DokuWiki_Basic')) {
	/**
	 * Class Jfusion_DokuWiki_Basic
	 */
	class Jfusion_DokuWiki_Basic {
		/**
		 * @var JFusionHelper_dokuwiki $helper
		 */
		protected $helper;

		/**
		 * Constructor.
		 * @param JFusionHelper_dokuwiki $helper
		 *
		 * Carry out sanity checks to ensure the object is
		 * able to operate. Set capabilities in $this->cando
		 * array here
		 *
		 * For future compatibility, sub classes should always include a call
		 * to parent::__constructor() in their constructors!
		 *
		 * Set $this->success to false if checks fail
		 *
		 * @author  Christopher Smith <chris@jalakai.co.uk>
		 */
		public function __construct($helper) {
			$this->helper = $helper;
			// the base class constructor does nothing, derived class
			// constructors do the real work
		}

		/**
		 * Check user+password [ MUST BE OVERRIDDEN ]
		 *
		 * Checks if the given user exists and the given
		 * plaintext password is correct
		 *
		 * May be ommited if trustExternal is used.
		 *
		 * @author  Andreas Gohr <andi@splitbrain.org>
		 * @param   string $user the user name
		 * @param   string $pass the clear text password
		 * @return  bool
		 */
		public function checkPass($user, $pass) {
			$this->debug('no valid authorisation system in use', -1);
			return false;
		}

		/**
		 * @param string $clear
		 * @param string $crypt
		 *
		 * @return bool
		 */
		function verifyPassword($clear, $crypt) {
			require_once 'passhash.class.php';
			$pass = new Jfusion_PassHash();
			return $pass->verify_hash($clear, $crypt);
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
		 * @author  Andreas Gohr <andi@splitbrain.org>
		 * @param   string $user the user name
		 * @return  array containing user data or false
		 */
		public function getUserData($user) {
			$this->debug('no valid authorisation system in use', -1);
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
		 * @author  Andreas Gohr <andi@splitbrain.org>
		 * @param  string     $user
		 * @param  string     $pass
		 * @param  string     $name
		 * @param  string     $mail
		 * @param  array      $grps
		 *
		 * @return bool|null
		 */
		public function createUser($user, $pass, $name, $mail, $grps) {
			$this->debug('authorisation method does not allow creation of new users', -1);
			return null;
		}

		/**
		 * Modify user data [implement only where required/possible]
		 *
		 * Set the mod* capabilities according to the implemented features
		 *
		 * @author  Chris Smith <chris@jalakai.co.uk>
		 * @param   string $user    nick of the user to be changed
		 * @param   array  $changes array of field/value pairs to be changed (password will be clear text)
		 * @return  bool
		 */
		public function modifyUser($user, $changes) {
			$this->debug('authorisation method does not allow modifying of user data', -1);
			return false;
		}

		/**
		 * Delete one or more users [implement only where required/possible]
		 *
		 * Set delUser capability when implemented
		 *
		 * @author  Chris Smith <chris@jalakai.co.uk>
		 * @param   array  $users
		 * @return  int    number of users deleted
		 */
		public function deleteUsers($users) {
			$this->debug('authorisation method does not allow deleting of users', -1);
			return false;
		}

		/**
		 * Return a count of the number of user which meet $filter criteria
		 * [should be implemented whenever retrieveUsers is implemented]
		 *
		 * Set getUserCount capability when implemented
		 *
		 * @author Chris Smith <chris@jalakai.co.uk>
		 * @param  array $filter array of field/pattern pairs, empty array for no filter
		 * @return int
		 */
		public function getUserCount($filter = array()) {
			$this->debug('authorisation method does not provide user counts', -1);
			return 0;
		}

		/**
		 * Bulk retrieval of user data [implement only where required/possible]
		 *
		 * Set getUsers capability when implemented
		 *
		 * @author  Chris Smith <chris@jalakai.co.uk>
		 * @param   int   $start     index of first user to be returned
		 * @param   int   $limit     max number of users to be returned
		 * @param   array $filter    array of field/pattern pairs, null for no filter
		 * @return  array list of userinfo (refer getUserData for internal userinfo details)
		 */
		public function retrieveUsers($start = 0, $limit = -1, $filter = null) {
			$this->debug('authorisation method does not support mass retrieval of user data', -1);
			return array();
		}

		/**
		 * Define a group [implement only where required/possible]
		 *
		 * Set addGroup capability when implemented
		 *
		 * @author  Chris Smith <chris@jalakai.co.uk>
		 * @param   string $group
		 * @return  bool
		 */
		public function addGroup($group) {
			$this->debug('authorisation method does not support independent group creationvs', -1);
			return false;
		}

		/**
		 * Retrieve groups [implement only where required/possible]
		 *
		 * Set getGroups capability when implemented
		 *
		 * @author  Chris Smith <chris@jalakai.co.uk>
		 * @param   int $start
		 * @param   int $limit
		 * @return  array
		 */
		public function retrieveGroups($start = 0, $limit = 0) {
			$this->debug('authorisation method does not support group list retrieval', -1);
			return array();
		}

		/**
		 * BASIC DOKUWIKI FUNCTIONS RECREATED
		 */

		/**
		 * @param        $clear
		 * @param string $method
		 * @param null   $salt
		 *
		 * @return bool
		 */

		function cryptPassword($clear, $method = '', $salt = null) {
			$conf = $this->helper->getConf();
			if(empty($method)) $method = $conf['passcrypt'];

			require_once 'passhash.class.php';
			$pass = new Jfusion_PassHash();
			$call = 'hash_' . $method;

			if(!method_exists($pass, $call)) {
				$this->debug("Unsupported crypt method $method", -1);
				return false;
			}

			return $pass->$call($clear, $salt);
		}

		/**
		 * @param $string
		 */
		function debug($string) {
			JFusionFunction::raiseWarning($string);
		}
	}
}