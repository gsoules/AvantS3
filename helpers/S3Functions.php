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

        $saveFilePathName = getS3StagingFolderPath() . '/' . $fileName;

        $s3Client->getObject(array(
            'Bucket' => $bucketName,
            'Key'    => $filePathName,
            'SaveAs' => $saveFilePathName
        ));
    }
}

function canAccessS3StagingFolder()
{
    $filesDir = getS3StagingFolderPath();
    return is_readable($filesDir) && is_writable($filesDir);
}

function showS3FilesForItem($item)
{
    testS3($item);
    echo common('s3-files-list', array(), 'index');
}

function getS3StagingFolderFileNames($directory)
{
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

function getS3StagingFolderPath()
{
    return AVANTS3_DIR . DIRECTORY_SEPARATOR . 'files';
}

function validateS3FileName($fileName)
{
    $s3StagingFolder = getS3StagingFolderPath();
    $filePath = $s3StagingFolder .DIRECTORY_SEPARATOR . $fileName;
    $realFilePath = realpath($filePath);

    // Ensure the path is actually within the staging folder.
    if (!$realFilePath
        || strpos($realFilePath, $s3StagingFolder . DIRECTORY_SEPARATOR) !== 0) {
        throw new Exception(__('The given path is invalid.'));
    }
    if (!file_exists($realFilePath)) {
        throw new Exception(__('The file "%s" does not exist or is not readable.', $fileName));
    }
    if (!is_readable($realFilePath)) {
        throw new Exception(__('The file "%s" is not readable.', $fileName));
    }
    return $realFilePath;
}
