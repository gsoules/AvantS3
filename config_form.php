<?php
$view = get_view();

$bucket = S3Config::getOptionValueForBucket();
$console = S3Config::getOptionValueForConsole();
$key = S3Config::getOptionValueForKey();
$pathAccessions = S3Config::getOptionValueForPathAccessions();
$pathItems = S3Config::getOptionValueForPathItems();
$region = S3Config::getOptionValueForRegion();
$secret = S3Config::getOptionValueForSecret();
$accessionElement = S3Config::getOptionTextForAccessionElement();

?>

<style>
    .error{color:red;font-size:16px;}
    .storage-engine {color:#9D5B41;margin-bottom:24px;font-weight:bold;}
</style>

<div class="plugin-help learn-more">
    <a href="https://digitalarchive.us/plugins/avants3/" target="_blank">Learn about this plugin</a>
</div>

<div class="field">
    <div class="two columns alpha">
        <label><?php echo CONFIG_LABEL_S3_CONSOLE; ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('S3 Management Console URL'); ?></p>
        <?php echo $view->formText(S3Config::OPTION_S3_CONSOLE, $console); ?>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label><?php echo CONFIG_LABEL_S3_BUCKET; ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('S3 bucket name for the Digital Archive'); ?></p>
        <?php echo $view->formText(S3Config::OPTION_S3_BUCKET, $bucket); ?>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label><?php echo CONFIG_LABEL_S3_PATH_ITEMS; ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Path to item folders'); ?></p>
        <?php echo $view->formText(S3Config::OPTION_S3_PATH_ITEMS, $pathItems); ?>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label><?php echo CONFIG_LABEL_S3_PATH_ACCESSIONS; ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Path to accession folders'); ?></p>
        <?php echo $view->formText(S3Config::OPTION_S3_PATH_ACCESSIONS, $pathAccessions); ?>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label><?php echo CONFIG_LABEL_S3_REGION; ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('AWS server region'); ?></p>
        <?php echo $view->formText(S3Config::OPTION_S3_REGION, $region); ?>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label><?php echo CONFIG_LABEL_S3_KEY; ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('AWS Access Key Id'); ?></p>
        <?php echo $view->formText(S3Config::OPTION_S3_KEY, $key); ?>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label><?php echo CONFIG_LABEL_S3_SECRET; ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('AWS Secret Access Key'); ?></p>
        <?php echo $view->formText(S3Config::OPTION_S3_SECRET, $secret); ?>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label><?php echo CONFIG_LABEL_S3_ACCESSION_ELEMENT; ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Accession # Element'); ?></p>
        <?php echo $view->formText(S3Config::OPTION_S3_ACCESSION_ELEMENT, $accessionElement); ?>
    </div>
</div>

