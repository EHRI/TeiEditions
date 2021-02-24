<?php echo head(array(
    'title' => __('TEI Editions | Ingest Items From TEI'),
)); ?>

<?php echo flash(); ?>
<?php echo $form; ?>
<script>
  var elem = document.getElementById('tei-editions-enhance'),
      lang = document.getElementById('tei-editions-enhance-lang'),
      dict = document.getElementById('tei-editions-enhance-dict');

  function updateState() {
    lang.disabled = !elem.checked;
    dict.disabled = !elem.checked;
  }
  elem.addEventListener('change', updateState);
  updateState();
</script>
<?php echo foot(); ?>


