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

        $failed = false;

        if ($post)
        {
            $avantS3 = new AvantS3($item);
            $s3KeyNames = "";
            $folderName = "";

            if ((isset($post['s3-item-files'])))
            {
                // Construct the folder name for files that were posted for an item.
                $s3KeyNames = $post['s3-item-files'];
                if (!empty($s3KeyNames))
                {
                    $parentFolderName = $avantS3->getItemParentFolderName($item);
                    $folderName = "Database/$parentFolderName";
                }
            }
            else if ((isset($post['s3-accession-files'])))
            {
                // Construct the folder name for files that were posted for an accession.
                $s3KeyNames = $post['s3-accession-files'];
                if (!empty($s3KeyNames))
                {
                    $accessionNumberElementId = S3Config::getElementIdForAccessionElement();
                    $accessionNumber = ItemMetadata::getElementTextFromElementId($item, $accessionNumberElementId);
                    if ($accessionNumber)
                    {
                        // Handle the case when the accession is a sub_accession e.g. 2026_02 which will be in S3 Accessions/2026/2026_02".
                        $parts = explode('_', $accessionNumber);
                        if (count($parts) == 2)
                            $accessionNumber = $parts[0] . "/" . $accessionNumber;
                        $folderName = "Accessions/$accessionNumber";
                    }
                }
            }

            if (!empty($folderName) && !empty($s3KeyNames))
            {
                $downloaded = $avantS3->downloadS3FilesToStagingFolder($s3KeyNames, $folderName);
                if ($downloaded)
                {
                    $avantS3->deleteExistingFilesAttachedToItem();
                    $avantS3->attachS3FilesToItem();
                }
                else
                {
                    $failed = true;
                }
            }
        }

        // Invoke the logic that would normally be called by AvantElasticsearch's after_save_item hook. When AvantS3
        // is installed, that hook does nothing so that this hook can first attach PDF files before AvantElasticsearch
        // indexes the item and its PDF files.
        $avantElasticsearch = new AvantElasticsearch();
        $avantElasticsearch->afterSaveItem($args);

        if ($failed)
        {
            // See AvantS3::resizeImage() for the cause of this error.
            throw new Exception("Unable to upload S3 image file because of insufficient memory. Resize the image to have smaller dimensions (width x height) and try again.");
        }
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
