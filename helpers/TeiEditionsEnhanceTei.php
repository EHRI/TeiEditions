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

function tei_editions_url_slug_mappings()
{
    return array(
        'geonames' => 'http://sws.geonames.org/<id>/',
        'ehri-authority' => 'https://portal.ehri-project.eu/authorities/<id>'
    );
}

function tei_editions_url_to_slug($url)
{
    foreach (tei_editions_url_slug_mappings() as $name => $pattern) {
        $regex = '~' . str_replace('<id>', '([^/]+)', $pattern) . '~';
        $matches = array();
        if (preg_match($regex, $url, $matches)) {
            return implode('-', array($name, $matches[1]));
        }
    }
    return null;
}

function tei_editions_slug_to_url($slug)
{
    foreach (tei_editions_url_slug_mappings() as $name => $pattern) {
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
function tei_editions_make_graphql_request($req, $params)
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

function tei_editions_get_wikidata_info($url)
{
    $json_url = preg_match("\.json$", $url) ? $url : "$url.json";
    $curl = curl_init($json_url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($curl);
    curl_close($curl);
    return json_decode($res, true);
}

function tei_editions_get_place($id)
{
    $geonames_url = "http://sws.geonames.org/$id/about.rdf";

    // fetch geonames RDF
    $data = file_get_contents($geonames_url);
    $xml = new SimpleXMLElement($data);
    if ($xml === FALSE) {
        error_log("Error reading URL!");
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
        "id" => $id,
        "url" => $geonames_url,
        "name" => $name[0],
        "latitude" => $lat[0],
        "longitude" => $lon[0],
        "wikipedia" => $wiki[0]
    );
}

function tei_editions_get_historical_agent($url, $lang = null)
{
    // build query
    $req = 'query getAgent($id: ID!, $lang: String) {
            HistoricalAgent(id: $id) {
                id
                identifier
                description(languageCode: $lang) {
                    name
                    lastName
                    firstName
                    biographicalHistory
                    datesOfExistence
                    source
                    otherFormsOfName
                    parallelFormsOfName
                }
            }
        }';

    // execute query and extract JSON
    $id = basename($url);
    $result = tei_editions_make_graphql_request($req, array("id" => $id, "lang" => $lang));
    return is_null($result['data']['HistoricalAgent'])
        ? null
        : array_merge(
            array("id" => $id, "url" => $url),
            $result['data']['HistoricalAgent']['description']
        );
}

function tei_editions_get_concept($url, $lang = null)
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
    $id = basename($url);
    $result = tei_editions_make_graphql_request($req, array("id" => $id, "lang" => $lang));
    return is_null($result['data']['CvocConcept']) ? null : array(
        "id" => $id,
        "url" => $url,
        "name" => $result['data']['CvocConcept']['description']['name'],
        "longitude" => $result['data']['CvocConcept']['longitude'],
        "latitude" => $result['data']['CvocConcept']['latitude'],
        "wikipedia" => array_reduce($result['data']['CvocConcept']['seeAlso'], function ($acc, $i) {
            return strpos($i, "wikipedia") ? $i : $acc;
        })
    );
}

function tei_editions_get_references(SimpleXMLElement $tei, $tag_name)
{
    $names = array();
    $urls = array();
    $nodes = $tei->xpath("/t:TEI/t:text/t:body/*/t:$tag_name");
    foreach ($nodes as $node) {
        $text = $node->xpath("text()");
        $ref = $node->xpath("@ref");
        if ($ref) {
            $urls[(string)($ref[0])] = (string)$text[0];
        } else {
            $names[(string)($text[0])] = null;
        }
    }

    return array_merge($names, array_flip($urls));
}

function tei_editions_add_place(SimpleXMLElement $place_list, $name, $url, $data)
{
    $name = isset($data["name"]) ? $data["name"] : $name;
    error_log("Adding place: $name");

    $place = $place_list->addChild('place');
    $place->addChild('placeName', trim($name));

    // longitude and latitude
    if (isset($data["longitude"]) and isset($data["latitude"])) {
        $location = $place->addChild('location');
        $location->addChild('geo', $data["latitude"] . " " . $data["longitude"]);
    }

    if ($url) {
        // URIs and links
        $link_grp = $place->addChild('linkGrp');

        $link = $link_grp->addChild('link');
        $link->addAttribute('type', 'normal');
        // $link->addAttribute('source', 'geonames') ; FIXME: doesn't validate in Oxygen
        $link->addAttribute('target', $url);

        if (isset($data["wikipedia"])) {
            $wlink = $link_grp->addChild('link');
            $wlink->addAttribute('type', 'desc');
            // $wlink->addAttribute('source', 'wikipedia') ; FIXME: doesn't validate in Oxygen
            $wlink->addAttribute('target', $data["wikipedia"]);
        }
    }

    return $place;
}

function tei_editions_process_tei_places(SimpleXMLElement $tei)
{
    // get place URLs
    $refs = tei_editions_get_references($tei, "placeName");

    if ($refs) {
        $list_place = $tei->teiHeader->fileDesc->sourceDesc->addChild('listPlace');

        foreach ($refs as $name => $url) {

            $data = array();

            // Geonames
            if ($url and preg_match("/(geonames)/", $url)) {

                // correct geonames url
                $parts = explode("/", str_replace("http://www.geonames.org/", "", $url));
                $data = array_merge($data, tei_editions_get_place($parts[0]));
            }

            tei_editions_add_place($list_place, $name, $url, $data);
        }
    }
}

function tei_editions_add_term(SimpleXMLElement $list, $name, $url, $data)
{
    $name = isset($data["name"]) ? $data["name"] : $name;
    error_log("Adding term: $name");

    $item = $list->addChild('item');
    $item->addChild('name', trim($name));

    if ($url) {
        $link_grp = $item->addChild("linkGrp");
        $link = $link_grp->addChild("link");
        $link->addAttribute("type", "normal");
        $link->addAttribute("target", $url);
    }

    return $item;
}

function tei_editions_process_tei_terms(SimpleXMLElement $tei)
{
    // query for terms URLs
    $refs = tei_editions_get_references($tei, "term");

    if ($refs) {
        $list = $tei->teiHeader->fileDesc->sourceDesc->addChild('list');

        foreach ($refs as $name => $url) {

            $data = array();

            if ($url) {
                $lookup = tei_editions_get_concept($url, "eng")
                    or tei_editions_get_concept($url);
                $data = array_merge($data, $lookup);
            }

            tei_editions_add_term($list, $name, $url, $data);
        }
    }
}

function tei_editions_add_person(SimpleXMLElement $list, $name, $url, $data)
{
    $name = isset($data["name"]) ? $data["name"] : $name;
    error_log("Adding person: $name");

    $item = $list->addChild('person');
    $item->addChild('persName', trim($name));

    if (isset($data['datesOfExistence'])) {
        $item->addChild("p", $data['datesOfExistence']);
    }
    if (isset($data['biographicalHistory'])) {
        $item->addChild("note", $data['biographicalHistory']);
    }
    if ($url) {
        $link_grp = $item->addChild("linkGrp");
        $link = $link_grp->addChild("link");
        $link->addAttribute("type", "normal");
        $link->addAttribute("target", $url);
    }

    return $item;
}


function tei_editions_process_tei_people(SimpleXMLElement $tei)
{
    // query for terms URLs
    $refs = tei_editions_get_references($tei, "persName");

    if ($refs) {
        $list_person = $tei->teiHeader->fileDesc->sourceDesc->addChild('listPerson');

        foreach ($refs as $name => $url) {

            $data = array();
            if ($url) {
                $lookup = tei_editions_get_historical_agent($url, "eng")
                    or tei_editions_get_historical_agent($url);
                $data = array_merge($data, $lookup);
            }

            tei_editions_add_person($list_person, $name, $url, $data);
        }
    }
}

function tei_editions_add_org(SimpleXMLElement $list, $name, $url, $data)
{
    $name = isset($data["name"]) ? $data["name"] : $name;
    error_log("Adding org: $name");

    $item = $list->addChild('org');
    $item->addChild('orgName', trim($name));

    if (isset($data['datesOfExistence'])) {
        $item->addChild("p", $data['datesOfExistence']);
    }
    if (isset($data['biographicalHistory'])) {
        $item->addChild("note", $data['biographicalHistory']);
    }
    if ($url) {
        $link_grp = $item->addChild("linkGrp");
        $link = $link_grp->addChild("link");
        $link->addAttribute("type", "normal");
        $link->addAttribute("target", $url);
    }

    return $item;
}


function tei_editions_process_tei_orgs(SimpleXMLElement $tei)
{
    // query for terms URLs
    $refs = tei_editions_get_references($tei, "orgName");

    if ($refs) {
        $list_org = $tei->teiHeader->fileDesc->sourceDesc->addChild('listOrg');

        foreach ($refs as $name => $url) {

            $data = array();
            if ($url) {
                $lookup = tei_editions_get_historical_agent($url, "eng")
                    or tei_editions_get_historical_agent($url);
                $data = array_merge($data, $lookup);
            }

            tei_editions_add_org($list_org, $name, $url, $data);
        }
    }
}

function tei_editions_enhance_tei(SimpleXMLElement $tei)
{
    tei_editions_process_tei_places($tei);
    tei_editions_process_tei_terms($tei);
    tei_editions_process_tei_people($tei);
    tei_editions_process_tei_orgs($tei);
}

// If we're running interactively...
if (!count(debug_backtrace())) {

    // Check availability of TEI file
    $in_file = $argv[1];
    if ($in_file == "") {
        die("Input file not defined. The script requires a parameter with path to the TEI file.");
    }

    // read TEI file
    $tei = simplexml_load_file($in_file) or exit("Couldn't load the TEI file.");
    $tei->registerXPathNamespace('t', 'http://www.tei-c.org/ns/1.0');

    // TODO: validate file
    tei_editions_enhance_tei($tei);

    // save the resulting TEI to output file or print
    // to stdout
    if (count($argv) > 2) {
        $tei->asXML($argv[2]);
    } else {
        print($tei->asXML());
    }
}