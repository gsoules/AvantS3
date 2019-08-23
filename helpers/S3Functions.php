<?php

require __DIR__ . '../../../AvantElasticsearch/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

function canAccessS3StagingFolder()
{
    $path = getS3StagingFolderPath();

    if (!file_exists($path))
    {
        mkdir($path, 0777, true);
    }

    return is_readable($path) && is_writable($path);
}

function emitS3FileList($item)
{
    echo common('s3-files-list', array('item' => $item), 'index');
}

function getS3BucketName()
{
    return 'swhpl-digital-archive';
}

function getS3ParentFolderName($item)
{
    $identifier = ItemMetadata::getItemIdentifier($item);
    $id = intval($identifier);
    $parentFolderName = $id - ($id % 1000);
    return "$parentFolderName/$identifier";
}

function getS3FileNamesForItem($item)
{
    $parentFolderName = getS3ParentFolderName($item);

    $s3Client = new Aws\S3\S3Client([
        'profile' => 'default',
        'version' => 'latest',
        'region' => 'us-east-2'
    ]);

    $bucketName = getS3BucketName();
    $prefix = "Database/$parentFolderName";

    $objects = $s3Client->getIterator('ListObjects', array(
        "Bucket" => $bucketName,
        "Prefix" => $prefix //must have the trailing forward slash "/"
    ));

    $fileNames = array();

    foreach ($objects as $object)
    {
        $filePathName = $object['Key'];
        $fileName = substr($filePathName, strlen($prefix) + 1);
        if (empty($fileName))
            continue;

        $fileNames[] = $fileName;
    }

    return $fileNames;
}

function downloadS3FilesToStagingFolder($item, $s3FileNames)
{
    $bucketName = getS3BucketName();


    foreach ($s3FileNames as $fileName)
    {
        $parentFolderName = getS3ParentFolderName($item);

        $itemFolder = getS3StagingFolderPath();

        $saveFilePathName = $itemFolder . '/' . $fileName;

        $s3Client = new Aws\S3\S3Client([
            'profile' => 'default',
            'version' => 'latest',
            'region' => 'us-east-2'
        ]);

        $key = "Database/$parentFolderName/$fileName";

        $s3Client->getObject(array(
            'Bucket' => $bucketName,
            'Key'    => $key,
            'SaveAs' => $saveFilePathName
        ));
    }
}

function getS3StagingFolderPath()
{
    // Return a unique staging area folder for the logged-in user.
    $userId = current_user()->id;
    return AVANTS3_DIR . DIRECTORY_SEPARATOR . 'files/' . $userId;
}

function getAbsoluteFilePathName($fileName)
{
    $s3StagingFolder = getS3StagingFolderPath();
    $filePath = $s3StagingFolder .DIRECTORY_SEPARATOR . $fileName;
    $realFilePath = realpath($filePath);

    if (!file_exists($realFilePath))
    {
        throw new Exception(__('The file "%s" does not exist or is not readable.', $fileName));
    }

    if (!is_readable($realFilePath))
    {
        throw new Exception(__('The file "%s" is not readable.', $fileName));
    }

    return $realFilePath;
}
