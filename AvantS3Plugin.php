<?php

define('AVANTS3_DIR',dirname(__FILE__));

require_once AVANTS3_DIR . '/helpers/S3Functions.php';

class AvantS3Plugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'after_save_item',
        'admin_items_form_files',
    );

    public function hookAdminItemsFormFiles($args)
    {
        echo '<h3>' . __('S3 files that can be attached to this item') . '</h3>';
        showS3FilesForItem($args['item']);
    }
    
    public function hookAfterSaveItem($args)
    {
        $item = $args['record'];
        $post = $args['post'];
    
        if (!($post && isset($post['s3-files'])))
        {
            return;
        }
        
        $fileNames = $post['s3-files'];

        if ($fileNames)
        {
            if (!canAccessS3StagingFolder())
            {
                throw new Exception(__('The AvantS3 staging folder must be both readable and writable.'));
            }

            $filePaths = array();
            foreach ($fileNames as $fileName)
            {
                $filePaths[] = validateS3FileName($fileName);
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

            // delete the files
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
}
