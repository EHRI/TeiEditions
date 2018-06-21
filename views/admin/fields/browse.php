<?php
$head = array('bodyclass' => 'tei-editions-field-mapping primary',
    'title' => html_escape(__('Field Mappings | Browse')),
    'content_class' => 'horizontal-nav');
echo head($head);
?>

<?php queue_css_string(".xpath { font-weight: bolder; }") ?>

<?php echo flash(); ?>

<div class="table-actions">
    <a class="add-page button small green" href="<?php echo html_escape(url(array('controller' => 'fields', 'action' => 'add'))); ?>">
        <?php echo __('Add a Field Mapping'); ?>
    </a>
</div>

    <p>
        <?php echo __('If the value of each mapped XPath matches data in a TEI document it will be copied to the corresponding Omeka element.'); ?>
    </p>
    <p>
        <?php echo __('<strong>Note:</strong> TEI tags must be namespaced with the prefix "<code>tei:</code>".'); ?>
    </p>

    <?php if (!has_loop_records('tei_editions_field_mappings')): ?>
        <p><?php echo __('There are no field mappings.'); ?>
            <a href="<?php echo html_escape(url(array('controller' => 'fields', 'action' => 'add'))); ?>"><?php echo __('Add a Field Mapping.'); ?></a>
        </p>
    <?php else: ?>
        <div id="tei-fields">
            <form id="tei-fields-form" method="post">
                <div>
                    <table class="tei-fields-mappings">
                        <thead>
                        <tr>
                            <th><?php echo __('XPath'); ?></th>
                            <th><?php echo __('Field'); ?></th>
                            <th></th>
                            <th></th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach (loop('tei_editions_field_mappings') as $mapping): ?>
                            <tr>
                                <td><span class="xpath"><?php echo $mapping->path; ?></span></td>
                                <td><?php echo $mapping->getElementName();?></td>
                                <td><a class="edit" href="<?php echo html_escape(record_url($mapping, 'edit')); ?>">
                                    <?php echo __('Edit'); ?></td>
                                <td><a class="delete-confirm" href="<?php echo html_escape(record_url($mapping, 'delete-confirm')); ?>">
                                        <?php echo __('Delete'); ?>
                                    </a></td>
                            </tr>

                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    <?php endif; ?>

<?php echo foot(); ?>
