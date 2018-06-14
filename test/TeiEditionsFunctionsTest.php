<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2018 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

include_once dirname(__FILE__) . "/../helpers/TeiEditionsFunctions.php";


class TeiEditionsFunctionsTest extends PHPUnit_Framework_Testcase
{

    private $file;

    public function setUp()
    {
        $this->file = dirname(__FILE__) . "/testing.xml";
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

    public function test_tei_editions_centre_points()
    {
        $points = array(array(1.0, 1.0), array(2.0, 2.0));
        $this->assertEquals(
            array(1.5, 1.5), tei_editions_centre_points($points));
    }
}