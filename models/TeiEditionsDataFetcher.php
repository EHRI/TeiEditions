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
    
    function __construct($dict, $lang)
    {
        $this->dict = $dict;
        $this->lang = $lang;
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
     * @param SimpleXMLElement $xml the RDF XML
     * @return string|false
     */
    private function _parseGeonamesPlaceName(SimpleXMLElement $xml, $lang = null)
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

        $entity = TeiEditionsEntity::create(
           $this->_parseGeonamesPlaceName($xml, $lang),
           $url
        );
        if ($wikiurl = @$wiki[0]) {
            $entity->urls["desc"] = $wikiurl;
        }
        $entity->latitude = (float)$lat[0];
        $entity->longitude = (float)$lon[0];
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
        $entity->notes = array_filter([
            @$desc['datesOfExistence'],
            @$desc['biographicalHistory'],
        ], function ($n) {
            return !is_null($n);
        });
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