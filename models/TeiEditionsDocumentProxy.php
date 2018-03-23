<?php

require_once dirname(__FILE__) . '/../helpers/TeiEditionsFunctions.php';

/**
 * Convenience class for accessing TEI information via
 * XPath.
 */
class TeiEditionsDocumentProxy
{
    private $uriOrPath;
    private $xml;
    private $query;

    /**
     * TeiEditionsDocumentProxy constructor.
     * @param $uriOrPath string an XML file or path
     */
    public function __construct($uriOrPath)
    {
        $this->uriOrPath = $uriOrPath;
        $this->xml = new DomDocument();
        $this->xml->load($uriOrPath);
        $this->query = new DOMXPath($this->xml);
        $this->query->registerNamespace("tei",
            "http://www.tei-c.org/ns/1.0");
    }

    /**
     * Get values at a given XPath
     *
     * @param $xpath string a single XPath string
     * @return array an array trimmed of text values
     */
    public function pathValues($xpath)
    {
        $out = [];
        $nodes = $this->query->query($xpath);
        for ($i = 0; $i < $nodes->length; $i++) {
            $out[] = trim($nodes->item($i)->textContent);
        }
        return $out;
    }

    /**
     * Get the TEI xml:id value
     *
     * @return null|string an ID value, or null
     */
    public function xmlId()
    {
        $id = $this->pathValues("/tei:TEI/@xml:id");
        return empty($id) ? null : $id[0];
    }

    /**
     * Get the TEI /TEI/teiHeader/profileDesc/creation/idno value
     *
     * @return null|string an ID value, or null
     */
    public function recordId()
    {
        $id = $this->pathValues("/tei:TEI/tei:teiHeader/tei:profileDesc/tei:creation/tei:idno");
        return empty($id) ? null : $id[0];
    }

    /**
     * Run xpath queries on a document.
     *
     * @param array $xpaths A mapping element ids to an array of XPath queries.
     *
     * @return array An array of element ids to matched strings.
     */
    function extractXPaths($elemToXPaths)
    {
        $out = [];

        try {
            foreach ($elemToXPaths as $name => $paths) {
                $all = [];
                foreach ($paths as $path) {
                    $all = array_merge($all, $this->pathValues($path));
                }
                $out[$name] = $all;
            }
        } catch (Exception $e) {
            $msg = $e->getMessage();
            error_log("Error extracting TEI data from " . $this->uriOrPath . ": $msg");
        }

        return $out;
    }

    /**
     * Obtain metadata ready to update an Item's element texts.
     *
     * @param array $xpaths A mapping element ids to an array of XPath queries.
     *
     * @return array an array of arrays. each containing the following fields:
     *  'element_id' => <id>, 'text' => <string>, 'html' => false
     */
    function metadata($elemToXPaths)
    {
        $out = [];
        foreach ($this->extractXPaths($elemToXPaths) as $elem => $data) {
            foreach ($data as $text) {
                $out[] = array('element_id' => $elem, 'text' => $text, 'html' => false);
            }
        }
        return $out;
    }

    /**
     * Find instances of tei:place elements which contain placeName
     * and geo and return them as an array.
     *
     * @return array
     */
    public function places()
    {
        $out = [];
        $placesXpath = "/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:listPlace/tei:place";
        $places = $this->query->query($placesXpath);
        foreach ($places as $place) {
            $names = $this->query->evaluate("./tei:placeName[1]", $place);
            if ($names->length === 0) continue;

            $lat_long = $this->query->evaluate("./tei:location/tei:geo[1]", $place);
            if ($lat_long->length === 0) continue;

            $links = $this->query->evaluate("./tei:linkGrp/tei:link[1]/@target", $place);
            $urls = [];
            foreach ($links as $link) {
                $urls[] = $link->value;
            }

            $parts = explode(" ", $lat_long->item(0)->textContent);

            $name = $names->item(0)->textContent;
            $out[$name] = array(
                "name" => $name,
                "latitude" => $parts[0],
                "longitude" => $parts[1],
                "urls" => $urls
            );
        }
        return array_values($out);
    }

    public function asHtml()
    {
        return tei_editions_tei_to_html($this->uriOrPath, []);
    }

    public function asSimpleHtml()
    {
        $xml = new DomDocument();
        $xml->loadXML($this->asHtml());
        $query = new DOMXPath($xml);
        $div = $query->query("/div/div[@class='tei-text']");
        return empty($div) ? "" : $xml->saveHTML($div[0]);
    }
}