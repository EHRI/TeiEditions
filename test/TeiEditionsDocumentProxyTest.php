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
        $out = $doc->metadata(array(
            1 => array(
                '/tei:TEI/tei:teiHeader/tei:fileDesc/tei:titleStmt/tei:title	'
            )
        ));
        $expect = array(array('element_id' => 1, 'text' => 'This is a test TEI', 'html' => false));
        $this->assertEquals($expect, $out);
    }


    public function testGetPlaces()
    {
        $doc = new TeiEditionsDocumentProxy($this->file);
        $this->assertEquals(
            array(array("name" => "Tartu", "longitude" => 26.72509, "latitude" => 58.38062)), $doc->places());
    }

    public function testGetId()
    {
        $doc = new TeiEditionsDocumentProxy($this->file);
        $this->assertEquals("testing", $doc->id());
    }
}