<?php
/**
 * Omeka TEI Editions
 *
 * @copyright Copyright 2021 King's College London, Department of Digital Humanities
 */

/**
 * TeiEditions Plugin class
 */
class TeiEditionsPlugin extends Omeka_Plugin_AbstractPlugin
{

    public static $DC_MAPPINGS = [
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

    public static $ITEM_TYPE_MAPPINGS = [
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
        'public_navigation_main',
        'item_previous',
        'item_next'
    );

    /**
     * @var array Options and their default values.
     */
    protected $_options = array();

    public function setUp()
    {
        add_shortcode('editions_item', 'tei_editions_item_shortcode');
        add_shortcode('editions_items', 'tei_editions_items_shortcode');
        add_shortcode('editions_recent_items', 'tei_editions_recent_items_shortcode');
        add_shortcode('editions_index', 'tei_editions_index_shortcode');
        parent::setUp();
    }

    /**
     * Create a new XPath-to-element mapping.
     *
     * @param string $element_id the element id
     * @param string $xpath the XPath
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
     * Create a new element
     *
     * @param string $name the element name
     * @param string $description the element description
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
     * Retrieve or create a new item type.
     *
     * @param string $name the item type name
     * @param string $description a description
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
     * Create mappings for DC metadata
     *
     * @param array $dc_mappings
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
     * Create mappings for Item Type metadata
     *
     * @param array $item_type_mappings
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


        $this->_db->getAdapter()->beginTransaction();
        try {
            $this->createDublinCoreMappings(TeiEditionsPlugin::$DC_MAPPINGS);
            $this->createItemTypeMappings(TeiEditionsPlugin::$ITEM_TYPE_MAPPINGS);
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

    /**
     * @param array $args
     * @throws Zend_Config_Exception
     */
    public function hookDefineRoutes($args)
    {
        $args['router']->addConfig(new Zend_Config_Ini(
                __DIR__ . "/routes.ini")
        );
    }

    /**
     * Display the plugin config form.
     */
    public function hookConfigForm()
    {
        require __DIR__ . '/config_form.php';
    }

    /**
     * Define the ACL.
     *
     * @param array $args
     */
    public function hookDefineAcl($args)
    {
        $acl = $args['acl'];

        $mappingResource = new Zend_Acl_Resource('TeiEditionsFieldMapping');
        $acl->add($mappingResource);
    }

    /**
     * Set the options from the config form input.
     */
    public function hookConfig()
    {
        set_option('tei_editions_default_item_type', (int)$_POST['tei_editions_default_item_type']);
        set_option('tei_editions_template_neatline', (int)$_POST['tei_editions_template_neatline']);
        set_option('tei_editions_geonames_username', $_POST['tei_editions_geonames_username']);
    }

    public function hookPublicHead($args)
    {
        // prevent text widgets showing on Neatline embedded maps by adding
        // a param: neatline-embed=true
        if (array_key_exists("neatline-embed", $_GET)) {
            queue_css_file('neatline-overrides', $media = "all", $conditional = false, $dir = 'css');
        }
    }

    /**
     * Add a link to the administrative navigation bar.
     *
     * @param array $nav The array of label/URI pairs.
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

    /**
     * Override item previous to exclude non-public items.
     *
     * @param $unused
     * @param array $args
     * @return Omeka_Record_AbstractRecord
     */
    public function filterItemPrevious($unused, array $args)
    {
        $item = $args['item'];

        $table = $this->_db->getTable('Item');
        $select = $table->getSelect()
            ->limit(1)
            ->where('items.id < ? AND items.public', $item->id);
        $this->_filterSolrExcludes($select);
        $select->order('items.id DESC');
        return $table->fetchObject($select);
    }

    /**
     * Override item next to exclude non-public items.
     *
     * @param $unused
     * @param array $args
     * @return Omeka_Record_AbstractRecord
     */
    public function filterItemNext($unused, array $args)
    {
        $item = $args['item'];

        $table = $this->_db->getTable('Item');
        $select = $table->getSelect()
            ->limit(1)
            ->where('items.id > ? AND items.public', $item->id);
        $this->_filterSolrExcludes($select);
        $select->order('items.id ASC');
        return $table->fetchObject($select);
    }

    /**
     * If the Solr plugin is enabled, exclude items from
     * previous/next that are excluded from indexing.
     *
     * @param Zend_Db_Select $select
     * @return Zend_Db_Select
     */
    private function _filterSolrExcludes(Zend_Db_Select $select)
    {
        return !plugin_is_active('SolrSearch')
            ? $select
            : $select->where("(
                        items.collection_id IS NULL 
                    OR items.collection_id NOT IN (
                        SELECT collection_id FROM {$this->_db->prefix}solr_search_excludes
                    )
                )");
    }
}
