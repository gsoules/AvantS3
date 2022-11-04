<?php
$avantS3 = new AvantS3($item);

// Determine if files for an item were posted.
$postId = "s3-item-files";
$s3Names = $avantS3->getS3NamesForItem();

// When there are no item files, determine if files for an accession were posted.
if (!$s3Names)
{
    $s3Names = $avantS3->getS3NamesForAccession();
    $postId = "s3-accession-files";
}

if (!$s3Names)
    {
        echo '<p><strong>' . __('There are no S3 files for this item.') . '</strong></p>';
        return;
    }
else
{
    echo '<div class="s3-add-files explanation">' . __('Choose S3 files to add to this item.') . '</div>';
}
?>

<script type="text/javascript">
    function selectAllCheckboxes(checked)
    {
        jQuery('#s3-file-checkboxes tr:visible input').each(function()
        {
            this.checked = checked;
        });
    }

    jQuery(document).ready(function ()
    {
        jQuery('#s3-select-all').click(function ()
        {
            selectAllCheckboxes(this.checked);
        });

        jQuery('.s3-header').show();
    });
</script>

<table>
    <colgroup>
        <col style="width: 2em">
        <col>
    </colgroup>
    <thead>
    <tr>
        <th><input type="checkbox" id="s3-select-all" class="s3-header" style="display:none"></th>
        <th><?php echo __('File Name'); ?></th>
        <th><?php echo __('Action'); ?></th>
    </tr>
    </thead>
    <tbody id="s3-file-checkboxes">
    <?php
    $tableHtml = '';

    foreach ($s3Names as $s3Name)
    {
        $keyName = $s3Name->keyName;
        $action = $s3Name->action;

        $tableHtml .= '<tr>';

        if ($action == AvantS3::S3_NEW || $action == AvantS3::S3_EXISTING)
        {
            $tableHtml .= '<td><input type="checkbox" name="' . $postId . '[]" value="' . html_escape($keyName) . '"/></td>';
            if ($action == AvantS3::S3_EXISTING)
            {
                $class = 's3-existing';
                $actionText = __('Replace existing file');
            }
            else
            {
                $class = 's3-add';
                $actionText = __('Add to item');
            }
        }
        else
        {
            $tableHtml .= '<td></td>';
            if ($action == AvantS3::S3_FOLDER)
            {
                $class = 's3-folder';
                $actionText = __('Folder');
            }
            else
            {
                $class = $action == AvantS3::S3_INELIGIBLE ? 's3-ineligible' : 's3-error';
                $actionText = $action == AvantS3::S3_INELIGIBLE ? __('Cannot add') : '';
            }
        }

        $text = html_escape($keyName);
        $tableHtml .= '<td class="' . $class .'">' . $text . '</td>';
        $tableHtml .= '<td>' . $actionText . '</td>';
        $tableHtml .= '</tr>';
    }
    echo $tableHtml;
    ?>
    </tbody>
</table>
<?php echo '<div class="s3-resize-message explanation">' . __('Large images will be downsized to 1200 px on long edge, but retain their S3 file name.') . '</div>'; ?>
