<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2017 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * The TeiEditions Edition record class.
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
function endswith($haystack, $needle) {
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

/**
 * Replace
 *
 * @param $str
 * @param $file_map
 * @return mixed
 */
function replace_urls($str, $file_map) {
    foreach ($file_map as $key => $value) {
        $find = "../images/$key";
        $str = str_replace($find, $value, $str);
    }
    return $str;
}

function prettify_tei($path, $img_map) {
    $teipb = dirname(dirname(__FILE__)) . '/teibp/content/teibp.xsl';

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

function replace_urls_xml($doc, $map) {
    $filename = dirname(dirname(__FILE__)) . "/teibp/content/replace-urls.xsl";
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

function get_tei_metadata(array $files) {
    return array(
        "Item Type Metadata" => array(
            "Subjects" => array(
                array('text' => 'Holocaust', 'html' => false),
                array('text' => 'Germany', 'html' => false)
            ),
            "Persons" => array(
                array('text' => 'Mike', 'html' => false),
                array('text' => 'Reto', 'html' => false)
            )
        )
    );
}

function set_tei_metadata(Item $item, File $file) {

    $element_sets = get_db()->getTable("ElementSet")
        ->findBy(array('name' => 'Item Type Metadata'));
    if (empty($element_sets)) return;

    $set = $element_sets[0];
    $elements = get_db()->getTable("Element")->findBy(
        array('element_set_id' => $set->id));

    $item->deleteElementTextsByElementId(
        array_map(function ($e) { return $e->id; }, $elements));

    $metadata = get_tei_metadata(array($file));
    $item->addElementTextsByArray($metadata);
    $item->saveElementTexts();
}
