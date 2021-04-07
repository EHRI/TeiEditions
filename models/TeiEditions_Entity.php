<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */

class TeiEditions_Entity
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
        $e = new TeiEditions_Entity;
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