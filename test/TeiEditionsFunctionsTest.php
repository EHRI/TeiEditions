<?php

include_once dirname(__FILE__) . "/../helpers/TeiEditionsFunctions.php";


class TeiEditionsFunctionsTest extends PHPUnit_Framework_Testcase
{

    private $file;

    public function setUp()
    {
        $this->file = dirname(__FILE__) . "/testing.xml";
    }

    public function test_tei_editions_centre_points()
    {
        $points = array(array(1.0, 1.0), array(2.0, 2.0));
        $this->assertEquals(
            array(1.5, 1.5), tei_editions_centre_points($points));
    }
}