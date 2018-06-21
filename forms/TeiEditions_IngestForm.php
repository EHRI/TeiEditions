<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2018 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */


class TeiEditions_IngestForm extends Omeka_Form
{
    /**
     * @throws Zend_Form_Exception
     */
    public function init()
    {
        parent::init();

        $this->addElement('file', 'file', [
            'required' => true,
            'label' => __('Select TEI XML or zip archive'),
            'description' => __('Select the file to be ingested. Multiple files can be ingested if contained within a zip archive.'),
        ]);

        $this->addElement('checkbox', 'create_exhibit', [
            'id' => 'tei-editions-upload-create-exhibit',
            'label' => __('Create Neatline Exhibit'),
            'class' => 'checkbox',
            'description' => __('Create a Neatline Exhibit containing records for each place element contained in the TEI')
        ]);

        $this->addElement('submit', 'submit', [
            'label' => __('Upload File')
        ]);

        $this->addDisplayGroup(['file', 'create_exhibit'], 'tei-editions_info');
        $this->addDisplayGroup(['submit'], 'tei-editions_submit');
    }
}