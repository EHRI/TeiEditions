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

/**
 * Attempt to convert an external URL for some resource
 * into a local slug value.
 *
 * @param $url
 */

$_KNOWN_URLS = array(
    'geonames' => 'http://sws.geonames.org/<id>/',
    'ehri-authority' => 'https://portal.ehri-project.eu/authorities/<id>'
);

function urlToSlug($url)
{
    global $_KNOWN_URLS;
    foreach ($_KNOWN_URLS as $name => $pattern) {
        $regex = '~' . str_replace('<id>', '([^/]+)', $pattern) . '~';
        $matches = array();
        if (preg_match($regex, $url, $matches)) {
            return implode('-', array($name, $matches[1]));
        }
    }
    return null;
}

function slugToUrl($slug)
{
    global $_KNOWN_URLS;
    foreach ($_KNOWN_URLS as $name => $pattern) {
        $pos = strpos($slug, $name);
        if ($pos !== false) {
            $id = substr($slug, strlen($name) + 1);
            return str_replace('<id>', $id, $pattern);
        }
    }
    return null;
}

/**
 * @param $req string the GraphQL request body
 * @param $params array the GraphQL parameters
 * @return array the return data
 */
function graphQLRequest($req, $params)
{
    $data = array("query" => $req, "variables" => $params);
    $json = json_encode($data);

    $curl = curl_init("https://portal.ehri-project.eu/api/graphql");
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json)
    ));
    $res = curl_exec($curl);
    curl_close($curl);
    return json_decode($res, true);
}

function wikidataRequest($url)
{
    $json_url = preg_match("\.json$", $url) ? $url : "$url.json";
    $curl = curl_init($json_url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($curl);
    curl_close($curl);
    return json_decode($res, true);
}

function getPlace($id)
{
    $geonames_url = "http://sws.geonames.org/$id/about.rdf";

    // fetch geonames RDF
    $data = file_get_contents($geonames_url);
    $xml = new SimpleXMLElement($data);
    if ($xml === FALSE) {
        print "Error reading URL!\n";
        return false;
    }

    // interpret geonames RDF
    $xml->registerXPathNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
    $xml->registerXPathNamespace('gn', 'http://www.geonames.org/ontology#');
    $xml->registerXPathNamespace('wgs84_pos', 'http://www.w3.org/2003/01/geo/wgs84_pos#');

    $name = (array)$xml->xpath("/rdf:RDF/gn:Feature/gn:name")[0][0];
    $lat = (array)$xml->xpath("/rdf:RDF/gn:Feature/wgs84_pos:lat")[0][0];
    $lon = (array)$xml->xpath("/rdf:RDF/gn:Feature/wgs84_pos:long")[0][0];
    $wiki = (array)$xml->xpath("/rdf:RDF/gn:Feature/gn:wikipediaArticle/@rdf:resource")[0][0];

    return array(
        "name" => $name[0],
        "latitude" => $lat[0],
        "longitude" => $lon[0],
        "wikipedia" => $wiki[0]
    );
}

function getHistoricalAgent($id, $lang = null)
{
    // build query
    $req = 'query getAgent($id: ID!, $lang: String) {
            HistoricalAgent(id: $id) {
                description(languageCode: $lang) {
                    name
                    lastName
                    firstName
                    biographicalHistory
                    datesOfExistence
                    source
                    otherFormsOfName
                }
            }
        }';

    // execute query and extract JSON
    $result = graphQLRequest($req, array("id" => $id, "lang" => $lang));
    return $result['data']['HistoricalAgent']['description'];
}

function getConcept($id, $lang = null)
{
    // build query
    $req = 'query getConcept($id: ID!, $lang: String) {
        CvocConcept(id: $id) {
            description(languageCode: $lang) {
                name                
            }
            longitude
            latitude
            seeAlso
        }
    }';

    // execute query and extract JSON
    $result = graphQLRequest($req, array("id" => $id, "lang" => $lang));
    $data = array(
        "name" => $result['data']['CvocConcept']['description']['name'],
        "longitude" => $result['data']['CvocConcept']['longitude'],
        "latitude" => $result['data']['CvocConcept']['latitude'],
        "wikipedia" => array_reduce($result['data']['CvocConcept']['seeAlso'], function($acc, $i) {
            return strpos($i, "wikipedia") ? $i : $acc;
        })
    );
    return $data;
}

function processTEIPlaces(SimpleXMLElement $tei)
{
    // get place URLs
    $urls = array_unique($tei->xpath('//t:placeName/@ref'));

    if ($urls) {
        $listPlace = $tei->teiHeader->fileDesc->sourceDesc->addChild('listPlace');

        foreach ($urls as $place_url) {
            // Geonames
            if (preg_match("/(geonames)/", $place_url)) {

                $place = $listPlace->addChild('place');

                // correct geonames url
                $partURL = str_replace("http://www.geonames.org/", "", $place_url);
                $partsURL = explode("/", $partURL);
                $geonamesID = $partsURL[0];

                $data = getPlace($geonamesID);
                if ($data and $data["name"]) {
                    $name = $data["name"];

                    print("Place URL: $place_url: $name\n");

                    // placeName
                    $place->addChild('placeName', $name);

                    // longitude and latitude
                    $location = $place->addChild('location');
                    $location->addChild('geo', $data["latitude"] . " " . $data["longitude"]);

                    // URIs and links
                    $linkGrp = $place->addChild('linkGrp');

                    $link = $linkGrp->addChild('link');
                    $link->addAttribute('type', 'normal');
                    // $link->addAttribute('source', 'geonames') ; FIXME: doesn't validate in Oxygen
                    $link->addAttribute('target', $place_url);

                    if ($data["wikipedia"]) {
                        $wlink = $linkGrp->addChild('link');
                        $wlink->addAttribute('type', 'desc');
                        // $wlink->addAttribute('source', 'wikipedia') ; FIXME: doesn't validate in Oxygen
                        $wlink->addAttribute('target', $data["wikipedia"]);

                    }
                }
            } elseif (preg_match("/(wikidata)/", $place_url)) {
                // https://www.wikidata.org/wiki/Special:EntityData/Q179251.json
            }
        }
    }
}

function processTEITerms(SimpleXMLElement $tei)
{

    // query for terms URLs
    $urls = array_unique($tei->xpath('//t:term/@ref'));

    if ($urls) {
        $list = $tei->teiHeader->fileDesc->sourceDesc->addChild('list');

        foreach ($urls as $URL) {
            $id = basename($URL);

            // test preferred language -> if empty result -> send query again with empty language code
            $data = getConcept($id, "eng") or getConcept($id);
            print json_encode($data) . "\n";
            if ($data and $data["name"]) {
                $name = $data["name"];
                print "Term URL: $URL: $name\n";
                $item = $list->addChild("item");
                $item->addChild("name", $name);
                $linkGrp = $item->addChild("linkGrp");
                $link = $linkGrp->addChild("link");
                $link->addAttribute("type", "normal");
                $link->addAttribute("target", $URL);
            }
        }
    }
}

function processTEIPeople(SimpleXMLElement $tei)
{
    // query for terms URLs
    $urls = array_unique($tei->xpath('//t:persName/@ref'));

    if ($urls) {
        $listPerson = $tei->teiHeader->fileDesc->sourceDesc->addChild('listPerson');

        foreach ($urls as $URL) {
            $id = basename($URL);
            $data = getHistoricalAgent($id, "eng") or getHistoricalAgent($id);

            if ($data and $data["name"]) {
                $name = $data["name"];
                print "Person URL: $URL: $name\n";
                $item = $listPerson->addChild("person");
                $item->addChild("persName", $name); // FIXME - add life span
                $datesOfExistence = $data['datesOfExistence'];
                if ($datesOfExistence)
                    $item->addChild("p", $datesOfExistence);
                $biographicalHistory = $data['biographicalHistory'];
                if ($biographicalHistory)
                    $item->addChild("note", $biographicalHistory);
                $linkGrp = $item->addChild("linkGrp");
                $link = $linkGrp->addChild("link");
                $link->addAttribute("type", "normal");
                $link->addAttribute("target", $URL);
            }
        }
    }
}

// If we're running interactively...
if (!count(debug_backtrace())) {

    // Check availability of TEI file
    $in_file = $argv[1];
    if ($in_file == "") {
        die("Input file not defined. The script requires a parameter with path to the TEI file.");
    }

    // FIXME: validate file

    // read TEI file
    $tei = simplexml_load_file($in_file) or exit("Couldn't load the TEI file.");
    $tei->registerXPathNamespace('t', 'http://www.tei-c.org/ns/1.0');

    // get normalized records and save them as XML fragments
    processTEIPlaces($tei);
    processTEITerms($tei);
    processTEIPeople($tei);

    // save the resulting TEI
    if (count($argv) > 2) {
        $tei->asXML($argv[2]);
    }
}