<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2018 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */


class TeiEditionsFieldMappingTable extends Omeka_Db_Table
{


    /**
     * Find the field associated with a given element text.
     *
     * @param ElementText $text The element text.
     * @return false|TeiEditionsFieldMapping
     */
    public function findByText($text)
    {
        return $this->findBySql('element_id=?', array($text->element_id), true);
    }


    /**
     * Find the field associated with a given element.
     *
     * @param Element $element The element.
     * @return false|TeiEditionsFieldMapping
     */
    public function findByElement($element)
    {
        return $this->findBySql('element_id=?', array($element->id), true);
    }


    /**
     * Find the field associated with a given element, identified by element
     * set name and element name.
     *
     * @param string $set The element set name.
     * @param string $element The element name.
     * @return TeiEditionsFieldMapping
     */
    public function findByElementName($set, $element)
    {

        // Get the element table.
        $elementTable = $this->getTable('Element');

        // Get the parent element.
        $element = $elementTable->findByElementSetNameAndElementName(
            $set, $element
        );

        // Find the element's field.
        return is_null($element) ? null : $this->findByElement($element);

    }
}
