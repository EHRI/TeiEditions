<style>
    .entity-name {
        font-size: 1.2em;
        font-weight: bold;
    }

    .entity-other-names {
        margin: 0;
        padding: 0;
    }
    .entity-other-names li {
        display: inline;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .entity-properties dt {
        font-weight: bold;
    }
    .entity-properties dd {
        margin: 0;
    }
</style>
<?php if (is_null($url) || is_null($data)): ?>
    <div class="entity">
        <?php echo __("No entity information found."); ?>
    </div>
<?php else: ?>
    <div class="entity">
        <div class="entity-name"><?php echo $data['name']; ?></div>

        <?php echo tei_editions_render_string_list($data['otherFormsOfName'], "entity-other-names"); ?>
        <?php echo tei_editions_render_string_list($data['parallelFormsOfName'], "entity-other-names"); ?>
        <?php echo tei_editions_render_properties($data, $mappings,
            array('biographicalHistory', 'datesOfExistence'), "entity-properties"); ?>
        <?php echo tei_editions_render_string_list($data['source'], "entity-source"); ?>
    </div>
<?php endif; ?>
