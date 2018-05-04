<?php
$head = array('bodyclass' => 'tei-editions-field-mapping primary',
              'title' => html_escape(__('Field Mappings | Browse')),
              'content_class' => 'horizontal-nav');
echo head($head);
?>

<?php queue_css_string(".xpath { font-weight: bolder; }") ?>

<?php echo flash(); ?>

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
                        <th><?php echo __('Field'); ?></th>
                        <th><?php echo __('Path'); ?></th>
                        <th></th>
                        <th></th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach (loop('tei_editions_field_mappings') as $mapping): ?>
                        <tr>
                            <td><?php echo $mapping->getElementName();?></td>
                            <td><span class="xpath"><?php echo $mapping->path; ?></span></td>
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

<a class="add-page button small green" href="<?php echo html_escape(url(array('controller' => 'fields', 'action' => 'add'))); ?>"><?php echo __('Add a Field Mapping'); ?></a>


<?php echo foot(); ?>
