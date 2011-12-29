<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Andreas Wolf <andreas.wolf@ikt-werk.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

include_once(t3lib_extMgm::extPath('fal_amazons3', 'Classes/amazon-sdk/sdk.class.php'));

/**
 * Driver for Amazon Simple Storage Service (S3).
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage t3lib
 */
// TODO implement basePath
class Tx_FalAmazonS3_Driver_AmazonS3Driver extends t3lib_file_Driver_AbstractDriver {

	/**
	 * The S3 bucket used.
	 *
	 * @var string
	 */
	protected $bucket;

	/**
	 * The region the bucket is located in
	 *
	 * @var string
	 */
	protected $region;

	/**
	 * The S3 API instance
	 *
	 * @var AmazonS3
	 */
	protected $s3ApiInstance;

	/**
	 * The base URL that points to this driver's storage. As long is this is not set, it is assumed that this folder
	 * is not publicly available
	 *
	 * @var string
	 */
	protected $baseUri;

	public function __construct(array $configuration = array()) {
		parent::__construct($configuration);
		$this->s3ApiInstance = new AmazonS3($this->configuration['key'], $this->configuration['secret-key']);
		$this->s3ApiInstance->authenticate($this->bucket);
	}

	public function injectAmazonApiInstance(AmazonS3 $apiInstance) {
		$this->s3ApiInstance = $apiInstance;
	}

	/**
	 * Checks if a configuration is valid for this storage.
	 *
	 * Throws an exception if a configuration will not work.
	 *
	 * @param array $configuration
	 * @return void
	 */
	public static function verifyConfiguration(array $configuration) {
		// TODO check if key and secret key are set
		// TODO check if bucket name conforms to Amazon rules, check if bucket exists
	}

	/**
	 * @return void
	 */
	protected function processConfiguration() {
		$this->bucket = $this->configuration['bucket'];
	}

	/**
	 * Initializes this object. This is called by the storage after the driver has been attached.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->baseUri = 'http://' . $this->bucket . '.s3.amazonaws.com/';
	}

	/**
	 * Returns the public URL to a file.
	 *
	 * @param t3lib_file_File $file
	 * @return string
	 */
	public function getPublicUrl(t3lib_file_File $file) {
		if (isset($this->baseUri)) {
			return $this->baseUri . ltrim($file->getIdentifier(), '/');
		} else {
			return $this->resourcePublisher->publishFile($file);
		}
	}

	/**
	 * Creates a (cryptographic) hash for a file.
	 *
	 * @param t3lib_file_File $file
	 * @param string $hashAlgorithm The hash algorithm to use
	 * @return string
	 */
	public function hash(t3lib_file_File $file, $hashAlgorithm) {
		// TODO get file metadata, throw exception if -sha1 entry is not set
	}

	/**
	 * Returns metadata of a file (size, times)
	 *
	 * @param t3lib_file_File $file
	 * @return array
	 */
	public function getLowLevelFileInfo(t3lib_file_File $file) {
		// TODO add ctime if set in metadata
		$filepath = ltrim($file->getIdentifier(), '/');
		$object = $this->s3ApiInstance->get_object_metadata($this->bucket, $filepath);

		$stat = array(
			'size' => (integer)$object['Size'],
			'atime' => 0,
			'mtime' => strtotime($object['lastModified']),
			'ctime' => 0,
			'nlink' => 1,
			'mimetype' => '', // TODO implement mimetype handling
		);
		return $stat;
	}

	/**
	 * Returns the root level folder of the storage.
	 *
	 * @return t3lib_file_Folder
	 */
	public function getRootLevelFolder() {
		if (!$this->rootLevelFolder) {
			/** @var $factory t3lib_file_Factory */
			$factory = t3lib_div::makeInstance('t3lib_file_Factory');
			$this->rootLevelFolder = $factory->createFolderObject($this->storage, '/', '');
		}

		return $this->rootLevelFolder;
	}

	/**
	 * Returns the default folder new files should be put into.
	 *
	 * @return t3lib_file_Folder
	 */
	public function getDefaultFolder() {
		// TODO: Implement getDefaultFolder() method.
	}

	/**
	 * Returns information about a file.
	 *
	 * @param string $identifier The (relative) path to the file.
	 * @return array
	 */
	public function getFileInfoByIdentifier($identifier) {
		// TODO handle errors
		$filePath = ltrim($identifier, '/');
		$object = $this->s3ApiInstance->get_object_metadata($this->bucket, $filePath);

		$fileName = basename($object['Key']);
		return array(
			'name' => $fileName,
			'identifier' => $identifier,
			'modificationDate' => strtotime($object['lastModified']),
			'size' => (integer)$object['Size'],
			'storage' => $this->storage->getUid()
		);
	}


	/**
	 * Returns a file by its identifier.
	 *
	 * @param $identifier
	 * @return t3lib_file_File
	 */
	public function getFile($identifier) {
		$fileInfo = $this->getFileInfoByIdentifier($identifier);
		$fileObject = $this->getFileObject($fileInfo);

		return $fileObject;
	}

	/**
	 * Returns a list of files inside the specified path
	 *
	 * @param string $path
	 * @param string $pattern
	 * @return array
	 */
	public function getFileList($path, $pattern = '') {
		// TODO handle errors
		// TODO implement caching
		$result = $this->s3ApiInstance->list_objects($this->bucket, array('prefix' => ltrim($path, '/'), 'delimiter' => '/'));

		$files = array();
		foreach($result->body->Contents as $object) {
				// skip directory entries
			if (substr($object->Key, -1) == '/') continue;

			$fileName = basename((string)$object->Key);
			$files[$fileName] = array(
				'name' => $fileName,
				'identifier' => $path . $fileName,
				'modificationDate' => strtotime($object->lastModified),
				'size' => (integer)$object->Size,
				'storage' => $this->storage->getUid()
			);
		}
		return $files;
	}

	/**
	 * Returns a list of all folders in a given path
	 *
	 * @param string $path
	 * @param string $pattern
	 * @return array
	 */
	public function getFolderList($path, $pattern = '') {
		// TODO handle errors
		// TODO implement caching
		$result = $this->s3ApiInstance->list_objects($this->bucket, array('prefix' => ltrim($path, '/'), 'delimiter' => '/'));

		$folders = array();
		foreach($result->body->CommonPrefixes as $object) {
			$dirname = basename(rtrim($object->Prefix, '/'));
			$folders[$dirname] = array(
				'name' => $dirname,
				'identifier' => $path . $dirname . '/',
				'creationDate' => 0,
				'storage' => $this->storage->getUid()
			);
		}

		return $folders;
	}

	/**
	 * Returns a folder by its identifier.
	 *
	 * @param $identifier
	 * @return t3lib_file_Folder
	 */
	public function getFolder($identifier) {
		// TODO: Implement getFolder() method.
	}

	/**
	 * Checks if a file exists.
	 *
	 * @param string $identifier
	 * @return bool
	 */
	public function fileExists($identifier) {
		// TODO: Implement fileExists() method.
		//$this->s3ApiInstance->get_ob
	}

	/**
	 * Checks if a folder exists
	 *
	 * @param $identifier
	 * @return bool
	 */
	public function folderExists($identifier) {
		// TODO: Implement folderExists() method.
	}

	/**
	 * Checks if a file inside a storage folder exists.
	 *
	 * @param string $fileName
	 * @param t3lib_file_Folder $folder
	 * @return bool
	 */
	public function fileExistsInFolder($fileName, t3lib_file_Folder $folder) {
		// TODO: Implement fileExistsInFolder() method.
	}

	/**
	 * Checks if a file inside a storage folder exists.
	 *
	 * @param string $fileName
	 * @param t3lib_file_Folder $folder
	 * @return bool
	 */
	public function folderExistsInFolder($fileName, t3lib_file_Folder $folder) {
		// TODO: Implement folderExistsInFolder() method.
	}

	/**
	 * Adds a file from the local server hard disk to a given path in TYPO3s virtual file system.
	 *
	 * This assumes that the local file exists, so no further check is done here!
	 *
	 * @param string $localFilePath
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $fileName The fileName. If this is not set, the local fileName is used
	 * @return t3lib_file_File
	 */
	public function addFile($localFilePath, t3lib_file_Folder $targetFolder = NULL, $fileName = NULL) {
		// TODO: Implement addFile() method.
		// TODO get SHA-1 hash, creation date, set these in file metadata when uploading
		$metadata = array(
			'sha1' => sha1_file($localFilePath),
			'ctime' => filectime($localFilePath)
		);
	}


	/**
	 * Adds a file at the specified location. This should only be used internally.
	 *
	 * @abstract
	 * @param string $localFilePath
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $targetFileName
	 * @return bool TRUE if adding the file succeeded
	 */
	public function addFileRaw($localFilePath, t3lib_file_Folder $targetFolder, $targetFileName = NULL) {
		// TODO: implement
	}

	/**
	 * Moves a file *within* the current storage.
	 * Note that this is only about an intra-storage move action, where a file is just
	 * moved to another folder in the same storage.
	 *
	 * @param t3lib_file_File $file
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $fileName
	 * @return bool
	 */
	public function moveFileWithinStorage(t3lib_file_File $file, t3lib_file_Folder $targetFolder, $fileName = NULL) {
		// TODO: implement
	}

	/**
	 * Copies a file *within* the current storage.
	 * Note that this is only about an intra-storage move action, where a file is just
	 * moved to another folder in the same storage.
	 *
	 * @param t3lib_file_File $file
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $fileName
	 * @return t3lib_file_File The new (copied) file object.
	 */
	public function copyFileWithinStorage(t3lib_file_File $file, t3lib_file_Folder $targetFolder, $fileName = NULL) {
		// TODO: implement
	}

	/**
	 * Deletes a file without access and usage checks. This should only be used internally.
	 *
	 * @abstract
	 * @param string $identifier
	 * @return bool TRUE if removing the file succeeded
	 */
	public function deleteFileRaw($identifier) {
		// TODO: implement
	}


	/**
	 * Replaces the contents (and file-specific metadata) of a file object with a local file.
	 *
	 * @param t3lib_file_File $file
	 * @param string $localFilePath
	 * @return void
	 */
	public function replaceFile(t3lib_file_File $file, $localFilePath) {
		// TODO: Implement replaceFile() method.
	}

	/**
	 * Copies a file to a temporary path and returns that path.
	 *
	 * @param t3lib_file_File $file
	 * @return string The temporary path
	 */
	public function copyFileToTemporaryPath(t3lib_file_File $file) {
		// TODO: Implement copyFileToTemporaryPath() method.
	}

	/**
	 * Creates a folder.
	 *
	 * @param string $newFolderName
	 * @param t3lib_file_Folder $parentFolder
	 * @return t3lib_file_Folder The new (created) folder object
	 */
	public function createFolder($newFolderName, t3lib_file_Folder $parentFolder) {
		// TODO: Implement createFolder() method.
	}

	/**
	 * Removes a file from this storage.
	 *
	 * @param t3lib_file_File $file
	 * @return void
	 */
	public function deleteFile(t3lib_file_File $file) {
		// TODO: Implement deleteFile() method.
	}

	/**
	 * Returns a (local copy of) a file for processing it. When changing the file, you have to take care of replacing the
	 * current version yourself!
	 *
	 * @param t3lib_file_File $file
	 * @param bool $writable Set this to FALSE if you only need the file for read operations. This might speed up things, e.g. by using a cached local version. Never modify the file if you have set this flag!
	 * @return string The path to the file on the local disk
	 */
	public function getFileForLocalProcessing(t3lib_file_File $file, $writable = TRUE) {
		// TODO: Implement getFileForLocalProcessing() method.
	}

	/**
	 * Returns the permissions of a file as an array (keys r, w) of boolean flags
	 *
	 * @param t3lib_file_File $file
	 * @return array
	 */
	public function getFilePermissions(t3lib_file_File $file) {
		// TODO: Implement getFilePermissions() method.
	}

	/**
	 * Returns the permissions of a folder as an array (keys r, w) of boolean flags
	 *
	 * @param $folder
	 * @return array
	 */
	public function getFolderPermissions(t3lib_file_Folder $folder) {
		// TODO: Implement getFolderPermissions() method.
	}

	/**
	 * Creates a new file and returns the matching file object for it.
	 *
	 * @abstract
	 * @param t3lib_file_Folder $folder
	 * @param string $fileName
	 * @return t3lib_file_File
	 */
	public function createFile(t3lib_file_Folder $folder, $fileName) {
		// TODO: Implement createFile() method.
	}

	/**
	 * Returns the contents of a file. Beware that this requires to load the complete file into memory and also may
	 * require fetching the file from an external location. So this might be an expensive operation (both in terms of
	 * processing resources and money) for large files.
	 *
	 * @param t3lib_file_File $file
	 * @return string The file contents
	 */
	public function getFileContents(t3lib_file_File $file) {
		// TODO: Implement getFileContents() method.
	}

	/**
	 * Sets the contents of a file to the specified value.
	 *
	 * @param t3lib_file_File $file
	 * @param string $contents
	 * @return t3lib_file_File
	 */
	public function setFileContents(t3lib_file_File $file, $contents) {
		// TODO: Implement setFileContents() method.
	}

	/**
	 * Renames a file
	 *
	 * @param t3lib_file_File $file
	 * @param string $newName
	 * @return bool
	 */
	public function renameFile(t3lib_file_File $file, $newName) {
		// TODO: Implement renameFile() method.
	}

	/**
	 * Folder equivalent to moveFileWithinStorage().
	 *
	 * @param t3lib_file_Folder $folderToMove
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $newFolderName
	 * @return bool
	 */
	public function moveFolderWithinStorage(t3lib_file_Folder $folderToMove, t3lib_file_Folder $targetFolder,
		$newFolderName = NULL) {
		// TODO: Implement moveFolderWithinStorage() method.
	}

	/**
	 * Folder equivalent to copyFileWithinStorage().
	 *
	 * @param t3lib_file_Folder $folderToMove
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $newFileName
	 * @return bool
	 */
	public function copyFolderWithinStorage(t3lib_file_Folder $folderToMove, t3lib_file_Folder $targetFolder,
		$newFileName = NULL) {
		// TODO: Implement copyFolderWithinStorage() method.
	}

	/**
	 * Checks if a given identifier is within a container, e.g. if a file or folder is within another folder.
	 * This can be used to check for webmounts.
	 *
	 * @param t3lib_file_Folder $container
	 * @param string $content
	 * @return void
	 */
	public function isWithin(t3lib_file_Folder $container, $content) {
		// TODO: Implement isWithin() method.
	}
}
