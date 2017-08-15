<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2017 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * The TeiEditions Edition record class.
 *
 * @package TeiEditions
 */
class TeiEditions_FieldsController extends Omeka_Controller_AbstractActionController
{

    public function init()
    {
        // Set the model class so this controller can perform some functions,
        // such as $this->findById()
        $this->_helper->db->setDefaultModelName('TeiEditionsFieldMapping');
    }

    public function indexAction()
    {
        // Always go to browse.
        $this->_helper->redirector('browse');
        return;
    }

    /**
     * Display the "Field Configuration" form.
     */
    public function addAction()
    {
        // Create a new page.
        $fieldMapping = new TeiEditionsFieldMapping;

        // Set the created by user ID.
        $form = $this->_getForm($fieldMapping);
        $this->view->form = $form;
        $this->_processFieldForm($fieldMapping, $form, 'add');
    }

    public function editAction()
    {
        // Get the requested page.
        $page = $this->_helper->db->findById();
        $form = $this->_getForm($page);
        $this->view->form = $form;
        $this->_processFieldForm($page, $form, 'edit');
    }

    private function _getForm($fieldMapping)
    {
        $formOptions = array('type' => 'tei_editions_field_mapping');
        if ($fieldMapping && $fieldMapping->exists()) {
            $formOptions['record'] = $fieldMapping;
        }

        $form = new Omeka_Form_Admin($formOptions);

        $form->addElementToEditGroup(
            'select', 'element_id',
            array(
                'id' => 'tei-editions-field-mapping-element-id',
                'multiOptions' => label_table_options(tei_editions_field_mappings_element_options()),
                'value' => $fieldMapping->element_id,
                'label' => __('Element'),
                'description' => __('The element'),
                'required' => true
            )
        );

        $form->addElementToEditGroup(
            'text', 'path',
            array(
                'id' => 'tei-editions-field-mapping-path',
                'value' => $fieldMapping->path,
                'label' => __('Path'),
                'description' => __('TEI XPath'),
                'required' => true
            )
        );

        return $form;
    }

    /**
     * Process the page edit and edit forms.
     */
    private function _processFieldForm($mapping, $form, $action)
    {
        // Set the page object to the view.
        $this->view->tei_editions_field_mapping = $mapping;

        if ($this->getRequest()->isPost()) {
            if (!$form->isValid($_POST)) {
                $this->_helper->_flashMessenger(__('There was an error on the form. Please try again.'), 'error');
                return;
            }
            try {
                $mapping->setPostData($_POST);
                if ($mapping->save()) {
                    if ('add' == $action) {
                        $this->_helper->flashMessenger(__('The "%s" mapping has been added.', $mapping->getElementName()), 'success');
                    } else if ('edit' == $action) {
                        $this->_helper->flashMessenger(__('The "%s" mapping has been edited.', $mapping->getElementName()), 'success');
                    }

                    $this->_helper->redirector('browse');
                    return;
                }
                // Catch validation errors.
            } catch (Omeka_Validate_Exception $e) {
                $this->_helper->flashMessenger($e);
            }
        }
    }

    protected function _getDeleteSuccessMessage($record)
    {
        return __('The "%s" mapping has been deleted.', $record->getElementName());
    }
}
