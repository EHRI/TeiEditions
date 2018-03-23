<?php

include_once dirname(__FILE__) . "/../models/TeiEditionsDocumentProxy.php";


class TeiEditionsDocumentProxyTest extends PHPUnit_Framework_Testcase
{

    private $file;

    public function setUp()
    {
        $this->file = dirname(__FILE__) . "/testing.xml";
    }

    public function testMetadata()
    {
        $doc = new TeiEditionsDocumentProxy($this->file);
        $out = $doc->metadata([
            1 => [
                '/tei:TEI/tei:teiHeader/tei:fileDesc/tei:titleStmt/tei:title	'
            ]
        ]);
        $expect = [['element_id' => 1, 'text' => 'This is a test TEI', 'html' => false]];
        $this->assertEquals($expect, $out);
    }


    public function testGetPlaces()
    {
        $doc = new TeiEditionsDocumentProxy($this->file);
        $tartu = [
            "name" => "Tartu",
            "longitude" => 26.72509,
            "latitude" => 58.38062,
            "urls" => [
                "http://www.geonames.org/588335/",
                "http://ru.wikipedia.org/wiki/%D0%A2%D0%B0%D1%80%D1%82%D1%83"
            ]
        ];
        $this->assertEquals([$tartu], $doc->places());
    }

    public function testGetXmlId()
    {
        $doc = new TeiEditionsDocumentProxy($this->file);
        $this->assertEquals("testing_EN.xml", $doc->xmlId());
    }

    public function testGetRecordId()
    {
        $doc = new TeiEditionsDocumentProxy($this->file);
        $this->assertEquals("testing", $doc->recordId());
    }

    public function testAsHtml()
    {
        $doc = new TeiEditionsDocumentProxy($this->file);
        $html = $doc->asHtml();
        $this->assertThat($html, self::stringStartsWith("<div class=\"tei\">"),
            'does not start with the correct div');
        $this->assertThat($html, self::stringContains('geonames-2643743',
            'does not contain geonames slug'));
    }

    public function testAsSimpleHtml()
    {
        $doc = new TeiEditionsDocumentProxy($this->file);
        $simple = $doc->asSimpleHtml();
        $this->assertThat($simple, self::stringStartsWith("<div class=\"tei-text\">"),
            'does not start with the correct div');
    }
}