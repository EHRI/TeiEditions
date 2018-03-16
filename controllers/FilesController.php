<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2017 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * The TeiEditions TEI file upload controller.
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

    /**
     * @return Omeka_Form_Admin
     * @throws Zend_Form_Exception
     */
    private function _getImportForm()
    {
        $formOptions = array('type' => 'tei_editions_upload');
        $form = new Omeka_Form_Admin($formOptions);

        $form->addElement('checkbox', 'create_exhibit', array(
            'id' => 'tei-editions-upload-create-exhibit',
            'label' => __('Create Neatline Exhibit'),
            'class' => 'checkbox',
            'description' => __('Create a Neatline Exhibit containing records for each place element contained in the TEI')
        ));

        return $form;
    }

    /**
     * @return Omeka_Form
     * @throws Zend_Form_Exception
     */
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

            $done = 0;
            $neatline = $form->getElement('create_exhibit')->isChecked();
            $tx = get_db()->getAdapter()->beginTransaction();
            try {
                foreach ($_FILES["file"]["name"] as $idx => $name) {
                    $path = $_FILES["file"]["tmp_name"][$idx];
                    $mime = $_FILES["file"]["type"][$idx];
                    switch ($mime) {
                        case "text/xml":
                        case "application/xml":
                            $item = new Item;
                            $this->_updateItemFromTEI($item, $path, $name, $neatline);
                            @insert_files_for_item($item, "Filesystem",
                                array('source' => $path, 'name' => $name));
                            $done++;
                            break;
                        case "application/zip":
                            $done += $this->_readZip($path, $neatline);
                            break;
                        default:
                            error_log("Unhandled file extension: $mime");
                    }
                }
                $tx->commit();
            } catch (Exception $e) {
                $tx->rollBack();
                $this->_helper->_flashMessenger(
                    __('There was an error on the form: %s', $e->getMessage()), 'error');
                return;
            }

            $this->_helper->flashMessenger(__("TEIs successfully imported: $done"), 'success');
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
                $neatline = $form->getElement('create_exhibit')->isChecked();
                $selected_items = $form->getValue('item');

                foreach ($this->_getCandidateItems() as $item) {
                    if (!in_array((string)$item->id, $selected_items)) {
                        continue;
                    }
                    foreach ($item->getFiles() as $file) {
                        if (tei_editions_is_xml_file($file)) {
                            $item->deleteElementTexts();
                            $this->_updateItemFromTEI($item, $file->getWebPath(),
                                $file->getProperty('display_title'), $neatline);
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
     * @throws Omeka_Record_Exception|Exception
     */
    private function _updateItemFromTEI(Item $item, $path, $name, $extract_neatline)
    {
        error_log("Processing $path");
        $xpaths = TeiEditionsFieldMapping::fieldMappings();
        $doc = new TeiEditionsDocumentProxy($path);
        if (is_null($doc->xmlId())) {
            throw new Exception("TEI document '$name' must have a unique 'xml:id' attribute");
        }
        if (is_null($doc->recordId())) {
            throw new Exception("TEI document '$name' must have a valid 'profileDesc/creation/idno' value");
        }

        $data = $doc->metadata($xpaths);
        $item->item_type_id = get_option('tei_editions_default_item_type');
        $item->addElementTextsByArray($data);
        $item->save();

        if ($extract_neatline) {
            $exhibit = new NeatlineExhibit;
            $title = metadata($item, 'display_title');
            $exhibit->title = $title;
            $exhibit->slug = $doc->recordId();
            $exhibit->spatial_layer = 'OpenStreetMap';
            $exhibit->save(true);

            $points = array();
            $geo = array_unique($doc->places(), SORT_REGULAR);
            error_log(json_encode($geo), true);
            foreach ($geo as $teiPlace) {
                if (isset($teiPlace["longitude"]) and isset($teiPlace["latitude"])) {
                    $place = new NeatlineRecord;
                    $place->exhibit_id = $exhibit->id;
                    $place->title = $teiPlace["name"];
                    $metres = tei_editions_degrees_to_metres(
                        array($teiPlace["longitude"], $teiPlace["latitude"]));
                    $points[] = $metres;
                    $place->coverage = "Point(" . implode(" ", $metres) . ")";
                    foreach ($teiPlace["urls"] as $url) {
                        $slug = tei_editions_url_to_slug($url);
                        if ($slug) {
                            $place->slug = $slug;
                            break;
                        }
                    }
                    $place->save();
                }
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

    /**
     * @param $path
     * @param bool $createExhibit
     * @throws Exception
     * @throws Omeka_Record_Exception
     */
    private function _readZip($path, $createExhibit = false)
    {
        $done = 0;
        $temp = $this->_tempDir();

        try {
            $zip = new ZipArchive;
            if ($zip->open($path) === true) {
                $zip->extractTo($temp);
                $zip->close();

                foreach (glob($temp . '/*.xml') as $filename) {
                    error_log("Importing file: $filename");
                    $item = new Item;
                    $this->_updateItemFromTEI($item, $filename, basename($filename), $createExhibit);
                    @insert_files_for_item($item, "Filesystem",
                        array('source' => $filename, 'name' => basename($filename)));
                    $done++;
                }
            } else {
                throw new Exception("Zip cannot be opened");
            }
        } finally {
            $this->_deleteDir($temp);
        }

        return $done;
    }

    private function _tempDir($mode = 0700) {
        do {
            $tmp = tempnam(sys_get_temp_dir(),'');
            unlink($tmp);
        }
        while (!@mkdir($tmp, $mode));
        return $tmp;
    }

    private function _deleteDir($path) {
        return is_file($path) ?
            @unlink($path) :
            array_map(function($p) { $this->_deleteDir($p); },
                glob($path.'/*')) == @rmdir($path);
    }
}
