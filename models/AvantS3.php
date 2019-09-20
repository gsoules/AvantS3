<?php

// Use the AWS/S3 files from the AvantElasticsearch plugin to avoid having all the files in two places.
require __DIR__ . '../../../AvantElasticsearch/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class AvantS3
{
    const S3_NEW = 1;
    const S3_EXISTING = 2;
    const S3_FOLDER = 3;
    const S3_INELIGIBLE = 4;
    const S3_ERROR = 5;
    const MAX_LONG_EDGE = 1200;

    protected $fileNameList = array();
    protected $fileNameOrder = array();
    protected $filePathList = array();
    protected $item;
    protected $stagingFoldePath;
    protected $s3Client;

    public function __construct($item)
    {
        $this->item = $item;
        $this->stagingFoldePath = $this->getStagingFolderPath();

        $key = S3Config::getOptionValueForKey();
        $secret = S3Config::getOptionValueForSecret();
        $credentials = new Aws\Credentials\Credentials($key, $secret);

        $this->s3Client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => S3Config::getOptionValueForRegion(),
            'credentials' => $credentials
        ]);
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

            $key = "Database/$parentFolderName/$fileName";

            $this->s3Client->getObject(array(
                'Bucket' => $bucketName,
                'Key'    => $key,
                'SaveAs' => $saveFilePathName
            ));
        }

        foreach ($s3FileNames as $fileName)
        {
            $filePath = $this->getAbsoluteFilePathName($fileName);
            $this->filePathList[] = $filePath;
            $this->fileNameList[] = $fileName;
        }

        $downSized = $this->downSizeLargeImages($s3FileNames);
        return $downSized;
    }

    protected function downSizeLargeImages($s3FileNames)
    {
        foreach ($s3FileNames as $fileName)
        {
            $filePath = $this->getAbsoluteFilePathName($fileName);
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $imageExt = array('jpg', 'jpeg');

            if (in_array($ext, $imageExt))
            {
                $resizedFilePath = $filePath . '_';

                $resized = $this->resizeImage($filePath, $resizedFilePath, self::MAX_LONG_EDGE);

                if ($resized)
                {
                    unlink($filePath);
                    rename($resizedFilePath, $filePath);
                }
                else
                    return false;
            }
        }
        return true;
    }

    protected function fileCanBeResized($sourceImage)
    {
        $imageInfo = getimagesize($sourceImage);

        // Determine if there is enough memory to resize the image. If not, this method returns false which results
        // in an exception being thrown later. The exception is better than running out of memory which manifests
        // itself as an all-white browser page. If that happens, fine-tune this logic to better guess available memory.
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $bitDepth = $imageInfo['bits'] * $imageInfo['channels'];
        $bits = $width * $height * $bitDepth;
        $bytes = $bits / 8;
        $mbBytes = round($bytes / (1024 * 1024));
        $mbLimit = 256;
        $isEnoughMemory = ($mbLimit - $mbBytes) > 0;
        return $isEnoughMemory;
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
        return S3Config::getOptionValueForBucket();
    }

    public function getS3FileNamesForItem()
    {
        $parentFolderName = $this->getItemParentFolderName($this->item);

        $bucketName = $this->getS3BucketName();
        $prefix = "Database/$parentFolderName";

        $objects = $this->s3Client->getIterator('ListObjects', array(
            "Bucket" => $bucketName,
            "Prefix" => $prefix //must have the trailing forward slash "/"
        ));

        $filesAttachedToItem = $this->item->getFiles();

        $fileNames = array();

        try
        {
            foreach ($objects as $object)
            {
                $filePathName = $object['Key'];
                $fileName = substr($filePathName, strlen($prefix) + 1);
                if (empty($fileName))
                    continue;

                $fileNames[$fileName] = $this->getS3FileAction($filesAttachedToItem, $fileName);
            }

            asort($fileNames);
        }
        catch (Aws\S3\Exception\S3Exception $e)
        {
            $fileNames['Unable to access AWS S3 Server'] = self::S3_ERROR;
        }

        return $fileNames;
    }

    protected function getS3FileAction($filesAttachedToItem, $fileName)
    {
        $action = self::S3_NEW;

        foreach ($filesAttachedToItem as $file)
        {
            if ($fileName == $file->original_filename)
            {
                $action = self::S3_EXISTING;
                break;
            }
        }

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $validExt = array('jpg', 'jpeg', 'pdf', 'txt');
        if (!in_array($ext, $validExt))
        {
            $action = self::S3_INELIGIBLE;
        }

        if (strpos($fileName, '/') !== false)
        {
            $action = self::S3_FOLDER;
        }

        return $action;
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

    function resizeImage($sourceImage, $targetImage, $maxEdgeLength, $quality = 80)
    {
       // Derived from: https://gist.github.com/janzikan/2994977

        if ($this->fileCanBeResized($sourceImage))
        {
            $image = @imagecreatefromjpeg($sourceImage);
        }
        else
        {
            // When there is not enough memory to resize the image, this method returns  false which results in an
            // exception being thrown later. The exception is better than running out of memory which manifests itself
            // as an all-white browser page. If that happens, fine-tune the logic to better guess available memory.
            return false;
        }

        if (!$image)
        {
            return false;
        }

        // Get dimensions of source image.
        list($origWidth, $origHeight) = getimagesize($sourceImage);
        // Calculate ratio of desired maximum sizes and original sizes.
        $widthRatio = $maxEdgeLength / $origWidth;
        $heightRatio = $maxEdgeLength / $origHeight;

        // Ratio used for calculating new image dimensions.
        $ratio = min($widthRatio, $heightRatio);

        if ($ratio < 1.0)
        {
            // The image needs to be downsized. Calculate new image dimensions.
            $newWidth  = (int)$origWidth  * $ratio;
            $newHeight = (int)$origHeight * $ratio;

            // Create final image with new dimensions.
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            imagejpeg($newImage, $targetImage, $quality);

            // Free up the memory.
            imagedestroy($newImage);
            imagedestroy($image);

            return true;
        }
        else
        {
            // The image is already small enough. Free the memory.
            imagedestroy($image);

            // Rename the source to the target as though the resize operation had occurred successfully.
            rename($sourceImage, $targetImage);
            return true;
        }
    }
}