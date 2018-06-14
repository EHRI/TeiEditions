<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2018 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * Miscellaneous TEI functions.
 *
 * @package TeiEditions
 */

/**
 * Get the TEI identifier from a file name
 *
 * @param string $name
 * @return string
 */
function tei_editions_get_identifier($name)
{
    $noext = substr($name, 0, strripos($name, "."));
    $nound = strripos($noext, "_");
    return $nound ? substr($noext, 0, $nound) : $noext;
}

/**
 * Get the TEI identifier from a file name
 *
 * @param string $name
 * @return string|null
 */
function tei_editions_get_language($name, $default)
{
    $noext = substr($name, 0, strripos($name, "."));
    $nound = strripos($noext, "_");
    if ($nound) {
        return substr($noext, $nound + 1);
    } else {
        $nound = strripos($noext, ".");
        if ($nound) {
            return substr($noext, $nound + 1);
        } else {
            return $default;
        }
    }
}

function tei_editions_iso639_3to2($three)
{
    $lookup = [
        "eng" => "en",
        "deu" => "de",
        "ces" => "cs",
        "hun" => "hu",
        "heb" => "he",
        "fre" => "fr",
        "slo" => "sk",
        "slk" => "sk",
        "nld" => "nl",
        "pol" => "pl",
        "rus" => "ru",
        "ron" => "ro"
    ];
    return isset($lookup[$three]) ? $lookup[$three] : $three;
}

function tei_editions_url_slug_mappings()
{
    static $mapping = [
        'geonames' => ['http://sws.geonames.org/<id>/', 'http://www.geonames.org/<id>/.*'],
        'ehri-authority' => ['https://portal.ehri-project.eu/authorities/<id>'],
        'ehri-term' => ['https://portal.ehri-project.eu/keywords/<id>'],
        'ehri-unit' => ['https://portal.ehri-project.eu/units/<id>'],
        'ehri-institution' => ['https://portal.ehri-project.eu/institutions/<id>'],
        'holocaust-cz' => ['https://www.holocaust.cz/databaze-obeti/obet/<id>']
    ];
    return $mapping;
}

function tei_editions_slug_to_url($slug)
{
    foreach (tei_editions_url_slug_mappings() as $name => $patterns) {
        foreach ($patterns as $pattern) {
            $pos = strpos($slug, $name);
            if ($pos !== false) {
                $id = substr($slug, strlen($name) + 1);
                return str_replace('<id>', $id, $pattern);
            }
        }
    }
    // assume a local slug
    return '#' . $slug;
}

function tei_editions_url_to_slug($url)
{
    // if it starts with a hash it's a local reference
    if (!empty($url) and $url[0] == '#') {
        return substr($url, 1);
    }

    foreach (tei_editions_url_slug_mappings() as $name => $patterns) {
        foreach ($patterns as $pattern) {
            $regex = '~' . str_replace('<id>', '([^/]+)', $pattern) . '~';
            $matches = [];
            if (preg_match($regex, $url, $matches)) {
                return implode('-', array($name, $matches[1]));
            }
        }
    }
    return null;
}


/**
 * Determine if an URL is an XML file.
 *
 * @param $file_url
 * @return bool
 */
function tei_editions_is_xml_file($file_or_path)
{
    $path = $file_or_path instanceof File
        ? $file_or_path->getWebPath()
        : $file_or_path;
    $url = strpos($path, "?")
        ? substr($path, 0, strpos($path, "?"))
        : $path;

    $suffix = ".xml";
    $length = strlen($suffix);

    return $length === 0 ? $length : (substr($url, -$length) === $suffix);
}

function full_path_to($file)
{
    return (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . web_path_to($file);
}

function tei_editions_tei_to_html($path, $img_map)
{

    $html_lang = function_exists("get_html_lang")
        ? get_html_lang()
        : "en-GB";
    $lang = explode('-', $html_lang)[0];
    $tohtml = dirname(__FILE__) . '/editions.xsl';

    $xsldoc = new DOMDocument();
    $xsldoc->load($tohtml);
    $xsldoc->documentURI = $tohtml;

    $xmldoc = new DOMDocument();
    $xmldoc->load($path);
    $xmldoc->documentURI = $path;

    // NB: Suppress annoying warnings here...
    $xmldoc = tei_editions_replace_urls_xml($xmldoc, $img_map);

    $proc = new XSLTProcessor;
    $proc->setParameter('', "lang", $lang);
    $proc->importStylesheet($xsldoc);
    return $proc->transformToXml($xmldoc);
}

function tei_editions_replace_urls_xml(DOMDocument $doc, $map)
{
    $filename = dirname(__FILE__) . '/replace-urls.xsl';
    $xsldoc = new DOMDocument();
    $xsldoc->load($filename);

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

function tei_editions_check_xpath_is_valid($path)
{
    $xpath = new DOMXPath(new DOMDocument);
    $xpath->registerNamespace("tei", "http://www.tei-c.org/ns/1.0");
    $check = $xpath->evaluate($path);
    return $check !== false;
}

/**
 * Get the first File item that ends with the given extension.
 *
 * @param Item $item the item
 * @param string $ext the extension
 * @param string|null $mime an optional mimetype to match
 * @return File|bool a matched file, or FALSE
 */
function tei_editions_get_first_file_with_extension(Item $item, $ext, $mime = null)
{
    foreach ($item->getFiles() as $f) {
        $fn = $f->original_filename;
        $pos = strlen($fn) - strlen($ext);
        if (stripos($fn, $ext) === $pos && (is_null($mime) || $f->mimetype === $mime)) {
            return $f;
        }
    }
    return false;
}

/**
 * Get the main (first) TEI file for this item.
 *
 * @param Item $item the item
 * @return File|false the first TEI, or null
 */
function tei_editions_get_main_tei(Item $item)
{
    return tei_editions_get_first_file_with_extension($item, ".xml");
}

/**
 * Get the associated files for this item.
 *
 * @param Item $item
 * @return array an array of File items
 */
function tei_editions_get_associated_files(Item $item)
{
    $files = [];
    $seenfirst = false;
    foreach ($item->getFiles() as $file) {
        if (!$seenfirst and tei_editions_is_xml_file($file)) {
            $seenfirst = true;
            continue;
        } else {
            $files[] = $file;
        }
    }
    return $files;
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
            $xml .= @tei_editions_tei_to_html($path, $file_url_map);
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
 * Find the centre of an array of long/lat arrays.
 *
 * @param $points
 * @return array min point long/lat values
 */
function tei_editions_centre_points($points)
{
    $num = count($points);
    $lons = array_map(function ($p) {
        return $p[0];
    }, $points);
    $lats = array_map(function ($p) {
        return $p[1];
    }, $points);

    $lonmid = (min($lons) + max($lons)) / 2;
    $latmid = (min($lats) + max($lats)) / 2;

    return [$lonmid, $latmid];
}

/**
 * Hack to convert degrees to Neatline's metres:
 *
 * http://neatline.org/2012/09/10/geocoding-for-neatline-part-i/
 *
 * @param $coords array an array containing latitude and longitude keys
 * in degrees
 *
 * @return array an array containing latitude and longitude keys
 * in metres
 */
function tei_editions_degrees_to_metres($lon_lat)
{
    $half_circumference = 20037508.34;

    $x = $lon_lat[0] * $half_circumference / 180;
    $y = log(tan((90 + $lon_lat[1]) * pi() / 360)) / (pi() / 180);
    $y = $y * $half_circumference / 180;
    return array($x, $y);
}

/**
 * Get the degrees separation of a lon/lat square formed
 * by a set of points.
 *
 * @param array $points a set of lon/lat arrays
 * @return array the degrees separation in the lon/lat planes
 */
function tei_editions_point_spread($points)
{
    $degsep = function($i) use ($points) {
        $axis = function ($p) use ($i) {
            return $p[$i];
        };
        $pn = array_map($axis, $points);
        $pnmin = min($pn);
        $pnmax = max($pn);
        return $pnmax - $pnmin;
    };
    // NB: lat is doubled to assume a map of 1/2 dimensions.
    // This is unfortunate.
    return [$degsep(0), $degsep(1) * 2];
}

/**
 * Attempts to approximate the best OpenStreetMap zoom level given a
 * set of points (in degrees).
 *
 * @param array $points an array of lon/lat arrays
 */
function tei_editions_approximate_zoom($points, $default)
{
    $deg = max(tei_editions_point_spread($points));

    // maximum zoom level of 12
    for($i = 0; $i < 12; $i++) {
        if ($deg > 360 / pow(2, $i)) {
            return $i;
        }
    }

    return $default;
}
