<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2020 King's College London Department of Digital Humanities
 */

require_once __DIR__ . '/TeiEditions_Helpers_DataFetcher.php';
require_once __DIR__ . '/TeiEditions_Helpers_DocumentProxy.php';
require_once __DIR__ . '/TeiEditions_Helpers_TeiEnhancer.php';


class TeiEditions_Helpers_DataImporter
{
    private $_db;
    private $_enhancer;
    private $_defaultItemType;
    private $_templateNeatline = null;

    /**
     * @param Omeka_Db db the database
     * @param TeiEditions_TeiEnhancerInterface $enhancer an optional enhancer
     * @package TeiEditionsDataImporter constructor.
     *
     */
    public function __construct(Omeka_Db $db, TeiEditions_TeiEnhancerInterface $enhancer = null)
    {
        $this->_db = $db;
        $this->_enhancer = $enhancer !== null ? $enhancer : new class implements TeiEditions_TeiEnhancerInterface {
            public function addReferences(TeiEditions_Helpers_DocumentProxy $tei)
            {
            }
        };
        $this->_defaultItemType = get_option('tei_editions_default_item_type');
        if (plugin_is_active('Neatline') && ($id = (int)get_option('tei_editions_template_neatline')) !== null) {
            $this->_templateNeatline = $this->_db->getTable('NeatlineExhibit')->find($id);
        }
    }

    /**
     * Import data from supplied TEI(s).
     *
     * @param string $path the file path to import
     * @param string $mime the mime type of the import file. Either text/xml or application/zip are supported
     * @param boolean $neatline whether or not to create a Neatline item from the TEI data
     * @param boolean $enhance whether or not to run enhancement on the input file by looking up entity metadata
     * @param boolean $force whether or not to force the import, refreshing existing items
     * @param int $created out-param for number of items created
     * @param int $updated out-param for number of items updated
     * @param callable $onDone function to call on completion
     * @throws Omeka_Record_Exception
     */
    public function importData($path, $mime, $neatline, $enhance, $force, &$created, &$updated, $onDone)
    {
        _log("Performing data import: " . json_encode([
                "path" => $path,
                "neatline" => $neatline,
                "enhance" => $enhance,
                "force" => $force,
                "mime" => $mime
            ]));

        $name = basename($path);
        $tx = $this->_db->getAdapter()->beginTransaction();
        try {
            switch ($mime) {
                case "text/xml":
                case "application/xml":
                    $this->updateItem($path, $name, $neatline, $enhance, $force, $created, $updated);
                    break;
                case "application/zip":
                    $this->readImportZip($path, $neatline, $enhance, $force, $created, $updated);
                    break;
                default:
                    throw new Exception("Unhandled file extension: $mime");
            }
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollBack();
            throw new TeiEditions_Helpers_ImportError("Error importing file: $name: " . $e->getMessage(), 0, $e);
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
    public function updateItems($items, $neatline, &$updated)
    {
        $tx = $this->_db->getAdapter()->beginTransaction();

        $currentItem = null;
        try {
            foreach ($items as $item) {
                $currentItem = $item;
                foreach ($item->getFiles() as $file) {
                    if (tei_editions_is_xml_file($file)) {
                        $item->deleteElementTexts();
                        $doc = $this->getProxy($file->getWebPath(), $file->getProperty('display_title'));
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
                $msg = __("There was an error processing element with id [%d] '%s': %s",
                    $currentItem->id, metadata($currentItem, "display_title"), $e->getMessage());
                throw new TeiEditions_Helpers_ImportError($msg, 0, $e);
            } else {
                throw new TeiEditions_Helpers_ImportError("Error running update", 0, $e);
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
    public function associateItems($path, $name, $mime, &$done)
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
     * @param boolean $neatline whether or not to create Neatline items
     * @param boolean $enhance whether or not to enhance TEIs by looking up entity references
     * @param int $created out-param for number of items created
     * @param int $updated out-param for the number of items updated
     * @throws Omeka_Record_Exception
     */
    private function readImportZip($zipPath, $neatline, $enhance, $force, &$created = 0, &$updated = 0)
    {
        $temp = $this->createTempDir();

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) === true) {
                $zip->extractTo($temp);
                $zip->close();

                foreach (glob($temp . '/*.xml') as $path) {
                    $this->updateItem($path, basename($path), $neatline, $enhance, $force, $created, $updated);
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
     * @param $neatline boolean whether or not to create a Neatline exhibit
     * @param $enhance boolean whether or not to enhance the TEI
     * @param $force boolean whether or not to force the update
     * @param int $created out-param for the number of created items
     * @param int $updated out-param for the number of updated items
     * @throws Omeka_Record_Exception
     */
    private function updateItem($path, $name, $neatline, $enhance, $force, &$created, &$updated)
    {
        _log("Importing file: $path");
        $create = false;

        if ($enhance) {
            $eDir = dirname($path) . DIRECTORY_SEPARATOR . "enhance-tei";
            if (!file_exists($eDir)) {
                mkdir($eDir);
            }

            $ePath = $eDir . DIRECTORY_SEPARATOR . basename($path);
            $tei = TeiEditions_Helpers_DocumentProxy::fromUriOrPath($path);
            $this->_enhancer->addReferences($tei);

            if ($f = fopen($ePath, "w")) {
                fwrite($f, $tei->document()->saveXML());
                register_shutdown_function(function () use ($ePath) {
                    if (file_exists($ePath)) {
                        unlink($ePath);
                    }
                });
                $path = $ePath;
            } else {
                _log("Unable to open temp file for writing: $ePath");
            }
        }

        $doc = $this->getProxy($path, $name);
        $item = $this->getOrCreateItem($doc, $create);
        $this->updateItemFromTEI($item, $doc, $neatline);

        $this->addOrUpdateItemFile($item, $path, $name, true, $force);
        if ($create) {
            $created++;
        } else {
            $updated++;
        }
    }

    /**
     * @param string $path
     * @param string $name
     * @return TeiEditions_Helpers_DocumentProxy
     * @throws Exception
     */
    private function getProxy($path, $name)
    {
        $doc = TeiEditions_Helpers_DocumentProxy::fromUriOrPath($path);
        if (!$doc) {
            throw new Exception("Unable to load TEI document at path: '$path'");
        }
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
     * @param TeiEditions_Helpers_DocumentProxy $doc
     * @param bool $created
     * @return Item
     * @throws Omeka_Record_Exception
     * @throws Exception
     */
    private function getOrCreateItem(TeiEditions_Helpers_DocumentProxy $doc, &$created)
    {
        $item = tei_editions_get_item_by_identifier($doc->recordId());
        $created = is_null($item);
        return $created ? new Item : $item;
    }


    /**
     * Update an item's data from that extracted from the TEI document.
     *
     * @param Item $item
     * @param TeiEditions_Helpers_DocumentProxy $doc
     * @param bool $neatline create a Neatline exhibit
     * @throws Omeka_Record_Exception
     */
    private function updateItemFromTEI(Item $item, TeiEditions_Helpers_DocumentProxy $doc, $neatline)
    {
        $item->item_type_id = $this->_defaultItemType;
        $data = $doc->metadata(TeiEditionsFieldMapping::fieldMappings());
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
     * @param bool $force whether or not to force the update
     */
    private function addOrUpdateItemFile(Item $item, $path, $name, $is_primary = false, $force = false)
    {
        $primaryXml = $is_primary ? $name : null;
        $md5 = md5_file($path);
        $refresh = true;
        foreach ($item->getFiles() as $file) {
            if (is_null($primaryXml) && tei_editions_is_xml_file($file)) {
                $primaryXml = $file->original_filename;
            }
            if ($file->original_filename == $name) {
                if ($force === false && $file->authentication == $md5) {
                    // We've already got the same md5 with the same
                    // name so no need to update it.
                    error_log("Not refreshing $name, file exists with the same md5");
                    $refresh = false;
                } else {
                    $file->unlinkFile();
                    $file->delete();
                }
            }
        }
        if ($refresh) {
            @insert_files_for_item($item, "Filesystem", ['source' => $path, 'name' => $name]);
        }

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
     * @param Item $entity
     * @param TeiEditions_Helpers_DocumentProxy $doc
     * @throws Omeka_Record_Exception
     */
    private function updateNeatlineExhibit(Item $entity, TeiEditions_Helpers_DocumentProxy $doc)
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
        if ($template = $this->_templateNeatline) {
            $exhibit->styles = $template->styles;
            $exhibit->spatial_layer = $template->spatial_layer;
            $exhibit->spatial_layers = $template->spatial_layers;
            $exhibit->spatial_querying = $template->spatial_querying;
            $exhibit->wms_layers = $template->wms_layers;
            $exhibit->wms_address = $template->wms_address;
        }

        $exhibit->save($throwIfInvalid = true);

        // copy records from the template...
        if ($template = $this->_templateNeatline) {
            foreach ($this->_db->getTable('NeatlineRecord')->findBy(['exhibit_id' => $template->id]) as $t) {
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
     * @param TeiEditions_Helpers_DocumentProxy $doc
     * @return NeatlineExhibit
     * @throws Omeka_Record_Exception
     */
    private function getOrCreateNeatlineExhibit(TeiEditions_Helpers_DocumentProxy $doc)
    {
        $exhibits = $this->_db->getTable('NeatlineExhibit')
            ->findBy(['slug' => strtolower($doc->recordId())]);
        return empty($exhibits) ? new NeatlineExhibit : $exhibits[0];
    }

    /**
     * @param TeiEditions_Helpers_DocumentProxy $doc
     * @param NeatlineExhibit $exhibit
     * @param TeiEditionsEntity $item
     * @param $points_deg
     * @param $points_metres
     */
    private function createRecord(TeiEditions_Helpers_DocumentProxy $doc,
                                  NeatlineExhibit $exhibit,
                                  TeiEditionsEntity $item,
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
            // NB: Sorry about all these hard-coded IDs, I
            // wish I knew a better way...
            // TODO: somehow extract all this to config somewhere?
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
                throw new TeiEditions_Helpers_ImportError("Zip cannot be opened");
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
     * @throws Omeka_Record_Exception
     */
    private function addAssociatedFile($path, $name)
    {
        $id = tei_editions_get_identifier($name);
        $item = tei_editions_get_item_by_identifier($id);
        if (is_null($item)) {
            throw new TeiEditions_Helpers_ImportError("Unable to locate item with identifier: " . $id . " (file: $path)");
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