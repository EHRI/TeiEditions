<?php

class TeiEditions_Case_Default extends Omeka_Test_AppTestCase
{


    /**
     * Install TeiEditions and prepare the database.
     */
    public function setUpLegacy()
    {

        parent::setUpLegacy();

        // Create and authenticate user.
        $this->user = $this->db->getTable('User')->find(1);
        $this->_authenticateUser($this->user);

        // Install TeiEditions.
        $this->helper = new Omeka_Test_Helper_Plugin;
        $this->helper->setUp('TeiEditions');

        // Add XML to allowed extensions and mime types
        set_option('file_extension_whitelist', get_option('file_extension_whitelist') . ',xml,epub');
        set_option('file_mime_type_whitelist', get_option('file_mime_type_whitelist') . ',text/xml');

        // Get tables.
        $this->fieldMappingTable       = $this->db->getTable('TeiEditionsFieldMapping');
        $this->elementSetTable  = $this->db->getTable('ElementSet');
        $this->itemTable        = $this->db->getTable('Item');
        $this->elementTable     = $this->db->getTable('Element');
        $this->itemTypeTable    = $this->db->getTable('ItemType');

        // Apply `tei-editions.ini` values.
        $this->_applyTestingOptions();

    }


    /**
     * Teardown tests
     */
    public function tearDownLegacy()
    {
        if ($items = $this->itemTable->findAll()) {
            foreach ($items as $item) {
                $item->delete();
            }
        }
        parent::tearDownLegacy();
    }

    /**
     * Apply options defined in the `tei-editions.ini` file.
     */
    protected function _applyTestingOptions()
    {

        // Parse the config file.
        $this->config = new Zend_Config_Ini(__DIR__.'/tei-editions.ini');

        // Apply the testing values.
        set_option('tei_editions_default_item_type',  $this->config->default_item_type);
        set_option('tei_editions_template_neatline',  $this->config->template_neatline);
        set_option('tei_editions_geonames_username',  $this->config->geonames_username);

    }
}
