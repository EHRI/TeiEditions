<?php
queue_js_file('items');
?>


<?php echo head(array(
    'title' => __('TEI Editions | Ingest Items From TEI'),
)); ?>

<script>
    jQuery(window).load(function () {
        Omeka.Items.enableAddFiles(<?php echo js_escape(__('Add Another File')); ?>);
    });
</script>

<?php echo flash(); ?>

<form enctype="multipart/form-data" method="post" lpformnum="1">
    <section class="seven columns alpha">
        <fieldset id="fieldset-teiimport_info">
            <div class="field">
                <div id="tei-editions-upload-create-exhibit-label" class="two columns alpha">
                    <?php
                    echo $this->formLabel(
                        'collection-search', __('Create Neatline Exhibits')
                    );
                    ?>
                </div>
                <div class="inputs five columns omega">
                    <?php
                    echo $this->formCheckbox(
                        'create_exhibit',
                        @$_REQUEST['create_exhibit'],
                        array('id' => 'tei-editions-upload-create-exhibit')
                    );
                    ?>
            </div>
        </fieldset>
        <div id="files-metadata">
            <fieldset class="set">
                <h2><?php echo __("TEI Files"); ?></h2>

                <div class="add-new"><?php echo __("Upload TEI or ZIP archive"); ?></div>
                <div class="drawer-contents">
                    <div class="field two columns alpha" id="file-inputs">
                        <label><?php echo __("Find a file"); ?></label>
                    </div>

                    <div class="files four columns omega">
                        <input name="file[0]" type="file">
                    </div>
                </div>
            </fieldset>
        </div>
    </section>
    <section id="save" class="three columns omega panel"><input id="save-changes" class="submit big green button"
                                                                type="submit" value="Import Files" name="submit">
    </section>
</form>


<?php echo foot(); ?>


