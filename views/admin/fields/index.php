<?php

?>

<?php echo head(array(
    'title' => __('TEI Editions | Field / Data mappings'),
)); ?>

<div id="tei-fields">
    <a href="<?php echo html_escape(url(array('controller' => 'fields', 'action' => 'add'))); ?>" class="small green button"><?php echo __('Edit Field Mappings'); ?></a>
</div>

<?php echo foot(); ?>
