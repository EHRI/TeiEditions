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
        'define_acl', 'define_routes', 'config_form', 'config');

    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array('admin_navigation_main',
        'public_navigation_main', 'search_record_types', 'page_caching_whitelist',
        'page_caching_blacklist_for_record');

    /**
     * @var array Options and their default values.
     */
    protected $_options = array(
    );

    /**
     * Install the plugin.
     */
    public function hookInstall()
    {
        // Create the table.
        $db = $this->_db;
        $sql = "
        CREATE TABLE IF NOT EXISTS `$db->TeiEdition` (
          `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `item_id` int(10) unsigned NOT NULL,
          `modified_by_user_id` int(10) unsigned NOT NULL,
          `created_by_user_id` int(10) unsigned NOT NULL,
          `is_published` tinyint(1) NOT NULL,
          `title` tinytext COLLATE utf8_unicode_ci NOT NULL,
          `slug` tinytext COLLATE utf8_unicode_ci NOT NULL,
          `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `inserted` timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
          `template` tinytext COLLATE utf8_unicode_ci NOT NULL,
          PRIMARY KEY (`id`),
          KEY `item_id` (`item_id`),
          KEY `is_published` (`is_published`),
          KEY `inserted` (`inserted`),
          KEY `updated` (`updated`),
          KEY `created_by_user_id` (`created_by_user_id`),
          KEY `modified_by_user_id` (`modified_by_user_id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        $db->query($sql);

        $this->_installOptions();
    }

    /**
     * Uninstall the plugin.
     */
    public function hookUninstall()
    {
        // Drop the table.
        $db = $this->_db;
        $sql = "DROP TABLE IF EXISTS `$db->TeiEdition`";
        $db->query($sql);

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
     * Define the ACL.
     * 
     * @param Omeka_Acl
     */
    public function hookDefineAcl($args)
    {
        $acl = $args['acl'];
        
        $indexResource = new Zend_Acl_Resource('TeiEditions_Index');
        $pageResource = new Zend_Acl_Resource('TeiEdition');
        $acl->add($indexResource);
        $acl->add($pageResource);

        $acl->allow(array('super', 'admin'), array('TeiEditions_Index', 'TeiEdition'));
        $acl->allow(null, 'TeiEdition', 'show');
        $acl->deny(null, 'TeiEdition', 'show-unpublished');
    }

    /**
     * Add the routes for accessing simple pages by slug.
     * 
     * @param Zend_Controller_Router_Rewrite $router
     */
    public function hookDefineRoutes($args)
    {
        // Don't add these routes on the admin side to avoid conflicts.
        if (is_admin_theme()) {
            return;
        }

        $router = $args['router'];

        // Add custom routes based on the page slug.
//        $editions = get_db()->getTable('TeiEdition')->findAll();
//        foreach ($editions as $edition) {
//            error_log("Adding edition " . $edition->slug . " as " . $edition->id);
//            $router->addRoute(
//                'tei_editions_show_edition_' . $edition->id,
//                new Zend_Controller_Router_Route(
//                    ":controller/:slug",
//                    array(
//                        'module'       => 'tei-editions',
//                        'controller'   => 'edition',
//                        'action'       => 'show',
//                        'id'           => $edition->id,
//                        'slug'         => $edition->slug
//                    )
//                )
//            );
//        }
    }

    /**
     * Display the plugin config form.
     */
    public function hookConfigForm()
    {
        require dirname(__FILE__) . '/config_form.php';
    }

    /**
     * Set the options from the config form input.
     */
    public function hookConfig()
    {
        //set_option('tei_editions_filter_page_content', (int)(boolean)$_POST['tei_editions_filter_page_content']);
    }

    /**
     * Add the Simple Pages link to the admin main navigation.
     * 
     * @param array Navigation array.
     * @return array Filtered navigation array.
     */
    public function filterAdminNavigationMain($nav)
    {
        $nav[] = array(
            'label' => __('TEI Editions'),
            'uri' => url('tei-editions'),
            'resource' => 'TeiEditions_Index',
            'privilege' => 'browse'
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
        return $nav;
    }

    /**
     * Add TeiEditionsPage as a searchable type.
     */
    public function filterSearchRecordTypes($recordTypes)
    {
        return $recordTypes;
    }

    /**
     * Specify the default list of urls to whitelist
     * 
     * @param $whitelist array An associative array urls to whitelist, 
     * where the key is a regular expression of relative urls to whitelist 
     * and the value is an array of Zend_Cache front end settings
     * @return array The whitelist
     */
    public function filterPageCachingWhitelist($whitelist)
    {
        return $whitelist;
    }

    /**
     * Add pages to the blacklist
     * 
     * @param $blacklist array An associative array urls to blacklist, 
     * where the key is a regular expression of relative urls to blacklist 
     * and the value is an array of Zend_Cache front end settings
     * @param $record
     * @param $args Filter arguments. contains:
     * - record: the record
     * - action: the action
     * @return array The blacklist
     */
    public function filterPageCachingBlacklistForRecord($blacklist, $args)
    {
        return $blacklist;
    }

    public function filterApiResources($apiResources)
    {
       return $apiResources;
    }
    
    public function filterApiImportOmekaAdapters($adapters, $args)
    {
        return $adapters;
    }
}
