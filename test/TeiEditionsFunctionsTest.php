<?php

include_once dirname(__FILE__) . "/../helpers/TeiEditionsFunctions.php";

class TeiEditionsFunctionsTest extends PHPUnit_Framework_Testcase
{

    private $file;

    public function setUp() {
        $this->file = dirname(__FILE__) . "/testing.xml";
    }

    public function test_tei_editions_is_xml_file() {
        $out = tei_editions_extract_metadata($this->file, array(
            1 => array(
                '/tei:TEI/tei:teiHeader/tei:fileDesc/tei:titleStmt/tei:title	'
            )
        ));
        $expect = array(array('element_id' => 1, 'text' => 'This is a test TEI', 'html' => false));
        $this->assertEquals($expect, $out);
    }
}