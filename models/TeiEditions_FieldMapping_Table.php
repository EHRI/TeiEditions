<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */


class TeiEditions_FieldMapping_Table extends Omeka_Db_Table
{


    /**
     * Find the field associated with a given element text.
     *
     * @param ElementText $text The element text.
     * @return false|TeiEditions_FieldMapping
     */
    public function findByText($text)
    {
        return $this->findBySql('element_id=?', array($text->element_id), true);
    }


    /**
     * Find the field associated with a given element.
     *
     * @param Element $element The element.
     * @return false|TeiEditions_FieldMapping
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
     * @return TeiEditions_FieldMapping
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
