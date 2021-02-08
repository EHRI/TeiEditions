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
    const TEI_NS = "http://www.tei-c.org/ns/1.0";

    private $uriOrPath;
    private $tei;
    private $xpath;
    private $htmlCache;

    /**
     * TeiEditionsDocumentProxy constructor.
     * @param $uriOrPath string an XML file or path
     */
    private function __construct(DOMDocument $doc, $uriOrPath)
    {
        $this->uriOrPath = $uriOrPath;
        $this->tei = $doc;
        $this->tei->preserveWhiteSpace = false;
        $this->xpath = new DOMXPath($this->tei);
        $this->xpath->registerNamespace("t", $this::TEI_NS);
        $this->xpath->registerNamespace("tei", $this::TEI_NS);
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

    public static function fromDocument(DOMDocument $elem, $uriOrPath)
    {
        return new TeiEditionsDocumentProxy($elem, $uriOrPath);
    }

    /**
     * Get the underlying DOMDocument object.
     *
     * @return DOMDocument
     */
    public function document() {
        return $this->tei;
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
        $nodes = $this->xpath->query($xpath);
        for ($i = 0; $i < $nodes->length; $i++) {
            $out[] = trim(preg_replace('/\s+/', ' ', $nodes->item($i)->textContent));
        }
        return $out;
    }

    public function manuscriptIds()
    {
        $path = "/t:TEI/t:teiHeader/t:fileDesc/t:sourceDesc/t:msDesc/t:msIdentifier/*/@ref";
        $values = [];
        $list = $this->xpath->query($path);
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
            error_log("Extract: $elem: " . json_encode($data));
            foreach ($data as $text) {
                $out[] = array('element_id' => $elem, 'text' => $text, 'html' => false);
            }
        }
        return $out;
    }

    /**
     * Extract references for a given entity tag name from a TEI
     * body text and return the data as a [$name => $url] array.
     *
     * NB: If an entity is found without a ref attribute a
     * numeric ref will be generated (and added to the document)
     * if the $addRefs param is true.
     *
     * @param string $nameTag the tag name to locate
     * @param integer $idx a count
     * @param boolean $addRefs add missing ref attributes with an
     * incrementing index.
     * @return array an array of [name => urls]
     */
    function entityReferences($nameTag, &$idx = 0, $addRefs = false)
    {
        $names = [];
        $urls = [];
        if (!($docid = @$this->xpath->query(
            "/t:TEI/t:teiHeader/t:profileDesc/t:creation/t:idno/text()")
            ->item(0)
            ->textContent)) {
            $docid = $this->xmlId();
        }
        $paths = [
            "/t:TEI/t:teiHeader/t:profileDesc/t:creation//t:$nameTag",
            "/t:TEI/t:text/t:body/*//t:$nameTag"
        ];
        foreach ($paths as $path) {
            $nodes = $this->xpath->query($path);
            for ($i = 0; $i < $nodes->length; $i++) {
                $ref = $nodes->item($i)->getAttribute("ref");
                $text = $nodes->item($i)->textContent;
                if ($ref) {
                    $urls[$ref] = $text;
                } else {
                    $idx++;
                    if ($addRefs) {
                        $locUrl = "#" . $docid . "_" . $idx;
                        $nodes->item($i)->setAttribute("ref", $locUrl);
                        $names[$text] = $locUrl;
                    }
                }
            }
        }

        return array_merge($names, array_flip($urls));
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
        $entities = $this->xpath->query($path);
        foreach ($entities as $entity) {
            $names = $this->xpath->evaluate("./t:{$nameTag}[1]", $entity);
            if ($names->length) {
                $name = $names->item(0)->textContent;
                $links = $this->xpath->evaluate("./t:linkGrp/t:link", $entity);
                $urls = [];
                for ($i = 0; $i < $links->length; $i++) {
                    $type = $this->xpath->evaluate("./@type", $links->item($i))[0]->textContent;
                    $url = $this->xpath->evaluate("./@target", $links->item($i))[0]->textContent;
                    $urls[$type] = $url;
                }
                $slug = isset($urls["normal"])
                    ? tei_editions_url_to_slug($urls["normal"])
                    : @$this->xpath->evaluate("./@xml:id", $entity)->item(0)->textContent;
                $item = new TeiEditionsEntity;
                $item->name = $name;
                $item->slug = $slug;
                $item->urls = $urls;

                $birth = $this->xpath->evaluate("./t:birth/@when", $entity);
                if ($birth->length) {
                    $item->birth = $birth->item(0)->textContent;
                }

                $death = $this->xpath->evaluate("./t:death/@when", $entity);
                if ($death->length) {
                    $item->death = $death->item(0)->textContent;
                }

                $desc = $this->xpath->evaluate("./t:note/t:p", $entity);
                if ($desc->length) {
                    for ($i = 0; $i < $desc->length; $i++) {
                        $item->notes[$i] = $desc->item($i)->textContent;
                    }
                }

                $lat_long = $this->xpath->evaluate("./t:location/t:geo[1]", $entity);
                if ($lat_long->length) {
                    $parts = explode(" ", $lat_long->item(0)->textContent);
                    list($item->latitude, $item->longitude) = $parts;
                }
                $out[$name] = $item;
            }
        }
        return array_values($out);
    }

    /**
     * Add an entity to the header with the given list/item/name.
     *
     * @param string $listTag the list tag name
     * @param string $itemTag the item tag name
     * @param string $nameTag the place tag name
     * @param TeiEditionsEntity $entity the entity
     */
    function addEntity($listTag, $itemTag, $nameTag, TeiEditionsEntity $entity)
    {

        $source = $this->xpath->query(
            "/t:TEI/t:teiHeader/t:fileDesc/t:sourceDesc")->item(0);
        $list = $source->getElementsByTagNameNS($this::TEI_NS, $listTag)->length
            ? $source->getElementsByTagNameNS($this::TEI_NS, $listTag)->item(0)
            : $source->appendChild($this->tei->createElementNS($this::TEI_NS, $listTag));

        $item = $this->tei->createElementNS($this::TEI_NS, $itemTag);
        $list->appendChild($item);
        $item->appendChild($this->tei->createElementNS($this::TEI_NS, $nameTag, htmlspecialchars($entity->name)));

        if ($entity->birth) {
            $birth = $this->tei->createElementNS($this::TEI_NS, "birth");
            $birth->setAttribute("when", $entity->birth);
        }

        if ($entity->death) {
            $death = $this->tei->createElementNS($this::TEI_NS, "death");
            $death->setAttribute("when", $entity->death);
        }

        if ($entity->hasGeo()) {
            $geo = $this->tei->createElementNS(
                $this::TEI_NS, "geo", $entity->latitude . " " . $entity->longitude);
            $loc = $this->tei->createElementNS($this::TEI_NS, "location");
            $item->appendChild($loc);
            $loc->appendChild($geo);
        }

        // Special case - if we have a local URL anchor, it refers to an xml:id
        // otherwise, add a link group.
        if ($entity->ref()[0] == '#') {
            $item->setAttributeNS("http://www.w3.org/XML/1998/namespace",
                "id", substr($entity->ref(), 1));
        }
        if (!empty($entity->urls)) {
            $linkGrp = $this->tei->createElementNS($this::TEI_NS, 'linkGrp');
            $item->appendChild($linkGrp);
            foreach ($entity->urls as $type => $url) {
                $link = $this->tei->createElementNS($this::TEI_NS, "link");
                $link->setAttribute("type", $type);
                $link->setAttribute("target", $url);
                $linkGrp->appendChild($link);
            }
        }
        if (!empty($entity->notes)) {
            $desc = $this->tei->createElementNS($this::TEI_NS, "note");
            $item->appendChild($desc);
            foreach ($entity->notes as $note) {
                $desc->appendChild(
                    $this->tei->createElementNS(
                        $this::TEI_NS, "p", htmlspecialchars($note)));
            }
        }
    }

    public function metaHtml() {
        return $this->asHtml()["meta"];
    }

    public function entityBodyHtml($urls, $slug)
    {
        $url = isset($urls["normal"]) ? $urls["normal"] : ('#' . $slug);
        $xml = new DomDocument;
        $xml->loadXML($this->asHtml()["entities"]);
        $query = new DOMXPath($xml);
        $path = "/div/div[@data-ref='$url']/div[@class='content-info-entity-body']";
        $node = $query->query($path);
        return empty($node) ? "" : $xml->saveXML($node->item(0));
    }

    public function asHtml()
    {
        if (is_null($this->htmlCache)) {
            $this->htmlCache = tei_editions_tei_to_html($this->uriOrPath, [], null, true, true);
        }
        return $this->htmlCache;
    }

    public function asSimpleHtml()
    {
        return $this->asHtml()["html"];
    }
}