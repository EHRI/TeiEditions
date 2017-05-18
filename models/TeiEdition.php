<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2017 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * The TeiEditions Edition record class.
 *
 * @package TeiEditions
 */
class TeiEdition extends Omeka_Record_AbstractRecord implements Zend_Acl_Resource_Interface
{
    public $item_id;
    public $modified_by_user_id;
    public $created_by_user_id;
    public $is_published = 0;
    public $title;
    public $slug;
    public $updated;
    public $inserted;
    public $template = '';

    protected function _initializeMixins()
    {
        $this->_mixins[] = new Mixin_Search($this);
        $this->_mixins[] = new Mixin_Timestamp($this, 'inserted', 'updated');
    }

    /**
     * Get the modified by user object.
     *
     * @return false|User
     */
    public function getModifiedByUser()
    {
        return $this->getTable('User')->find($this->modified_by_user_id);
    }

    /**
     * Get the created by user object.
     *
     * @return false|User
     */
    public function getCreatedByUser()
    {
        return $this->getTable('User')->find($this->created_by_user_id);
    }

    /**
     * Get the associated item object.
     *
     * @return false|Item
     */
    public function getItem()
    {
        return $this->getTable('Item')->find($this->item_id);
    }

    /**
     * Validate the form data.
     */
    protected function _validate()
    {
        if (empty($this->title)) {
            $this->addError('title', __('The edition must be given a title.'));
        }

        if (255 < strlen($this->title)) {
            $this->addError('title', __('The title for your edition must be 255 characters or less.'));
        }

        if (!$this->fieldIsUnique('title')) {
            $this->addError('title', __('The title is already in use by another edition. Please choose another.'));
        }

        if (trim($this->slug) == '') {
            $this->addError('slug', __('The edition must be given a valid slug.'));
        }

        if (preg_match('/^\/+$/', $this->slug)) {
            $this->addError('slug', __('The slug for your edition must not be a forward slash.'));
        }

        if (255 < strlen($this->slug)) {
            $this->addError('slug', __('The slug for your edition must be 255 characters or less.'));
        }

        if (!$this->fieldIsUnique('slug')) {
            $this->addError('slug', __('The slug is already in use by another edition. Please choose another.'));
        }
    }

    /**
     * Prepare special variables before saving the form.
     */
    protected function beforeSave($args)
    {
        $this->title = trim($this->title);
        // Generate the page slug.
        $this->slug = $this->_generateSlug($this->slug);
        // If the resulting slug is empty, generate it from the page title.
        if (empty($this->slug)) {
            $this->slug = $this->_generateSlug($this->title);
        }

        $this->modified_by_user_id = current_user()->id;
    }

    protected function afterSave($args)
    {
        if (!$this->is_published) {
            $this->setSearchTextPrivate();
        }
        $this->setSearchTextTitle($this->title);
        $this->addSearchText($this->title);
    }

    /**
     * Generate a slug given a seed string.
     *
     * @param string
     * @return string
     */
    private function _generateSlug($seed)
    {
        $seed = trim($seed);
        $seed = strtolower($seed);
        // Replace spaces with dashes.
        $seed = str_replace(' ', '-', $seed);
        // Remove all but alphanumeric characters, underscores, and dashes.
        return preg_replace('/[^\w\/-]/i', '', $seed);
    }

    public function getRecordUrl($action = 'show')
    {
//        if ('show' == $action) {
//            // FIXME: This doesn't look nice!
//            return public_url("edition/" . $this->slug);
//        }
        return array('module' => 'tei-editions', 'controller' => 'index',
            'action' => $action, 'id' => $this->id);
    }

    public function getProperty($property)
    {
        switch ($property) {
            case 'created_username':
                return $this->getCreatedByUser()->username;
            case 'modified_username':
                return $this->getModifiedByUser()->username;
            default:
                return parent::getProperty($property);
        }
    }

    public function getResourceId()
    {
        return 'TeiEdition';
    }
}
