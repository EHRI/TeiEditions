<?php


/*
Extracts URIs of annotated/linked terms, people, organisations, places, ghettos and camps from TEI document,
fetches metadata from EHRI, Geonames and possibly other services
and adds normalised records to TEI Header.

TEI elements and services handled:
------------------------------

<placeName>
- Geonames: DONE
- EHRI camps and ghettos: TBD
- EHRI countries: TBD
- Wikidata: manually?
- Wikipedia: is used at all?

<personName>
- EHRI personalities: TBD
- Holocaust.cz: manually (no API yet)
- Yad Vashem victims database: manually (is there an API?)

<orgName>
- EHRI corporate bodies: TBD

<term>
- EHRI terms: DONE

*/

include_once dirname(__FILE__) . "/../models/TeiEditionsDataFetcher.php";
include_once dirname(__FILE__) . "/../models/TeiEditionsDocumentProxy.php";


function tei_editions_get_references(SimpleXMLElement $tei, $tag_name)
{
    $names = array();
    $urls = array();
    if (!($docid = (string)@$tei->xpath("/t:TEI/t:teiHeader/t:profileDesc/t:creation/t:idno/text()")[0])) {
        $docid = (string)$tei->xpath("/t:TEI/@xml:id")[0];
    }
    $idx = 0;
    $paths = [
        "/t:TEI/t:teiHeader/t:profileDesc/t:creation//t:$tag_name",
        "/t:TEI/t:text/t:body/*//t:$tag_name"
    ];
    foreach ($paths as $path) {
        $nodes = $tei->xpath($path);
        foreach ($nodes as $node) {
            $text = $node->xpath("text()");
            $ref = $node->xpath("@ref");
            if ($ref) {
                $urls[(string)($ref[0])] = (string)$text[0];
            } else {
                $idx++;
                $locUrl = "#" . $docid . "_" . $idx;
                $node->addAttribute("ref", $locUrl);
                $names[(string)($text[0])] = $locUrl;
            }
        }
    }

    return array_merge($names, array_flip($urls));
}

function tei_editions_add_entity(SimpleXMLElement $tei, $listTag, $itemTag, $nameTag, TeiEditionsEntity $entity)
{
    $source = $tei->teiHeader->fileDesc->sourceDesc;
    $list = $source->$listTag ? $source->$listTag : $source->addChild($listTag);

    $item = $list->addChild($itemTag);
    $item->addChild($nameTag, htmlspecialchars($entity->name));

    // Special case - if we have a local URL anchor, it refers to an xml:id
    // otherwise, add a link group.
    if ($entity->ref()[0] == '#') {
        $item->addAttribute("id", substr($entity->ref(), 1));
    } else if (!empty($entity->urls)) {
        $link_grp = $item->addChild('linkGrp');
        foreach ($entity->urls as $type => $url) {
            $link = $link_grp->addChild("link");
            $link->addAttribute("type", $type);
            $link->addAttribute("target", $url);
        }
    }
    if (!empty($entity->notes)) {
        $desc = $item->addChild("note");
        foreach ($entity->notes as $p) {
            $desc->addChild("p", htmlspecialchars($p));
        }
    }
    if ($entity->hasGeo()) {
        $location = $item->addChild('location');
        $location->addChild('geo', $entity->latitude . " " . $entity->longitude);
    }
}

function tei_editions_process_tei_places(SimpleXMLElement $tei, TeiEditionsDataFetcher $enhancer)
{
    // get place URLs
    $refs = tei_editions_get_references($tei, "placeName");
    foreach ($enhancer->fetchPlaces($refs) as $place) {
        tei_editions_add_entity($tei, "listPlace", "place", "placeName", $place);
    }
}

function tei_editions_process_tei_terms(SimpleXMLElement $tei, TeiEditionsDataFetcher $enhancer)
{
    // query for terms URLs
    $refs = tei_editions_get_references($tei, "term");
    foreach ($enhancer->fetchConcepts($refs) as $term) {
        tei_editions_add_entity($tei, "list", "item", "name", $term);
    }
}

function tei_editions_process_tei_people(SimpleXMLElement $tei, TeiEditionsDataFetcher $enhancer)
{
    // query for terms URLs
    $refs = tei_editions_get_references($tei, "persName");
    foreach ($enhancer->fetchHistoricalAgents($refs) as $person) {
        tei_editions_add_entity($tei, "listPerson", "person", "persName", $person);
    }
}

function tei_editions_process_tei_orgs(SimpleXMLElement $tei, TeiEditionsDataFetcher $enhancer)
{
    // query for terms URLs
    $refs = tei_editions_get_references($tei, "orgName");
    foreach ($enhancer->fetchHistoricalAgents($refs) as $org) {
        tei_editions_add_entity($tei, "listOrg", "org", "orgName", $org);
    }
}

function tei_editions_enhance_tei(SimpleXMLElement $tei, $lang, $dict = [])
{
    $enhancer = new TeiEditionsDataFetcher($dict, $lang);
    tei_editions_process_tei_places($tei, $enhancer);
    tei_editions_process_tei_terms($tei, $enhancer);
    tei_editions_process_tei_people($tei, $enhancer);
    tei_editions_process_tei_orgs($tei, $enhancer);
}

/**
 * Reads a TEI file and harvests the entity data.
 *
 * @param array $dictfiles local TEI paths
 * @return array an array of entities by slug
 */
function load_dict($dictfiles)
{
    $entities = [];
    foreach ($dictfiles as $file) {
        $doc = new TeiEditionsDocumentProxy($file);
        foreach ($doc->entities() as $entity) {
            $entities[$entity->ref()] = $entity;
        }
    }
    return $entities;
}

// If we're running interactively...
if (!count(debug_backtrace())) {

    $name = array_shift($argv);
    $lang = "eng";
    $dictfiles = [];
    $posargs = [];
    while ($arg = array_shift($argv)) {
        switch ($arg) {
            case "-l":
            case "--lang":
                $lang = array_shift($argv);
                break;
            case "-d":
            case "--dict":
                $dictfiles[] = array_shift($argv);
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
    $tei->registerXPathNamespace('t', 'http://www.tei-c.org/ns/1.0');

    $dict = load_dict($dictfiles);

    // TODO: validate file
    tei_editions_enhance_tei($tei, $lang, $dict);

    // save the resulting TEI to output file or print
    // to stdout
    if (count($posargs) > 1) {
        $tei->asXML($posargs[1]);
    } else {
        print($tei->asXML());
    }
}
