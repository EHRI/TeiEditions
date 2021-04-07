<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */


class TeiEditions_AssociateForm extends Omeka_Form
{
    /**
     * @throws Zend_Form_Exception
     */
    public function init()
    {
        parent::init();

        $this->setAttrib('id', 'tei-editions-associate-form');

        $this->addElement('file', 'file', [
            'required' => true,
            'label' => __('Select file or zip archive'),
            'description' => __('Select the file to be associated. Multiple files can be uploaded if contained within a zip archive.'),
        ]);

        $this->addElement('submit', 'submit', [
            'label' => __('Upload File')
        ]);

        $this->addDisplayGroup(['file'], 'tei-editions_info');
        $this->addDisplayGroup(['submit'], 'tei-editions_submit');
    }
}