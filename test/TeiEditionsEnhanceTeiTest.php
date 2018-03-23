<?php

include_once dirname(__FILE__) . "/../helpers/TeiEditionsEnhanceTei.php";


class TeiEditionsEnhanceTeiTest extends PHPUnit_Framework_Testcase
{

    private $file;
    private $tei;

    public function setUp()
    {
        $this->file = dirname(__FILE__) . "/enhance-tei.xml";
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
        $this->assertEquals('geonames-2782113',
            tei_editions_url_to_slug('http://www.geonames.org/2782113/republic-of-austria.html'));
        $this->assertEquals('ehri-authority-12345',
            tei_editions_url_to_slug('https://portal.ehri-project.eu/authorities/12345'));
    }

    public function test_tei_editions_get_references()
    {
        $refs = tei_editions_get_references($this->tei, "placeName");
        $this->assertEquals(
            array(
                "Tartu" => null,
                "London" => "http://www.geonames.org/2643743/",
                "Munich" => "http://www.geonames.org/6559171/"
            ),
            $refs
        );
    }

    public function test_tei_editions_enhance_tei()
    {
        // TODO: fix this so we can mock the data lookups!
        tei_editions_enhance_tei($this->tei, "eng");

        $this->assertEquals(
            "Tartu",
            $this->tei->xpath("//t:fileDesc/t:sourceDesc/t:listPlace/t:place[1]/t:placeName/text()")[0]
        );
        $this->assertEquals(
            "London",
            $this->tei->xpath("//t:fileDesc/t:sourceDesc/t:listPlace/t:place[2]/t:placeName/text()")[0]
        );
        $this->assertEquals(
            "Munich",
            $this->tei->xpath("//t:fileDesc/t:sourceDesc/t:listPlace/t:place[3]/t:placeName/text()")[0]
        );
        $this->assertEquals(
            "Confiscation of property",
            $this->tei->xpath("//t:fileDesc/t:sourceDesc/t:list/t:item[1]/t:name/text()")[0]
        );
        $this->assertEquals(
            "Mach Alexander",
            $this->tei->xpath("//t:fileDesc/t:sourceDesc/t:listPerson/t:person[1]/t:persName/text()")[0]
        );
        $this->assertEquals(
            "Československá vláda v exilu",
            $this->tei->xpath("//t:fileDesc/t:sourceDesc/t:listOrg/t:org[1]/t:orgName/text()")[0]
        );
    }

    public function test_tei_editions_enhance_tei_lang()
    {
        // TODO: fix this so we can mock the data lookups!
        tei_editions_enhance_tei($this->tei, "deu");

        $this->assertEquals(
            "München",
            $this->tei->xpath("//t:fileDesc/t:sourceDesc/t:listPlace/t:place[3]/t:placeName/text()")[0]
        );
        $this->assertEquals(
            "Beschlagnahme von Eigentum",
            $this->tei->xpath("//t:fileDesc/t:sourceDesc/t:list/t:item[1]/t:name/text()")[0]
        );
    }
}