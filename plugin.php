<?php

/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */

if (!function_exists('exception_error_handler')) {
    function exception_error_handler($severity, $message, $file, $line)
    {
        if (!(error_reporting() & $severity)) {
            // This error code is not included in error_reporting
            return;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
}
// NB: Getting notices and want an exception to find out where they're coming from?
// Uncomment this line:
//set_error_handler("exception_error_handler");


if (!defined('TEI_EDITIONS_DIR')) define('TEI_EDITIONS_DIR', __DIR__);

require_once TEI_EDITIONS_DIR . '/vendor/autoload.php';

require_once TEI_EDITIONS_DIR . '/TeiEditionsPlugin.php';

require_once TEI_EDITIONS_DIR . '/models/TeiEditionsFieldMapping.php';
require_once TEI_EDITIONS_DIR . '/models/TeiEditionsFieldMappingTable.php';
require_once TEI_EDITIONS_DIR . '/models/TeiEditionsEntity.php';

require_once TEI_EDITIONS_DIR . '/forms/TeiEditions_Form_Archive.php';
require_once TEI_EDITIONS_DIR . '/forms/TeiEditions_Form_Associate.php';
require_once TEI_EDITIONS_DIR . '/forms/TeiEditions_Form_Enhance.php';
require_once TEI_EDITIONS_DIR . '/forms/TeiEditions_Form_Import.php';
require_once TEI_EDITIONS_DIR . '/forms/TeiEditions_Form_Update.php';

require_once TEI_EDITIONS_DIR . '/helpers/TeiEditions_Helpers_Functions.php';
require_once TEI_EDITIONS_DIR . '/helpers/TeiEditions_Helpers_View.php';
require_once TEI_EDITIONS_DIR . '/helpers/TeiEditions_Helpers_DataImporter.php';
require_once TEI_EDITIONS_DIR . '/helpers/TeiEditions_Helpers_DocumentProxy.php';
require_once TEI_EDITIONS_DIR . '/helpers/TeiEditions_Helpers_DataFetcher.php';
require_once TEI_EDITIONS_DIR . '/helpers/TeiEditions_Helpers_BatchEnhancer.php';
require_once TEI_EDITIONS_DIR . '/helpers/TeiEditions_Helpers_ImportError.php';

$teiEditions = new TeiEditionsPlugin();
$teiEditions->setUp();