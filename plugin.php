<?php

/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */

if (!defined('TEI_EDITIONS_DIR')) define('TEI_EDITIONS_DIR', __DIR__);

require_once TEI_EDITIONS_DIR . '/vendor/autoload.php';

require_once TEI_EDITIONS_DIR . '/models/TeiEditions_FieldMapping.php';
require_once TEI_EDITIONS_DIR . '/models/TeiEditions_FieldMapping_Table.php';
require_once TEI_EDITIONS_DIR . '/models/TeiEditions_Entity.php';

require_once TEI_EDITIONS_DIR . '/forms/TeiEditions_ArchiveForm.php';
require_once TEI_EDITIONS_DIR . '/forms/TeiEditions_AssociateForm.php';
require_once TEI_EDITIONS_DIR . '/forms/TeiEditions_EnhanceForm.php';
require_once TEI_EDITIONS_DIR . '/forms/TeiEditions_IngestForm.php';
require_once TEI_EDITIONS_DIR . '/forms/TeiEditions_UpdateForm.php';

require_once TEI_EDITIONS_DIR . '/helpers/TeiEditions_Functions.php';
require_once TEI_EDITIONS_DIR . '/helpers/TeiEditions_View_Helpers.php';
require_once TEI_EDITIONS_DIR . '/helpers/TeiEditions_DataImporter.php';
require_once TEI_EDITIONS_DIR . '/helpers/TeiEditions_DocumentProxy.php';
require_once TEI_EDITIONS_DIR . '/helpers/TeiEditions_DataFetcher.php';

require_once TEI_EDITIONS_DIR . '/jobs/TeiEditions_Job_DataImporter.php';

$teiEditions = new TeiEditionsPlugin();
$teiEditions->setUp();

