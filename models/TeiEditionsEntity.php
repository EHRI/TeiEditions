<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2018 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

class TeiEditionsEntity
{
    public $name;
    public $slug;
    public $notes = [];
    public $urls = [];
    public $birth = null;
    public $death = null;
    public $longitude = null;
    public $latitude = null;

    public function hasGeo()
    {
        return isset($this->longitude) and isset($this->latitude);
    }

    public function ref()
    {
        return isset($this->urls["normal"])
            ? $this->urls["normal"]
            : ("#" . $this->slug);
    }
    
    static function create($name, $url) {
        $e = new TeiEditionsEntity;
        $e->name = $name;
        $e->urls = ["normal" => $url];
        $e->slug = tei_editions_url_to_slug($url);
        return $e;
    }

    public function __toString()
    {
        return sprintf("<Entity: %s [%s] (%s)>",
            $this->name, $this->slug, json_encode([
                "urls" => $this->urls,
                "notes" => $this->notes,
                "lat" => $this->latitude,
                "lon" => $this->longitude
            ])
        );
    }
}