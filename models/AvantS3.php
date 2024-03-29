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

    protected function changeTifExtToJpg($path)
    {
        $path = str_ireplace('.tiff', '.jpg', $path);
        $path = str_ireplace('.tif', '.jpg', $path);
        return $path;
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

    public function downloadS3FilesToStagingFolder($s3KeyNames, $folderName)
    {
        if (!$this->canAccessStagingFolder())
        {
            throw new Exception(__('The AvantS3 staging folder "%s" does have the correct permissions.', $this->stagingFoldePath));
        }

        $bucketName = $this->getS3BucketName();

        $s3Names = array();
        foreach ($s3KeyNames as $s3KeyName)
        {
            $s3Names[] = new S3Name($s3KeyName, 0);
        }

        foreach ($s3Names as $s3Name)
        {
            $stagingFolderFileName = $s3Name->fileName;

            $saveFilePathName = $this->stagingFoldePath . '/' . $stagingFolderFileName;
            $key = "$folderName/$s3Name->keyName";

            $this->s3Client->getObject(array(
                'Bucket' => $bucketName,
                'Key'    => $key,
                'SaveAs' => $saveFilePathName
            ));
        }

        // Look over the Windows file names to create a list of absolute file paths.
        // Change the extension for any paths containing files that were converted from tif to jpg.
        foreach ($s3Names as $s3Name)
        {
            $filePath = $this->getAbsoluteFilePathName($s3Name->fileName);
            $filePath = $this->changeTifExtToJpg($filePath);
            $this->filePathList[] = $filePath;
            $this->fileNameList[] = $s3Name->fileName;
        }

        $downSized = $this->downSizeLargeImages($this->fileNameList);

        if ($downSized)
        {
            // Change the extension for any files that were converted from tif to jpg.
            foreach ($this->fileNameList as $index => $fileName)
            {
                $fileName = $this->changeTifExtToJpg($fileName);
                $this->fileNameList[$index] = $fileName;
            }
        }

        return $downSized;
    }

    protected function downSizeLargeImages($s3Names)
    {
        foreach ($s3Names as $s3Name)
        {
            $filePath = $this->getAbsoluteFilePathName($s3Name);
            $ext = strtolower(pathinfo($s3Name, PATHINFO_EXTENSION));
            $imageExt = array('jpg', 'jpeg', 'tif', 'tiff');

            if (in_array($ext, $imageExt))
            {
                $isTif = $ext == 'tif' || $ext == 'tiff';

                if ($isTif)
                {
                    $resizedFilePath = $filePath . '.jpg';
                    $resized = $this->resizeImageTif($filePath, $resizedFilePath, self::MAX_LONG_EDGE);
                }
                else
                {
                    $resizedFilePath = $filePath . '_';
                    $resized = $this->resizeImageJpg($filePath, $resizedFilePath, self::MAX_LONG_EDGE);
                }

                if ($resized)
                {
                    // Delete the original file.
                    unlink($filePath);

                    // Change the extension for any files that were converted from tif to jpg.
                    $filePath = $this->changeTifExtToJpg($filePath);

                    // Rename the resized file to have the original file's name.
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

    public function getItemParentFolderName($item)
    {
        $identifier = ItemMetadata::getItemIdentifier($item);
        $id = intval($identifier);
        $parentFolderName = $id - ($id % 1000);
        return "$parentFolderName/$identifier";
    }

    public function getRatio($maxEdgeLength, int $origWidth, int $origHeight): mixed
    {
        $widthRatio = $maxEdgeLength / $origWidth;
        $heightRatio = $maxEdgeLength / $origHeight;

        // Ratio used for calculating new image dimensions.
        $ratio = min($widthRatio, $heightRatio);
        return $ratio;
    }

    protected function getS3BucketName()
    {
        return S3Config::getOptionValueForBucket();
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
        $validExt = array('jpg', 'jpeg', 'tif', 'tiff', 'pdf', 'txt', 'mp3');
        if (!in_array($ext, $validExt))
        {
            $action = self::S3_INELIGIBLE;
        }

        return $action;
    }

    protected function getS3Names(Iterator $objects, $prefixLen): array
    {
        $filesAttachedToItem = $this->item->getFiles();

        $s3Names = array();

        try
        {
            foreach ($objects as $object)
            {
                $filePathName = $object['Key'];
                $fileName = substr($filePathName, $prefixLen);
                if (empty($fileName))
                    continue;
                $s3Names[] = new S3Name($fileName, $this->getS3FileAction($filesAttachedToItem, $fileName));
            }

            usort($s3Names, function(S3Name $a, S3Name $b)
            {
                // Prefix the action number to the file name so that files will sort first by action, then by name.
                $left = "$a->action $a->fileName";
                $right = "$b->action $b->fileName";
                $result = strtolower($left) <=> strtolower($right);
                return $result;
            });
        }
        catch (Aws\S3\Exception\S3Exception $e)
        {
            $s3Names[] = new S3Name('Unable to access AWS S3 Server', self::S3_ERROR);
        }

        return $s3Names;
    }

    public function getS3NamesForAccession()
    {
        $bucketName = $this->getS3BucketName();

        $accessionNumberElementId = S3Config::getElementIdForAccessionElement();
        $accessionNumber = ItemMetadata::getElementTextFromElementId($this->item, $accessionNumberElementId);
        if ($accessionNumber == "")
            return [];

        // Handle the case when the accession is a sub_accession e.g. 2026_02 which will be in S3 Accessions/2026/2026_02".
        $parts = explode('_', $accessionNumber);
        if (count($parts) == 2)
            $accessionNumber = $parts[0] . "/" . $accessionNumber;

        $prefix = "Accessions/$accessionNumber/";
        $prefixLen = strlen($prefix);

        $objects = $this->s3Client->getIterator('ListObjects', array(
            "Bucket" => $bucketName,
            "Prefix" => $prefix
        ));

        return $this->getS3Names($objects, $prefixLen);
    }

    public function getS3NamesForItem()
    {
        $parentFolderName = $this->getItemParentFolderName($this->item);

        $bucketName = $this->getS3BucketName();
        $prefix = "Database/$parentFolderName";

        // Add 1 to the prefix len to account for the item's grouping folder '/' e.g. Database/16000/16630/filename.jpg
        $prefixLen = strlen($prefix) + 1;

        $objects = $this->s3Client->getIterator('ListObjects', array(
            "Bucket" => $bucketName,
            "Prefix" => $prefix
        ));

        return $this->getS3Names($objects, $prefixLen);
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

    function resizeImageJpg($sourceImage, $targetImage, $maxEdgeLength, $quality = 80)
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
        $ratio = $this->getRatio($maxEdgeLength, $origWidth, $origHeight);

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
        }
        else
        {
            // The image is already small enough. Free the memory.
            imagedestroy($image);

            // Rename the source to the target as though the resize operation had occurred successfully.
            rename($sourceImage, $targetImage);
        }

        return true;
    }

    function resizeImageTif($sourceImage, $targetImage, $maxEdgeLength, $quality = 80)
    {
        try
        {
            $imagick = new Imagick($sourceImage);

            // Get dimensions of source image.
            $origWidth = $imagick->getImageWidth();
            $origHeight = $imagick->getImageHeight();
            $ratio = $this->getRatio($maxEdgeLength, $origWidth, $origHeight);

            if ($ratio < 1.0)
            {
                // The image needs to be downsized. Calculate new image dimensions.
                $newWidth  = (int)$origWidth  * $ratio;
                $newHeight = (int)$origHeight * $ratio;
            }
            else
            {
                // The image is already small enough.
                $newWidth = $origWidth;
                $newHeight = $origHeight;
            }

            // Setting the compression quality to be fairly high (100 is best quality) still results
            // in small files, especially in comparison to the hugh original tif files.
            $imagick->setCompression(Imagick::COMPRESSION_JPEG);
            $imagick->setImageCompressionQuality(90);
            $imagick->setImageFormat('jpeg');

            // Resize the image and write it to a temporary files.
            $imagick->resizeImage($newWidth, $newHeight, imagick::FILTER_LANCZOS, 1);
            $imagick->writeImage($targetImage);

            // Free up the imagick object's resources.
            $imagick->clear();
        }
        catch (ImagickException $e)
        {
            _log("Imagick failed to open $sourceImage. Details:\n$e", Zend_Log::ERR);
            return false;
        }
        return true;
    }
}