<?php

?>

<?php echo head(array(
    'title' => __('TEI Editions | Update Items From TEI'),
)); ?>


<?php echo flash(); ?>
<?php echo $form; ?>
<script>
  var form = document.getElementById('tei-editions-update-form'),
      button = document.getElementById('tei-editions-submit');

  form.addEventListener('submit', function() {
    button.disabled = true;
  });
</script>
<?php echo foot(); ?>


