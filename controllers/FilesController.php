<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */

require_once dirname(__DIR__) . '/forms/TeiEditions_IngestForm.php';
require_once dirname(__DIR__) . '/forms/TeiEditions_UpdateForm.php';
require_once dirname(__DIR__) . '/forms/TeiEditions_AssociateForm.php';
require_once dirname(__DIR__) . '/forms/TeiEditions_ArchiveForm.php';
require_once dirname(__DIR__) . '/forms/TeiEditions_EnhanceForm.php';
require_once dirname(__DIR__) . '/jobs/TeiEditions_Job_DataImporter.php';


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
        $form = new TeiEditions_IngestForm();
        $this->view->form = $form;
        $this->_processIngestForm($form);
        // Clear and reindex.
        Zend_Registry::get('job_dispatcher')->sendLongRunning('SolrSearch_Job_Reindex');
    }

    /**
     * Display the data update form.
     *
     * @throws Zend_Form_Exception
     */
    public function updateAction()
    {
        // Set the created by user ID.
        $form = new TeiEditions_UpdateForm();
        $this->view->form = $form;
        $this->_processUpdateForm($form);
        Zend_Registry::get('job_dispatcher')->sendLongRunning('SolrSearch_Job_Reindex');
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
            try {
                $importer = new TeiEditions_DataImporter(get_db());
                $importer->associateItems(
                    $_FILES["file"]["tmp_name"],
                    $_FILES["file"]["name"],
                    $_FILES["file"]["type"],
                    $done
                );
                $this->_helper->flashMessenger(
                    __("Files successfully imported: $done"), 'success');
            } catch (Exception $e) {
                echo $e->getTraceAsString();
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
        $form = new TeiEditions_ArchiveForm();
        $this->view->form = $form;

        if ($this->getRequest()->isPost() and $form->isValid($_POST)) {
            $associated = $form->getElement('type')->getValue() === 'associated';
            $tmp = tempnam(sys_get_temp_dir(), '');
            $archive = new ZipArchive();
            $archive->open($tmp, ZipArchive::CREATE);

            $name = $associated ? 'associated' : 'tei';
            $date = date('c');
            $filename = "${name}-${date}.zip";

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
            header("Content-Type: application/zip");
            header("Content-Disposition: attachment; filename=$filename");
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

            $added = 0;
            $name = $_FILES["file"]["name"];
            $base = pathinfo($name, PATHINFO_FILENAME);
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $path = $_FILES["file"]["tmp_name"];
            $mime = $_FILES["file"]["type"];

            $dict_path = $_FILES["dict"]["tmp_name"];
            $lang = $form->getValue("lang");

            $enhancer = new TeiEditions_BatchEnhancer();
            $out_path = $enhancer->enhance($path, $mime, $dict_path, $lang, $added);

            header("Content-Type: $mime");
            header("Content-Disposition: attachment; filename=${base}-added-${added}.$ext");
            header("Content-Length: " . filesize($out_path));
            ob_end_flush();
            readfile($out_path);
            unlink($out_path);
            exit();
        }
    }

    /**
     * Process the import form.
     * @throws Zend_File_Transfer_Exception
     */
    private function _processIngestForm(TeiEditions_IngestForm $form)
    {
        if ($this->getRequest()->isPost()) {
            if (!$form->isValid($_POST)) {
                error_log("Errors: ". json_encode($form->getErrors()));
                $this->_helper->_flashMessenger(__('There was an error on the form. Please try again.'), 'error');
                return;
            }

            $created = 0;
            $updated = 0;

            $name = $_FILES["file"]["name"];
            $mime = $_FILES["file"]["type"];

            if(($temp = $this->_tempDir()) === false) {
                $this->_helper->_flashMessenger(
                        __('There was an error creating a temporary processing location', 'error'));
                return;
            }

            $path = $temp . DIRECTORY_SEPARATOR . $name;
            move_uploaded_file($_FILES["file"]["tmp_name"], $path);

            $dict_path = null;
            if ($form->getElement("enhance") && isset($_FILES["enhance_dict"])) {
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
            //Zend_Registry::get('job_dispatcher')->sendLongRunning(
            //    'TeiEditions_Job_DataImporter', [
            //        "path" => $path,
            //        "mime" => $mime,
            //        "dict_path" => $dict_path,
            //        "neatline" => $form->getElement('create_exhibit')->isChecked(),
            //        "enhance" => $form->getElement('enhance')->isChecked(),
            //        "lang" => $form->getValue('enhance_lang'),
            //    ]
            //);

            try {
                $importer = new TeiEditions_DataImporter(get_db());
                $importer->importData(
                    $path,
                    $mime,
                    $form->getElement('create_exhibit')->isChecked(),
                    $form->getElement('enhance')->isChecked(),
                    $dict_path,
                    $form->getValue('enhance_lang'),
                    $created,
                    $updated,
                    function () {
                        _log("Import complete");
                    }
                );

                $this->_helper->flashMessenger(__("TEIs successfully created: $created, updated: $updated"), 'success');
            } catch (Exception $e) {
                $this->_helper->_flashMessenger(__('There was an error on the form: %s', $e->getMessage()), 'error');
            } finally {
                $this->_deleteDir($temp);
            }
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

            $updated = 0;
            try {
                $neatline = $form->getElement('create_exhibit')->isChecked();
                $importer = new TeiEditions_DataImporter(get_db());
                $importer->updateItems($form->getSelectedItems(), $neatline, $updated);
                $this->_helper->flashMessenger(__("TEI items updated: $updated"), 'success');
            } catch (Exception $e) {
                error_log($e->getTraceAsString());
                $this->_helper->_flashMessenger(
                    __("There was an error: %s", $e->getMessage()), 'error');
            }
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
