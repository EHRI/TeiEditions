<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2020 King's College London Department of Digital Humanities
 */

require_once __DIR__ . '/TeiEditions_DataFetcher.php';
require_once __DIR__ . '/TeiEditions_DocumentProxy.php';
require_once __DIR__ . '/TeiEditions_TeiEnhancer.php';


class TeiEditions_DataImporter
{
    private $_db;

    /**
     * @package TeiEditionsDataImporter constructor.
     */
    public function __construct(Omeka_Db $db)
    {
        $this->_db = $db;
    }

    /**
     * Import data from supplied TEI(s).
     *
     * @param string $path the file path to import
     * @param string $mime the mime type of the import file. Either text/xml or application/zip are supported
     * @param boolean $neatline whether or not to create a Neatline item from the TEI data
     * @param boolean $enhance whether or not to run enhancement on the input file by looking up entity metadata
     * @param string|null $dictPath an optional path to a TEI dictionary file
     * @param string $lang the default language or enhanced metadata
     * @param int $created out-param for number of items created
     * @param int $updated out-param for number of items updated
     * @param callable $onDone function to call on completion
     * @throws Omeka_Record_Exception
     */
    public function importData($path, $mime, $neatline, $enhance, $dictPath, $lang, &$created, &$updated, $onDone)
    {
        _log("PERFORMING DATA IMPORT: " . json_encode([
                "path" => $path,
                "dict_path" => $dictPath,
                "neatline" => $neatline,
                "enhance" => $enhance,
                "lang" => $lang,
                "mime" => $mime
            ]));

        $dict = [];
        if ($dictPath) {
            $doc = TeiEditions_DocumentProxy::fromUriOrPath($dictPath);
            foreach ($doc->entities() as $entity) {
                $dict[$entity->ref()] = $entity;
            }
        }

        $tx = $this->_db->getAdapter()->beginTransaction();
        try {
            switch ($mime) {
                case "text/xml":
                case "application/xml":
                    $this->updateItem($path, basename($path), $enhance, $dict, $neatline, $lang, $created, $updated);
                    break;
                case "application/zip":
                    $this->readImportZip($path, $enhance, $dict, $neatline, $lang, $created, $updated);
                    break;
                default:
                    error_log("Unhandled file extension: $mime");
            }
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollBack();
            throw $e;
        } finally {
            $onDone();
        }
    }

    /**
     * Update Omeka Items from their TEI data.
     *
     * @param array $items an array of items to update
     * @param boolean $neatline whether or not to (re)create a Neatline item from the TEI data
     * @param int $updated
     * @throws Omeka_Record_Exception
     */
    function updateItems($items, $neatline, &$updated)
    {
        $tx = $this->_db->getAdapter()->beginTransaction();

        $currentItem = null;
        try {
            foreach ($items as $item) {
                $currentItem = $item;
                foreach ($item->getFiles() as $file) {
                    if (tei_editions_is_xml_file($file)) {
                        $item->deleteElementTexts();
                        $doc = $this->getDoc($file->getWebPath(), $file->getProperty('display_title'));
                        $this->updateItemFromTEI($item, $doc, $neatline);
                        $updated++;
                        break;
                    }
                }
            }
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollBack();
            if ($currentItem) {
                $msg = __("There was an processing element %d '%s': %s",
                    $currentItem->id, metadata($currentItem, "display_title"), $e->getMessage());
                throw new Exception($msg, $e);
            } else {
                throw $e;
            }
        }
    }


    /**
     * @param string $path the file's path
     * @param string $name the file's base name
     * @param string $mime the file's mimetype
     * @param int $done out-param for number of items associated
     * @throws Omeka_Record_Exception
     */
    function associateItems($path, $name, $mime, &$done)
    {
        $tx = $this->_db->getAdapter()->beginTransaction();
        try {
            if ($path === "") {
                throw new Exception("upload failed (check max file size?)");
            }
            if (preg_match('/.+\.zip$/', $path) or $mime === 'application/zip') {
                $done += $this->readAssociatedItemsZip($path);
            } else {
                $this->addAssociatedFile($path, $name);
                $done++;
            }
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollBack();
            throw $e;
        }
    }


    /**
     * @param string $zipPath the path to the zip file
     * @param boolean $enhance whether or not to enhance TEIs by looking up entity references
     * @param array $dict a dictionary of local TEI references
     * @param boolean $neatline whether or not to create Neatline items
     * @param string $lang the default language for entity lookups during enhancement
     * @param int $created out-param for number of items created
     * @param int $updated out-param for the number of items updated
     * @throws Omeka_Record_Exception
     */
    private function readImportZip($zipPath, $enhance, $dict, $neatline, $lang, &$created = 0, &$updated = 0)
    {
        $temp = $this->createTempDir();

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) === true) {
                $zip->extractTo($temp);
                $zip->close();

                foreach (glob($temp . '/*.xml') as $path) {
                    $this->updateItem($path, basename($path), $enhance, $dict, $neatline, $lang, $created, $updated);
                    // NB: If I'm reading the docs right this should prevent a timeout
                    // on large zips:
                    set_time_limit(10);
                }
            } else {
                throw new Exception("Zip cannot be opened");
            }
        } finally {
            $this->deleteDir($temp);
        }
    }

    /**
     * Update an item from the given TEI XML file.
     *
     * @param string $path the path to the XML file
     * @param string $name the item name
     * @param int $created out-param for the number of created items
     * @param int $updated out-param for the number of updated items
     * @throws Omeka_Record_Exception
     * @throws Exception
     */
    private function updateItem($path, $name, $enhance, $dict, $neatline, $lang, &$created, &$updated)
    {
        _log("Importing file: $path");
        $create = false;

        if ($enhance) {
            $eDir = dirname($path) . DIRECTORY_SEPARATOR . "enhance-tei";
            if (!file_exists($eDir)) {
                mkdir($eDir);
            }

            $opts = [];
            if ($geonamesUser = get_option("tei_editions_geonames_username")) {
                $opts['geonames_username'] = $geonamesUser;
            }
            $ePath = $eDir . DIRECTORY_SEPARATOR . basename($path);
            $tei = TeiEditions_DocumentProxy::fromUriOrPath($path);
            $src = new TeiEditions_DataFetcher($dict, $lang, $opts);
            $enhancer = new TeiEditions_TeiEnhancer($tei, $src);
            $enhancer->addReferences();
            $f = fopen($ePath, "w");
            fwrite($f, $tei->document()->saveXML());
            register_shutdown_function(function () use ($ePath) {
                if (file_exists($ePath)) {
                    unlink($ePath);
                }
            });
            $path = $ePath;
        }

        $doc = $this->getDoc($path, $name);
        $item = $this->getOrCreateItem($doc, $create);
        $this->updateItemFromTEI($item, $doc, $neatline);

        $this->addOrUpdateItemFile($item, $path, $name, true);
        if ($create) {
            $created++;
        } else {
            $updated++;
        }
    }

    /**
     * @param string $path
     * @param string $name
     * @return TeiEditions_DocumentProxy
     * @throws Exception
     */
    private function getDoc($path, $name)
    {
        $doc = TeiEditions_DocumentProxy::fromUriOrPath($path);
        if (is_null($doc->xmlId())) {
            throw new Exception("TEI document '$name' must have a unique 'xml:id' attribute");
        }
        if (is_null($doc->recordId())) {
            throw new Exception("TEI document '$name' must have a valid 'profileDesc/creation/idno' value");
        }

        return $doc;
    }

    /**
     * Get an existing item by TEI identifier, or create a new one.
     *
     * @param TeiEditions_DocumentProxy $doc
     * @param bool $created
     * @return Item
     * @throws Omeka_Record_Exception
     * @throws Exception
     */
    private function getOrCreateItem(TeiEditions_DocumentProxy $doc, &$created)
    {
        $item = tei_editions_get_item_by_identifier($doc->recordId());
        $created = is_null($item);
        return $created ? new Item : $item;
    }


    /**
     * Update an item's data from that extracted from the TEI document.
     *
     * @param Item $item
     * @param TeiEditions_DocumentProxy $doc
     * @param bool $neatline create a Neatline exhibit
     * @throws Omeka_Record_Exception|Exception
     */
    private function updateItemFromTEI(Item $item, TeiEditions_DocumentProxy $doc, $neatline)
    {
        $item->item_type_id = get_option('tei_editions_default_item_type');
        $data = $doc->metadata(TeiEditions_FieldMapping::fieldMappings());
        $item->deleteElementTexts();
        $item->addElementTextsByArray($data);
        $item->save();

        if ($neatline) {
            $this->updateNeatlineExhibit($item, $doc);
        }
    }


    /**
     * Add a file to the item, or update it from the given
     * path if the original filename already exists.
     *
     * @param Item $item the item
     * @param string $path the file path
     * @param string $name the file name
     * @param bool $is_primary if this file is the primary TEI
     */
    private function addOrUpdateItemFile(Item $item, $path, $name, $is_primary = false)
    {
        $primaryXml = $is_primary ? $name : null;
        foreach ($item->getFiles() as $file) {
            if (is_null($primaryXml) && tei_editions_is_xml_file($file)) {
                $primaryXml = $file->original_filename;
            }
            if ($file->original_filename == $name) {
                $file->unlinkFile();
                $file->delete();
            }
        }
        @insert_files_for_item($item, "Filesystem",
            ['source' => $path, 'name' => $name]);

        $images = [];
        $others = [];
        $xml = [];
        foreach ($item->getFiles() as $file) {
            if (tei_editions_is_xml_file($file)) {
                if ($primaryXml && $file->original_filename == $primaryXml) {
                    array_unshift($xml, $file);
                } else {
                    $xml[] = $file;
                }
            } else if (substr($file->mime_type, 0, 5) == "image") {
                $images[] = $file;
            } else {
                $others[] = $file;
            }
        }
        $order = 1;
        foreach (array_merge($images, $others, $xml) as $file) {
            $file->order = $order++;
            $file->save();
        }
    }


    /**
     * Fetch the Neatline record that provides a template for
     * item-based ones.
     *
     * @return NeatlineExhibit|null
     */
    private function getTemplateNeatline()
    {
        $id = get_option('tei_editions_template_neatline');
        if ($id) {
            if ($t = $this->_db->getTable('NeatlineExhibit')->findBy($id)) {
                return $t[0];
            }
        }
        return null;
    }

    /**
     * @param Item $entity
     * @param TeiEditions_DocumentProxy $doc
     * @throws Omeka_Record_Exception
     */
    private function updateNeatlineExhibit(Item $entity, TeiEditions_DocumentProxy $doc)
    {
        if (!plugin_is_active('Neatline')) {
            return;
        }

        $entities = array_unique($doc->entities(), SORT_REGULAR);
        $withgeo = array_filter($entities, function ($i) {
            return $i->hasGeo();
        });

        // if there are no mapped places, delete existing exhibits and return
        // early.
        if (empty($withgeo)) {
            $exhibits = $this->_db->getTable('NeatlineExhibit')
                ->findBy(['slug' => strtolower($doc->recordId())]);
            foreach ($exhibits as $exhibit) {
                $exhibit->delete();
            }
            return;
        }

        $exhibit = $this->getOrCreateNeatlineExhibit($doc);
        $exhibit->deleteChildRecords();
        $title = metadata($entity, 'display_title');
        $exhibit->title = $title;
        $exhibit->slug = strtolower($doc->recordId());
        $exhibit->public = true;
        $exhibit->spatial_layer = 'OpenStreetMap';
        $exhibit->narrative = $doc->asSimpleHtml();
        if (plugin_is_active('NeatlineText')) {
            $exhibit->widgets = 'Text';
        }

        // copy settings from template exhibit
        if ($template = $this->getTemplateNeatline()) {
            $exhibit->styles = $template->styles;
            $exhibit->spatial_layer = $template->spatial_layer;
            $exhibit->spatial_layers = $template->spatial_layers;
            $exhibit->spatial_querying = $template->spatial_querying;
            $exhibit->wms_layers = $template->wms_layers;
            $exhibit->wms_address = $template->wms_address;
        }

        $exhibit->save($throwIfInvalid = true);

        // copy records from the template...
        if ($id = get_option('tei_editions_template_neatline')) {
            foreach ($this->_db->getTable('NeatlineRecord')->findBy(['exhibit_id' => $id]) as $t) {
                $record = clone $t;
                $record->id = null;
                $record->exhibit_id = $exhibit->id;
                $record->save();
            }
        }

        $points_deg = [];
        $points_metres = [];
        foreach ($entities as $entity) {
            $this->createRecord($doc, $exhibit, $entity, $points_deg, $points_metres);
        }

        if (!empty($points_metres)) {
            $exhibit->map_focus = implode(",", tei_editions_centre_points($points_metres));
            $exhibit->map_zoom = tei_editions_approximate_zoom($points_deg, 7);
        }
        $exhibit->save($throwIfInvalid = true);
    }


    /**
     * @param TeiEditions_DocumentProxy $doc
     * @return NeatlineExhibit
     * @throws Omeka_Record_Exception
     */
    private function getOrCreateNeatlineExhibit(TeiEditions_DocumentProxy $doc)
    {
        $exhibits = $this->_db->getTable('NeatlineExhibit')
            ->findBy(['slug' => strtolower($doc->recordId())]);
        return empty($exhibits) ? new NeatlineExhibit : $exhibits[0];
    }

    /**
     * @param TeiEditions_DocumentProxy $doc
     * @param NeatlineExhibit $exhibit
     * @param TeiEditions_Entity $item
     * @param $points_deg
     * @param $points_metres
     */
    private function createRecord(TeiEditions_DocumentProxy $doc,
                                  NeatlineExhibit $exhibit,
                                  TeiEditions_Entity $item,
                                  &$points_deg, &$points_metres)
    {
        $record = new NeatlineRecord;
        $record->exhibit_id = $exhibit->id;
        $record->title = $item->name;
        $record->added = (new DateTime('now'))->format('Y-m-d H:i:s');
        if ($item->hasGeo()) {
            $deg = [$item->longitude, $item->latitude];
            $metres = tei_editions_degrees_to_metres($deg);
            $points_deg[] = $deg;
            $points_metres[] = $metres;
            $record->coverage = "Point(" . implode(" ", $metres) . ")";
        }
        $record->tags = $this->getRecordTags($item->urls);
        $body = $doc->entityBodyHtml($item->urls, $item->slug);
        if ($body) {
            $record->body = $body;
        }
        if (isset($item->slug)) {
            $record->slug = $item->slug;
        }
        $record->save();
    }

    private function getRecordTags($urls)
    {
        $tags = [];
        foreach ($urls as $url) {
            if (preg_match('/geonames/', $url)) {
                $tags[] = "location";
            }
            if (preg_match('/ehri_camps/', $url)) {
                $tags[] = "camp";
            }
            if (preg_match('/ehri_ghettos/', $url)) {
                $tags[] = "ghetto";
            }
            if (preg_match('/ehri_pers/', $url)) {
                $tags[] = "person";
            }
            if (preg_match('/ehri_cb/', $url)) {
                $tags[] = "organisation";
            }
            if (preg_match('/ehri_terms/', $url)) {
                $tags[] = "subject";
            }
        }
        return implode(',', array_unique($tags));
    }

    /**
     * @param $zipPath
     * @return int
     * @throws Exception
     * @throws Omeka_Record_Exception
     */
    private function readAssociatedItemsZip($zipPath)
    {
        $temp = $this->createTempDir();
        $done = 0;

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) === true) {
                $zip->extractTo($temp);
                $zip->close();

                foreach (glob($temp . '/*') as $path) {
                    $this->addAssociatedFile($path, basename($path));
                    $done++;
                }
            } else {
                throw new Exception("Zip cannot be opened");
            }
            return $done;
        } finally {
            $this->deleteDir($temp);
        }
    }

    /**
     * Add a file to an item assuming the filename prior to the
     * first underscore is the item identifier.
     *
     * @param $path
     * @param $name
     * @throws Exception
     * @throws Omeka_Record_Exception
     */
    private function addAssociatedFile($path, $name)
    {
        $id = tei_editions_get_identifier($name);
        $item = tei_editions_get_item_by_identifier($id);
        if (is_null($item)) {
            throw new Exception("Unable to locate item with identifier: " . $id . " (file: $path)");
        }
        $this->addOrUpdateItemFile($item, $path, $name);
    }

    private function createTempDir($mode = 0700)
    {
        do {
            $tmp = tempnam(sys_get_temp_dir(), '');
            unlink($tmp);
        } while (!@mkdir($tmp, $mode));
        return $tmp;
    }

    private function deleteDir($path)
    {
        return is_file($path) ?
            @unlink($path) :
            array_map(function ($p) {
                $this->deleteDir($p);
            }, glob($path . '/*')) == @rmdir($path);
    }
}