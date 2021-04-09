<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */


class AdminController_FieldsTest extends TeiEditions_Case_Default
{

    public function testFieldsBrowse() {

        $this->dispatch('tei-editions/fields/browse');

        foreach (TeiEditionsPlugin::$DC_MAPPINGS as $key => $mappings) {
            foreach ($mappings as $xpath) {
                error_log("Checking path: $xpath");
                // Label:
                $this->assertXpath("//span[@class='xpath' and text()='{$xpath}']");
            }
        }
    }
}