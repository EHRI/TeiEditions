<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */

require_once __DIR__ . '/TeiEditions_Helpers_DataFetcher.php';

/**

*/
class TeiEditions_Helpers_BatchEnhancer
{
    private $_enhancer;

    public function __construct(TeiEditions_TeiEnhancerInterface $enhancer) {
        $this->_enhancer = $enhancer;
    }

    public function enhance($path, $mime, &$added): string
    {
        switch ($mime) {
            case "text/xml":
            case "application/xml":
                $temp = tempnam(sys_get_temp_dir(), '');
                if (($fh = fopen($temp, 'w')) !== false) {
                    $doc = TeiEditions_Helpers_DocumentProxy::fromUriOrPath($path);
                    fwrite($fh, $this->enhanceFile($doc, $added));
                    fclose($fh);
                } else {
                    throw new Exception("Unable to open temp file for writing: $temp");
                }
                return $temp;
            case "application/zip":
                return $this->enhanceZip($path, $added);
            default:
                throw new Exception("Unrecognised mimetype: $mime");
        }
    }

    private function enhanceZip($path, &$added): string
    {
        $temp = tempnam(sys_get_temp_dir(), '');
        $out = new ZipArchive;
        if ($out->open($temp) !== false) {
            $zip = new ZipArchive;
            if ($zip->open($path) !== false) {
                for ($i = 0; $i < $zip->count(); $i++) {
                    $name = $zip->getNameIndex($i);
                    $xml_str = $zip->getFromIndex($i);
                    $doc = TeiEditions_Helpers_DocumentProxy::fromString($xml_str);
                    $out->addFromString($name, $this->enhanceFile($doc, $added));

                    // In theory this should extend the default script timeout
                    // on every pass...
                    set_time_limit(10);
                }
                $zip->close();
            } else {
                throw new Exception("Unable to open input zip file");
            }
            $out->close();
        } else {
            throw new Exception("Unable to create output zip file");
        }

        return $temp;
    }

    private function enhanceFile(TeiEditions_Helpers_DocumentProxy $doc, &$added): string
    {
        $added += $this->_enhancer->addReferences($doc);
        return $doc->document()->saveXML();
    }
}