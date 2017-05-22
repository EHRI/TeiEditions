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
class TeiEditions_EditionController extends Omeka_Controller_AbstractActionController
{

    public function init()
    {
        $this->_helper->db->setDefaultModelName('TeiEdition');
    }

//    public function browseAction()
//    {
//
//    }

    public function showAction()
    {
        $query = array('slug' => $this->_getParam('slug'), 'is_published' => true);
        $editions = $this->_helper->db->getTable('TeiEdition')->findBy($query);

        if (count($editions) == 0) {
            throw new Omeka_Controller_Exception_404;
        }

        $edition = $editions[0];

        $files = $edition->getItem()->getFiles();

        $file_url_map = array();
        foreach ($files as $file) {
            $file_url_map[basename($file->original_filename)] = $file->getWebPath();
        }

        $xml = "";
        foreach ($files as $file) {
            $path = $file->getWebPath();
            if (endswith($path, ".xml")) {
                $xml .= @prettify_tei($path, $file_url_map);
                break;
            }
        }

        // Set the page object to the view.
        $this->view->assign(array(
            'tei_edition' => $edition,
            'xml' => $xml
        ));
    }
}
