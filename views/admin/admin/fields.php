<?php

?>

<?php echo head(array(
    'title' => __('TEI Editions | Field / Data mappings'),
)); ?>

<div id="tei-fields">

    <form id="tei-fields-form" method="post">

        <div>
            <table class="tie-fields-mappings">

                <thead>
                <tr>
                    <th><?php echo __('Field'); ?></th>
                    <th><?php echo __('Path'); ?></th>
                </tr>
                </thead>

                <tbody>

                <tr>
                    <td>
                        <?php $itemType = get_db()->getTable("ItemType")->findBySql("name = ?", array('name' => 'TEI'), true) ?>
                        <?php $elems = is_null($itemType) ? array() : get_db()->getTable('Element')->findByItemType($itemType->id); ?>
                        <?php $options = array(); ?>
                        <?php foreach ($elems as $elem) { $options[$elem->id] = $elem->name; }; ?>
                        <?php
                        echo $this->formSelect(
                            null, null,
                            array('class' => 'existing-element-drop-down'),
                            label_table_options($options)
                        );
                        ?>
                    </td>
                    <td>
                        <input name="mapping[0][path]" type="text"/>
                    </td>
                </tr>
                </tbody>

            </table>
        </div>

        <?php echo $this->formSubmit('submit', __('Update Mappings')); ?>

    </form>
</div>

<?php echo foot(); ?>
