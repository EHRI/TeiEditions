<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */

require_once __DIR__ . "/../helpers/TeiEditions_Helpers_DocumentProxy.php";
require_once __DIR__ . "/../helpers/TeiEditions_Helpers_DataFetcher.php";
require_once __DIR__ . "/../helpers/TeiEditions_Helpers_TeiEnhancer.php";

// If we're running interactively...
if (!count(debug_backtrace())) {

    $name = array_shift($argv);
    $lang = "eng";
    $dict = [];
    $posargs = [];
    while ($arg = array_shift($argv)) {
        switch ($arg) {
            case "-l":
            case "--lang":
                $lang = array_shift($argv);
                break;
            case "-d":
            case "--dict":
                $dict_file = array_shift($argv);
                $doc = TeiEditions_Helpers_DocumentProxy::fromUriOrPath($dict_file);
                foreach ($doc->entities() as $entity) {
                    $dict[$entity->ref()] = $entity;
                }
                break;
            case "-h":
            case "--help":
                print("usage: $name [-l|--lang [LANG]] input [output]\n");
                exit(1);
            default:
                array_push($posargs, $arg);
        }
    }

    // Check availability of TEI file
    if (!isset($posargs[0])) {
        die("Input file not defined. The script requires a parameter with path to the TEI file.\n");
    }

    // read TEI file
    $in_file = $posargs[0];
    $tei = TeiEditions_Helpers_DocumentProxy::fromUriOrPath($in_file);
    $src = new TeiEditions_Helpers_DataFetcher($dict, $lang);
    $enhancer = new TeiEditions_Helpers_TeiEnhancer($tei, $src);
    $enhancer->addReferences();

    // save the resulting TEI to output file or print
    // to stdout
    if (count($posargs) > 1) {
        $tei->document()->save($posargs[1]) or exit(2);
    } else {
        print($tei->document()->saveXML());
    }
    exit(0);
}
