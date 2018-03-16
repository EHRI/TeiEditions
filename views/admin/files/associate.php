<?php
queue_js_file('items');
?>


<?php echo head(array(
    'title' => __('TEI Editions | Upload Associated Files'),
)); ?>

<script>
    jQuery(window).load(function () {
        Omeka.Items.enableAddFiles(<?php echo js_escape(__('Add Another File')); ?>);
    });
</script>

<?php echo flash(); ?>

<form enctype="multipart/form-data" method="post" lpformnum="1">
    <section class="seven columns alpha">
        <div id="files-metadata">
            <fieldset class="set">
                <h2><?php echo __("Associated Files"); ?></h2>

                <div class="add-new"><?php echo __("Upload TEI, Image, or ZIP archive"); ?></div>
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
                                                                type="submit" value="Upload Associated Files" name="submit">
    </section>
</form>


<?php echo foot(); ?>


