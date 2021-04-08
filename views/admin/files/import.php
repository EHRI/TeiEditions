<?php echo head(array(
    'title' => __('TEI Editions | Ingest Items From TEI'),
)); ?>

<?php echo flash(); ?>
<?php echo $form; ?>
<script>
  var elem = document.getElementById('tei-editions-enhance'),
      opts = document.getElementById('fieldset-teieditionsenhanceopts'),
      form = document.getElementById('tei-editions-import-form'),
      button = document.getElementById('tei-editions-submit');

  function updateState() {
    opts.style.display = elem.checked ? 'block' : 'none';
  }

  elem.addEventListener('change', updateState);
  updateState();

  form.addEventListener('submit', function() {
    button.disabled = true;
  });
</script>
<?php echo foot(); ?>


