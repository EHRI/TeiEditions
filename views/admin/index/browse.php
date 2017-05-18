<?php
$head = array('bodyclass' => 'tei-editions primary',
              'title' => html_escape(__('TEI Editions | Browse')),
              'content_class' => 'horizontal-nav');
echo head($head);
?>
<ul id="section-nav" class="navigation">
    <li class="<?php if (isset($_GET['view']) &&  $_GET['view'] != 'hierarchy') {echo 'current';} ?>">
        <a href="<?php echo html_escape(url('tei-editions/index/browse?view=list')); ?>"><?php echo __('List View'); ?></a>
    </li>
</ul>
<?php echo flash(); ?>

<a class="add-page button small green" href="<?php echo html_escape(url('tei-editions/index/add')); ?>"><?php echo __('Add a Page'); ?></a>
<?php if (!has_loop_records('tei_editions')): ?>
    <p><?php echo __('There are no pages.'); ?> <a href="<?php echo html_escape(url('tei-editions/index/add')); ?>"><?php echo __('Add a page.'); ?></a></p>
<?php else: ?>
    <?php echo $this->partial('index/browse-list.php', array('teiEditions' => $tei_editions)); ?>
<?php endif; ?>
<?php echo foot(); ?>
