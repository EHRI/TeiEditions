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
        'define_routes', 'config_form', 'config',
        'after_save_item');

    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array('public_navigation_main');

    /**
     * @var array Options and their default values.
     */
    protected $_options = array();

    /**
     * Install the plugin.
     */
    public function hookInstall()
    {
        $this->_installOptions();
    }

    /**
     * Uninstall the plugin.
     */
    public function hookUninstall()
    {
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
