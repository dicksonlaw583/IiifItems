<?php
$formStem = $block->getFormStem();
$options = $block->getOptions();
?>
<div class="showcase-position">
    <h4><?php echo __('Show page number:'); ?></h4>
    <input type="number" name="<?php echo $formStem; ?>[options][defaultCanvas]" value="<?php echo @$options['defaultCanvas'] ?: 1; ?>" min="1">
</div>
<div class="selected-items">
    <h4><?php echo __('Items'); ?></h4>
    <?php echo $this->exhibitFormAttachments($block); ?>
    <h4><?php echo __('Description'); ?></h4>
    <?php echo $this->exhibitFormText($block); ?>
</div>