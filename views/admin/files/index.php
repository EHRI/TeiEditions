<?php

?>

<?php echo head(array(
    'title' => __('TEI Editions'),
)); ?>

<?php echo flash(); ?>

<h2><?php echo __('Ingest and Update'); ?></h2>

<div id="tei-fields">
    <a href="<?php echo html_escape(url(['controller' => 'files', 'action' => 'import'])); ?>" class="small green button"><?php echo __('TEI Ingest'); ?></a>
</div>

<div id="tei-fields">
    <a href="<?php echo html_escape(url(['controller' => 'files', 'action' => 'update'])); ?>" class="small green button"><?php echo __('Update TEI Items'); ?></a>
</div>

<div id="tei-fields">
    <a href="<?php echo html_escape(url(['controller' => 'files', 'action' => 'associate'])); ?>" class="small green button"><?php echo __('Upload Associated Files'); ?></a>
</div>

<h2><?php echo __('Download'); ?></h2>

<div id="tei-fields">
    <a href="<?php echo html_escape(url(['controller' => 'files', 'action' => 'zip'])); ?>" class="small green button"><?php echo __('Download TEI Archive'); ?></a>
</div>

<div id="tei-fields">
    <a href="<?php echo html_escape(url(['controller' => 'files', 'action' => 'zip'], null, ['associated' => true])); ?>" class="small green button"><?php echo __('Download Associated File Archive'); ?></a>
</div>

<h2><?php echo __('Configuration'); ?></h2>

<div id="tei-fields">
    <a href="<?php echo html_escape(url(['action' => 'browse', 'controller' => 'fields'])); ?>" class="small green button"><?php echo __('Edit Field Mappings'); ?></a>
</div>


<?php echo foot(); ?>


