<?php

/**
 * Check the versions of the tool matches in various files.
 */
$json = json_decode(file_get_contents(__DIR__.'/../package.json'));
$ini = parse_ini_file(__DIR__.'/../plugin.ini');

if ($json->version !== $ini['version']) {
    error_log(sprintf("Version mismatch: package.json: '%s', plugin.ini: '%s'", $json->version, $ini['version']));
    exit(1);
}
