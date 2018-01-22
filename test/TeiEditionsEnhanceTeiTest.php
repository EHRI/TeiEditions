<?php

include_once dirname(__FILE__) . "/../helpers/TeiEditionsEnhanceTei.php";


class TeiEditionsEnhanceTeiTest extends PHPUnit_Framework_Testcase
{

    public function testUrlToSlug()
    {
        $this->assertEquals('http://sws.geonames.org/12345/',
            slugToUrl('geonames-12345'));
        $this->assertEquals('https://portal.ehri-project.eu/authorities/12345',
            slugToUrl('ehri-authority-12345'));
    }

    public function testSlugToUrl()
    {
        $this->assertEquals('geonames-12345',
            urlToSlug('http://sws.geonames.org/12345/'));
        $this->assertEquals('ehri-authority-12345',
            urlToSlug('https://portal.ehri-project.eu/authorities/12345'));
    }
}