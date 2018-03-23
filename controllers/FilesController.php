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
     *
     * @throws Zend_Form_Exception
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
     *
     * @throws Zend_Form_Exception
     */
    public function updateAction()
    {
        // Set the created by user ID.
        $form = $this->_getUpdateForm();
        $this->view->form = $form;
        $this->_processUpdateForm($form);
    }

    /**
     * Display the import associated items form.
     */
    public function associateAction()
    {
        if ($this->getRequest()->isPost()) {

            $done = 0;
            $tx = get_db()->getAdapter()->beginTransaction();
            try {
                foreach ($_FILES["file"]["name"] as $idx => $name) {
                    $path = $_FILES["file"]["tmp_name"][$idx];
                    $mime = $_FILES["file"]["type"][$idx];
                    switch ($mime) {
                        case "application/zip":
                            $done += $this->_readAssociatedItemsZip($path);
                            break;
                        default:
                            $this->_addAssociatedFile($path, $name);
                            $done++;
                    }
                }
                $tx->commit();
            } catch (Exception $e) {
                $tx->rollBack();
                $this->_helper->_flashMessenger(
                    __('There was an error on the form: %s', $e->getMessage()), 'error');
                return;
            }

            $this->_helper->flashMessenger(__("Files successfully imported: $done"), 'success');
        }
    }

    /**
     * @return Omeka_Form_Admin
     * @throws Zend_Form_Exception
     */
    private function _getImportForm()
    {
        $formOptions = ['type' => 'tei_editions_upload'];
        $form = new Omeka_Form_Admin($formOptions);

        $form->addElement('checkbox', 'create_exhibit', [
            'id' => 'tei-editions-upload-create-exhibit',
            'label' => __('Create Neatline Exhibit'),
            'class' => 'checkbox',
            'description' => __('Create a Neatline Exhibit containing records for each place element contained in the TEI')
        ]);

        return $form;
    }

    /**
     * @return Omeka_Form
     * @throws Zend_Form_Exception
     */
    private function _getUpdateForm()
    {
        $formOptions = ['type' => 'tei_editions_update'];
        $form = new Omeka_Form($formOptions);

        // The pick an item drop-down select:
        $select = $form->createElement('select', 'item', [
            'required' => false,
            'multiple' => 'multiple',
            'label' => __('Item'),
            'description' => __('Select a specific item (optional). If left blank all items with a TEI file will be processed'),
            'multiOptions' => $this->_getItemsForSelect(),
        ]);
        $select->setRegisterInArrayValidator(false);
        $form->addElement($select);

        $form->addElement('checkbox', 'create_exhibit', [
            'id' => 'tei-editions-upload-create-exhibit',
            'label' => __('Create Neatline Exhibit'),
            'class' => 'checkbox',
            'description' => __('Create a Neatline Exhibit containing records for each place element contained in the TEI')
        ]);

        $form->addElement('submit', 'submit', [
            'label' => __('Update Items')
        ]);

        $form->addDisplayGroup(['create_exhibit'], 'teiupdate_info');
        $form->addDisplayGroup(['submit'], 'teiupdate_submit');

        return $form;
    }

    /**
     * Process the page edit and edit forms.
     */
    private function _processImportForm(Omeka_Form_Admin $form)
    {
        if ($this->getRequest()->isPost()) {
            if (!$form->isValid($_POST)) {
                $this->_helper->_flashMessenger(__('There was an error on the form. Please try again.'), 'error');
                return;
            }

            $created = 0;
            $updated = 0;
            $neatline = $form->getElement('create_exhibit')->isChecked();
            $tx = get_db()->getAdapter()->beginTransaction();
            try {
                foreach ($_FILES["file"]["name"] as $idx => $name) {
                    $path = $_FILES["file"]["tmp_name"][$idx];
                    $mime = $_FILES["file"]["type"][$idx];
                    switch ($mime) {
                        case "text/xml":
                        case "application/xml":
                            $this->_updateItem($path, $name, $neatline, $created, $updated);
                            break;
                        case "application/zip":
                            $this->_readZip($path, $neatline, $created, $updated);
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

            $this->_helper->flashMessenger(__("TEIs successfully created: $created, updated: $updated"), 'success');
        }
    }

    /**
     * Process the page edit and edit forms.
     */
    private function _processUpdateForm(Omeka_Form $form)
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
                            $doc = $this->_getDoc($file->getWebPath(), $file->getProperty('display_title'));
                            $this->_updateItemFromTEI($item, $doc, $neatline);
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
     * @return Item|null
     * @throws Omeka_Record_Exception|Exception
     */
    private function _getItemByIdentifier($identifier)
    {
        $element = get_db()->getTable('Element')->findBy([
            'element_set_id' => 1, // FIXME: hard-coded set name
            'name' => 'Identifier'
        ])[0]; // hack!
        $text = get_db()->getTable('ElementText')->findBy([
            'element_id' => $element->id,
            'text' => $identifier
        ]);
        if (!empty($text)) {
            $item = get_db()->getTable('Item')->find($text[0]->record_id);
            if (!is_null($item) && $item !== false) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param TeiEditionsDocumentProxy $doc
     * @param bool $created
     * @return Item
     * @throws Omeka_Record_Exception
     * @throws Exception
     */
    private function _getOrCreateItem(TeiEditionsDocumentProxy $doc, &$created)
    {
        $item = $this->_getItemByIdentifier($doc->recordId());
        $created = is_null($item);
        return $created ? new Item : $item;
    }

    /**
     * @param Item $item
     * @param string $path
     * @param string $name
     * @param bool $created
     */
    private function _updateItemFile(Item $item, $path, $name, $created)
    {
        if (!$created) {
            foreach ($item->getFiles() as $file) {
                if ($file->original_filename == $name) {
                    $file->unlinkFile();
                    $file->delete();
                }
            }
        }
        @insert_files_for_item($item, "Filesystem",
            ['source' => $path, 'name' => $name]);
    }

    /**
     * @param TeiEditionsDocumentProxy $doc
     * @return NeatlineExhibit
     * @throws Omeka_Record_Exception
     */
    private function _getOrCreateNeatlineExhibit(TeiEditionsDocumentProxy $doc)
    {
        $exhibits = get_db()->getTable('NeatlineExhibit')->findBy(['slug' => $doc->recordId()]);
        return empty($exhibits) ? new NeatlineExhibit : $exhibits[0];
    }

    /**
     * @param Item $item
     * @param TeiEditionsDocumentProxy $doc
     * @param bool $neatline create a Neatline exhibit
     * @throws Omeka_Record_Exception|Exception
     */
    private function _updateItemFromTEI(Item $item, TeiEditionsDocumentProxy $doc, $neatline)
    {
        $item->item_type_id = get_option('tei_editions_default_item_type');
        $data = $doc->metadata(TeiEditionsFieldMapping::fieldMappings());
        $item->deleteElementTexts();
        $item->addElementTextsByArray($data);
        $item->save();

        if ($neatline) {
            $this->_updateNeatlineExhibit($item, $doc);
        }
    }

    private function _getCandidateItems()
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
        foreach ($this->_getCandidateItems() as $item) {
            $options[$item->id] = metadata($item, 'display_title');
        }
        return $options;
    }

    /**
     * @param string $path
     * @param string $name
     * @return TeiEditionsDocumentProxy
     * @throws Exception
     */
    private function _getDoc($path, $name)
    {
        $doc = new TeiEditionsDocumentProxy($path);
        if (is_null($doc->xmlId())) {
            throw new Exception("TEI document '$name' must have a unique 'xml:id' attribute");
        }
        if (is_null($doc->recordId())) {
            throw new Exception("TEI document '$name' must have a valid 'profileDesc/creation/idno' value");
        }

        return $doc;
    }

    /**
     * @param string $zipPath the local path the the zip file
     * @param bool $neatline create a Neatline exhibit
     * @throws Exception
     * @throws Omeka_Record_Exception
     */
    private function _readZip($zipPath, $neatline = false, &$created = 0, &$updated = 0)
    {
        $temp = $this->_tempDir();

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) === true) {
                $zip->extractTo($temp);
                $zip->close();

                foreach (glob($temp . '/*.xml') as $path) {
                    $this->_updateItem($path, basename($path), $neatline, $created, $updated);
                }
            } else {
                throw new Exception("Zip cannot be opened");
            }
        } finally {
            $this->_deleteDir($temp);
        }
    }

    /**
     * @param $zipPath
     * @return int
     * @throws Exception
     * @throws Omeka_Record_Exception
     */
    private function _readAssociatedItemsZip($zipPath)
    {
        $temp = $this->_tempDir();
        $done = 0;

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) === true) {
                $zip->extractTo($temp);
                $zip->close();

                foreach (glob($temp . '/*') as $path) {
                    $this->_addAssociatedFile($path, basename($path));
                    $done++;
                }
            } else {
                throw new Exception("Zip cannot be opened");
            }
            return $done;
        } finally {
            $this->_deleteDir($temp);
        }
    }

    /**
     * Add a file to an item assuming the filename prior to the
     * first underscore is the item identifier.
     *
     * @param $path
     * @param $name
     * @throws Exception
     * @throws Omeka_Record_Exception
     */
    private function _addAssociatedFile($path, $name)
    {
        $id = $this->identifierFromFilename($name);
        $item = $this->_getItemByIdentifier($id);
        if (is_null($item)) {
            throw new Exception("Unable to locate item with identifier: " . $id . " (file: $path)");
        }
        $this->_updateItemFile($item, $path, $name, false);
    }

    private function _tempDir($mode = 0700)
    {
        do {
            $tmp = tempnam(sys_get_temp_dir(), '');
            unlink($tmp);
        } while (!@mkdir($tmp, $mode));
        return $tmp;
    }

    private function _deleteDir($path)
    {
        return is_file($path) ?
            @unlink($path) :
            array_map(function ($p) {
                $this->_deleteDir($p);
            }, glob($path . '/*')) == @rmdir($path);
    }

    /**
     * @param string $path
     * @param string $name
     * @param bool $neatline
     * @param int $created
     * @param int $updated
     * @throws Exception
     * @throws Omeka_Record_Exception
     */
    private function _updateItem($path, $name, $neatline, &$created, &$updated)
    {
        error_log("Importing file: $path");
        $create = false;
        $doc = $this->_getDoc($path, $name);
        $item = $this->_getOrCreateItem($doc, $create);
        $this->_updateItemFromTEI($item, $doc, $neatline);
        $this->_updateItemFile($item, $path, $name, $create);
        if ($create) {
            $created++;
        } else {
            $updated++;
        }
    }

    /**
     * @param Item $item
     * @param TeiEditionsDocumentProxy $doc
     * @throws Omeka_Record_Exception
     */
    private function _updateNeatlineExhibit(Item $item, TeiEditionsDocumentProxy $doc)
    {
        $exhibit = $this->_getOrCreateNeatlineExhibit($doc);
        $exhibit->deleteChildRecords();
        $title = metadata($item, 'display_title');
        $exhibit->title = $title;
        $exhibit->slug = $doc->recordId();
        $exhibit->spatial_layer = 'OpenStreetMap';
        $exhibit->narrative = $doc->asSimpleHtml();
        $exhibit->save(true);

        $points = [];
        $geo = array_unique($doc->places(), SORT_REGULAR);
        error_log(json_encode($geo), true);
        foreach ($geo as $teiPlace) {
            if (isset($teiPlace["longitude"]) and isset($teiPlace["latitude"])) {
                $place = new NeatlineRecord;
                $place->exhibit_id = $exhibit->id;
                $place->title = $teiPlace["name"];
                $metres = tei_editions_degrees_to_metres(
                    [$teiPlace["longitude"], $teiPlace["latitude"]]);
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

    /**
     * @param $name
     * @return bool|string
     */
    private function identifierFromFilename($name)
    {
        $noext = substr($name, 0, strripos($name, "."));
        $nound = strripos($noext, "_");
        return $nound ? substr($noext, 0, $nound) : $noext;
    }
}
