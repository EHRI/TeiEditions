<?php

echo head(array(
    'title' => metadata('tei_edition', 'title'),
    'bodyclass' => 'edition tei-edition',
    'bodyid' => metadata('tei_edition', 'slug')
));
?>
<div id="primary">
    <h1>TODO: Stuff</h1>

    <?php echo metadata('tei_edition', 'title'); ?>
</div>

<?php echo foot(); ?>
