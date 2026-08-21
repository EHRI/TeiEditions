<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */

/**
 * In-request cache for the TEI rendering/lookup helpers. The global
 * tei_editions_* wrapper functions delegate to a shared instance so
 * existing themes keep working; newer code can call instance() directly.
 *
 * State doesn't persist across requests. Call reset() to clear it in
 * processes that handle more than one unit of work (tests, CLI tools).
 */
class TeiEditions_Helpers_Cache
{
    /** @var TeiEditions_Helpers_Cache|null */
    private static $_instance;

    /** @var DOMDocument[] parsed XSL stylesheets, keyed by file path */
    private $xslDocs = [];

    /** @var array parsed TEI XML documents (or false on failure), keyed by file path */
    private $xmlDocs = [];

    /** @var array element_texts values, keyed by element name */
    private $elementValues = [];

    /** @var int|null the 'Identifier' element's id */
    private $identifierElementId;

    /**
     * @return TeiEditions_Helpers_Cache
     */
    public static function instance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Clear all cached state.
     */
    public function reset()
    {
        $this->xslDocs = [];
        $this->xmlDocs = [];
        $this->elementValues = [];
        $this->identifierElementId = null;
    }

    /**
     * Transform a TEI document to HTML via the editions.xsl stylesheet.
     *
     * @param string $path the TEI XML path
     * @param array $img_map a map of image paths in the TEI to
     * their web-resolvable paths
     * @param null $text_lang the 2-letter language code
     * @param bool $meta whether to also extract metadata from the header
     * @param bool $entities whether to also extract a set of entities
     * from the header
     *
     * @return array containing keys for 'html' (default) and optionally
     * 'entities' and 'meta'
     */
    public function teiToHtml($path, $img_map, $text_lang = null, $meta = false, $entities = false)
    {
        $html_lang = function_exists("get_html_lang")
            ? get_html_lang()
            : "en-GB";
        $lang = explode('-', $html_lang)[0];
        $text_lang = $text_lang === null ? $lang : $text_lang;
        $tohtml = __DIR__ . '/editions.xsl';

        $xsldoc = $this->_cachedXsl($tohtml);
        $xsldoc->documentURI = $tohtml;

        $xmldoc = $this->xmlDocument($path);
        if ($xmldoc === false) {
            $xmldoc = new DOMDocument();
        }
        $xmldoc->documentURI = $path;

        // NB: Suppress annoying warnings here...
        $xmldoc = $this->replaceUrlsXml($xmldoc, $img_map);

        $proc = new XSLTProcessor;
        $proc->setParameter('', "lang", $lang);
        $proc->setParameter('', 'text-lang', $text_lang);
        $proc->importStylesheet($xsldoc);

        $data = [];

        $data["html"] = $proc->transformToXml($xmldoc);

        if ($entities) {
            $proc->setParameter('', "entities", true);
            $data["entities"] = $proc->transformToXml($xmldoc);
        }
        if ($meta) {
            $proc->setParameter('', 'file-id', tei_editions_get_identifier(basename($path)));
            $proc->setParameter('', 'meta', true);
            $data["meta"] = $proc->transformToXml($xmldoc);
        }
        return $data;
    }

    /**
     * Replace image URLs in a TEI document via the replace-urls.xsl stylesheet.
     *
     * @param DOMDocument $doc
     * @param array $map a map of names to their web-resolvable paths
     * @return DOMDocument
     */
    public function replaceUrlsXml(DOMDocument $doc, $map)
    {
        $filename = __DIR__ . '/replace-urls.xsl';
        $xsldoc = $this->_cachedXsl($filename);

        foreach ($xsldoc->getElementsByTagName('url-lookup') as $elem) {
            foreach ($map as $name => $path) {
                $kv = $xsldoc->createElement('entry');
                $kv->setAttribute('key', $name);
                $kv->appendChild($xsldoc->createTextNode($path));
                $elem->appendChild($kv);
            }
        }

        $proc = new XSLTProcessor();
        $proc->registerPHPFunctions('basename');
        $proc->importStylesheet($xsldoc);
        return $proc->transformToDoc($doc);
    }

    /**
     * Get the distinct public values of the given element name.
     *
     * @param string $element_name
     * @return array
     */
    public function elementsByName($element_name)
    {
        if (!array_key_exists($element_name, $this->elementValues)) {
            $db = get_db();
            $this->elementValues[$element_name] = $db->query("SELECT DISTINCT text
                              FROM {$db->prefix}element_texts t
                              JOIN {$db->prefix}elements e
                                ON t.element_id = e.id
                              JOIN {$db->prefix}items i
                                ON t.record_id = i.id
                              WHERE i.public AND e.name  = ?
                              ORDER BY text",
                ["name" => $element_name]
            )->fetchAll($style = 0, $col = 0);
        }
        return $this->elementValues[$element_name];
    }

    /**
     * Get an Item by its DC Identifier.
     *
     * @param string $identifier the identifier value
     * @return Item|null
     * @throws Omeka_Record_Exception|Exception
     */
    public function itemByIdentifier($identifier)
    {
        if ($this->identifierElementId === null) {
            $element = get_db()->getTable('Element')->findBy([
                'name' => 'Identifier'
            ])[0]; // hack!
            $this->identifierElementId = $element->id;
        }
        $text = get_db()->getTable('ElementText')->findBy([
            'element_id' => $this->identifierElementId,
            'text' => $identifier
        ]);
        if (!empty($text)) {
            $item = get_db()->getTable('Item')->find($text[0]->record_id);
            if (!is_null($item) && $item !== false) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param string $path
     * @return DOMDocument|false a clone of a cached parse of the TEI XML
     * document at $path, or false if it could not be loaded. Cloned so
     * that callers (e.g. teiToHtml() and DocumentProxy, which parse the
     * same item's main TEI file) can't mutate each other's copy.
     */
    public function xmlDocument($path)
    {
        if (!array_key_exists($path, $this->xmlDocs)) {
            $doc = new DOMDocument();
            $this->xmlDocs[$path] = $doc->load($path) ? $doc : false;
        }
        return $this->xmlDocs[$path] === false ? false : clone $this->xmlDocs[$path];
    }

    /**
     * @param string $path
     * @return DOMDocument a clone of a cached parse of the stylesheet at
     * $path. Cloned because reusing the same instance across multiple
     * importStylesheet() calls is unsafe, and replaceUrlsXml() mutates it.
     */
    private function _cachedXsl($path)
    {
        if (!isset($this->xslDocs[$path])) {
            $doc = new DOMDocument();
            $doc->load($path);
            $this->xslDocs[$path] = $doc;
        }
        return clone $this->xslDocs[$path];
    }
}
