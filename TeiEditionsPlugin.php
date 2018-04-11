<?php
/**
 * Omeka TEI Editions
 *
 * @copyright Copyright 2017 King's College London, Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

require_once dirname(__FILE__) . '/helpers/TeiEditionsFunctions.php';
require_once dirname(__FILE__) . '/helpers/TeiEditionsEnhanceTei.php';
require_once dirname(__FILE__) . '/helpers/TeiEditionsViewHelpers.php';

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
        'public_head');

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

    public function setUp()
    {
        add_shortcode('editions_item', 'tei_editions_item_shortcode');
        parent::setUp();
    }

    /**
     * @param $element_id
     * @param $xpath
     * @return TeiEditionsFieldMapping
     * @throws Omeka_Record_Exception
     * @throws Omeka_Validate_Exception
     */
    private function createMapping($element_id, $xpath)
    {
        $mapping = new TeiEditionsFieldMapping;
        $mapping->path = $xpath;
        $mapping->element_id = $element_id;
        $mapping->save(true);
        return $mapping;
    }

    /**
     * @param $name
     * @param $description
     * @return Element
     * @throws Omeka_Record_Exception
     * @throws Omeka_Validate_Exception
     */
    private function createElement($name, $description)
    {
        $elem = new Element;
        $elem->setName($name);
        $elem->setDescription($description);
        $elem->setElementSet("Item Type Metadata");
        $elem->save(true);
        return $elem;
    }

    /**
     * @param $name
     * @return ItemType
     * @throws Omeka_Record_Exception
     * @throws Omeka_Validate_Exception
     */
    private function getOrCreateItemType($name, $description)
    {
        $types = get_db()->getTable('ItemType')->findBy(["name" => $name], 1);
        if ($types) {
            return $types[0];
        }

        // we need to create a new item type
        $type = new ItemType;
        $type->name = $name;
        $type->description = $description;
        $type->save(true);
        return $type;
    }

    /**
     * @param $dc_mappings
     * @return array
     * @throws Omeka_Record_Exception
     * @throws Omeka_Validate_Exception
     */
    private function createDublinCoreMappings($dc_mappings)
    {
        $dc_set = get_db()->getTable('ElementSet')
            ->findBy(["name" => "Dublin Core"], 1)[0];
        $dc_elements_to_ids = [];
        foreach ($dc_set->getElements() as $element) {
            $dc_elements_to_ids[$element->name] = $element->id;
        }
        foreach ($dc_mappings as $name => $xpaths) {
            foreach ($xpaths as $xpath) {
                $this->createMapping($dc_elements_to_ids[$name], $xpath);
            }
        }
        return array($element, $name, $xpath);
    }

    /**
     * @param $item_type_mappings
     * @throws Omeka_Record_Exception
     * @throws Omeka_Validate_Exception
     */
    private function createItemTypeMappings($item_type_mappings)
    {
        foreach ($item_type_mappings as $type_name => $data) {

            $type = $this->getOrCreateItemType($type_name, $data["description"]);
            $elements = get_db()->getTable('Element')->findByItemType($type->id);
            $elements_to_ids = [];
            foreach ($elements as $element) {
                $elements_to_ids[$element->name] = $element->id;
            }

            $elements_to_add = [];
            foreach ($data["mappings"] as $name => $details) {
                if (!isset($elements_to_ids[$name])) {
                    $elem = $this->createElement($name, $details["description"]);
                    $elements_to_ids[$name] = $elem->id;
                    $elements_to_add[] = $elem;
                }
            }
            if ($elements_to_add) {
                $type->addElements($elements_to_add);
                $type->save();
            }

            foreach ($data["mappings"] as $name => $config) {
                foreach ($config["xpaths"] as $xpath) {
                    $this->createMapping($elements_to_ids[$name], $xpath);
                }
            }
        }
    }

    /**
     * Install the plugin.
     * @throws Exception
     */
    public function hookInstall()
    {

        $this->_db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS {$this->_db->prefix}tei_editions_field_mappings (
            id          int(10) unsigned NOT NULL auto_increment,
            element_id  int(10) unsigned NOT NULL,
            path        tinytext collate utf8_unicode_ci NOT NULL,
            FOREIGN KEY (element_id) 
              REFERENCES {$this->_db->prefix}elements(id)
              ON DELETE CASCADE, 
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SQL
        );

        $dc_mappings = [
            "Identifier" => ["/tei:TEI/tei:teiHeader/tei:profileDesc/tei:creation/tei:idno"],
            "Title" => ["/tei:TEI/tei:teiHeader/tei:fileDesc/tei:titleStmt/tei:title"],
            "Subject" => ["/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:list/tei:item/tei:name"],
            "Description" => ["/tei:TEI/tei:teiHeader/tei:profileDesc/tei:abstract"],
            "Creator" => [
                "/tei:TEI/tei:teiHeader/tei:profileDesc/tei:creation/tei:persName",
                "/tei:TEI/tei:teiHeader/tei:profileDesc/tei:creation/tei:orgName"
            ],
            "Source" => [
                "/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:bibl",
                "/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:msDesc/tei:msIdentifier/tei:collection/@ref"
            ],
            "Publisher" => ["/tei:TEI/tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:publisher/tei:ref"],
            "Date" => ["/tei:TEI/tei:teiHeader/tei:profileDesc/tei:creation/tei:date/@when"],
            "Rights" => ["/tei:TEI/tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:availability/tei:licence"],
            "Format" => ["/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:msDesc/tei:physDesc"],
            "Language" => [
                "/tei:TEI/tei:teiHeader/tei:profileDesc/tei:langUsage/tei:language",
                "/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:bibl/tei:textLang"
            ],
            "Coverage" => ["/tei:TEI/tei:teiHeader/tei:profileDesc/tei:creation/tei:placeName"]
        ];

        $item_type_mappings = [
            "Text" => [
                "description" => "Text items",
                "mappings" => [
                    "Text" => [
                        "description" => "The TEI text",
                        "xpaths" => [
                            "/tei:TEI/tei:text/tei:body"
                        ]
                    ]
                ]
            ],
            "TEI" => [
                "description" => "TEI items",
                "mappings" => [
                    "Person" => [
                        "description" => "Persons mentioned in the text.",
                        "xpaths" => [
                            "/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:listPerson/tei:person/tei:persName"
                        ]
                    ],
                    "Organisation" => [
                        "description" => "Organisations mentioned in the text.",
                        "xpaths" => [
                            "/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:listOrg/tei:org/tei:orgName"
                        ]
                    ],
                    "Place" => [
                        "description" => "Places mentioned in the text.",
                        "xpaths" => [
                            "/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:listPlace/tei:place/tei:placeName"
                        ]
                    ]
                ]
            ]
        ];

        $this->_db->getAdapter()->beginTransaction();
        try {
            $this->createDublinCoreMappings($dc_mappings);
            $this->createItemTypeMappings($item_type_mappings);
            $this->_db->getAdapter()->commit();
        } catch (Exception $e) {
            $this->_db->getAdapter()->rollBack();
            throw $e;
        }

        $this->_installOptions();
    }

    /**
     * Uninstall the plugin.
     */
    public function hookUninstall()
    {
        $this->_db->query(
            "DROP TABLE IF EXISTS {$this->_db->prefix}tei_editions_field_mappings");

        $this->_db->getAdapter()->beginTransaction();

        $item_types = get_db()->getTable("ItemType")->findBy(['name' => 'TEI']);
        if (!empty($item_types)) {
            $type = $item_types[0];
            $elements = get_db()->getTable('Element')->findByItemType($type->id);
            foreach ($elements as $element) {
                $element->delete();
            }
            $type->delete();
        }
        $this->_db->getAdapter()->commit();

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
                dirname(__FILE__) . "/routes.ini")
        );
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
        set_option('tei_editions_default_item_type', (int)$_POST['tei_editions_default_item_type']);
    }

    public function hookPublicHead($args)
    {
        queue_css_file('editions', $media = "all", $conditional = false, $dir = 'css');
        queue_js_file("jquery.hoverIntent.min", $dir = "js");
        queue_js_file("editions", $dir = "js");
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
            'uri' => url('tei-editions')
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
