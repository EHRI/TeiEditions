<?php

include_once dirname(__FILE__) . "/../models/TeiEditionsTeiEnhancer.php";


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

    public function test_getReferences()
    {
        $src = new TeiEditionsDataFetcher([], null);
        $tester = new TeiEditionsTeiEnhancer($this->tei, $src);
        $refs = $tester->getReferences("placeName");
        $this->assertEquals(
            array(
                "Tartu" => "#test_1",
                "London" => "http://www.geonames.org/2643743/",
                "Munich" => "http://www.geonames.org/6559171/"
            ),
            $refs
        );
    }

    public function test_addRefs()
    {
        // TODO: fix this so we can mock the data lookups!
        $src = new TeiEditionsDataFetcher([], "eng");
        (new TeiEditionsTeiEnhancer($this->tei, $src))->addRefs();

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
        $this->assertRegExp(
          "/11\/10\/1902\s+15\/10\/1980/",
            (string)$this->tei->xpath("//t:fileDesc/t:sourceDesc/t:listPerson/t:person[1]/t:note/t:p[1]/text()")[0]
        );
        $this->assertEquals(
            "Československá vláda v exilu",
            $this->tei->xpath("//t:fileDesc/t:sourceDesc/t:listOrg/t:org[1]/t:orgName/text()")[0]
        );
    }

    public function test_addRefsWithLang()
    {
        // TODO: fix this so we can mock the data lookups!
        $src = new TeiEditionsDataFetcher([], "deu");
        (new TeiEditionsTeiEnhancer($this->tei, $src))->addRefs();

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