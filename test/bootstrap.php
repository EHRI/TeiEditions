<?php

define('TEI_EDITIONS_DIR', dirname(__DIR__));
define('TEI_EDITIONS_TEST_DIR', TEI_EDITIONS_DIR.'/test');

if ($omeka = getenv('OMEKA_DIR')) {
    define('OMEKA_DIR', $omeka);

    // Bootstrap Omeka.
    require_once OMEKA_DIR.'/application/tests/bootstrap.php';

    // Base test case.
    require_once 'TeiEditions_Case_Default.php';

    // Load other files.
    require_once TEI_EDITIONS_DIR . '/helpers/TeiEditions_Helpers_Functions.php';
} else {
    error_log("NB: 'OMEKA_DIR' not defined, integration tests will fail");
}
