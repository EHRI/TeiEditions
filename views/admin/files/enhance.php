<?php

?>

<?php echo head(array(
    'title' => __('TEI Editions | Enhance TEI'),
)); ?>

<?php echo flash(); ?>

<p>
    <?php echo __('This tool attempts to populate the TEI header with canonical references to 
    entities marked in the text by &lt;term&gt;, &lt;persName&gt;, &lt;orgName&gt; and &lt;place&gt; markup
    if they contain \'ref\' attributes that point to the Geonames or EHRI data sources.'); ?>
</p>

<?php echo $form; ?>
<?php echo foot(); ?>


