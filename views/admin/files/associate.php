<?php echo head(array(
    'title' => __('TEI Editions | Upload Associated Files'),
)); ?>

<?php echo flash(); ?>
<?php echo $form; ?>
<script>
  jQuery(function ($) {
    $('form#tei-editions-associate-form').submit(function () {
      $(this).find(':input[type=submit]').prop('disabled', true);
    });
  });
</script>
<?php echo foot(); ?>


