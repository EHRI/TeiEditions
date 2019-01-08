<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2018 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

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
    private function __construct(DOMDocument $doc, $uriOrPath)
    {
        $this->uriOrPath = $uriOrPath;
        $this->xml = $doc;
        $this->xml->preserveWhiteSpace = false;
        $this->query = new DOMXPath($this->xml);
        $this->query->registerNamespace("t",
            "http://www.tei-c.org/ns/1.0");
    }

    public static function fromString($str)
    {
        $doc = new DOMDocument;
        $doc->loadXML($str);
        return new TeiEditionsDocumentProxy($doc, "");
    }

    public static function fromUriOrPath($uriOrPath)
    {
        $doc = new DOMDocument;
        $doc->load($uriOrPath);
        return new TeiEditionsDocumentProxy($doc, $uriOrPath);
    }

    public static function fromSimpleXMLElement(SimpleXMLElement $elem)
    {
        return TeiEditionsDocumentProxy::fromString($elem->asXML(), "");
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
        $path = "/t:TEI/t:teiHeader/t:fileDesc/t:sourceDesc/t:msDesc/t:msIdentifier/*/@ref";
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
        $id = $this->pathValues("/t:TEI/@xml:id");
        return empty($id) ? null : $id[0];
    }

    /**
     * Get the TEI /TEI/teiHeader/profileDesc/creation/idno value
     *
     * @return null|string an ID value, or null
     */
    public function recordId()
    {
        $id = $this->pathValues("/t:TEI/t:teiHeader/t:profileDesc/t:creation/t:idno");
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
     * @return array|TeiEditionsEntity
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

    /**
     * List entities with the given list, item, and name tags.
     *
     * @param string $listTag
     * @param string $itemTag
     * @param string $nameTag
     * @return array|TeiEditionsEntity
     */
    function getEntities($listTag, $itemTag, $nameTag)
    {
        $out = [];
        $path = "/t:TEI/t:teiHeader/t:fileDesc/t:sourceDesc/t:$listTag/t:$itemTag";
        $entities = $this->query->query($path);
        foreach ($entities as $entity) {
            $names = $this->query->evaluate("./t:{$nameTag}[1]", $entity);
            if ($names->length) {
                $name = $names->item(0)->textContent;
                $links = $this->query->evaluate("./t:linkGrp/t:link", $entity);
                $urls = [];
                for ($i = 0; $i < $links->length; $i++) {
                    $type = $this->query->evaluate("./@type", $links->item($i))[0]->textContent;
                    $url = $this->query->evaluate("./@target", $links->item($i))[0]->textContent;
                    $urls[$type] = $url;
                }
                $slug = isset($urls["normal"])
                    ? tei_editions_url_to_slug($urls["normal"])
                    : @$this->query->evaluate("./@xml:id", $entity)->item(0)->textContent;
                $item = new TeiEditionsEntity;
                $item->name = $name;
                $item->slug = $slug;
                $item->urls = $urls;

                $desc = $this->query->evaluate("./t:note/t:p", $entity);
                if ($desc->length) {
                    for ($i = 0; $i < $desc->length; $i++) {
                        $item->notes[] = $desc->item($i)->textContent;
                    }
                }

                $lat_long = $this->query->evaluate("./t:location/t:geo[1]", $entity);
                if ($lat_long->length) {
                    $parts = explode(" ", $lat_long->item(0)->textContent);
                    list($item->latitude, $item->longitude) = $parts;
                }
                $out[$name] = $item;
            }
        }
        return array_values($out);
    }

    public function entityBodyHtml($urls, $slug)
    {
        $url = isset($urls["normal"]) ? $urls["normal"] : ('#' . $slug);
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