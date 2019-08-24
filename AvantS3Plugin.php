<?php

define('AVANTS3_DIR',dirname(__FILE__));

class AvantS3Plugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'after_save_item',
        'admin_items_form_files',
    );

    public function hookAdminItemsFormFiles($args)
    {
        $item = $args['item'];
        echo '<h3>' . __('Add S3 Files') . '</h3>';
        echo common('s3-file-selector', array('item' => $item), 'index');
    }
    
    public function hookAfterSaveItem($args)
    {
        $avantS3 = new AvantS3();

        $item = $args['record'];
        $post = $args['post'];
    
        if (!($post && isset($post['s3-files'])))
            return;

        $s3FileNames = $post['s3-files'];
        if (empty($s3FileNames))
            return;

        if (!$avantS3->canAccessS3StagingFolder())
        {
            throw new Exception(__('The AvantS3 staging folder must be both readable and writable.'));
        }

        $avantS3->downloadS3FilesToStagingFolder($item, $s3FileNames);

        $filePaths = array();
        foreach ($s3FileNames as $fileName)
        {
            $filePaths[] = $avantS3->getAbsoluteFilePathName($fileName);
        }

        $files = array();
        try
        {
            $files = insert_files_for_item($item, 'Filesystem', $filePaths, array('file_ingest_options' => array('ignore_invalid_files' => false)));
        }
        catch (Omeka_File_Ingest_InvalidException $e)
        {
            release_object($files);
            $item->addError('AvantS3', $e->getMessage());
            return;
        }
        catch (Exception $e)
        {
            release_object($files);
            throw $e;
        }
        release_object($files);

        // Delete the files from the staging folders.
        foreach ($filePaths as $filePath)
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
}
