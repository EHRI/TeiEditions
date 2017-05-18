<table class="full">
    <thead>
        <tr>
            <?php echo browse_sort_links(array(
                __('Title') => 'title',
                __('Slug') => 'slug',
                __('Last Modified') => 'updated'), array('link_tag' => 'th scope="col"', 'list_tag' => ''));
            ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach (loop('tei_editions') as $edition): ?>
        <tr>
            <td>
                <span class="title">
                    <a href="<?php echo html_escape(record_url('tei_editions', 'show')); ?>">
                        <?php echo metadata('tei_edition', 'title'); ?>
                    </a>
                    <?php if(!metadata('tei_edition', 'is_published')): ?>
                        (<?php echo __('Private'); ?>)
                    <?php endif; ?>
                </span>
                <ul class="action-links group">
                    <li><a class="edit" href="<?php echo html_escape(record_url('tei_editions', 'edit')); ?>">
                        <?php echo __('Edit'); ?>
                    </a></li>
                    <li><a class="delete-confirm" href="<?php echo html_escape(record_url('tei_editions', 'delete-confirm')); ?>">
                        <?php echo __('Delete'); ?>
                    </a></li>
                </ul>
            </td>
            <td><?php echo metadata('tei_editions', 'slug'); ?></td>
            <td><?php echo __('<strong>%1$s</strong> on %2$s',
                metadata('tei_editions', 'modified_username'),
                html_escape(format_date(metadata('tei_edition', 'updated'), Zend_Date::DATETIME_SHORT))); ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
