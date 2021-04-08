<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2020 King's College London Department of Digital Humanities
 */

require_once '/home/mike/dev/php/Omeka/plugins/Neatline/plugin.php';
require_once '/home/mike/dev/php/TeiEditions/TeiEditionsPlugin.php';


class TeiEditions_Job_DataImporter extends Omeka_Job_AbstractJob
{
    private $_path = null;
    private $_dictPath = null;
    private $_enhance = false;
    private $_neatline = false;
    private $_lang = "eng";
    private $_mime = null;

    public function __construct(array $options)
    {
        parent::__construct($options);
    }

    public function setDictPath($dict_path)
    {
        $this->_dictPath = $dict_path;
    }

    public function setPath($path)
    {
        $this->_path = $path;
    }

    public function setNeatline($neatline)
    {
        $this->_neatline = $neatline;
    }

    public function setEnhance($enhance)
    {
        $this->_enhance = $enhance;
    }

    public function setMime($mime)
    {
        $this->_mime = $mime;
    }


    public function perform()
    {
        $created = 0;
        $updated = 0;

        $importer = new TeiEditions_Helpers_DataImporter($this->_db);
        $importer->importData(
            $this->_path,
            $this->_mime,
            $this->_neatline,
            $this->_enhance,
            $this->_dictPath,
            $this->_lang,
            $created,
            $updated,
            function () {
                _log("Done import");
            }
        );
    }
}