<?php

include_once dirname(__FILE__) . '/TeiEditionsEntity.php';
include_once dirname(__FILE__) . '/../helpers/TeiEditionsFunctions.php';
include_once dirname(__FILE__) . '/../helpers/TeiEditionsEnhanceTei.php';


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
     * @param $nametourl
     */
    public function fetchPlaces($nametourl)
    {
        $entities = [];
        foreach ($nametourl as $name => $url) {
            if ($e = $this->_getPlace($url, $this->lang)) {
                $entities[] = $e;
            } else {
                $entities[] = TeiEditionsEntity::create($name, $url);
            }
        }
        return $entities;
    }

    /**
     * @param $nametourl
     */
    public function fetchHistoricalAgents($nametourl)
    {
        $entities = [];
        foreach ($nametourl as $name => $url) {
            if ($e = $this->_getHistoricalAgent($url, $this->lang)) {
                $entities[] = $e;
            } else {
                $entities[] = TeiEditionsEntity::create($name, $url);
            }
        }
        return $entities;
    }

    /**
     * @param $nametourl
     */
    public function fetchConcepts($nametourl)
    {
        $entities = [];
        foreach ($nametourl as $name => $url) {
            if ($e = $this->_getConcept($url, $this->lang)) {
                $entities[] = $e;
            } else {
                $entities[] = TeiEditionsEntity::create($name, $url);
            }
        }
        return $entities;
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

    private function _getWikidataInfo($url)
    {
        $json_url = preg_match("\.json$", $url) ? $url : "$url.json";
        $curl = curl_init($json_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($curl);
        curl_close($curl);
        return json_decode($res, true);
    }

    /**
     * @param SimpleXMLElement $xml
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
     * @param $url
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
           $geonames_url
        );
        if ($wikiurl = @$wiki[0]) {
            $entity->urls["desc"] = $wikiurl;
        }
        $entity->latitude = (float)$lat[0];
        $entity->longitude = (float)$lon[0];
        return $entity;
    }

    /**
     * @param $url
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

        $entity = TeiEditionsEntity::create(
            $result['data']['HistoricalAgent']['description']['name'],
            $url
        );
        $entity->notes = array_filter([
            @$result['data']['HistoricalAgent']['description']['datesOfExistence'],
            @$result['data']['HistoricalAgent']['description']['biographicalHistory'],
        ], function ($n) {
            return !is_null($n);
        });
        return $entity;
    }

    /**
     * @param $url
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

        $entity = TeiEditionsEntity::create(
            $result['data']['CvocConcept']['description']['name'],
            $url
        );
        $entity->notes = $result['data']['CvocConcept']['description']['scopeNote'];
        foreach ($result['data']['CvocConcept']['seeAlso'] as $seeAlso) {
            if (preg_match('/wikipedia/', $seeAlso)) {
                $entity->urls['desc'] = $seeAlso;
            }
        }
        $entity->longitude = $result['data']['CvocConcept']['longitude'];
        $entity->latitude = $result['data']['CvocConcept']['latitude'];
        return $entity;
    }
}