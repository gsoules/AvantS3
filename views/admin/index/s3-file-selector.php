<?php
$avantS3 = new AvantS3();
$fileNames = $avantS3->getS3FileNamesForItem($item);
if (!$fileNames)
    {
        echo '<p><strong>' . __('There are no S3 files for this item.') . '</strong></p>';
        return;
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
        </tr>
    </thead>
    <tbody id="s3-file-checkboxes">
    <?php foreach ($fileNames as $fileName): ?>
        <tr><td><input type="checkbox" name="s3-files[]" value="<?php echo html_escape($fileName); ?>"/></td><td><?php echo html_escape($fileName); ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
