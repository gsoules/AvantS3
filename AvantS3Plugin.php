<?php

define('AVANTS3_DIR',dirname(__FILE__));

require_once AVANTS3_DIR.'/helpers/DropboxFunctions.php';

class AvantS3Plugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'after_save_item',
        'admin_items_form_files',
    );

    public function hookAdminItemsFormFiles($args)
    {
        echo '<h3>' . __('Add Dropbox Files') . '</h3>';
        dropbox_list($args['item']);
    }
    
    public function hookAfterSaveItem($args)
    {
        $item = $args['record'];
        $post = $args['post'];
    
        if (!($post && isset($post['dropbox-files']))) {
            return;
        }
        
        $fileNames = $post['dropbox-files'];
        if ($fileNames) {
            if (!dropbox_can_access_files_dir()) {
                throw new Dropbox_Exception(__('The Dropbox files directory must be both readable and writable.'));
            }
            $filePaths = array();
            foreach($fileNames as $fileName) {
                $filePaths[] = dropbox_validate_file($fileName);
            }
    
            $files = array();
            try {
                $files = insert_files_for_item($item, 'Filesystem', $filePaths, array('file_ingest_options'=> array('ignore_invalid_files'=>false)));
            } catch (Omeka_File_Ingest_InvalidException $e) {
                release_object($files);
                $item->addError('Dropbox', $e->getMessage());
                return;
            } catch (Exception $e) {
                release_object($files);
                throw $e;
            }
            release_object($files);
    
            // delete the files
            foreach($filePaths as $filePath) {
                try {
                    unlink($filePath);
                } catch (Exception $e) {
                    throw $e;
                }
            }
        }
    }
}
