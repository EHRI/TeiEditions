<?php

/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */

if (!defined('TEI_EDITIONS_DIR')) define('TEI_EDITIONS_DIR', __DIR__);

require_once TEI_EDITIONS_DIR . '/vendor/autoload.php';

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

require_once TEI_EDITIONS_DIR . '/jobs/TeiEditions_Job_DataImporter.php';

$teiEditions = new TeiEditionsPlugin();
$teiEditions->setUp();

