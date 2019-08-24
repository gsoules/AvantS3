<?php

require __DIR__ . '../../../AvantElasticsearch/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class AvantS3
{
    const S3_NEW = 1;
    const S3_EXISTING = 2;
    const S3_INELIGIBLE = 3;

    protected $fileNameList = array();
    protected $fileNameOrder = array();
    protected $filePathList = array();
    protected $item;
    protected $stagingFoldePath;

    public function __construct($item)
    {
        $this->item = $item;
        $this->stagingFoldePath = $this->getStagingFolderPath();
    }

    public function attachS3FilesToItem()
    {
        $files = array();

        try
        {
            $files = insert_files_for_item($this->item, 'Filesystem', $this->filePathList, array('file_ingest_options' => array('ignore_invalid_files' => false)));
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
        foreach ($this->filePathList as $filePath)
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

        $this->preserveOriginalFileOrder();
    }

    protected function canAccessStagingFolder()
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
        // Get all the files attached to this item.
        $filesAttachedToItem = $this->item->getFiles();

        // Detach any file that is to be replaced by an S3 file having the same name.
        foreach ($filesAttachedToItem as $file)
        {
            $originalFileName = $file->original_filename;

            // Remember this file's order so it can be used as the order for the replacement file from S3.
            $this->fileNameOrder[$originalFileName] = $file->order;

            $s3FileReplacesAttachedFile = in_array($originalFileName, $this->fileNameList);
            if ($s3FileReplacesAttachedFile)
            {
                $file->delete();
            }
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
            $this->filePathList[] = $this->getAbsoluteFilePathName($fileName);
            $this->fileNameList[] = $fileName;
        }
    }

    protected function getAbsoluteFilePathName($fileName)
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

    protected function getFileOrder($fileName)
    {
        foreach ($this->fileNameOrder as $name => $order)
        {
            if ($fileName == $name)
                return $order;
        }

        return null;
    }

    protected function getItemParentFolderName($item)
    {
        $identifier = ItemMetadata::getItemIdentifier($item);
        $id = intval($identifier);
        $parentFolderName = $id - ($id % 1000);
        return "$parentFolderName/$identifier";
    }

    protected function getS3BucketName()
    {
        // TO-DO Get from plugin configuration
        return 'swhpl-digital-archive';
    }

    public function getS3FileNamesForItem()
    {
        $parentFolderName = $this->getItemParentFolderName($this->item);

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

        $filesAttachedToItem = $this->item->getFiles();

        $fileNames = array();

        foreach ($objects as $object)
        {
            $filePathName = $object['Key'];
            $fileName = substr($filePathName, strlen($prefix) + 1);
            if (empty($fileName))
                continue;

            $fileNames[$fileName] = $this->getS3FileStatus($filesAttachedToItem, $fileName);
        }

        return $fileNames;
    }

    protected function getS3FileStatus($filesAttachedToItem, $fileName)
    {
        $status = self::S3_NEW;

        foreach ($filesAttachedToItem as $file)
        {
            if ($fileName == $file->original_filename)
            {
                $status = self::S3_EXISTING;
                break;
            }
        }

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $validExt = array('jpg', 'jpeg', 'pdf', 'txt');
        if (!in_array($ext, $validExt))
        {
            $status = self::S3_INELIGIBLE;
        }

        return $status;
    }

    protected function getStagingFolderPath()
    {
        // Return a unique folder for this user to avoid collisions when multiple users are logged in.
        $userId = current_user()->id;
        return FILES_DIR . DIRECTORY_SEPARATOR . 's3/user-' . $userId;
    }

    protected function preserveOriginalFileOrder()
    {
        // Restores the original order of files that were replaced by newer S3 files. Without
        // this, the replacements end up at the end of the file list as though added as new files.

        $filesAttachedToItem = $this->item->getFiles();

        foreach ($filesAttachedToItem as $file)
        {
            $originalFileName = $file->original_filename;
            $s3FileReplacedAttachedFile = in_array($originalFileName, $this->fileNameList);
            if ($s3FileReplacedAttachedFile)
            {
                $order = $this->getFileOrder($originalFileName);
                $file->order = $order;
                $file->save();
            }
        }
    }
}