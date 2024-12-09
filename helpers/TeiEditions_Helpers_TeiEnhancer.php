<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */

require_once __DIR__ . '/TeiEditions_Helpers_DataFetcher.php';


interface TeiEditions_TeiEnhancerInterface {
    public function addReferences(TeiEditions_Helpers_DocumentProxy $tei);
}

/**
Extracts URIs of annotated/linked terms, people, organisations, places, ghettos and camps from TEI document,
fetches metadata from EHRI, Geonames and possibly other services
and adds normalised records to TEI Header.

TEI elements and services handled:
------------------------------

<placeName>
- Geonames: DONE
- EHRI camps and ghettos: TBD
- EHRI countries: TBD
- Wikidata: manually?
- Wikipedia: is used at all?

<personName>
- EHRI personalities: DONE
- Holocaust.cz: manually (no API yet)
- Yad Vashem victims database: manually (is there an API?)

<orgName>
- EHRI corporate bodies: DONE

<term>
- EHRI terms: DONE
 *
*/
class TeiEditions_Helpers_TeiEnhancer implements TeiEditions_TeiEnhancerInterface
{

    private $dataSrc;

    // Describes the XML paths in which to add entities,
    // the source tag from which to extract references,
    // and a function which fetches data about the references.
    private static $TYPES = [
        // dest-list-tag, dest-item-tag, dest-name-tag, src-tag, fetch-function
        ["list", "item", "name", "term", "fetchConcepts"],
        ["listOrg", "org", "orgName", "orgName", "fetchHistoricalAgents"],
        ["listPerson", "person", "persName", "persName", "fetchHistoricalAgents"],
        ["listPlace", "place", "placeName", "placeName", "fetchPlaces"],
    ];

    function __construct(TeiEditions_Helpers_DataFetcher $src)
    {
        $this->dataSrc = $src;
    }

    /**
     * Adds references to the TEI header for the following items:
     *  - orgName
     *  - persName
     *  - placeName
     *  - term
     *
     * @return integer the number of items added.
     */
    public function addReferences(TeiEditions_Helpers_DocumentProxy $tei)
    {
        // Index for generated entity IDs
        $idx = 0;
        $added = 0;

        foreach ($this::$TYPES as $typeSpec) {
            list($listTag, $itemTag, $nameTag, $srcTag, $fetcherFunc) = $typeSpec;

            $existing_entities = $tei->getEntities($listTag, $itemTag, $nameTag);
            $refs = $tei->entityReferences($srcTag, $idx, $addRefs = true);
            foreach ($this->dataSrc->{$fetcherFunc}($refs) as $entity) {
                if (!in_array($entity, $existing_entities)) {
                    error_log("Found $srcTag " . $entity->name . "\n");
                    $tei->addEntity($listTag, $itemTag, $nameTag, $entity);
                    $added++;
                } else {
                    error_log("Not updating existing $srcTag " . $entity->name);
                }
            }
        }
        return $added;
    }
}