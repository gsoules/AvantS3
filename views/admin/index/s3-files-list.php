<?php if (!canAccessS3StagingFolder()): ?>
    <p class="error"><?php echo __('The S3 staging folder must be both readable and writable.'); ?></p>
<?php else: ?>
    <?php $fileNames = getS3StagingFolderFileNames(getS3StagingFolderPath()); ?>
    <?php if (!$fileNames): ?>
        <p><strong><?php echo __('The S3 staging folder is empty.'); ?></strong></p>
    <?php else: ?>
        <script type="text/javascript">
            function selectAllCheckboxes(checked)
            {
                jQuery('#s3-file-checkboxes tr:visible input').each(function() {
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
    <?php endif; ?>
<?php endif ?>
