<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */


class AdminController_FilesTest extends TeiEditions_Case_Default
{
    public function setUpLegacy()
    {
        parent::setUpLegacy();

        $importer = new TeiEditions_Helpers_DataImporter(get_db());

        $created = 0;
        $updated = 0;
        $onDone = function() { };
        $importer->importData(
            __DIR__ . '/../resources/enhance-tei.xml',
            'text/xml',
            false,
            false,
            $created,
            $updated,
            function(){}
        );
        $this->assertEquals(1, $created);
    }

    public function testFilesImport() {
        // TODO: figure out how to test an upload...???
        $this->request->setMethod('POST');
        $this->dispatch('tei-editions/files/import');

        // The form will error because we haven't provided a file
        $this->assertXpath("//div[@id='flash']/ul/li[@class='error']");
    }

    public function testFilesUpdate() {
        // An empty items[] key should update all items...
        $this->request->setMethod('POST')->setPost(['create_exhibit' => 0]);
        $this->dispatch('tei-editions/files/update');

        // NB: as with the Data Import test I haven't figured out how to
        // make updating work in the test environment because the files are
        // not available at their web URL. Therefore, for now asserting that
        // this method errors as expected at least ensures it doesn't crash.
        $this->assertXpath("//div[@id='flash']/ul/li[@class='error']");
    }
}