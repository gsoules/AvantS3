<?php

class AvantS3Plugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'admin_head',
        'after_save_item',
        'admin_items_form_files',
    );

    public function hookAdminHead($args)
    {
        queue_css_file('avants3');
    }

    public function hookAdminItemsFormFiles($args)
    {
        $item = $args['item'];
        echo common('s3-file-selector', array('item' => $item), 'index');
    }
    
    public function hookAfterSaveItem($args)
    {
        $item = $args['record'];
        $post = $args['post'];
    
        if (!($post && isset($post['s3-files'])))
            return;

        $s3FileNames = $post['s3-files'];
        if (empty($s3FileNames))
            return;

        $avantS3 = new AvantS3($item);
        $avantS3->downloadS3FilesToStagingFolder($s3FileNames);
        $avantS3->deleteExistingFilesAttachedToItem();
        $avantS3->attachS3FilesToItem();
    }
}
