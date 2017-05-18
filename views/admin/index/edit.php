<?php
$head = array('bodyclass' => 'tei-editions primary',
    'title' => __('TEI Editions | Edit "%s"', metadata('tei_edition', 'title')));
echo head($head);
?>

<?php echo flash(); ?>
<p><?php echo __('This page was created by <strong>%1$s</strong> on %2$s, and last modified by <strong>%3$s</strong> on %4$s.',
    metadata('tei_edition', 'created_username'),
    html_escape(format_date(metadata('tei_edition', 'inserted'), Zend_Date::DATETIME_SHORT)),
    metadata('tei_edition', 'modified_username'),
    html_escape(format_date(metadata('tei_edition', 'updated'), Zend_Date::DATETIME_SHORT))); ?></p>
<?php echo $form; ?>
<?php echo foot(); ?>
