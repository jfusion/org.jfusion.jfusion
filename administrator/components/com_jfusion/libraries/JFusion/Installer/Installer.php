<?php namespace JFusion\Installer;
/**
 * @package     Joomla.Libraries
 * @subpackage  Installer
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

use JFusion\Factory;
use JFusion\Framework;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\Language\Text;

use Psr\Log\LogLevel;
use RuntimeException;
use SimpleXMLElement;

jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.path');

/**
 * Joomla base installer class
 *
 * @package     Joomla.Libraries
 * @subpackage  Installer
 * @since       3.1
 *
 *
 */
class Installer
{
	/**
	 * Array of paths needed by the installer
	 *
	 * @var    array
	 * @since  3.1
	 */
	protected $paths = array();

	/**
	 * True if package is an upgrade
	 *
	 * @var    boolean
	 * @since  3.1
	 */
	protected $upgrade = null;

	/**
	 * True if existing files can be overwritten
	 *
	 * @var    boolean
	 * @since  12.1
	 */
	protected $overwrite = false;

	/**
	 * Stack of installation steps
	 * - Used for installation rollback
	 *
	 * @var    array
	 * @since  3.1
	 */
	protected $stepStack = array();

	/**
	 * The output from the install/uninstall scripts
	 *
	 * @var    string
	 * @since  3.1
	 * */
	public $message = null;

	/**
	 * The installation manifest XML object
	 *
	 * @var    object
	 * @since  3.1
	 */
	public $manifest = null;

	/**
	 * Constructor
	 *
	 * @since   3.1
	 */
	public function __construct()
	{
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed $level
	 * @param string $message
	 *
	 * @return null
	 */
	private function log($level, $message) {
		Framework::raise($level, $message);
	}

	/**
	 * Get the allow overwrite switch
	 *
	 * @return  boolean  Allow overwrite switch
	 *
	 * @since   3.1
	 */
	public function isOverwrite()
	{
		return $this->overwrite;
	}

	/**
	 * Set the allow overwrite switch
	 *
	 * @param   boolean  $state  Overwrite switch state
	 *
	 * @return  boolean  True it state is set, false if it is not
	 *
	 * @since   3.1
	 */
	public function setOverwrite($state = false)
	{
		$tmp = $this->overwrite;

		if ($state)
		{
			$this->overwrite = true;
		}
		else
		{
			$this->overwrite = false;
		}

		return $tmp;
	}

	/**
	 * Get the upgrade switch
	 *
	 * @return  boolean
	 *
	 * @since   3.1
	 */
	public function isUpgrade()
	{
		return $this->upgrade;
	}

	/**
	 * Set the upgrade switch
	 *
	 * @param   boolean  $state  Upgrade switch state
	 *
	 * @return  boolean  True if upgrade, false otherwise
	 *
	 * @since   3.1
	 */
	public function setUpgrade($state = false)
	{
		$tmp = $this->upgrade;

		if ($state)
		{
			$this->upgrade = true;
		}
		else
		{
			$this->upgrade = false;
		}

		return $tmp;
	}

	/**
	 * Get an installer path by name
	 *
	 * @param   string  $name     Path name
	 * @param   string  $default  Default value
	 *
	 * @return  string  Path
	 *
	 * @since   3.1
	 */
	public function getPath($name, $default = null)
	{
		return (!empty($this->paths[$name])) ? $this->paths[$name] : $default;
	}

	/**
	 * Sets an installer path by name
	 *
	 * @param   string  $name   Path name
	 * @param   string  $value  Path
	 *
	 * @return  void
	 *
	 * @since   3.1
	 */
	public function setPath($name, $value)
	{
		$this->paths[$name] = $value;
	}

	/**
	 * Pushes a step onto the installer stack for rolling back steps
	 *
	 * @param   array  $step  Installer step
	 *
	 * @return  void
	 *
	 * @since   3.1
	 */
	public function pushStep($step)
	{
		$this->stepStack[] = $step;
	}

	/**
	 * Installation abort method
	 *
	 * @param   string  $msg   Abort message from the installer
	 * @param   string  $type  Package type if defined
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   3.1
	 * @throws  RuntimeException
	 */
	public function abort($msg = null, $type = null)
	{
		$retval = true;
		$step = array_pop($this->stepStack);

		// Raise abort warning
		if ($msg)
		{
			$this->log(LogLevel::WARNING, $msg);
		}

		while ($step != null)
		{
			switch ($step['type'])
			{
				case 'file':
					// Remove the file
					$stepval = File::delete($step['path']);
					break;

				case 'folder':
					// Remove the folder
					$stepval = File::delete($step['path']);
					break;

				default:
					$stepval = false;
					break;
			}

			// Only set the return value if it is false
			if ($stepval === false)
			{
				$retval = false;
			}

			// Get the next step and continue
			$step = array_pop($this->stepStack);
		}

		$debug = Factory::getConfig()->get('debug');

		if ($debug) {
			throw new RuntimeException('Installation unexpectedly terminated: ' . $msg, 500);
		}
		return $retval;
	}

	// Adapter functions
	/**
	 * Method to parse through a files element of the installation manifest and take appropriate
	 * action.
	 *
	 * @param   SimpleXMLElement  $element   The XML node to process
	 * @param   integer           $cid       Application ID of application to install to
	 * @param   array             $oldFiles  List of old files (SimpleXMLElement's)
	 * @param   array             $oldMD5    List of old MD5 sums (indexed by filename with value as MD5)
	 *
	 * @return  boolean      True on success
	 *
	 * @since   3.1
	 */
	public function parseFiles(SimpleXMLElement $element, $cid = 0, $oldFiles = null, $oldMD5 = null)
	{
		// Get the array of file nodes to process; we checked whether this had children above.
		if (!$element || !$element->children()->count())
		{
			// Either the tag does not exist or has no children (hence no files to process) therefore we return zero files processed.
			return 0;
		}

		$copyfiles = array();

		/*
		 * Here we set the folder we are going to remove the files from.
		 */
		$pathname = 'extension_root';
		$destination = $this->getPath($pathname);

		/*
		 * Here we set the folder we are going to copy the files from.
		 *
		 * Does the element have a folder attribute?
		 *
		 * If so this indicates that the files are in a subdirectory of the source
		 * folder and we should append the folder attribute to the source path when
		 * copying files.
		 */

		$folder = (string) $element->attributes()->folder;

		if ($folder && file_exists($this->getPath('source') . '/' . $folder))
		{
			$source = $this->getPath('source') . '/' . $folder;
		}
		else
		{
			$source = $this->getPath('source');
		}

		// Work out what files have been deleted
		if ($oldFiles && ($oldFiles instanceof SimpleXMLElement))
		{
			$oldEntries = $oldFiles->children();

			if ($oldEntries->count())
			{
				$deletions = $this->findDeletedFiles($oldEntries, $element->children());

				foreach ($deletions['folders'] as $deleted_folder)
				{
					Folder::delete($destination . '/' . $deleted_folder);
				}

				foreach ($deletions['files'] as $deleted_file)
				{
					File::delete($destination . '/' . $deleted_file);
				}
			}
		}

		$path = array();

		// Copy the MD5SUMS file if it exists
		if (file_exists($source . '/MD5SUMS'))
		{
			$path['src'] = $source . '/MD5SUMS';
			$path['dest'] = $destination . '/MD5SUMS';
			$path['type'] = 'file';
			$copyfiles[] = $path;
		}

		// Process each file in the $files array (children of $tagName).
		/**
		 * @ignore
		 * @var SimpleXMLElement $file
		 */
		foreach ($element->children() as $file)
		{
			$path['src'] = $source . '/' . $file;
			$path['dest'] = $destination . '/' . $file;

			// Is this path a file or folder?
			$path['type'] = ($file->getName() == 'folder') ? 'folder' : 'file';

			/*
			 * Before we can add a file to the copyfiles array we need to ensure
			 * that the folder we are copying our file to exits and if it doesn't,
			 * we need to create it.
			 */

			if (basename($path['dest']) != $path['dest'])
			{
				$newdir = dirname($path['dest']);

				if (!Folder::create($newdir))
				{
					$this->log(LogLevel::WARNING, Text::sprintf('JLIB_INSTALLER_ERROR_CREATE_DIRECTORY', $newdir));
					return false;
				}
			}

			// Add the file to the copyfiles array
			$copyfiles[] = $path;
		}

		return $this->copyFiles($copyfiles);
	}

	/**
	 * Copyfiles
	 *
	 * Copy files from source directory to the target directory
	 *
	 * @param   array    $files      Array with filenames
	 * @param   boolean  $overwrite  True if existing files can be replaced
	 *
	 * @return  boolean  True on success
	 *
	 * @since   3.1
	 */
	public function copyFiles($files, $overwrite = null)
	{
		/*
		 * To allow for manual override on the overwriting flag, we check to see if
		 * the $overwrite flag was set and is a boolean value.  If not, use the object
		 * allowOverwrite flag.
		 */

		if (is_null($overwrite) || !is_bool($overwrite))
		{
			$overwrite = $this->overwrite;
		}

		/*
		 * $files must be an array of filenames.  Verify that it is an array with
		 * at least one file to copy.
		 */
		if (is_array($files) && count($files) > 0)
		{

			foreach ($files as $file)
			{
				// Get the source and destination paths
				$filesource = Path::clean($file['src']);
				$filedest = Path::clean($file['dest']);
				$filetype = array_key_exists('type', $file) ? $file['type'] : 'file';

				if (!file_exists($filesource))
				{
					/*
					 * The source file does not exist.  Nothing to copy so set an error
					 * and return false.
					 */
					$this->log(LogLevel::WARNING, Text::sprintf('JLIB_INSTALLER_ERROR_NO_FILE', $filesource));

					return false;
				}
				elseif (($exists = file_exists($filedest)) && !$overwrite)
				{

					// It's okay if the manifest already exists
					if ($this->getPath('manifest') == $filesource)
					{
						continue;
					}

					// The destination file already exists and the overwrite flag is false.
					// Set an error and return false.
					$this->log(LogLevel::WARNING, Text::sprintf('JLIB_INSTALLER_ERROR_FILE_EXISTS', $filedest));

					return false;
				}
				else
				{
					// Copy the folder or file to the new location.
					if ($filetype == 'folder')
					{
						if (!(Folder::copy($filesource, $filedest, null, $overwrite)))
						{
							$this->log(LogLevel::WARNING, Text::sprintf('JLIB_INSTALLER_ERROR_FAIL_COPY_FOLDER', $filesource, $filedest));

							return false;
						}

						$step = array('type' => 'folder', 'path' => $filedest);
					}
					else
					{
						if (!(File::copy($filesource, $filedest, null)))
						{
							$this->log(LogLevel::WARNING, Text::sprintf('JLIB_INSTALLER_ERROR_FAIL_COPY_FILE', $filesource, $filedest));

							// In 3.2, TinyMCE language handling changed.  Display a special notice in case an older language pack is installed.
							if (strpos($filedest, 'media/editors/tinymce/jscripts/tiny_mce/langs'))
							{
								$this->log(LogLevel::WARNING, Text::_('JLIB_INSTALLER_NOT_ERROR'));
							}

							return false;
						}

						$step = array('type' => 'file', 'path' => $filedest);
					}

					/*
					 * Since we copied a file/folder, we want to add it to the installation step stack so that
					 * in case we have to roll back the installation we can remove the files copied.
					 */
					if (!$exists)
					{
						$this->stepStack[] = $step;
					}
				}
			}
		}
		else
		{
			// The $files variable was either not an array or an empty array
			return false;
		}

		return count($files);
	}

	/**
	 * Compares two "files" entries to find deleted files/folders
	 *
	 * @param   array[SimpleXMLElement]  $old_files  An array of SimpleXMLElement objects that are the old files
	 * @param   array[SimpleXMLElement]  $new_files  An array of SimpleXMLElement objects that are the new files
	 *
	 * @return  array  An array with the delete files and folders in findDeletedFiles[files] and findDeletedFiles[folders] respectively
	 *
	 * @since   3.1
	 */
	public function findDeletedFiles($old_files, $new_files)
	{
		// The magic find deleted files function!
		// The files that are new
		$files = array();

		// The folders that are new
		$folders = array();

		// The folders of the files that are new
		$containers = array();

		// A list of files to delete
		$files_deleted = array();

		// A list of folders to delete
		$folders_deleted = array();

		/**
		 * @ignore
		 * @var SimpleXMLElement $file
		 */
		foreach ($new_files as $file)
		{
			switch ($file->getName())
			{
				case 'folder':
					// Add any folders to the list
					$folders[] = (string) $file; // add any folders to the list
					break;

				case 'file':
				default:
					// Add any files to the list
					$files[] = (string) $file;

					// Now handle the folder part of the file to ensure we get any containers
					// Break up the parts of the directory
					$container_parts = explode('/', dirname((string) $file));

					// Make sure this is clean and empty
					$container = '';

					foreach ($container_parts as $part)
					{
						// Iterate through each part
						// Add a slash if its not empty
						if (!empty($container))
						{
							$container .= '/';
						}

						// Aappend the folder part
						$container .= $part;

						if (!in_array($container, $containers))
						{
							// Add the container if it doesn't already exist
							$containers[] = $container;
						}
					}
					break;
			}
		}

		foreach ($old_files as $file)
		{
			switch ($file->getName())
			{
				case 'folder':
					if (!in_array((string) $file, $folders))
					{
						// See whether the folder exists in the new list
						if (!in_array((string) $file, $containers))
						{
							// Check if the folder exists as a container in the new list
							// If it's not in the new list or a container then delete it
							$folders_deleted[] = (string) $file;
						}
					}
					break;

				case 'file':
				default:
					if (!in_array((string) $file, $files))
					{
						// Look if the file exists in the new list
						if (!in_array(dirname((string) $file), $folders))
						{
							// Look if the file is now potentially in a folder
							$files_deleted[] = (string) $file; // not in a folder, doesn't exist, wipe it out!
						}
					}
					break;
			}
		}

		return array('files' => $files_deleted, 'folders' => $folders_deleted);
	}
}
