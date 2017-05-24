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
class TeiEditionTable extends Omeka_Db_Table
{
    /**
     * Find all pages, ordered by slug name.
     *
     * @return array The pages ordered alphabetically by their slugs
     */
    public function findAllPagesOrderBySlug()
    {
        $select = $this->getSelect()->order('slug');
        return $this->fetchObjects($select);
    }
    
    public function applySearchFilters($select, $params)
    {
        $alias = $this->getTableAlias();
        $paramNames = array('is_published',
                            'title', 
                            'slug',
                            'created_by_user_id',
                            'modified_by_user_id',
                            'template');
                            
        foreach($paramNames as $paramName) {
            if (isset($params[$paramName])) {             
                $select->where($alias . '.' . $paramName . ' = ?', array($params[$paramName]));
            }            
        }

        if (isset($params['sort'])) {
            switch($params['sort']) {
                case 'alpha':
                    $select->order("{$alias}.title ASC");
                    break;
            }
        }         
    }
    
    protected function _createIdToEditionLookup()
    {
        // get all of the pages
        $allPages = $this->findAll();
        
        // create the page lookup                
        $idToPageLookup = array();
        foreach($allPages as $page) {
            $idToPageLookup[$page->id] = $page;
        }
        
        return $idToPageLookup;
    }

    public function getSelect()
    {
        $select = parent::getSelect();
        $permissions = new Omeka_Db_Select_PublicPermissions('TeiEdition');
        $permissions->apply($select, 'tei_editions','created_by_user_id','is_published');

        return $select;
    }
}
