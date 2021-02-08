<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2018 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

include_once dirname(__FILE__) . "/../models/TeiEditionsTeiEnhancer.php";


class TeiEditionsEnhanceTeiTest extends PHPUnit_Framework_Testcase
{
    private $file;
    private $tei;
    private $xpath;

    public function setUp()
    {
        $this->file = dirname(__FILE__) . "/enhance-tei.xml";
        $this->tei = TeiEditionsDocumentProxy::fromUriOrPath($this->file);
        $this->xpath = new DOMXPath($this->tei->document());
        $this->xpath->registerNamespace("t", TeiEditionsDocumentProxy::TEI_NS);
    }

    private function valueOf($path) {
        $n = $this->xpath->query($path);
        return $n->length ? $n->item(0)->textContent : "";
    }

    public function test_addRefs()
    {
        // TODO: fix this so we can mock the data lookups!
        $src = new TeiEditionsDataFetcher([], "eng");
        $num = (new TeiEditionsTeiEnhancer($this->tei, $src))->addReferences();
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
        $src = new TeiEditionsDataFetcher([], "deu");
        $num = (new TeiEditionsTeiEnhancer($this->tei, $src))->addReferences();

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
        $testdata = TeiEditionsDocumentProxy::fromUriOrPath(
            dirname(__FILE__) . "/enhance-tei-3.xml");
        $dictfile = dirname(__FILE__) . "/dict-tei.xml";
        $dict = [];
        $doc = TeiEditionsDocumentProxy::fromUriOrPath($dictfile);
        foreach ($doc->entities() as $entity) {
            $dict[$entity->ref()] = $entity;
        }
        $src = new TeiEditionsDataFetcher($dict, "eng");
        $num = (new TeiEditionsTeiEnhancer($testdata, $src))->addReferences();
        $this->assertEquals(4, $num);
        $num2 = (new TeiEditionsTeiEnhancer($testdata, $src))->addReferences();
        $this->assertEquals(0, $num2);
    }

    public function test_addRefsIdempotency() {
        $src = new TeiEditionsDataFetcher([], "eng");
        $num1 = (new TeiEditionsTeiEnhancer($this->tei, $src))->addReferences();
        $this->assertEquals($num1, 7);
        $before = $this->tei->document()->saveXML();
        $num2 = (new TeiEditionsTeiEnhancer($this->tei, $src))->addReferences();
        $after = $this->tei->document()->saveXML();
        $this->assertEquals($before, $after);
        $this->assertEquals($num2, 0);
    }
}