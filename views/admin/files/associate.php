<?php echo head(array(
    'title' => __('TEI Editions | Upload Associated Files'),
)); ?>

<?php echo flash(); ?>
<?php echo $form; ?>
<script>
  var form = document.getElementById('tei-editions-associate-form'),
      button = document.getElementById('tei-editions-submit');

  form.addEventListener('submit', function() {
    button.disabled = true;
  });
</script>
<?php echo foot(); ?>


