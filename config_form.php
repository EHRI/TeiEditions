<div class="field">
    <div class="two columns alpha">
        <label for="tei_editions_default_item_type"><?php echo __('Default Item Type'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation">
            <?php echo __("The default item type for items created via TEI ingest."); ?>
        </p>
        <?php echo get_view()->formSelect('tei_editions_default_item_type', get_option('tei_editions_default_item_type'), null,
            get_table_options('ItemType')); ?>
    </div>
</div>
<div class="field">
    <div class="two columns alpha">
        <label for="tei_editions_template_neatline"><?php echo __('Template Exhibit'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation">
            <?php echo __("A template Neatline exhibit from which settings will be inherited."); ?>
        </p>
        <?php echo get_view()->formSelect('tei_editions_template_neatline', get_option('tei_editions_template_neatline'), null,
            get_table_options('NeatlineExhibit')); ?>
    </div>
</div>
