<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */

use PHPUnit\Framework\TestCase;

require_once TEI_EDITIONS_DIR . "/helpers/TeiEditions_Helpers_Functions.php";


class TeiEditionsFunctionsTest extends TestCase
{

    private $file;

    public function setUp(): void
    {
        $this->file = TEI_EDITIONS_TEST_DIR . "/resources/testing.xml";
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