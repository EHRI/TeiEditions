<?php

$head = array('bodyclass' => 'tei-editions primary',
              'title' => html_escape(__('TEI Editions | Add Edition')));
echo head($head);
?>

<?php echo flash(); ?>
<?php echo $form; ?>
<?php echo foot(); ?>
