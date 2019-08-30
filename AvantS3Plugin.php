<?php

class AvantS3Plugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'admin_head',
        'after_save_item',
        'admin_items_form_files',
        'config',
        'config_form'
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
    
        if (($post && isset($post['s3-files'])))
        {
            $s3FileNames = $post['s3-files'];
            if (!empty($s3FileNames))
            {
                $avantS3 = new AvantS3($item);
                $avantS3->downloadS3FilesToStagingFolder($s3FileNames);
                $avantS3->deleteExistingFilesAttachedToItem();
                $avantS3->attachS3FilesToItem();
            }
        }

        // Invoke the logic that would normally be called by AvantElasticsearch's after_save_item hook. When AvantS3
        // is installed, that hook does nothing so that this hook can first attach PDF files before AvantElasticsearch
        // indexes the item and its PDF files.
        $avantElasticsearch = new AvantElasticsearch();
        $avantElasticsearch->afterSaveItem($args);
    }

    public function hookConfig()
    {
        S3Config::saveConfiguration();
    }

    public function hookConfigForm()
    {
        require dirname(__FILE__) . '/config_form.php';
    }
}
