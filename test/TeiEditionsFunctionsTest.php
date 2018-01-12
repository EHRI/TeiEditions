<?php

include_once dirname(__FILE__) . "/../helpers/TeiEditionsFunctions.php";

class TeiEditionsFunctionsTest extends PHPUnit_Framework_Testcase
{

    private $file;

    public function setUp()
    {
        $this->file = dirname(__FILE__) . "/testing.xml";
    }

    public function test_tei_editions_is_xml_file()
    {
        $out = tei_editions_extract_metadata($this->file, array(
            1 => array(
                '/tei:TEI/tei:teiHeader/tei:fileDesc/tei:titleStmt/tei:title	'
            )
        ));
        $expect = array(array('element_id' => 1, 'text' => 'This is a test TEI', 'html' => false));
        $this->assertEquals($expect, $out);
    }

    public function test_tei_editions_get_places()
    {
        $out = tei_editions_get_places($this->file);
        $this->assertEquals(
            array(array("name" => "Tartu", "longitude" => 26.72509, "latitude" => 58.38062)), $out);
    }

    public function test_tei_editions_centre_points()
    {
        $points = array(array(1.0, 1.0), array(2.0, 2.0));
        $this->assertEquals(
            array(1.5, 1.5), tei_editions_centre_points($points));
    }
}