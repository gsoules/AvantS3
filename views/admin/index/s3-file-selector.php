<?php
$avantS3 = new AvantS3($item);
$fileNames = $avantS3->getS3FileNamesForItem();
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
        <th><?php echo __('Action'); ?></th>
    </tr>
    </thead>
    <tbody id="s3-file-checkboxes">
    <?php
    $tableHtml = '';
    foreach ($fileNames as $fileName => $action)
    {
        $actionText = __('Add to item');
        $tableHtml .= '<tr>';

        if ($action == AvantS3::S3_INELIGIBLE)
        {
            $tableHtml .= '<td></td>';
            $actionText = __('Cannot add this file type');
        }
        else
        {
            $tableHtml .= '<td><input type="checkbox" name="s3-files[]" value="' . html_escape($fileName) . '"/></td>';
        }

        $text = html_escape($fileName);
        if ($action == AvantS3::S3_EXISTING)
        {
            $tableHtml .= '<td><strong>' . $text . '</strong></td>';
            $actionText = __('Replace existing file');
        }
        else
        {
            $tableHtml .= '<td>' . $text . '</td>';
        }

        $tableHtml .= '<td>' . $actionText . '</td>';

        $tableHtml .= '</tr>';
    }
    echo $tableHtml;
    ?>
    </tbody>
</table>
