<?php

?>

<?php echo head(array(
    'title' => __('TEI Editions'),
)); ?>


<?php echo flash(); ?>

<div id="tei-fields">

    <a href="<?php echo html_escape(url(array('controller' => 'files', 'action' => 'import'))); ?>" class="small green button"><?php echo __('TEI Ingest'); ?></a>
</div>

<div id="tei-fields">

    <a href="<?php echo html_escape(url(array('controller' => 'files', 'action' => 'update'))); ?>" class="small green button"><?php echo __('Update TEI Items'); ?></a>
</div>


<div id="tei-fields">

    <a href="<?php echo html_escape(url(array('action' => 'browse', 'controller' => 'fields'))); ?>" class="small green button"><?php echo __('Edit Field Mappings'); ?></a>
</div>


<?php echo foot(); ?>


