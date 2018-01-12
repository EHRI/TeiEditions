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
        $form = $this->_getForm();
        $this->view->form = $form;
        $this->_processFieldForm($form, 'import');
    }

    private function _getForm()
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

    /**
     * Process the page edit and edit forms.
     */
    private function _processFieldForm($form, $action)
    {
        if ($this->getRequest()->isPost()) {
            if (!$form->isValid($_POST)) {
                $this->_helper->_flashMessenger(__('There was an error on the form. Please try again.'), 'error');
                return;
            }

            $tx = get_db()->getAdapter()->beginTransaction();
            try {

                $item = new Item;
                $xpaths = TeiEditionsFieldMapping::fieldMappings();
                $path = $_FILES["file"]["tmp_name"];
                $data = @tei_editions_extract_metadata($path, $xpaths);
                error_log("Extracted from " . $path . " -> " .
                    json_encode($data, JSON_PRETTY_PRINT));

                $item->addElementTextsByArray($data);
                $item->save();
                @insert_files_for_item($item, "Upload", "file");

                if ($form->getElement('create_exhibit')->isChecked()) {
                    $geo = array_unique(@tei_editions_get_item_places($item), SORT_REGULAR);

                    $exhibit = new NeatlineExhibit;
                    $title = metadata($item, 'display_title');
                    $exhibit->title = $title;
                    $exhibit->slug = $this->slugify($title);
                    $exhibit->spatial_layer = 'OpenStreetMap';
                    $exhibit->save(true);

                    $points = array();
                    foreach ($geo as $teiPlace) {
                        $place = new NeatlineRecord;
                        $place->exhibit_id = $exhibit->id;
                        $place->title = $teiPlace["name"];
                        $place->item_id = $item->id;
                        $metres = tei_editions_degrees_to_metres(
                            array($teiPlace["longitude"], $teiPlace["latitude"]));
                        $points[] = $metres;
                        $place->coverage = "Point(" . implode(" ", $metres) . ")";
                        $place->save();
                    }
                    $exhibit->map_focus = implode(",", tei_editions_centre_points($points));
                    $exhibit->map_zoom = 7; // guess?
                    $exhibit->save(true);

                }
                $tx->commit();
            } catch (Exception $e) {
                error_log($e->getTraceAsString());
                $tx->rollBack();
                $this->_helper->_flashMessenger(
                    __('There was an error on the form: ' . $e->getMessage()), 'error');
                return;
            }

            $this->_helper->flashMessenger(
                __('The TEI was successfully imported!'), 'success');

            $this->_helper->redirector($item->id, 'show', "items");
        }
    }

    private function slugify($text)
    {
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        return strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $text));
    }
}
