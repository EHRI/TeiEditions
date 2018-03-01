<?php

include_once dirname(__FILE__) . "/../helpers/TeiEditionsEnhanceTei.php";


class TeiEditionsEnhanceTeiTest extends PHPUnit_Framework_Testcase
{

    private $file;
    private $tei;

    public function setUp()
    {
        $this->file = dirname(__FILE__) . "/testing.xml";
        $this->tei = simplexml_load_file($this->file);
        $this->tei->registerXPathNamespace('t', 'http://www.tei-c.org/ns/1.0');
    }

    public function test_tei_editions_url_to_slug()
    {
        $this->assertEquals('http://sws.geonames.org/12345/',
            tei_editions_slug_to_url('geonames-12345'));
        $this->assertEquals('https://portal.ehri-project.eu/authorities/12345',
            tei_editions_slug_to_url('ehri-authority-12345'));
    }

    public function test_tei_editions_slug_to_url()
    {
        $this->assertEquals('geonames-12345',
            tei_editions_url_to_slug('http://sws.geonames.org/12345/'));
        $this->assertEquals('ehri-authority-12345',
            tei_editions_url_to_slug('https://portal.ehri-project.eu/authorities/12345'));
    }

    public function test_tei_editions_get_references()
    {
        $refs = tei_editions_get_references($this->tei, "placeName");
        $this->assertEquals(
            (object)array(
                "names" => array("Tartu"),
                "refs" => array("http://www.geonames.org/2643743/" => "London")),
            $refs
        );
    }
}