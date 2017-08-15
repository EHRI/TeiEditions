<?php
/**
 * Omeka TEI Editions
 *
 * @copyright Copyright 2017 King's College London, Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

require_once dirname(__FILE__) . '/helpers/TeiEditionsFunctions.php';

/**
 * Simple Pages plugin.
 */
class TeiEditionsPlugin extends Omeka_Plugin_AbstractPlugin
{
    /**
     * @var array Hooks for the plugin.
     */
    protected $_hooks = array('install', 'uninstall', 'upgrade', 'initialize',
        'define_acl', 'define_routes', 'config_form', 'config',
        'after_save_item');

    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array(
        'admin_navigation_main',
        'public_navigation_main'
    );

    /**
     * @var array Options and their default values.
     */
    protected $_options = array();

    /**
     * Install the plugin.
     */
    public function hookInstall()
    {
        /*
         * TODO: Need to create TEI item type and
         *  add associations for new Elements:
         *
         *   Author
         *       The TEI author.
         *   Source Details
         *       Details about the document source.
         *   Encoding Description
         *       A description of the encoding process.
         *   Publisher
         *       The TEI publisher.
         *   Publication Date
         *       The TEI's date of publication.
         *   Subjects
         *       Subjects mentioned in this text.
         *   Places
         *       Places mentioned in this text.
         *   Persons
         *       People mentioned in this text.
         *   XML Text
         *       The body text of the TEI document.
         *
         * Also:
         *
         *  - ensure that file types 'xml' are allowed
         *  - ensure that mimetypes 'application/xml' is allowed
         *
         * Also add default mappings, e.g.
         *
         *   "Persons" => "/tei:TEI/tei:teiHeader/tei:profileDesc/tei:abstract/tei:persName",
         *   "Subjects" => "/tei:TEI/tei:teiHeader/tei:profileDesc/tei:abstract/tei:term",
         *   "Places" => "/tei:TEI/tei:teiHeader/tei:profileDesc/tei:abstract/tei:placeName",
         *   "XML Text" => "/tei:TEI/tei:text",
         *   "Source Details" => "/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc",
         *   "Encoding Description" => "/tei:TEI/tei:teiHeader/tei:encodingDesc/tei:projectDesc",
         *   "Publisher" => "/tei:TEI/tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:publisher",
         *   "Publication Date" => "/tei:TEI/tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:date",
         *   "Author" => "/tei:TEI/tei:teiHeader/tei:fileDesc/tei:titleStmt/tei:author",
         *
         */
        $this->_db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS {$this->_db->prefix}tei_editions_field_mappings (
            id          int(10) unsigned NOT NULL auto_increment,
            element_id  int(10) unsigned NOT NULL,
            path        tinytext collate utf8_unicode_ci NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SQL
        );


        $this->_installOptions();
    }

    /**
     * Uninstall the plugin.
     */
    public function hookUninstall()
    {
        $this->_db->query(
            "DROP TABLE IF EXISTS {$this->_db->prefix}tei_editions_field_mappings");

        $this->_uninstallOptions();
    }

    /**
     * Upgrade the plugin.
     *
     * @param array $args contains: 'old_version' and 'new_version'
     */
    public function hookUpgrade($args)
    {
    }

    /**
     * Add the translations.
     */
    public function hookInitialize()
    {
    }

    public function hookDefineRoutes($args)
    {
        $args['router']->addConfig(new Zend_Config_Ini(
            dirname(__FILE__) . '/routes.ini'
        ));
    }

    /**
     * Display the plugin config form.
     */
    public function hookConfigForm()
    {
        require dirname(__FILE__) . '/config_form.php';
    }

    /**
     * Define the ACL.
     *
     * @param Omeka_Acl
     */
    public function hookDefineAcl($args)
    {
        $acl = $args['acl'];

        $mappingResource = new Zend_Acl_Resource('TeiEditions_FieldMapping');
        $acl->add($mappingResource);
    }

    /**
     * Set the options from the config form input.
     */
    public function hookConfig()
    {
        //set_option('tei_editions_filter_page_content', (int)(boolean)$_POST['tei_editions_filter_page_content']);
    }

    public function hookAfterSaveItem($args)
    {
        if ($item = $args["record"]) {
            if ($item->getProperty('item_type_name') == "TEI") {
                set_tei_metadata($item);
            }
        }
    }

    /**
     * Add a link to the administrative navigation bar.
     *
     * @param string $nav The array of label/URI pairs.
     * @return array
     */
    public function filterAdminNavigationMain($nav)
    {
        $nav[] = array(
            'label' => __('Editions'),
            'uri' => url('editions/fields')
        );
        return $nav;
    }

    /**
     * Add the pages to the public main navigation options.
     *
     * @param array Navigation array.
     * @return array Filtered navigation array.
     */
    public function filterPublicNavigationMain($nav)
    {
        array_unshift($nav, array(
            'label' => __('Editions'),
            'uri' => url('editions')
        ));
        return $nav;
    }
}
