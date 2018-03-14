<style>
    .tei-entity-name {
        font-size: 1.2em;
        font-weight: bold;
    }

    .tei-entity-other-names {
        margin: 0;
        padding: 0;
    }
    .tei-entity-other-names li {
        display: inline;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .tei-entity-properties dt {
        font-weight: bold;
    }
    .tei-entity-properties dd {
        margin: 0;
    }
</style>
<?php if (is_null($url) || is_null($data)): ?>
    <div class="tei-entity">
        <?php echo __("No entity information found."); ?>
    </div>
<?php else: ?>
    <div class="tei-entity">
        <div class="tei-entity-name"><?php echo $data['name']; ?></div>

        <?php echo tei_editions_render_string_list($data['otherFormsOfName'], "tei-entity-other-names"); ?>
        <?php echo tei_editions_render_string_list($data['parallelFormsOfName'], "tei-entity-other-names"); ?>
        <?php echo tei_editions_render_properties($data, $mappings,
            array('biographicalHistory', 'datesOfExistence'), "tei-entity-properties"); ?>
        <?php echo tei_editions_render_string_list($data['source'], "tei-entity-source"); ?>
        <?php echo tei_editions_render_map($data); ?>
    </div>
<?php endif; ?>
