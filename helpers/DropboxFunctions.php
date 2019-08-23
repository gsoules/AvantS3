<?php

require __DIR__ . '../../../AvantElasticsearch/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

function testS3($item)
{
    $identifier = ItemMetadata::getItemIdentifier($item);
    $id = intval($identifier);
    $parentFolder = $id - ($id % 1000);

    $s3Client = new Aws\S3\S3Client([
        'profile' => 'default',
        'version' => 'latest',
        'region' => 'us-east-2'
    ]);

    $bucketName = 'swhpl-digital-archive';
    $prefix = "Database/$parentFolder/$identifier";

    $objects = $s3Client->getIterator('ListObjects', array(
        "Bucket" => $bucketName,
        "Prefix" => $prefix //must have the trailing forward slash "/"
    ));

    foreach ($objects as $object)
    {
        $filePathName = $object['Key'];
        $fileName = substr($filePathName, strlen($prefix) + 1);
        if (empty($fileName))
            continue;
        echo "$fileName<br>";

        $saveFilePathName = dropbox_get_files_dir_path() . '/' . $fileName;

        $s3Client->getObject(array(
            'Bucket' => $bucketName,
            'Key'    => $filePathName,
            'SaveAs' => $saveFilePathName
        ));
    }
}

/**
 * Print the list of files in the Dropbox.
 */
function dropbox_list($item)
{
    testS3($item);
    echo common('dropboxlist', array(), 'index');
}
/**
 * Get the absolute path to the Dropbox "files" directory.
 *
 * @return string
 */
function dropbox_get_files_dir_path()
{
    return AVANTS3_DIR . DIRECTORY_SEPARATOR . 'files';
}

/**
 * Check if the necessary permissions are set for the files directory.
 *
 * The directory must be both writable and readable.
 *
 * @return boolean
 */
function dropbox_can_access_files_dir()
{
    $filesDir = dropbox_get_files_dir_path();
    return is_readable($filesDir) && is_writable($filesDir);
}

/**
 * Get a list of files in the given directory.
 *
 * The files are returned in natural-sorted, case-insensitive order.
 *
 * @param string $directory Path to directory.
 * @return array Array of filenames in the directory.
 */
function dropbox_dir_list($directory)
{
    // create an array to hold directory list
    $filenames = array();

    $iter = new DirectoryIterator($directory);

    foreach ($iter as $fileEntry) {
        if ($fileEntry->isFile()) {
            $filenames[] = $fileEntry->getFilename();
        }
    }

    natcasesort($filenames);

    return $filenames;
}

/**
 * Check if the given file can be uploaded from the dropbox.
 *
 * @throws Dropbox_Exception
 * @return string Validated path to the file
 */
function dropbox_validate_file($fileName)
{
    $dropboxDir = dropbox_get_files_dir_path();
    $filePath = $dropboxDir .DIRECTORY_SEPARATOR . $fileName;
    $realFilePath = realpath($filePath);
    // Ensure the path is actually within the dropbox files dir.
    if (!$realFilePath
        || strpos($realFilePath, $dropboxDir . DIRECTORY_SEPARATOR) !== 0) {
        throw new Dropbox_Exception(__('The given path is invalid.'));
    }
    if (!file_exists($realFilePath)) {
        throw new Dropbox_Exception(__('The file "%s" does not exist or is not readable.', $fileName));
    }
    if (!is_readable($realFilePath)) {
        throw new Dropbox_Exception(__('The file "%s" is not readable.', $fileName));
    }
    return $realFilePath;
}
