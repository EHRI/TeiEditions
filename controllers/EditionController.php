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
    public function showAction()
    {
        // Get the page object from the passed ID.
        $editionId = $this->_getParam('id');
        $edition = $this->_helper->db->getTable('TeiEdition')->find($editionId);

        error_log("Rendering! " . $edition->slug);
        
        // Restrict access to the page when it is not published.
//        if (!$edition->is_published
//            && !$this->_helper->acl->isAllowed('show-unpublished')) {
//            throw new Omeka_Controller_Exception_403;
//        }

        // Set the page object to the view.
        $this->view->tei_edition = $edition;
    }
}
