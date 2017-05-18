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
class TeiEditions_IndexController extends Omeka_Controller_AbstractActionController
{    
    public function init()
    {
        // Set the model class so this controller can perform some functions, 
        // such as $this->findById()
        $this->_helper->db->setDefaultModelName('TeiEdition');
    }
    
    public function indexAction()
    {
        // Always go to browse.
        $this->_helper->redirector('browse');
        return;
    }

    function endsWith($haystack, $needle) {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }

    function replaceUrls($xml, $file_map) {
        foreach ($file_map as $key => $value) {
            $find = "../images/$key";
            error_log("Replacing " . $find . " with " . $value);
            $xml = str_replace($find, $value, $xml);
        }
        return $xml;
    }

    public function showAction()
    {
        $editionId = $this->_getParam('id');
        $edition = $this->_helper->db->getTable('TeiEdition')->find($editionId);

        error_log($_SERVER['REQUEST_URI']);
        $teipb = implode(array('http://localhost',
            'omeka', 'plugins', 'TeiEditions', 'teibp', 'content', 'teibp.xsl'), '/');

        $xsldoc = new DOMDocument();
        $xsldoc->loadXML(file_get_contents($teipb));
        $xsldoc->documentURI = $teipb;

        $xml = "";

        $file_map = [];
        foreach ($edition->getItem()->getFiles() as $file) {
            $file_map[basename($file->original_filename)] = $file->getWebPath();
        }

        foreach ($edition->getItem()->getFiles() as $file) {

            $path = $file->getWebPath();

            if ($this->endsWith($path, ".xml")) {
                $xmldoc = new DOMDocument();
                $xmldoc->loadXML($this->replaceUrls(file_get_contents($path), $file_map));
                $xmldoc->documentURI = $path;

                $proc = new XSLTProcessor;
                $proc->importStylesheet($xsldoc);
                $xml .= $proc->transformToXml($xmldoc);

                break;
            }
        }


        // Set the page object to the view.
        $this->view->assign(array(
            'tei_edition' => $edition,
            'xml' => $xml,
            'xsl' => $teipb
        ));
    }

    public function addAction()
    {
        // Create a new edition.
        $edition = new TeiEdition();
        
        // Set the created by user ID.
        $edition->created_by_user_id = current_user()->id;
        $edition->template = '';
        $form = $this->_getForm($edition);
        $this->view->form = $form;
        $this->_processEditionForm($edition, $form, 'add');
    }
    
    public function editAction()
    {
        // Get the requested edition.
        $edition = $this->_helper->db->findById();
        $form = $this->_getForm($edition);
        $this->view->form = $form;
        $this->_processEditionForm($edition, $form, 'edit');
    }
    
    protected function _getForm($edition = null)
    { 
        $formOptions = array('type' => 'tei_edition', 'hasPublicPage' => true);
        if ($edition && $edition->exists()) {
            $formOptions['record'] = $edition;
        }
        
        $form = new Omeka_Form_Admin($formOptions);
        $form->addElementToEditGroup(
            'text', 'title',
            array(
                'id' => 'tei-editions-title',
                'value' => $edition->title,
                'label' => __('Title'),
                'description' => __('Name and heading for the edition (required)'),
                'required' => true
            )
        );
        
        $form->addElementToEditGroup(
            'text', 'slug',
            array(
                'id' => 'tei-editions-slug',
                'value' => $edition->slug,
                'label' => __('Slug'),
                'description' => __(
                    'The slug is the part of the URL for this edition. A slug '
                    . 'will be created automatically from the title if one is '
                    . 'not entered. Letters, numbers, underscores, dashes, and '
                    . 'forward slashes are allowed.'
                )
            )
        );

        // The pick an item drop-down select:
        $form->addElementToEditGroup(
            'select', 'item_id',
            array(
            "required" => true,
            'value' => $edition->item_id,
            'label' => __('Item'),
                'description' => __(
                    'The Omeka item which which the XML and image data will be derived.'
                ),
            'multiOptions' => $this->getItemsForSelect(),
        ));

        $form->addElementToSaveGroup(
            'checkbox', 'is_published',
            array(
                'id' => 'tei-editions-is-published',
                'values' => array(1, 0),
                'checked' => $edition->is_published,
                'label' => __('Publish this edition?'),
                'description' => __('Checking this box will make the edition public')
            )
        );

        if (class_exists('Omeka_Form_Element_SessionCsrfToken')) {
            $form->addElement('sessionCsrfToken', 'csrf_token');
        }
        
        return $form;
    }
    
    /**
     * Process the edition edit and edit forms.
     */
    private function _processEditionForm($edition, $form, $action)
    {
        // Set the edition object to the view.
        $this->view->tei_edition = $edition;

        if ($this->getRequest()->isPost()) {
            if (!$form->isValid($_POST)) {
                $this->_helper->_flashMessenger(__('There was an error on the form. Please try again.'), 'error');
                return;
            }
            try {
                $edition->setPostData($_POST);
                if ($edition->save()) {
                    if ('add' == $action) {
                        $this->_helper->flashMessenger(__('The edition "%s" has been added.', $edition->title), 'success');
                    } else if ('edit' == $action) {
                        $this->_helper->flashMessenger(__('The edition "%s" has been edited.', $edition->title), 'success');
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
        return __('The edition "%s" has been deleted.', $record->title);
    }

    private function getItemsForSelect() {
        $options = array("" => "");
        foreach (get_db()->getTable("Item")->findAll() as $item) {
            $options[$item->id] = metadata($item, array('Dublin Core', 'Title'));
        }
        return $options;
    }
}
