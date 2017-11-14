<?php


class TeiEditionsFieldMapping extends Omeka_Record_AbstractRecord implements Zend_Acl_Resource_Interface
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
            $this->addError('path', __('Invalid XPath query.'));
        }
    }

    public function hasElement()
    {
        return is_null($this->element_id);
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
        return $this->hasElement()
            ? false
            : $this->getTable('Element')->find($this->element_id)->name;
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
