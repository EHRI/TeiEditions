<?php
$head = array('bodyclass' => 'tei-editions primary',
    'title' => __('TEI Editions | Edit "%s"', metadata('tei_edition', 'title')));
echo head($head);
?>


<section class="seven columns alpha">
    <?php echo flash(); ?>
    <?php echo $xml; ?>
</section>

<section class="three columns omega">
    <div id="edit" class="panel">
        <?php echo link_to_item(__('Edit'), array('class'=>'big green button'), 'edit', $tei_edition); ?>
        <a href="<?php echo html_escape(public_url('editions/'.metadata('tei_edition', 'slug'))); ?>" class="big blue button" target="_blank"><?php echo __('View Public Page'); ?></a>
        <?php echo link_to_item(__('Delete'), array('class'=>'delete-confirm big red button'), 'delete-confirm', $tei_edition); ?>
    </div>

    <div class="public-featured panel">
        <p><span class="label"><?php echo __('Public'); ?>:</span> <?php echo ($tei_edition->is_published) ? __('Yes') : __('No'); ?></p>
    </div>
</section>


<?php echo foot(); ?>
