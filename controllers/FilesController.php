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
class TeiEditions_FilesController extends Omeka_Controller_AbstractActionController
{

    public function init()
    {
        // Set the model class so this controller can perform some functions,
        // such as $this->findById()
        $this->_helper->db->setDefaultModelName('Item');
    }

    public function indexAction()
    {
    }

    /**
     * Display the "Field Configuration" form.
     */
    public function importAction()
    {
        // Set the created by user ID.
        $form = $this->_getImportForm();
        $this->view->form = $form;
        $this->_processImportForm($form);
    }

    /**
     * Display the "Field Configuration" form.
     */
    public function updateAction()
    {
        // Set the created by user ID.
        $form = $this->_getUpdateForm();
        $this->view->form = $form;
        $this->_processUpdateForm($form);
    }

    private function _getImportForm()
    {
        $formOptions = array('type' => 'tei_editions_upload');
        $form = new Omeka_Form($formOptions);

        $form->addElement('file', 'file', array(
            'id' => 'tei-editions-upload-file',
            'label' => __('Select TEI'),
            'description' => __('A TEI file to upload as a new item'),
            'required' => true
        ));

        $form->addElement('checkbox', 'create_exhibit', array(
            'id' => 'tei-editions-upload-create-exhibit',
            'label' => __('Create Neatline Exhibit'),
            'class' => 'checkbox',
            'description' => __('Create a Neatline Exhibit containing records for each place element contained in the TEI')
        ));


        $form->addElement('submit', 'submit', array(
            'label' => __('Import TEI')
        ));

        $form->addDisplayGroup(array('file', 'create_exhibit'), 'teiimport_info');
        $form->addDisplayGroup(array('submit'), 'teiimport_submit');

        return $form;
    }

    private function _getUpdateForm()
    {
        $formOptions = array('type' => 'tei_editions_update');
        $form = new Omeka_Form($formOptions);

        // The pick an item drop-down select:
        $select = $form->createElement('select', 'item', array(
            'required' => false,
            'multiple' => 'multiple',
            'label' => __('Item'),
            'description' => __('Select a specific item (optional). If left blank all items with a TEI file will be processed'),
            'multiOptions' => $this->_getItemsForSelect(),
        ));
        $select->setRegisterInArrayValidator(false);
        $form->addElement($select);

        $form->addElement('checkbox', 'create_exhibit', array(
            'id' => 'tei-editions-upload-create-exhibit',
            'label' => __('Create Neatline Exhibit'),
            'class' => 'checkbox',
            'description' => __('Create a Neatline Exhibit containing records for each place element contained in the TEI')
        ));

        $form->addElement('submit', 'submit', array(
            'label' => __('Update Items')
        ));

        $form->addDisplayGroup(array('create_exhibit'), 'teiupdate_info');
        $form->addDisplayGroup(array('submit'), 'teiupdate_submit');

        return $form;
    }

    /**
     * Process the page edit and edit forms.
     */
    private function _processImportForm($form)
    {
        if ($this->getRequest()->isPost()) {
            if (!$form->isValid($_POST)) {
                $this->_helper->_flashMessenger(__('There was an error on the form. Please try again.'), 'error');
                return;
            }

            $tx = get_db()->getAdapter()->beginTransaction();
            try {
                $item = new Item;
                $file = "file";
                $path = $_FILES[$file]["tmp_name"];
                $this->_updateItemFromTEI($item, $path,
                    $form->getElement('create_exhibit')->isChecked());
                @insert_files_for_item($item, "Upload", "file");
                $tx->commit();
            } catch (Exception $e) {
                error_log($e->getTraceAsString());
                $tx->rollBack();
                $this->_helper->_flashMessenger(
                    __('There was an error on the form: %s', $e->getMessage()), 'error');
                return;
            }

            $this->_helper->flashMessenger(
                __('The TEI was successfully imported!'), 'success');

            $this->_helper->redirector($item->id, 'show', "items");
        }
    }

    /**
     * Process the page edit and edit forms.
     */
    private function _processUpdateForm($form)
    {
        if ($this->getRequest()->isPost()) {
            if (!$form->isValid($_POST)) {
                $this->_helper->_flashMessenger(__('There was an error on the form. Please try again.'), 'error');
                return;
            }

            $db = get_db();
            $tx = $db->getAdapter()->beginTransaction();
            $updated = 0;
            try {
                $extract_neatline = $form->getElement('create_exhibit')->isChecked();
                $selected_items = $form->getValue('item');

                foreach ($this->_getCandidateItems() as $item) {
                    if (!in_array((string)$item->id, $selected_items)) {
                        continue;
                    }
                    foreach ($item->getFiles() as $file) {
                        if (tei_editions_is_xml_file($file)) {
                            $item->deleteElementTexts();
                            $this->_updateItemFromTEI($item, $file->getWebPath(),
                                $extract_neatline);
                            $updated++;
                        }
                    }
                }
                $tx->commit();
            } catch (Exception $e) {
                error_log($e->getTraceAsString());
                $tx->rollBack();
                $this->_helper->_flashMessenger(
                    __("There was an processing element %d '%s': %s",
                        $item->id, metadata($item, "display_title"), $e->getMessage()), 'error');
                return;
            }

            $this->_helper->flashMessenger(
                __("TEI items updated: $updated"), 'success');
        }
    }

    /**
     * @param $form
     * @param $path
     * @param $xpaths
     * @param $item
     */
    private function _updateItemFromTEI($item, $path, $extract_neatline)
    {
        $xpaths = TeiEditionsFieldMapping::fieldMappings();
        $doc = new TeiEditionsDocumentProxy($path);
        $data = $doc->metadata($xpaths);
        error_log("Extracted from " . $path . " -> " .
            json_encode($data, JSON_PRETTY_PRINT));

        $item->addElementTextsByArray($data);
        $item->save();

        if ($extract_neatline) {
            $exhibit = new NeatlineExhibit;
            $title = metadata($item, 'display_title');
            $exhibit->title = $title;
            $exhibit->slug = $doc->id();
            $exhibit->spatial_layer = 'OpenStreetMap';
            $exhibit->save(true);

            $points = array();
            $geo = array_unique($doc->places(), SORT_REGULAR);
            foreach ($geo as $teiPlace) {
                $place = new NeatlineRecord;
                $place->exhibit_id = $exhibit->id;
                $place->title = $teiPlace["name"];
                $metres = tei_editions_degrees_to_metres(
                    array($teiPlace["longitude"], $teiPlace["latitude"]));
                $points[] = $metres;
                $place->coverage = "Point(" . implode(" ", $metres) . ")";
                $place->save();
            }
            if (!empty($points)) {
                $exhibit->map_focus = implode(",", tei_editions_centre_points($points));
                $exhibit->map_zoom = 7; // guess?
            }
            $exhibit->save(true);
        }
    }

    private function _getCandidateItems() {
        $items = array();
        foreach (get_db()->getTable('Item')->findAll() as $item) {
            foreach ($item->getFiles() as $file) {
                if (tei_editions_is_xml_file($file)) {
                    $items[] = $item;
                }
            }
        }
        return $items;
    }

    private function _getItemsForSelect() {
        $options = array();
        foreach ($this->_getCandidateItems() as $item) {
            $options[$item->id] = metadata($item, 'display_title');
        }
        return $options;
    }
}
