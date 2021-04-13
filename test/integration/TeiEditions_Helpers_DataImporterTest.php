<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */


class TeiEditions_Helpers_DataImporterTest extends TeiEditions_Case_Default
{
    private $_mockEnhancer;
    private $_importer;

    public function setUpLegacy()
    {
        parent::setUpLegacy();
        $this->_mockEnhancer = new class implements TeiEditions_TeiEnhancerInterface {
            public $refs = 0;
            public function addReferences(TeiEditions_Helpers_DocumentProxy $tei) {
                $this->refs++;
            }
        };
        $this->_importer = new TeiEditions_Helpers_DataImporter(get_db(), $this->_mockEnhancer);
    }

    public function testImportData()
    {
        $created = 0;
        $updated = 0;
        $onDone = function() { error_log("Done!"); };
        $this->_importer->importData(
            __DIR__ . '/../resources/enhance-tei.xml',
            'text/xml',
            false, // can't test this without Neatline installed :(
            true,  // testing w/ a mock here
            $created,
            $updated,
            $onDone
        );

        $this->assertEquals(1, $created);
        $this->assertEquals(0, $updated);
        $this->assertEquals(1, $this->_mockEnhancer->refs);
    }

    public function testUpdateItems()
    {
        $created = 0;
        $updated = 0;
        $testFile = __DIR__ . '/../resources/enhance-tei.xml';
        $onDone = function() {};
        $this->_importer->importData(
            $testFile,
            'text/xml',
            false,
            true,
            $created,
            $updated,
            $onDone
        );



        $this->assertEquals(1, $created);

        $items = $this->itemTable->findAll();
        // TODO: at the moment this errors because the web path of the XML file
        // associated with an item is not accessible in the test environment.
        // We could somehow mock that to test the process actually works.
        $this->expectException(TeiEditions_Helpers_ImportError::class);
        $this->expectExceptionMessage('There was an error processing');
        $this->_importer->updateItems($items, false, $updated);
    }
}