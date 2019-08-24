<?php

require __DIR__ . '../../../AvantElasticsearch/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class AvantS3
{
    protected $fileList = array();
    protected $item;
    protected $stagingFoldePath;

    public function __construct($item = null)
    {
        $this->item = $item;
        $this->stagingFoldePath = $this->getStagingFolderPath();
    }

    public function attachS3FilesToItem()
    {
        $files = array();

        try
        {
            $files = insert_files_for_item($this->item, 'Filesystem', $this->fileList, array('file_ingest_options' => array('ignore_invalid_files' => false)));
        }
        catch (Omeka_File_Ingest_InvalidException $e)
        {
            release_object($files);
            $this->item->addError('AvantS3', $e->getMessage());
            return;
        }
        catch (Exception $e)
        {
            release_object($files);
            throw $e;
        }
        release_object($files);

        // Delete the files from the staging folder.
        foreach ($this->filePaths as $filePath)
        {
            try
            {
                unlink($filePath);
            }
            catch (Exception $e)
            {
                throw $e;
            }
        }
    }

    public function canAccessStagingFolder()
    {
        $path = $this->stagingFoldePath;

        if (!file_exists($path))
        {
            mkdir($path, 0777, true);
        }

        return is_readable($path) && is_writable($path);
    }

    public function deleteExistingFilesAttachedToItem()
    {
        $fileIds = array();

        $files = $this->item->getFiles();
        foreach ($files as $file)
        {
            $fileIds[] = $file->id;
        }

        $filesToDelete = $this->item->getTable('File')->findByItem($this->item->id, $fileIds, 'id');

        foreach ($filesToDelete as $fileRecord)
        {
            $fileRecord->delete();
        }
    }

    public function downloadS3FilesToStagingFolder($s3FileNames)
    {
        if (!$this->canAccessStagingFolder())
        {
            throw new Exception(__('The AvantS3 staging folder "%s" does have the correct permissions.', $this->stagingFoldePath));
        }

        $bucketName = $this->getS3BucketName();

        foreach ($s3FileNames as $fileName)
        {
            $parentFolderName = $this->getItemParentFolderName($this->item);

            $saveFilePathName = $this->stagingFoldePath . '/' . $fileName;

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

        foreach ($s3FileNames as $fileName)
        {
            $this->fileList[] = $this->getAbsoluteFilePathName($fileName);
        }
    }

    public function getAbsoluteFilePathName($fileName)
    {
        $filePath = $this->stagingFoldePath . DIRECTORY_SEPARATOR . $fileName;
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

    public function getItemParentFolderName($item)
    {
        $identifier = ItemMetadata::getItemIdentifier($item);
        $id = intval($identifier);
        $parentFolderName = $id - ($id % 1000);
        return "$parentFolderName/$identifier";
    }

    public function getS3BucketName()
    {
        // TO-DO Get from plugin configuration
        return 'swhpl-digital-archive';
    }

    public function getS3FileNamesForItem($item)
    {
        $parentFolderName = $this->getItemParentFolderName($item);

        $s3Client = new Aws\S3\S3Client([
            'profile' => 'default',
            'version' => 'latest',
            'region' => 'us-east-2'
        ]);

        $bucketName = $this->getS3BucketName();
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

        // TO-DO Filter out ineligible files i.e. leave only PDF, JPG, TXT, and audio

        return $fileNames;
    }

    public function getStagingFolderPath()
    {
        // Return a unique folder for this user to avoid collisions when multiple users are logged in.
        $userId = current_user()->id;
        return FILES_DIR . DIRECTORY_SEPARATOR . 's3/user-' . $userId;
    }
}