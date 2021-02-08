<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2018 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

include_once dirname(dirname(__FILE__)) . '/forms/TeiEditions_IngestForm.php';
include_once dirname(dirname(__FILE__)) . '/forms/TeiEditions_UpdateForm.php';
include_once dirname(dirname(__FILE__)) . '/forms/TeiEditions_AssociateForm.php';
include_once dirname(dirname(__FILE__)) . '/forms/TeiEditions_ArchiveForm.php';
include_once dirname(dirname(__FILE__)) . '/forms/TeiEditions_EnhanceForm.php';

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
        $form = new TeiEditions_IngestForm();
        $this->view->form = $form;
        $this->_processImportForm($form);
        // Clear and reindex.
        Zend_Registry::get('job_dispatcher')->sendLongRunning(
            'SolrSearch_Job_Reindex'
        );
    }

    /**
     * Display the "Field Configuration" form.
     *
     * @throws Zend_Form_Exception
     */
    public function updateAction()
    {
        // Set the created by user ID.
        $form = new TeiEditions_UpdateForm();
        $this->view->form = $form;
        $this->_processUpdateForm($form);
        Zend_Registry::get('job_dispatcher')->sendLongRunning(
            'SolrSearch_Job_Reindex'
        );
    }

    /**
     * Display the import associated items form.
     */
    public function associateAction()
    {
        $form = new TeiEditions_AssociateForm();
        $this->view->form = $form;

        if ($this->getRequest()->isPost()) {
            $done = 0;
            $tx = get_db()->getAdapter()->beginTransaction();
            try {
                $name = $_FILES["file"]["name"];
                $path = $_FILES["file"]["tmp_name"];
                $mime = $_FILES["file"]["type"];
                if ($path === "") {
                    throw new Exception("upload failed (check max file size?)");
                }
                if (preg_match('/.+\.zip$/', $path) or $mime === 'application/zip') {
                    $done += $this->_readAssociatedItemsZip($path);
                } else {
                    $this->_addAssociatedFile($path, $name);
                    $done++;
                }
                $tx->commit();
            } catch (Exception $e) {
                $tx->rollBack();
                echo $e->getTraceAsString();
                $this->_helper->_flashMessenger(
                    __('There was an error on the form: %s', $e->getMessage()), 'error');
                return;
            }

            $this->_helper->flashMessenger(__("Files successfully imported: $done"), 'success');
        }
    }

    public function downloadAction()
    {
        if ($this->_getParam("id")) {
            $file = get_db()->getTable("File")->find($this->_getParam("id"));
            if ($file) {
                $url = $file->getWebPath();
                header("Content-Type: " . $file->mimetype);
                header("Content-Disposition: attachment; filename='" . $file->original_filename . "'");
                readfile($url);
                exit();
            }
        }
        $this->_helper->_flashMessenger(
            __('No file ID provided'), 'error');
        return;
    }

    public function archiveAction()
    {
        $form = new TeiEditions_ArchiveForm();
        $this->view->form = $form;

        if ($this->getRequest()->isPost() and $form->isValid($_POST)) {
            $associated = $form->getElement('type')->getValue() === 'associated';
            $tmp = tempnam(sys_get_temp_dir(), '');
            $archive = new ZipArchive();
            $archive->open($tmp, ZipArchive::CREATE);
            foreach (get_db()->getTable('Item')->findAll() as $item) {
                $files = $associated
                    ? tei_editions_get_associated_files($item)
                    : (tei_editions_get_main_tei($item)
                        ? [tei_editions_get_main_tei($item)]
                        : []
                    );
                foreach ($files as $file) {
                    $archive->addFromString($file->original_filename,
                        file_get_contents($file->getWebPath()));
                }
            }
            $archive->close();
            $name = $associated ? 'associated' : 'tei';
            $date = date('c');
            header("Content-Type: application/zip");
            header("Content-Disposition: attachment; filename='${name}-${date}.zip'");
            header("Content-Length: " . filesize($tmp));
            ob_end_flush();
            readfile($tmp);
            unlink($tmp);
            exit();
        }
    }

    public function enhanceAction()
    {
        $form = new TeiEditions_EnhanceForm();
        $this->view->form = $form;

        if ($this->getRequest()->isPost() and $form->isValid($_POST)) {
            $dict = [];
            if ($dictpath = $_FILES["dict"]["tmp_name"]) {
                $doc = TeiEditionsDocumentProxy::fromUriOrPath($dictpath);
                foreach ($doc->entities() as $entity) {
                    $dict[$entity->ref()] = $entity;
                }
            }

            $name = $_FILES["file"]["name"];
            $path = $_FILES["file"]["tmp_name"];
            $mime = $_FILES["file"]["type"];
            switch ($mime) {
                case "text/xml":
                case "application/xml":
                    $data = TeiEditionsDocumentProxy::fromUriOrPath($path);
                    $src = new TeiEditionsDataFetcher($dict, $form->getValue("lang"));
                    $tool = new TeiEditionsTeiEnhancer($data, $src);
                    $num = $tool->addReferences();
                    $enhancedxml = $data->document()->saveXML();

                    $fname = pathinfo($name, PATHINFO_FILENAME);
                    header("Content-Type: $mime");
                    header("Content-Disposition: attachment; filename='${fname}-added-${num}.xml'");
                    header("Content-Length: " . strlen($enhancedxml));
                    ob_end_flush();
                    echo $enhancedxml;
                    exit();
                default:
                    $this->_helper->_flashMessenger(__('Unrecognised or unsuitable file type'), 'error');
                    return;
            }
        }
    }

    /**
     * Process the page edit and edit forms.
     * @throws Zend_File_Transfer_Exception
     */
    private function _processImportForm(TeiEditions_IngestForm $form)
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
                $name = $_FILES["file"]["name"];
                $path = $_FILES["file"]["tmp_name"];
                $mime = $_FILES["file"]["type"];
                switch ($mime) {
                    case "text/xml":
                    case "application/xml":
                        $this->_updateItem($path, $name, $neatline, true, $created, $updated);
                        break;
                    case "application/zip":
                        $this->_readZip($path, $neatline, true, $created, $updated);
                        break;
                    default:
                        error_log("Unhandled file extension: $mime");
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
    private function _processUpdateForm(TeiEditions_UpdateForm $form)
    {
        if ($this->getRequest()->isPost()) {
            if (!$form->isValid($_POST)) {
                $this->_helper->_flashMessenger(__('There was an error on the form. Please try again.'), 'error');
                return;
            }

            $db = get_db();
            $tx = $db->getAdapter()->beginTransaction();
            $updated = 0;
            $citem = null;
            try {
                $neatline = $form->getElement('create_exhibit')->isChecked();
                $selected_items = $form->getValue('item');

                foreach ($form->getCandidateItems() as $item) {
                    $citem = $item;
                    if ($selected_items and !in_array((string)$item->id, $selected_items)) {
                        continue;
                    }
                    foreach ($item->getFiles() as $file) {
                        if (tei_editions_is_xml_file($file)) {
                            $item->deleteElementTexts();
                            $doc = $this->_getDoc($file->getWebPath(), $file->getProperty('display_title'));
                            $this->_updateItemFromTEI($item, $doc, $neatline);
                            $updated++;
                            break;
                        }
                    }
                }
                $tx->commit();
            } catch (Exception $e) {
                error_log($e->getTraceAsString());
                $tx->rollBack();
                if ($citem) {
                    $this->_helper->_flashMessenger(
                        __("There was an processing element %d '%s': %s",
                            $citem->id, metadata($citem, "display_title"), $e->getMessage()), 'error');
                } else {
                    $this->_helper->_flashMessenger(
                        __("There was an error: %s", $e->getMessage()), 'error');
                }
                return;
            }

            $this->_helper->flashMessenger(
                __("TEI items updated: $updated"), 'success');
        }
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
        $item = tei_editions_get_item_by_identifier($doc->recordId());
        $created = is_null($item);
        return $created ? new Item : $item;
    }

    /**
     * Add a file to the item, or update it from the given
     * path if the original filename already exists.
     *
     * @param Item $item the item
     * @param string $path the file path
     * @param string $name the file name
     * @param bool $is_primary if this file is the primary TEI
     */
    private function _addOrUpdateItemFile(Item $item, $path, $name, $is_primary = false)
    {
        $primaryXml = $is_primary ? $name : null;
        foreach ($item->getFiles() as $file) {
            if (is_null($primaryXml) && tei_editions_is_xml_file($file)) {
                $primaryXml = $file->original_filename;
            }
            if ($file->original_filename == $name) {
                $file->unlinkFile();
                $file->delete();
            }
        }
        @insert_files_for_item($item, "Filesystem",
            ['source' => $path, 'name' => $name]);

        $images = [];
        $others = [];
        $xml = [];
        foreach ($item->getFiles() as $file) {
            if (tei_editions_is_xml_file($file)) {
                if ($primaryXml && $file->original_filename == $primaryXml) {
                    array_unshift($xml, $file);
                } else {
                    $xml[] = $file;
                }
            } else if (substr($file->mime_type, 0, 5) == "image") {
                $images[] = $file;
            } else {
                $others[] = $file;
            }
        }
        $order = 1;
        foreach (array_merge($images, $others, $xml) as $file) {
            $file->order = $order++;
            $file->save();
        }
    }

    /**
     * @param TeiEditionsDocumentProxy $doc
     * @return NeatlineExhibit
     * @throws Omeka_Record_Exception
     */
    private function _getOrCreateNeatlineExhibit(TeiEditionsDocumentProxy $doc)
    {
        $exhibits = get_db()->getTable('NeatlineExhibit')
            ->findBy(['slug' => strtolower($doc->recordId())]);
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

    /**
     * @param string $path
     * @param string $name
     * @return TeiEditionsDocumentProxy
     * @throws Exception
     */
    private function _getDoc($path, $name)
    {
        $doc = TeiEditionsDocumentProxy::fromUriOrPath($path);
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
    private function _readZip($zipPath, $neatline = false, $primary = false, &$created = 0, &$updated = 0)
    {
        $temp = $this->_tempDir();

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) === true) {
                $zip->extractTo($temp);
                $zip->close();

                foreach (glob($temp . '/*.xml') as $path) {
                    $this->_updateItem($path, basename($path), $neatline, $primary, $created, $updated);
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
        $id = tei_editions_get_identifier($name);
        $item = tei_editions_get_item_by_identifier($id);
        if (is_null($item)) {
            throw new Exception("Unable to locate item with identifier: " . $id . " (file: $path)");
        }
        $this->_addOrUpdateItemFile($item, $path, $name);
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
     * Update an item from the given TEI XML file.
     *
     * @param string $path
     * @param string $name
     * @param bool $neatline
     * @param bool $primary
     * @param int $created
     * @param int $updated
     * @throws Exception
     * @throws Omeka_Record_Exception
     */
    private function _updateItem($path, $name, $neatline, $primary, &$created, &$updated)
    {
        error_log("Importing file: $path");
        $create = false;
        $doc = $this->_getDoc($path, $name);
        $item = $this->_getOrCreateItem($doc, $create);
        $this->_updateItemFromTEI($item, $doc, $neatline);
        $this->_addOrUpdateItemFile($item, $path, $name, $primary);
        if ($create) {
            $created++;
        } else {
            $updated++;
        }
    }

    /**
     * @return NeatlineExhibit|null
     */
    private function _getTemplateNeatline()
    {
        $id = get_option('tei_editions_template_neatline');
        if ($id) {
            if ($t = get_db()->getTable('NeatlineExhibit')->findBy($id)) {
                return $t[0];
            }
        }
        return null;
    }

    /**
     * @param Item $entity
     * @param TeiEditionsDocumentProxy $doc
     * @throws Omeka_Record_Exception
     */
    private function _updateNeatlineExhibit(Item $entity, TeiEditionsDocumentProxy $doc)
    {
        $entities = array_unique($doc->entities(), SORT_REGULAR);
        $withgeo = array_filter($entities, function ($i) {
            return $i->hasGeo();
        });

        // if there are no mapped places, delete existing exhibits and return
        // early.
        if (empty($withgeo)) {
            $exhibits = get_db()->getTable('NeatlineExhibit')
                ->findBy(['slug' => strtolower($doc->recordId())]);
            foreach ($exhibits as $exhibit) {
                $exhibit->delete();
            }
            return;
        }

        $exhibit = $this->_getOrCreateNeatlineExhibit($doc);
        $exhibit->deleteChildRecords();
        $title = metadata($entity, 'display_title');
        $exhibit->title = $title;
        $exhibit->slug = strtolower($doc->recordId());
        $exhibit->public = true;
        $exhibit->spatial_layer = 'OpenStreetMap';
        $exhibit->narrative = $doc->asSimpleHtml();
        if (plugin_is_active('NeatlineText')) {
            $exhibit->widgets = 'Text';
        }

        // copy settings from template exhibit
        if ($template = $this->_getTemplateNeatline()) {
            $exhibit->styles = $template->styles;
            $exhibit->spatial_layer = $template->spatial_layer;
            $exhibit->spatial_layers = $template->spatial_layers;
            $exhibit->spatial_querying = $template->spatial_querying;
            $exhibit->wms_layers = $template->wms_layers;
            $exhibit->wms_address = $template->wms_address;
        }

        $exhibit->save($throwIfInvalid = true);

        // copy records from the template...
        if ($id = get_option('tei_editions_template_neatline')) {
            foreach (get_db()->getTable('NeatlineRecord')->findBy(['exhibit_id' => $id]) as $t) {
                $record = clone $t;
                $record->id = null;
                $record->exhibit_id = $exhibit->id;
                $record->save();
            }
        }

        $points_deg = [];
        $points_metres = [];
        foreach ($entities as $entity) {
            $this->_createRecord($doc, $exhibit, $entity, $points_deg, $points_metres);
        }

        if (!empty($points_metres)) {
            $exhibit->map_focus = implode(",", tei_editions_centre_points($points_metres));
            $exhibit->map_zoom = tei_editions_approximate_zoom($points_deg, 7);
        }
        $exhibit->save($throwIfInvalid = true);
    }

    /**
     * @param TeiEditionsDocumentProxy $doc
     * @param NeatlineExhibit $exhibit
     * @param TeiEditionsEntity $item
     * @param $points_deg
     * @param $points_metres
     */
    private function _createRecord(TeiEditionsDocumentProxy $doc,
                                   NeatlineExhibit $exhibit,
                                   TeiEditionsEntity $item,
                                   &$points_deg, &$points_metres)
    {
        $record = new NeatlineRecord;
        $record->exhibit_id = $exhibit->id;
        $record->title = $item->name;
        $record->added = (new \DateTime('now'))->format('Y-m-d H:i:s');
        if ($item->hasGeo()) {
            $deg = [$item->longitude, $item->latitude];
            $metres = tei_editions_degrees_to_metres($deg);
            $points_deg[] = $deg;
            $points_metres[] = $metres;
            $record->coverage = "Point(" . implode(" ", $metres) . ")";
        }
        $record->tags = $this->_getRecordTags($item->urls);
        $body = $doc->entityBodyHtml($item->urls, $item->slug);
        if ($body) {
            $record->body = $body;
        }
        if (isset($item->slug)) {
            $record->slug = $item->slug;
        }
        $record->save();
    }

    private function _getRecordTags($urls)
    {
        $tags = [];
        foreach ($urls as $url) {
            if (preg_match('/geonames/', $url)) {
                $tags[] = "location";
            }
            if (preg_match('/ehri_camps/', $url)) {
                $tags[] = "camp";
            }
            if (preg_match('/ehri_ghettos/', $url)) {
                $tags[] = "ghetto";
            }
            if (preg_match('/ehri_pers/', $url)) {
                $tags[] = "person";
            }
            if (preg_match('/ehri_cb/', $url)) {
                $tags[] = "organisation";
            }
            if (preg_match('/ehri_terms/', $url)) {
                $tags[] = "subject";
            }
        }
        return implode(',', array_unique($tags));
    }
}
