<?php

class TeiEditionsEntity
{
    public $name;
    public $slug;
    public $notes = [];
    public $urls = [];
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
}