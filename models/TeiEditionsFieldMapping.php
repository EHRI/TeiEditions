<?php

/**
 * @package     omeka
 * @subpackage  solr-search
 * @copyright   2012 Rector and Board of Visitors, University of Virginia
 * @license     http://www.apache.org/licenses/LICENSE-2.0.html
 */


class TeiEditionsFieldMapping extends Omeka_Record_AbstractRecord
{
    /**
     * The id of the parent element [integer].
     */
    public $element_id;

    /**
     * The name of the element [string].
     */
    public $path;

    /**
     * Get the parent element.
     *
     * @return false|Element The element.
     */
    public function getElement()
    {
        return is_null($this->element_id)
            ? false
            : $this->getTable('Element')->find($this->element_id);
    }


    /**
     * Get the parent element set.
     *
     * @return false|ElementSet The element set.
     */
    public function getElementSet()
    {
        return is_null($this->element_id)
            ? false
            : $this->getElement()->getElementSet();
    }


    /**
     * Get the name of the parent element set.
     *
     * @return string The element set name.
     */
    public function getElementSetName()
    {
        if (!$this->hasElement()) return __('Omeka Categories');
        else return $this->getElementSet()->name;
    }


    /**
     * Return the original label for the field.
     *
     * @return string|null
     **/
    public function getOriginalLabel()
    {
        switch ($this->slug) {

            case 'tag':         return __('Tag');
            case 'collection':  return __('Collection');
            case 'itemtype':    return __('Item Type');
            case 'resulttype':  return __('Result Type');
            case 'featured':    return __('Featured');

            default: return $this->getElement()->name;

        }
    }


    /**
     * If the label is empty, revert to the original label.
     *
     * @return string The facet label.
     */
    public function beforeSave()
    {
        $label = trim($this->label);
        if (empty($label)) $this->label = $this->getOriginalLabel();
    }


}
