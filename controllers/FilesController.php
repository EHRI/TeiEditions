<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */

require_once dirname(__DIR__) . '/forms/TeiEditions_Form_Import.php';
require_once dirname(__DIR__) . '/forms/TeiEditions_Form_Update.php';
require_once dirname(__DIR__) . '/forms/TeiEditions_Form_Associate.php';
require_once dirname(__DIR__) . '/forms/TeiEditions_Form_Archive.php';
require_once dirname(__DIR__) . '/forms/TeiEditions_Form_Enhance.php';

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * The TeiEditions TEI file upload controller.
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
     * Display the data import form.
     *
     * @throws Zend_Form_Exception
     */
    public function importAction()
    {
        // Set the created by user ID.
        $form = new TeiEditions_Form_Import();
        $this->view->form = $form;

        if ($this->getRequest()->isPost()) {
            if (!$form->isValid($_POST)) {
                $this->_helper->_flashMessenger(__('There was an error on the form. Please try again.'), 'error');
                return;
            }

            $start = time();
            $created = 0;
            $updated = 0;

            $name = $_FILES["file"]["name"];
            $mime = $_FILES["file"]["type"];

            if (($temp = $this->_tempDir()) === false) {
                $this->_helper->_flashMessenger(
                    __('There was an error creating a temporary processing location', 'error'));
                return;
            }

            $path = $temp . DIRECTORY_SEPARATOR . $name;
            move_uploaded_file($_FILES["file"]["tmp_name"], $path);

            $dict_path = null;
            if ($form->getElement("enhance")->isChecked()
                    && isset($_FILES["enhance_dict"]["tmp_name"])
                    && !empty($_FILES["enhance_dict"]["tmp_name"])) {
                $dict_path = $temp . DIRECTORY_SEPARATOR . $_FILES['enhance_dict']['name'];
                move_uploaded_file($_FILES["enhance_dict"]["tmp_name"], $dict_path);
            }

            // NB: Dispatching this as a long-running job doesn't quite work
            // because of crashes when creating Neatline items. This is likely
            // because the job runner is just a command line and the full Neatline
            // libraries are not being loaded correctly - unfortunately I haven't
            // figured out how to get proper error messages since the job runner
            // just dies without apparently sending its output anywhere...
            // In any case, a long running job can't return output of any kind
            // so it's a bit fire and forget really.
            try {
                $opts = [];
                if ($geonames_user = get_option('tei_editions_geonames_user')) {
                    $opts['geonames_user'] = $geonames_user;
                }
                $src = new TeiEditions_Helpers_DataFetcher($dict_path, $form->getValue('enhance_lang'), $opts);
                $enhancer = new TeiEditions_Helpers_TeiEnhancer($src);
                $importer = new TeiEditions_Helpers_DataImporter(get_db(), $enhancer);
                $importer->importData(
                    $path,
                    $mime,
                    $form->getElement('create_exhibit')->isChecked(),
                    $form->getElement('enhance')->isChecked(),
                    $form->getElement('force_refresh')->isChecked(),
                    $created,
                    $updated,
                    function () {
                        _log("Import complete");
                    }
                );

                $time = time() - $start;
                $this->_helper->flashMessenger(__("TEIs successfully created: $created, updated: $updated, time: ${time}s"), 'success');
            } catch (Exception $e) {
                error_log($e->getTraceAsString());
                $this->_helper->_flashMessenger(__('There was an error on the form: %s', $e->getMessage()), 'error');
            } finally {
                $this->_deleteDir($temp);

                // Clear and reindex.
                Zend_Registry::get('job_dispatcher')->sendLongRunning('SolrSearch_Job_Reindex');
            }
        }
    }

    /**
     * Display the data update form.
     *
     * @throws Zend_Form_Exception
     */
    public function updateAction()
    {
        // Set the created by user ID.
        $form = new TeiEditions_Form_Update();
        $this->view->form = $form;

        if ($this->getRequest()->isPost()) {
            if (!$form->isValid($_POST)) {
                $this->_helper->_flashMessenger(__('There was an error on the form. Please try again.'), 'error');
                return;
            }

            $updated = 0;
            try {
                $neatline = $form->getElement('create_exhibit')->isChecked();
                $importer = new TeiEditions_Helpers_DataImporter(get_db());
                $importer->updateItems($form->getSelectedItems(), $neatline, $updated);
                $this->_helper->flashMessenger(__("TEI items updated: $updated"), 'success');
            } catch (Exception $e) {
                error_log($e->getTraceAsString());
                $this->_helper->_flashMessenger(
                    __("There was an error: %s", $e->getMessage()), 'error');
            } finally {
                Zend_Registry::get('job_dispatcher')->sendLongRunning('SolrSearch_Job_Reindex');
            }
        }
    }

    /**
     * Display the import associated items form.
     */
    public function associateAction()
    {
        $form = new TeiEditions_Form_Associate();
        $this->view->form = $form;

        if ($this->getRequest()->isPost()) {
            $done = 0;
            try {
                $importer = new TeiEditions_Helpers_DataImporter(get_db());
                $importer->associateItems(
                    $_FILES["file"]["tmp_name"],
                    $_FILES["file"]["name"],
                    $_FILES["file"]["type"],
                    $done
                );
                $this->_helper->flashMessenger(
                    __("Files successfully imported: $done"), 'success');
            } catch (Exception $e) {
                error_log($e->getTraceAsString());
                $this->_helper->_flashMessenger(
                    __('There was an error on the form: %s', $e->getMessage()), 'error');
            }

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
        $this->_helper->_flashMessenger(__('No file ID provided'), 'error');
        return;
    }

    public function archiveAction()
    {
        $form = new TeiEditions_Form_Archive();
        $this->view->form = $form;

        if ($this->getRequest()->isPost() and $form->isValid($_POST)) {
            $name = $form->getElement('type')->getValue()
                ? $form->getElement('type')->getValue()
                : 'tei';

            $date = date('c');
            $filename = "$name-$date.zip";

            # enable output of HTTP headers
            $options = new ZipStream\Option\Archive();
            $options->setFlushOutput(true);
            $options->setSendHttpHeaders(true);

            # create a new zipstream object
            $zip = new ZipStream\ZipStream($filename, $options);

            foreach (get_db()->getTable('Item')->findAll() as $item) {
                $files = [];
                switch ($name) {
                    case 'tei':
                        $files = tei_editions_get_main_tei($item)
                            ? [tei_editions_get_main_tei($item)]
                            : [];
                        break;
                    case 'associated':
                        $files = tei_editions_get_associated_files($item);
                        break;
                    case 'tei-all':
                        $files = tei_editions_get_all_xml_files($item);
                        break;
                }

                foreach ($files as $file) {
                    // FIXME: add directly from stream?
                    if (($data = file_get_contents($file->getWebPath())) !== false) {
                        _log("Adding to zip: " . $file->getWebPath());
                        $zip->addFile($file->original_filename, $data);
                    } else {
                        // Should we throw an exception here???
                        _log("Unable to read URL: " . $file->getWebPath(), Zend_Log::ERR);
                    }
                }
            }

            # finish the zip stream
            $zip->finish();
        }
    }

    public function enhanceAction()
    {
        $form = new TeiEditions_Form_Enhance();
        $this->view->form = $form;

        if ($this->getRequest()->isPost() and $form->isValid($_POST)) {

            $name = $_FILES["file"]["name"];
            $path = $_FILES["file"]["tmp_name"];
            $mime = $_FILES["file"]["type"];
            $base = pathinfo($name, PATHINFO_FILENAME);
            $ext = pathinfo($name, PATHINFO_EXTENSION);

            $dict_path = $_FILES["dict"]["tmp_name"];
            $lang = $form->getValue("lang");

            $added = 0;

            $opts = [];
            if ($geonames_user = get_option('tei_editions_geonames_user')) {
                $opts['geonames_user'] = $geonames_user;
            }
            $src = new TeiEditions_Helpers_DataFetcher($dict_path, $lang, $opts);
            $enhancer = new TeiEditions_Helpers_TeiEnhancer($src);
            $batchEnhancer = new TeiEditions_Helpers_BatchEnhancer($enhancer);
            $out_path = $batchEnhancer->enhance($path, $mime, $added);

            header("Content-Type: $mime");
            header("Content-Disposition: attachment; filename=$base-added-$added.$ext");
            header("Content-Length: " . filesize($out_path));
            ob_end_flush();
            readfile($out_path);
            unlink($out_path);
            exit();
        }
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
}
