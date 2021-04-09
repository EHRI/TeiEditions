<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */


class TeiEditionsFieldMappingsTest extends TeiEditions_Case_Default
{
    public function testFieldMappings()
    {
        $mappings = TeiEditionsFieldMapping::fieldMappings();
        $this->assertNotEmpty($mappings);
    }
}