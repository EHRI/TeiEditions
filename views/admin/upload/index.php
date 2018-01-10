<?php

?>

<?php echo head(array(
    'title' => __('TEI Editions'),
)); ?>


<?php echo flash(); ?>

<div id="tei-fields">

    <a href="<?php echo html_escape(url('editions/import')); ?>" class="small green button"><?php echo __('New Item From TEI'); ?></a>
</div>


<div id="tei-fields">

    <a href="<?php echo html_escape(url('edition-fields')); ?>" class="small green button"><?php echo __('Edit Field Mappings'); ?></a>
</div>


<?php echo foot(); ?>


