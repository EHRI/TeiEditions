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
    private $htmlCache;

    /**
     * TeiEditionsDocumentProxy constructor.
     * @param $uriOrPath string an XML file or path
     */
    public function __construct($uriOrPath)
    {
        $this->uriOrPath = $uriOrPath;
        $this->xml = new DomDocument();
        $this->xml->preserveWhiteSpace = false;
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
            $out[] = trim(preg_replace('/\s+/', ' ', $nodes->item($i)->textContent));
        }
        return $out;
    }

    public function manuscriptIds()
    {
        $path = "/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:msDesc/tei:msIdentifier/*/@ref";
        $values = [];
        $list = $this->query->query($path);
        for ($i = 0; $i < $list->length; $i++) {
            $values[] = $list->item($i)->textContent;
        }
        return $values;
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
     * List places, people, orgs, and term entities.
     *
     * @return array
     */
    function entities()
    {
        return array_merge(
            $this->getEntities("listPlace", "place", "placeName"),
            $this->getEntities("listPerson", "person", "persName"),
            $this->getEntities("listOrg", "org", "orgName"),
            $this->getEntities("list", "item", "name")
        );
    }

    private function getEntities($listTag, $itemTag, $nameTag)
    {
        $out = [];
        $path = "/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:$listTag/tei:$itemTag";
        $entities = $this->query->query($path);
        foreach ($entities as $entity) {
            $names = $this->query->evaluate("./tei:{$nameTag}[1]", $entity);
            if ($names->length) {
                $name = $names->item(0)->textContent;
                $links = $this->query->evaluate("./tei:linkGrp/tei:link[1]/@target", $entity);
                $urls = [];
                for ($i = 0; $i < $links->length; $i++) {
                    $urls[] = $links->item($i)->value;
                }
                $slug = !empty($urls)
                    ? tei_editions_url_to_slug($urls[0])
                    : @$this->query->evaluate("./@xml:id", $entity)->item(0)->textContent;
                $item = [
                    "name" => $name,
                    "urls" => $urls,
                    "slug" => $slug,
                    "body" => $this->_getEntityBodyHtml($urls, $slug)
                ];

                $lat_long = $this->query->evaluate("./tei:location/tei:geo[1]", $entity);
                if ($lat_long->length) {
                    $parts = explode(" ", $lat_long->item(0)->textContent);
                    $item["latitude"] = $parts[0];
                    $item["longitude"] = $parts[1];
                }

                $out[$name] = $item;
            }
        }
        return array_values($out);
    }

    private function _getEntityBodyHtml($urls, $slug) {
        $url = empty($urls) ? ('#' . $slug) : $urls[0];
        $xml = new DomDocument;
        $xml->loadXML($this->asHtml());
        $query = new DOMXPath($xml);
        $path = "/div/div[@class='tei-entities']/div[@data-ref='$url']/div[@class='content-info-entity-body']";
        $node = $query->query($path);
        return empty($node) ? "" : $xml->saveXML($node->item(0));
    }

    public function asHtml()
    {
        if (is_null($this->htmlCache)) {
            $this->htmlCache = tei_editions_tei_to_html($this->uriOrPath, []);
        }
        return $this->htmlCache;
    }

    public function asSimpleHtml()
    {
        $xml = new DomDocument();
        $xml->loadXML($this->asHtml());
        $query = new DOMXPath($xml);
        $div = $query->query("/div/div[@class='tei-text']");
        return empty($div) ? "" : $xml->saveHTML($div->item(0));
    }
}