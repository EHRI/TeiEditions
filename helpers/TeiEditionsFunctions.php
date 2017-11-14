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
 * Determine if an URL is an XML file.
 *
 * @param $file_url
 * @return bool
 */
function tei_editions_is_xml_file($file)
{
    if ($file instanceof File) {
        $file = $file->getWebPath();
    }
    $url = strpos($file, "?")
        ? substr($file, 0, strpos($file, "?"))
        : $file;

    $suffix = ".xml";
    $length = strlen($suffix);
    if ($length == 0) {
        return true;
    }

    return (substr($url, -$length) === $suffix);
}

function tei_editions_prettify_tei($path, $img_map)
{
    $teipb = web_path_to('teibp/content/teibp.xsl');

    $xsldoc = new DOMDocument();
    $xsldoc->load($teipb);
    $xsldoc->documentURI = $teipb;

    $xmldoc = new DOMDocument();
    $xmldoc->loadXML(file_get_contents($path));
    $xmldoc->documentURI = $path;

    // NB: Suppress annoying warnings here...
    $xmldoc = tei_editions_replace_urls_xml($xmldoc, $img_map);

    $proc = new XSLTProcessor;
    $proc->importStylesheet($xsldoc);
    return $proc->transformToXml($xmldoc);
}

function tei_editions_replace_urls_xml($doc, $map)
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

function tei_editions_xpath_dom_query(DOMXPath $doc, $xpath)
{
    $out = array();
    $nodes = $doc->query($xpath);
    for ($i = 0; $i < $nodes->length; $i++) {
        _log("Got node for " . $xpath . " -> " . $nodes->item($i)->tagName);
        $out[] = trim($nodes->item($i)->textContent);
    }
    return $out;
}

/**
 * Run xpath queries on a document.
 *
 * @param string $uri The uri of the document.
 * @param array $xpaths A mapping element names to an array of XPath queries.
 *
 * @return array An array of element names to matched strings.
 */
function tei_editions_xpath_query_uri($uri, $xpaths)
{
    $out = [];

    try {
        $xml = new DomDocument();
        $xml->load($uri);
        $query = new DOMXPath($xml);
        $query->registerNamespace("tei", "http://www.tei-c.org/ns/1.0");

        foreach ($xpaths as $name => $paths) {
            $all = array();
            foreach ($paths as $path) {
                $all = array_merge($all, tei_editions_xpath_dom_query($query, $path));
            }
            $out[$name] = $all;
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        _log("Error extracting TEI data from $uri: $msg", Zend_Log::ERR);
    }

    return $out;
}


function tei_editions_check_xpath_is_valid($path)
{
    $xpath = new DOMXPath(new DOMDocument);
    $xpath->registerNamespace("tei", "http://www.tei-c.org/ns/1.0");
    $check = $xpath->evaluate($path);
    return $check !== false;
}

/**
 * Render the first XML file associated with the item as TEI.
 *
 * @param Item $item an Omeka item
 * @return string
 */
function tei_editions_render_item(Item $item)
{
    $files = $item->getFiles();

    $file_url_map = array();
    foreach ($files as $file) {
        $file_url_map[basename($file->original_filename)] = $file->getWebPath();
    }

    $xml = "";
    foreach ($files as $file) {
        $path = $file->getWebPath();
        if (tei_editions_is_xml_file($path)) {
            $xml .= @tei_editions_prettify_tei($path, $file_url_map);
            break;
        }
    }
    return $xml;
}

function tei_editions_get_tei_path(Item $item)
{
    foreach ($item->getFiles() as $file) {
        $path = $file->getWebPath();
        if (tei_editions_is_xml_file($path)) {
            return $path;
        }
    }
    return null;
}

/**
 * @param File $file
 * @return array
 */
function tei_editions_extract_metadata(File $file)
{
    $xpaths = tei_editions_get_field_mappings();
    $out = [];

    foreach (tei_editions_xpath_query_uri($file->getWebPath('original'), $xpaths) as $elem => $data) {
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
function tei_editions_get_metadata(array $files)
{
    $out = array();

    foreach ($files as $file) {
        if ($file->mime_type == "text/xml"
            || $file->mime_type == "application/xml"
            || tei_editions_is_xml_file($file->original_filename)
        ) {

            $meta = tei_editions_extract_metadata($file);
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

function tei_editions_set_metadata(Item $item)
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
    $metadata = tei_editions_get_metadata($item->getFiles());
    $item->addElementTextsByArray(array('Item Type Metadata' => $metadata));
    $item->saveElementTexts();
}

function tei_editions_field_mappings_element_options()
{
    $valuePairs = array();
    $itemType = get_db()->getTable("ItemType")->findBySql("name = ?", array('name' => 'TEI'), true);
    $elems = is_null($itemType) ? array() : get_db()->getTable('Element')->findByItemType($itemType->id);
    foreach ($elems as $elem) {
        $valuePairs[$elem->id] = $elem->name;
    };

    return $valuePairs;
}

function tei_editions_get_field_mappings()
{
    $mappings = array();
    foreach (get_db()->getTable("TeiEditionsFieldMapping")->findAll() as $mapping) {
        $name = $mapping->getElementName();
        if (!array_key_exists($name, $mappings)) {
            $mappings[$name] = array();
        }
        $mappings[$name][] = $mapping->path;
    }
    return $mappings;
}