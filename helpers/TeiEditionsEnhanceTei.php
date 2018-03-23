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

function tei_editions_iso639_3to2($three)
{
    $lookup = [
        "eng" => "en",
        "deu" => "de",
        "ces" => "cs",
        "hun" => "hu",
        "heb" => "he",
        "fre" => "fr",
        "slo" => "sk",
        "slk" => "sk",
        "nld" => "nl",
        "pol" => "pl",
        "rus" => "ru",
        "ron" => "ro"
    ];
    return isset($lookup[$three]) ? $lookup[$three] : $three;
}

function tei_editions_url_slug_mappings()
{
    return [
        'geonames' => ['http://sws.geonames.org/<id>/', 'http://www.geonames.org/<id>/.*'],
        'ehri-authority' => ['https://portal.ehri-project.eu/authorities/<id>'],
        'ehri-term' => ['https://portal.ehri-project.eu/keywords/<id>'],
        'ehri-unit' => ['https://portal.ehri-project.eu/units/<id>'],
        'ehri-institution' => ['https://portal.ehri-project.eu/institutions/<id>'],
        'holocaust-cz' => ['https://www.holocaust.cz/databaze-obeti/obet/<id>']
    ];
}

function tei_editions_url_to_slug($url)
{
    foreach (tei_editions_url_slug_mappings() as $name => $patterns) {
        foreach ($patterns as $pattern) {
            $regex = '~' . str_replace('<id>', '([^/]+)', $pattern) . '~';
            $matches = [];
            if (preg_match($regex, $url, $matches)) {
                return implode('-', array($name, $matches[1]));
            }
        }
    }
    return null;
}

function tei_editions_slug_to_url($slug)
{
    foreach (tei_editions_url_slug_mappings() as $name => $patterns) {
        foreach ($patterns as $pattern) {
            $pos = strpos($slug, $name);
            if ($pos !== false) {
                $id = substr($slug, strlen($name) + 1);
                return str_replace('<id>', $id, $pattern);
            }
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

function tei_editions_parse_geonames_rdf_place_name(SimpleXMLElement $xml, $lang)
{
    // try and translate the language code.
    $shortlang = tei_editions_iso639_3to2($lang);
    $paths = [
        "/rdf:RDF/gn:Feature/gn:officialName[@xml:lang = '$shortlang']/text()",
        "/rdf:RDF/gn:Feature/gn:alternateName[@xml:lang = '$shortlang']/text()",
        "/rdf:RDF/gn:Feature/gn:name/text()"
    ];

    foreach ($paths as $path) {
        $value = $xml->xpath($path);
        if (!empty($value)) {
            return (string)$value[0];
        }
    }

    return null;
}

function tei_editions_get_place($url, $lang = null)
{
    if (!preg_match("/(geonames)/", $url)) {
        return false;
    }

    // correct geonames url
    $id = explode("/", str_replace("http://www.geonames.org/", "", $url))[0];

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

    $lat = (array)$xml->xpath("/rdf:RDF/gn:Feature/wgs84_pos:lat")[0][0];
    $lon = (array)$xml->xpath("/rdf:RDF/gn:Feature/wgs84_pos:long")[0][0];
    $wiki = @(array)$xml->xpath("/rdf:RDF/gn:Feature/gn:wikipediaArticle/@rdf:resource")[0][0];

    return array(
        "id" => $id,
        "url" => $geonames_url,
        "name" => tei_editions_parse_geonames_rdf_place_name($xml, $lang),
        "latitude" => $lat[0],
        "longitude" => $lon[0],
        "wikipedia" => @$wiki[0]
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
    return isset($result['data']['HistoricalAgent']['description'])
        ? array_merge(
            array("id" => $id, "url" => $url),
            $result['data']['HistoricalAgent']['description']
        )
        : false;
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
    return isset($result['data']['CvocConcept']["description"])
        ? array(
            "id" => $id,
            "url" => $url,
            "name" => $result['data']['CvocConcept']['description']['name'],
            "longitude" => $result['data']['CvocConcept']['longitude'],
            "latitude" => $result['data']['CvocConcept']['latitude'],
            "wikipedia" => array_reduce($result['data']['CvocConcept']['seeAlso'], function ($acc, $i) {
                return strpos($i, "wikipedia") ? $i : $acc;
            })
        )
        : false;
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

function tei_editions_add_link_group(SimpleXMLElement $item, $url)
{
    $link_grp = $item->addChild('linkGrp');
    $link = $link_grp->addChild('link');
    $link->addAttribute('type', 'normal');
    $link->addAttribute('target', $url);
    return $link_grp;
}

function tei_editions_add_bio_data(SimpleXMLElement $item, $data)
{
    if (isset($data['datesOfExistence']) OR isset($data['biographicalHistory'])) {
        $note = $item->addChild('note');
        if (isset($data['datesOfExistence'])) {
            $note->addChild("p", $data['datesOfExistence']);
        }
        if (isset($data['biographicalHistory'])) {
            $note->addChild("p", $data['biographicalHistory']);
        }
        return $note;
    }
    return null;
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
        $link_grp = tei_editions_add_link_group($place, $url);
        if (isset($data["wikipedia"])) {
            $wlink = $link_grp->addChild('link');
            $wlink->addAttribute('type', 'desc');
            // $wlink->addAttribute('source', 'wikipedia') ; FIXME: doesn't validate in Oxygen
            $wlink->addAttribute('target', $data["wikipedia"]);
        }
    }

    return $place;
}

function tei_editions_process_tei_places(SimpleXMLElement $tei, $lang)
{
    // get place URLs
    $refs = tei_editions_get_references($tei, "placeName");

    if ($refs) {
        $list_place = $tei->teiHeader->fileDesc->sourceDesc->addChild('listPlace');

        foreach ($refs as $name => $url) {
            $data = array();
            if ($url) {
                $lookup = tei_editions_get_place($url, $lang);
                if ($lookup) {
                    $data = $lookup;
                }
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
        tei_editions_add_link_group($item, $url);
    }

    return $item;
}

function tei_editions_process_tei_terms(SimpleXMLElement $tei, $lang)
{
    // query for terms URLs
    $refs = tei_editions_get_references($tei, "term");

    if ($refs) {
        $list = $tei->teiHeader->fileDesc->sourceDesc->addChild('list');

        foreach ($refs as $name => $url) {
            $data = array();
            if ($url) {
                foreach (array_unique([$lang, "eng", null]) as $ln) {
                    $lookup = tei_editions_get_concept($url, $ln);
                    if ($lookup) {
                        $data = $lookup;
                        break;
                    }
                }
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

    tei_editions_add_bio_data($item, $data);
    if ($url) {
        tei_editions_add_link_group($item, $url);
    }

    return $item;
}


function tei_editions_process_tei_people(SimpleXMLElement $tei, $lang)
{
    // query for terms URLs
    $refs = tei_editions_get_references($tei, "persName");

    if ($refs) {
        $list_person = $tei->teiHeader->fileDesc->sourceDesc->addChild('listPerson');

        foreach ($refs as $name => $url) {
            $data = array();
            if ($url) {
                foreach (array_unique([$lang, "eng", null]) as $ln) {
                    $lookup = tei_editions_get_historical_agent($url, $ln);
                    if ($lookup) {
                        $data = $lookup;
                        break;
                    }
                }
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

    tei_editions_add_bio_data($item, $data);
    if ($url) {
        tei_editions_add_link_group($item, $url);
    }

    return $item;
}


function tei_editions_process_tei_orgs(SimpleXMLElement $tei, $lang)
{
    // query for terms URLs
    $refs = tei_editions_get_references($tei, "orgName");

    if ($refs) {
        $list_org = $tei->teiHeader->fileDesc->sourceDesc->addChild('listOrg');

        foreach ($refs as $name => $url) {
            $data = array();
            if ($url) {
                foreach (array_unique([$lang, "eng", null]) as $ln) {
                    $lookup = tei_editions_get_historical_agent($url, $ln);
                    if ($lookup) {
                        $data = $lookup;
                        break;
                    }
                }
            }

            tei_editions_add_org($list_org, $name, $url, $data);
        }
    }
}

function tei_editions_enhance_tei(SimpleXMLElement $tei, $lang)
{
    tei_editions_process_tei_places($tei, $lang);
    tei_editions_process_tei_terms($tei, $lang);
    tei_editions_process_tei_people($tei, $lang);
    tei_editions_process_tei_orgs($tei, $lang);
}

// If we're running interactively...
if (!count(debug_backtrace())) {

    $name = array_shift($argv);
    $lang = "eng";
    $posargs = [];
    while ($arg = array_shift($argv)) {
        switch ($arg) {
            case "-l":
            case "--lang":
                $lang = array_shift($argv);
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

    // TODO: validate file
    tei_editions_enhance_tei($tei, $lang);

    // save the resulting TEI to output file or print
    // to stdout
    if (count($posargs) > 1) {
        $tei->asXML($posargs[1]);
    } else {
        print($tei->asXML());
    }
}
