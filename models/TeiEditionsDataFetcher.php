<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2018 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

include_once dirname(__FILE__) . '/TeiEditionsEntity.php';
include_once dirname(__FILE__) . '/../helpers/TeiEditionsFunctions.php';


/**
 * A class for fetching information about entities given
 * URL references. Currently supported URLs are:
 *
 *  - Geonames
 *  - EHRI Portal authorities and keywords
 */
class TeiEditionsDataFetcher
{
    private $dict;
    private $lang;
    private $opts;
    
    function __construct($dict, $lang, $opts = array())
    {
        $this->dict = $dict;
        $this->lang = $lang;
        $this->opts = $opts;
    }

    /**
     * Fetch info about places given an array of URL references.
     *
     * @param array $nametourl an array mapping place names to URLs
     */
    public function fetchPlaces($nametourl)
    {
        $entities = [];
        foreach ($nametourl as $name => $url) {
            // HACK! if we can't get a place, try to look up the
            // item as a concept
            if (!($e = $this->_getPlace($url, $this->lang))
                    && !($e = $this->_getConcept($url, $this->lang))) {
                $e = TeiEditionsEntity::create($name, $url);
            }
            $entities[$e->ref()] = $e;
        }
        return array_values($entities);
    }

    /**
     * Fetch info about people, corporate bodies, and families
     * given an array of URL references.
     *
     * @param array $nametourl an array mapping agent names to URLs
     */
    public function fetchHistoricalAgents($nametourl)
    {
        $entities = [];
        foreach ($nametourl as $name => $url) {
            if (!($e = $this->_getHistoricalAgent($url, $this->lang))) {
                $e = TeiEditionsEntity::create($name, $url);
            }
            $entities[$e->ref()] = $e;
        }
        return array_values($entities);
    }

    /**
     * Fetch info about keywords/concepts given an array of URL references.
     *
     * @param array $nametourl an array mapping concept names to URLs
     */
    public function fetchConcepts($nametourl)
    {
        $entities = [];
        foreach ($nametourl as $name => $url) {
            if (!($e = $this->_getConcept($url, $this->lang))) {
                $e = TeiEditionsEntity::create($name, $url);
            }
            $entities[$e->ref()] = $e;
        }
        return array_values($entities);
    }

    /**
     * @param $url
     * @return TeiEditionsEntity|false
     */
    private function _findInDict($url)
    {
        return current(array_filter($this->dict, function(TeiEditionsEntity $e) use ($url) {
            return $e->ref() == $url;
        }));
    }

    /**
     * Make a request to the EHRI GraphQL API.
     *
     * @param $req string the GraphQL request body
     * @param $params array the GraphQL parameters
     * @return array the return data
     */
    private function _makeGraphQLRequest($req, $params)
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

    /**
     * Extract the place name from Geonames XML.
     *
     * @param DOMDocument $xml the RDF XML
     * @return string|false
     */
    private function _parseGeonamesPlaceName(DOMXPath $xpath, $lang = null)
    {
        // try and translate the language code.
        $shortlang = tei_editions_iso639_3to2($lang);
        $paths = [
            "/rdf:RDF/gn:Feature/gn:officialName[@xml:lang = '$shortlang']/text()",
            "/rdf:RDF/gn:Feature/gn:alternateName[@xml:lang = '$shortlang']/text()",
            "/rdf:RDF/gn:Feature/gn:name/text()"
        ];



        foreach ($paths as $path) {
            $value = $xpath->query($path);
            if ($value->length) {
                return $value->item(0)->textContent;
            }
        }

        return false;
    }

    /**
     * Fetch info about a place given an URL reference.
     *
     * @param string $url the canonical URL
     * @return TeiEditionsEntity|false
     */
    private function _getPlace($url, $lang = null)
    {
        if ($url[0] == '#') {
            return $this->_findInDict($url);
        }

        if (!preg_match("/(geonames)/", $url)) {
            return false;
        }

        // correct geonames url
        $id = explode("/",
            str_replace(["http://www.geonames.org/", "https://www.geonames.org/"], "", $url))[0];

        $geonames_url = "http://sws.geonames.org/$id/about.rdf";
        if (isset($this->opts['geonames_user'])) {
            $geonames_url = $geonames_url . "?username=" . $this->opts['geonames_user'];
        }

        // fetch geonames RDF
        $xml = new DOMDocument();
        if (@$xml->load($geonames_url) === false) {
            $error = error_get_last();
            error_log("Error reading URL '$url': " . $error["message"]);
            return false;
        }
        $xpath = new DOMXPath($xml);

        // interpret geonames RDF
        $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        $xpath->registerNamespace('gn', 'http://www.geonames.org/ontology#');
        $xpath->registerNamespace('wgs84_pos', 'http://www.w3.org/2003/01/geo/wgs84_pos#');

        $lat = @(float)$xpath->query("/rdf:RDF/gn:Feature/wgs84_pos:lat")->item(0)->textContent;
        $lon = @(float)$xpath->query("/rdf:RDF/gn:Feature/wgs84_pos:long")->item(0)->textContent;
        $wiki = @$xpath->query("/rdf:RDF/gn:Feature/gn:wikipediaArticle/@rdf:resource")->item(0)->textContent;

        $entity = TeiEditionsEntity::create(
           $this->_parseGeonamesPlaceName($xpath, $lang),
           $url
        );
        if ($wiki) {
            $entity->urls["desc"] = $wiki;
        }
        $entity->latitude = $lat;
        $entity->longitude = $lon;
        return $entity;
    }

    /**
     * Fetch info about a person, corporate body, or family
     * given an URL reference.
     *
     * @param string $url the canonical URL
     * @return TeiEditionsEntity|false
     */
    private function _getHistoricalAgent($url, $lang = null)
    {
        if ($url[0] == '#') {
            return $this->_findInDict($url);
        }

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
        $result = $this->_makeGraphQLRequest($req, array("id" => $id, "lang" => $lang));

        if (!isset($result['data']['HistoricalAgent']['description'])) {
            if ($lang) {
                return $this->_getHistoricalAgent($url);
            }
            return false;
        }
        $item = $result['data']['HistoricalAgent'];
        $desc = $item['description'];
        $entity = TeiEditionsEntity::create($desc['name'], $url);
        foreach (['datesOfExistence', 'biographicalHistory'] as $key) {
            if ($desc[$key]) {
                $entity->notes[] = $desc[$key];
            }
        }
        return $entity;
    }

    /**
     * Fetch info about a keyword/concept given an URL reference.
     *
     * @param string $url the canonical URL
     * @return TeiEditionsEntity|false
     */
    private function _getConcept($url, $lang = null)
    {
        if ($url[0] == '#') {
            return $this->_findInDict($url);
        }

        // build query
        $req = 'query getConcept($id: ID!, $lang: String) {
            CvocConcept(id: $id) {
                description(languageCode: $lang) {
                    name
                    scopeNote                
                }
                longitude
                latitude
                seeAlso
            }
        }';

        // execute query and extract JSON
        $id = basename($url);
        $result = $this->_makeGraphQLRequest($req, array("id" => $id, "lang" => $lang));
        if (!isset($result['data']['CvocConcept']['description'])) {
            // if $lang is set, try with default language
            if ($lang) {
                return $this->_getConcept($url);
            }
            return false;
        }
        $item = $result['data']['CvocConcept'];
        $desc = $item['description'];

        $entity = TeiEditionsEntity::create($desc['name'], $url);
        $entity->notes = $desc['scopeNote'];
        foreach ($item['seeAlso'] as $seeAlso) {
            if (preg_match('/wikipedia/', $seeAlso)) {
                $entity->urls['desc'] = $seeAlso;
            }
        }
        $entity->longitude = $item['longitude'];
        $entity->latitude =  $item['latitude'];
        return $entity;
    }
}