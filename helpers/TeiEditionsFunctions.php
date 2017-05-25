<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2017 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * Miscellaneous TEI functions.
 *
 * @package TeiEditions
 */

/**
 * Determine if a string ends with another.
 *
 * @param $haystack
 * @param $needle
 * @return bool
 */
function endswith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

function prettify_tei($path, $img_map)
{
    $teipb = web_path_to('teibp/content/teibp.xsl');

    $xsldoc = new DOMDocument();
    $xsldoc->load($teipb);
    $xsldoc->documentURI = $teipb;

    $xmldoc = new DOMDocument();
    $xmldoc->loadXML(file_get_contents($path));
    $xmldoc->documentURI = $path;

    // NB: Suppress annoying warnings here...
    $xmldoc = replace_urls_xml($xmldoc, $img_map);

    $proc = new XSLTProcessor;
    $proc->importStylesheet($xsldoc);
    return $proc->transformToXml($xmldoc);
}

function replace_urls_xml($doc, $map)
{
    $filename = web_path_to('teibp/content/replace-urls.xsl');
    $xsldoc = new DOMDocument();
    $xsldoc->loadXML(file_get_contents($filename));

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

function xpath_dom_query(DOMXPath $doc, $xpath)
{
    $out = array();
    $nodes = $doc->query($xpath);
    for ($i = 0; $i < $nodes->length; $i++) {
        _log("Got node for " . $xpath . " -> " . $nodes->item($i)->tagName);
        $out[] = $nodes->item($i)->textContent;
    }
    return $out;
}

/**
 * Run xpath queries on a document.
 *
 * @param string $uri The uri of the document.
 * @param array $xpaths An array of element names to XPath queries.
 *
 * @return array An array of element names to matched strings.
 */
function xpath_query_uri($uri, $xpaths)
{
    $out = [];

    try {
        $xml = new DomDocument();
        $xml->load($uri);
        $query = new DOMXPath($xml);
        $query->registerNamespace("tei", "http://www.tei-c.org/ns/1.0");

        foreach ($xpaths as $name => $path) {
            $out[$name] = xpath_dom_query($query, $path);
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        _log("Error extracting TEI data from $uri: $msg", Zend_Log::ERR);
    }

    return $out;
}


/**
 * @param File $file
 * @return array
 */
function extract_metadata(File $file)
{
    // TODO: Configure these mappings in the database
    $xpaths = array(
        "Persons" => "/tei:TEI/tei:teiHeader/tei:profileDesc/tei:abstract/tei:persName",
        "Subjects" => "/tei:TEI/tei:teiHeader/tei:profileDesc/tei:abstract/tei:term",
        "Places" => "/tei:TEI/tei:teiHeader/tei:profileDesc/tei:abstract/tei:placeName",
        "XML Text" => "/tei:TEI/tei:text",
        "Source Details" => "/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc",
        "Encoding Description" => "/tei:TEI/tei:teiHeader/tei:encodingDesc/tei:projectDesc",
        "Publisher" => "/tei:TEI/tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:publisher",
        "Publication Date" => "/tei:TEI/tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:date",
        "Author" => "/tei:TEI/tei:teiHeader/tei:fileDesc/tei:titleStmt/tei:author",
    );

    $out = [];

    foreach (xpath_query_uri($file->getWebPath('original'), $xpaths) as $elem => $data) {
        $meta = [];
        foreach ($data as $text) {
            $meta[] = array('text' => $text, 'html' => false);
        }
        $out[$elem] = $meta;
    }

    _log("Extracted from " . $file->getWebPath() . " -> " . json_encode($out));

    return $out;
}

/** @var File[] $files
 *
 * Returns an array like:
 *
 * array(
 *   "Subjects" => array(
 *       array('text' => 'Germany', 'html' => false)
 *   ),
 *   "Persons" => array(
 *       array('text' => 'Bob', 'html' => false)
 *   )
 * );
 *
 * @return array
 */
function get_tei_metadata(array $files)
{
    $out = array();

    foreach ($files as $file) {
        if ($file->mime_type == "text/xml"
            || $file->mime_type == "application/xml"
            || endswith($file->original_filename, ".xml")
        ) {

            $meta = extract_metadata($file);
            foreach ($meta as $elem => $data) {
                if (array_key_exists($elem, $out)) {
                    $out[$elem] = array_unique(array_merge($out[$elem], $data), SORT_REGULAR);
                } else {
                    $out[$elem] = $data;
                }
            }
        }
    }

    return $out;
}

function set_tei_metadata(Item $item)
{
    $item_types = get_db()->getTable("ItemType")->findBy(array('name' => 'TEI'));
    if (empty($item_types)) {
        _log("No TEI item type found, skipping metadata.", Zend_Log::WARN);
        return;
    }

    $elements = get_db()->getTable('Element')->findByItemType($item_types[0]->id);
    $ids = array_map(function ($e) {
        return $e->id;
    }, $elements);

    $item->deleteElementTextsByElementId($ids);
    $metadata = get_tei_metadata($item->getFiles());
    $item->addElementTextsByArray(array('Item Type Metadata' => $metadata));
    $item->saveElementTexts();
}
