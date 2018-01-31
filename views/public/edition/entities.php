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

        <?php if (array_key_exists('otherFormsOfName', $data) and !empty($data['otherFormsOfName'])): ?>
            <ul class="entity-other-names">
                <?php foreach ($data['otherFormsOfName'] as $altname): ?>
                    <li><?php echo $altname; ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php foreach (array('biographicalHistory', 'datesOfExistence') as $key): ?>
            <dl class="entity-properties">
                <?php if (array_key_exists($key, $data)): ?>
                    <dt><?php echo $mappings[$key]; ?></dt>
                    <dd><?php echo $data[$key]; ?></dd>
                <?php endif; ?>
            </dl>
        <?php endforeach; ?>

        <?php if (array_key_exists('source', $data) && (is_array($data['source']) && !empty($data['source']))): ?>
            <ul class="entity-source">
                <?php var_dump($data['source']);?>
                <?php foreach ($data['source'] as $source): ?>
                    <li><?php echo $source; ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>
