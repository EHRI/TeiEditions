<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2018 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

include_once dirname(__FILE__) . "/../models/TeiEditionsDocumentProxy.php";
include_once dirname(__FILE__) . "/../models/TeiEditionsDataFetcher.php";
include_once dirname(__FILE__) . "/../models/TeiEditionsTeiEnhancer.php";

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
                $doc = new TeiEditionsDocumentProxy($dict_file);
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
    $tei = simplexml_load_file($in_file) or exit("Couldn't load the TEI file.");
    $src = new TeiEditionsDataFetcher($dict, $lang);
    $enhancer = new TeiEditionsTeiEnhancer($tei, $src);
    $enhancer->addReferences();

    // save the resulting TEI to output file or print
    // to stdout
    if (count($posargs) > 1) {
        $tei->asXML($posargs[1]);
    } else {
        print($tei->asXML());
    }
}
