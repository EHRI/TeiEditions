<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2018 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */


class TeiEditions_AssociateForm extends Omeka_Form
{
    /**
     * @throws Zend_Form_Exception
     */
    public function init()
    {
        parent::init();

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