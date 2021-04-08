<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */

require_once __DIR__ . "/../helpers/TeiEditions_Helpers_TeiEnhancer.php";


class TeiEditionsEnhanceTeiTest extends PHPUnit_Framework_Testcase
{
    private $file;
    private $tei;
    private $xpath;

    public function setUp()
    {
        $this->file = __DIR__ . "/enhance-tei.xml";
        $this->tei = TeiEditions_Helpers_DocumentProxy::fromUriOrPath($this->file);
        $this->xpath = new DOMXPath($this->tei->document());
        $this->xpath->registerNamespace("t", TeiEditions_Helpers_DocumentProxy::TEI_NS);
    }

    private function valueOf($path) {
        $n = $this->xpath->query($path);
        return $n->length ? $n->item(0)->textContent : "";
    }

    public function test_addRefs()
    {
        // TODO: fix this so we can mock the data lookups!
        $src = new TeiEditions_Helpers_DataFetcher([], "eng");
        $num = (new TeiEditions_Helpers_TeiEnhancer($this->tei, $src))->addReferences();
        $this->assertEquals($num, 7);
        $this->assertEquals(
            "Tartu",
            $this->valueOf("//t:fileDesc/t:sourceDesc/t:listPlace/t:place[1]/t:placeName")
        );
        $this->assertEquals(
            "London",
            $this->valueOf("//t:fileDesc/t:sourceDesc/t:listPlace/t:place[2]/t:placeName")
        );
        $this->assertEquals(
            "Munich",
            $this->valueOf("//t:fileDesc/t:sourceDesc/t:listPlace/t:place[3]/t:placeName")
        );
        $this->assertEquals(
            "Confiscation of property",
            $this->valueOf("//t:fileDesc/t:sourceDesc/t:list/t:item[1]/t:name")
        );
        $this->assertEquals(
            "Mach Alexander",
            $this->valueOf("//t:fileDesc/t:sourceDesc/t:listPerson/t:person[1]/t:persName")
        );
        $this->assertRegExp(
          "/11\/10\/1902\s+15\/10\/1980/",
            $this->valueOf("//t:fileDesc/t:sourceDesc/t:listPerson/t:person[1]/t:note/t:p[1]")
        );
        $this->assertEquals(
            "Československá vláda v exilu",
            $this->valueOf("//t:fileDesc/t:sourceDesc/t:listOrg/t:org[1]/t:orgName")
        );
    }

    public function test_addRefsWithLang()
    {
        // TODO: fix this so we can mock the data lookups!
        $src = new TeiEditions_Helpers_DataFetcher([], "deu");
        $num = (new TeiEditions_Helpers_TeiEnhancer($this->tei, $src))->addReferences();

        $this->assertEquals($num, 7);
        $this->assertEquals(
            "München",
            $this->valueOf("//t:fileDesc/t:sourceDesc/t:listPlace/t:place[3]/t:placeName")
        );
        $this->assertEquals(
            "Beschlagnahme von Eigentum",
            $this->valueOf("//t:fileDesc/t:sourceDesc/t:list/t:item[1]/t:name")
        );
    }

    public function test_addTefsWithLocalDict()
    {
        $testdata = TeiEditions_Helpers_DocumentProxy::fromUriOrPath(
            __DIR__ . "/enhance-tei-3.xml");
        $dictfile = __DIR__ . "/dict-tei.xml";
        $dict = [];
        $doc = TeiEditions_Helpers_DocumentProxy::fromUriOrPath($dictfile);
        foreach ($doc->entities() as $entity) {
            $dict[$entity->ref()] = $entity;
        }
        $src = new TeiEditions_Helpers_DataFetcher($dict, "eng");
        $num = (new TeiEditions_Helpers_TeiEnhancer($testdata, $src))->addReferences();
        $this->assertEquals(4, $num);
        $num2 = (new TeiEditions_Helpers_TeiEnhancer($testdata, $src))->addReferences();
        $this->assertEquals(0, $num2);
    }

    public function test_addRefsIdempotency() {
        $src = new TeiEditions_Helpers_DataFetcher([], "eng");
        $num1 = (new TeiEditions_Helpers_TeiEnhancer($this->tei, $src))->addReferences();
        $this->assertEquals($num1, 7);
        $before = $this->tei->document()->saveXML();
        $num2 = (new TeiEditions_Helpers_TeiEnhancer($this->tei, $src))->addReferences();
        $after = $this->tei->document()->saveXML();
        $this->assertEquals($before, $after);
        $this->assertEquals($num2, 0);
    }
}