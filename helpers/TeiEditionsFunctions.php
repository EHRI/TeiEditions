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

function replace_urls_xml($doc, $map) {
    $xsl = implode(array('http://localhost',
        'omeka', 'plugins', 'TeiEditions', 'teibp', 'content', 'replace-urls.xsl'), '/');
    $xsldoc = new DOMDocument();
    $xsldoc->loadXML(file_get_contents($xsl));
    foreach ($xsldoc->getElementsByTagName('url-lookup') as $elem) {
        foreach ($map as $name => $path) {
            $kv = $xsldoc->createElement('entry');
            $kv->setAttribute('key', $name);
            $kv->appendChild($xsldoc->createTextNode($path));
            $elem->appendChild($kv);
            error_log("Appending " . $name . ' => ' . $path);
        }
    }
    error_log($xsldoc->saveXML());

    $proc = new XSLTProcessor();
    $proc->registerPHPFunctions('basename');
    $proc->importStylesheet($xsldoc);
    return $proc->transformToDoc($doc);
}
