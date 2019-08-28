<?php
$avantS3 = new AvantS3($item);
$fileNames = $avantS3->getS3FileNamesForItem();
if (!$fileNames)
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

    foreach ($fileNames as $fileName => $action)
    {
        $tableHtml .= '<tr>';

        if ($action == AvantS3::S3_INELIGIBLE || $action == AvantS3::S3_ERROR)
        {
            $tableHtml .= '<td></td>';
            $class = $action == AvantS3::S3_INELIGIBLE ? 's3-ineligible' : 's3-error';
            $actionText = $action == AvantS3::S3_INELIGIBLE ? __('Cannot add this file type') : '';
        }
        else
        {
            $tableHtml .= '<td><input type="checkbox" name="s3-files[]" value="' . html_escape($fileName) . '"/></td>';
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

        $text = html_escape($fileName);
        $tableHtml .= '<td class="' . $class .'">' . $text . '</td>';
        $tableHtml .= '<td>' . $actionText . '</td>';
        $tableHtml .= '</tr>';
    }
    echo $tableHtml;
    ?>
    </tbody>
</table>
<?php echo '<div class="s3-resize-message explanation">' . __('Large images will be downsized to 1200 px on long edge, but retain their S3 file name.') . '</div>'; ?>
