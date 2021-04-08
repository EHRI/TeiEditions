<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */


class TeiEditions_FieldMapping extends Omeka_Record_AbstractRecord implements Zend_Acl_Resource_Interface
{
    /**
     * The id of the parent element [integer].
     */
    public $element_id;

    /**
     * The name of the element [string].
     */
    public $path;


    protected function _validate()
    {
        if (!@tei_editions_check_xpath_is_valid($this->path)) {
            $this->addError('path', __('Invalid XPath query: ' . $this->path));
        }
    }

    public function hasElement()
    {
        return is_null($this->element_id);
    }

    /**
     * Fetch a list of element options by type category.
     *
     * @return array
     */
    public static function elementOptions() {
        $db = get_db();
        $types = array();

        foreach ($db->getTable("ElementSet")
                     ->findBySql('name = ?', array('name' => 'Dublin Core'), $findOne = true)
                     ->getElements() as $elem) {
            $types["Dublin Core"][$elem->id] = $elem->name;
        }

        foreach ($db->getTable("ItemType")->findAll() as $itemType) {
            foreach ($db->getTable('Element')->findByItemType($itemType->id) as $elem) {
                $types[$itemType->name][$elem->id] = $elem->name;
            };
        }
        return $types;
    }

    /**
     * Fetch a mapping of element IDs to an array of XPath strings.
     *
     * @return array element id to XPath array
     */
    public static function fieldMappings()
    {
        $mappings = array();
        foreach (get_db()->getTable("TeiEditions_FieldMapping")->findAll() as $mapping) {
            $id = $mapping->element_id;
            if (!array_key_exists($id, $mappings)) {
                $mappings[$id] = array();
            }
            $mappings[$id][] = $mapping->path;
        }
        return $mappings;
    }

    /**
     * Get the parent element.
     *
     * @return false|Element The element.
     */
    public function getElement()
    {
        return $this->hasElement()
            ? false
            : $this->getTable('Element')->find($this->element_id);
    }

    /**
     * Get the element name.
     *
     * @return false|string.
     */
    public function getElementName()
    {
        $elem = $this->getTable('Element')->find($this->element_id);

        return $this->hasElement()
            ? false
            : $elem->getElementSet()->name . ' / ' . $elem->name;
    }

    /**
     * Get the parent element set.
     *
     * @return false|ElementSet The element set.
     */
    public function getElementSet()
    {
        return $this->hasElement()
            ? false
            : $this->getElement()->getElementSet();
    }

    public function getRecordUrl($action = 'show')
    {
        return array('module' => 'tei-editions', 'controller' => 'fields',
            'action' => $action, 'id' => $this->id);
    }

    public function getResourceId()
    {
        return 'TeiEditions_FieldMapping';
    }
}
