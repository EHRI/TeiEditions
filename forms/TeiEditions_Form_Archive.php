<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */


class TeiEditions_Form_Archive extends Omeka_Form
{
    /**
     * @throws Zend_Form_Exception
     */
    public function init()
    {
        parent::init();

        // The pick an item drop-down select:
        $select = $this->createElement('radio', 'type', [
            'required' => true,
            'multiple' => 'multiple',
            'label' => __('Type'),
            'description' => __('Choose the type of data to download'),
            'multiOptions' => ['tei' => 'Primary TEIs', 'associated' => 'Associated Files'],
            'size' => 10,
        ]);
        $this->addElement($select);

        $this->addElement('submit', 'submit', [
            'label' => __('Download'),
            'id' => 'tei-editions-submit'
        ]);

        $this->addDisplayGroup(['type'], 'tei-editions_info');
        $this->addDisplayGroup(['submit'], 'tei-editions_submit');
    }
}