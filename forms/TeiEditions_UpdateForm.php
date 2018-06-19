<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2018 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */


class TeiEditions_UpdateForm extends Omeka_Form
{
    /**
     * @throws Zend_Form_Exception
     */
    public function init()
    {
        parent::init();

        // The pick an item drop-down select:
        $select = $this->createElement('select', 'item', [
            'required' => false,
            'multiple' => 'multiple',
            'label' => __('Item'),
            'description' => __('Select a specific item (optional). If left blank all items with a TEI file will be processed'),
            'multiOptions' => $this->_getItemsForSelect(),
            'size' => 10,
        ]);
        $select->setRegisterInArrayValidator(false);
        $this->addElement($select);

        $this->addElement('checkbox', 'create_exhibit', [
            'id' => 'tei-editions-upload-create-exhibit',
            'label' => __('Create Neatline Exhibit'),
            'class' => 'checkbox',
            'description' => __('Create a Neatline Exhibit containing records for each place element contained in the TEI')
        ]);

        $this->addElement('submit', 'submit', [
            'label' => __('Update Items')
        ]);

        $this->addDisplayGroup(['item', 'create_exhibit'], 'tei-editions_info');
        $this->addDisplayGroup(['submit'], 'tei-editions_submit');
    }

    public function getCandidateItems()
    {
        $items = [];
        foreach (get_db()->getTable('Item')->findAll() as $item) {
            foreach ($item->getFiles() as $file) {
                if (tei_editions_is_xml_file($file)) {
                    $items[] = $item;
                }
            }
        }
        return $items;
    }

    private function _getItemsForSelect()
    {
        $options = [];
        foreach ($this->getCandidateItems() as $item) {
            $options[$item->id] = sprintf("%s: %s",
                metadata($item, ['Dublin Core', 'Identifier']),
                metadata($item, 'display_title'));
        }
        return $options;
    }

}