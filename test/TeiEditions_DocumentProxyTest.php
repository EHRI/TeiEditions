<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */

require_once __DIR__ . "/../models/TeiEditions_Entity.php";
require_once __DIR__ . "/../helpers/TeiEditions_DocumentProxy.php";


class TeiEditionsDocumentProxyTest extends PHPUnit_Framework_Testcase
{

    private $file;

    public function setUp()
    {
        $this->file = __DIR__ . "/testing.xml";
    }

    public function testMetadata()
    {
        $doc = TeiEditions_DocumentProxy::fromUriOrPath($this->file);
        $out = $doc->metadata([
            1 => [
                '/t:TEI/t:teiHeader/t:fileDesc/t:titleStmt/t:title'
            ]
        ]);
        $expect = [['element_id' => 1, 'text' => 'This is a test TEI', 'html' => false]];
        $this->assertEquals($expect, $out);
    }

    public function testEntityReferences()
    {
        $doc = TeiEditions_DocumentProxy::fromUriOrPath(
            __DIR__ . "/enhance-tei.xml");
        $i = 0;
        $refs = $doc->entityReferences("placeName", $i, $addRefs = true);
        $this->assertEquals($i, 1);
        $this->assertEquals(
            array(
                "Tartu" => "#test_1",
                "London" => "https://www.geonames.org/2643743/",
                "Munich" => "http://www.geonames.org/6559171/",
                "Invalid" => "http://www.geonames.org/INVALID"
            ),
            $refs
        );
    }

    public function testGetEntities()
    {
        $doc = TeiEditions_DocumentProxy::fromUriOrPath($this->file);
        $place = new TeiEditions_Entity;
        $place->name =  "Tartu";
        $place->slug = "geonames-588335";
        $place->longitude = 26.72509;
        $place->latitude = 58.38062;
        $place->urls = [
            "normal" => "http://www.geonames.org/588335/",
            "desc" => "http://ru.wikipedia.org/wiki/%D0%A2%D0%B0%D1%80%D1%82%D1%83"
        ];
        $pers = new TeiEditions_Entity;
        $pers->name =  "Grün, Maurycy Moses";
        $pers->slug = "ehri_nisko_gruen_maurycy";
        $pers->birth = "1890";
        $pers->death = "1944";
        $pers->urls = [];

        $org = new TeiEditions_Entity;
        $org->name = "Test English";
        $org->slug = "this-is-a-test";
        $org->notes = ["Blah blah"];
        $org->urls = [];
        $this->assertEquals([$place, $pers, $org], $doc->entities());
    }

    public function testGetXmlId()
    {
        $doc = TeiEditions_DocumentProxy::fromUriOrPath($this->file);
        $this->assertEquals("testing_EN.xml", $doc->xmlId());
    }

    public function testGetRecordId()
    {
        $doc = TeiEditions_DocumentProxy::fromUriOrPath($this->file);
        $this->assertEquals("testing", $doc->recordId());
    }

    public function testAsHtml()
    {
        $doc = TeiEditions_DocumentProxy::fromUriOrPath($this->file);
        $html = $doc->asHtml();
        $this->assertThat($html["meta"], self::stringStartsWith("<div class=\"tei-meta\">"),
            'does not start with the correct div');
        $this->assertThat($html["entities"], self::stringStartsWith("<div class=\"tei-entities\">"),
            'does not start with the correct div');
        $this->assertThat($html["html"], self::stringStartsWith("<div class=\"tei-text\" dir=\"auto\">"),
            'does not start with the correct div');
        $this->assertThat($html["html"], self::stringContains('geonames-2643743',
            'does not contain geonames slug'));
    }

    public function testAsSimpleHtml()
    {
        $doc = TeiEditions_DocumentProxy::fromUriOrPath($this->file);
        $simple = $doc->asSimpleHtml();
        $this->assertThat($simple, self::stringStartsWith("<div class=\"tei-text\" dir=\"auto\">"),
            'does not start with the correct div');
    }
}