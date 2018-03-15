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
