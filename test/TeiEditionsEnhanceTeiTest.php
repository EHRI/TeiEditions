<?php

include_once dirname(__FILE__) . "/../helpers/TeiEditionsEnhanceTei.php";


class TeiEditionsEnhanceTeiTest extends PHPUnit_Framework_Testcase
{

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
}