<?php
if ($this->pageCount > 1):
    $getParams = $_GET;
    ?>
    <nav class="pagination" aria-label="<?php echo __('Pagination'); ?>">
        <?php if (isset($this->previous)): ?>
                <?php $getParams['page'] = $previous; ?>
                <a class="pagination-previous" rel="prev" href="<?php echo html_escape($this->url(array(), null, $getParams)); ?>"><?php echo __('Previous Page'); ?></a>
        <?php else:; ?>
            <a class="pagination-previous" rel="prev" disabled><?php echo __('Previous Page'); ?></a>
        <?php endif; ?>
        <?php if (isset($this->next)): ?>
            <!-- Next page link -->
                <?php $getParams['page'] = $next; ?>
                <a class="pagination-next" rel="next" href="<?php echo html_escape($this->url(array(), null, $getParams)); ?>"><?php echo __('Next Page'); ?></a>
        <?php else:; ?>
            <a class="pagination-next" rel="prev" disabled><?php echo __('Next Page'); ?></a>
        <?php endif; ?>
        <ul class="pagination-list"></ul>
    </nav>
<?php endif; ?>
