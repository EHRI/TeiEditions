<?php

?>

<?php echo head(array(
    'title' => __('TEI Editions | Update Items From TEI'),
)); ?>


<?php echo flash(); ?>
<?php echo $form; ?>
<script>
  jQuery(function ($) {
    $('form#tei-editions-update-form').submit(function () {
      $(this).find(':input[type=submit]').prop('disabled', true);
    });
  });
</script>
<?php echo foot(); ?>


