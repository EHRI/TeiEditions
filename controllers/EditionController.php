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
class TeiEditions_EditionController extends Omeka_Controller_AbstractActionController
{

    public function init()
    {
        $this->_fields = $this->_helper->db->getTable('SolrSearchField');
    }

    public function showAction()
    {
        $item = $this->_helper->db->getTable('Item')->find($this->_getParam('id'));

        if (is_null($item)) {
            throw new Omeka_Controller_Exception_404;
        }

        // Set the page object to the view.
        $this->view->assign(array('item' => $item));
    }

    public function indexAction()
    {
        // Get pagination settings.
        $limit = get_option('per_page_public');
        $page  = $this->_request->page ? $this->_request->page : 1;
        $start = ($page-1) * $limit;


        // determine whether to display private items or not
        // items will only be displayed if:
        // solr_search_display_private_items has been enabled in the Solr Search admin panel
        // user is logged in
        // user_role has sufficient permissions

        $user = current_user();
        if(get_option('solr_search_display_private_items')
            && $user
            && is_allowed('Items','showNotPublic')) {
            // limit to public items
            $limitToPublicItems = false;
        } else {
            $limitToPublicItems = true;
        }

        // Execute the query.
        $results = $this->_search($start, $limit, $limitToPublicItems);

        // Set the pagination.
        Zend_Registry::set('pagination', array(
            'page'          => $page,
            'total_results' => $results->response->numFound,
            'per_page'      => $limit
        ));

        _log(print_r($results->response, true));

        // Push results to the view.
        $this->view->assign(array(
            'results' => $results,
            'q' => $this->_request->q,
            'page' => $page,
            'limit' => $limit
        ));
    }

    public function entitiesAction()
    {
        $url = array_key_exists('url', $_REQUEST) ? $_REQUEST['url'] : null;
        $this->view->assign(
            array(
                'url' => $url,
                'data' => $this->_lookupInfo($url),
                'mappings' => array(
                    'otherFormsOfName' => __("Also Known As"),
                    'parallelFormsOfName' => __("Parallel Names"),
                    'biographicalHistory' => __("Biographical History"),
                    'datesOfExistence' => __("Dates"),
                    'source' => __("Source"),
                    'longitude' =>  __("Longitude"),
                    'latitude' => __("Latitude"),
                    'seeAlso' => __("See Also")
                )
            )
        );
    }

    protected function _lookupInfo($url) {
        if (preg_match('/\/keywords\//', $url)) {
            return tei_editions_get_concept($url);
        } else if (preg_match('/\/authorities\//', $url)) {
            return tei_editions_get_historical_agent($url);
        }
        return null;

    }

    /**
     * Pass setting to Solr search
     *
     * @param int $offset Results offset
     * @param int $limit  Limit per page
     * @return SolrResultDoc Solr results
     */
    protected function _search($offset, $limit, $limitToPublicItems = true)
    {

        // Connect to Solr.
        $solr = SolrSearch_Helpers_Index::connect();

        // Get the parameters.
        $params = $this->_getParameters();

        // Construct the query.
        $query = $this->_getQuery($limitToPublicItems);

        // Execute the query.
        return $solr->search($query, $offset, $limit, $params);

    }


    /**
     * Form the complete Solr query.
     *
     * @return string The Solr query.
     */
    protected function _getQuery($limitToPublicItems = true)
    {

        // Get the `q` GET parameter.
        $query = $this->_request->q;

        // If defined, replace `:`; otherwise, revert to `*:*`.
        // Also, clean it up some.
        if (!empty($query)) {
            $query = str_replace(':', ' ', $query);
            $to_remove = array('[', ']');
            foreach ($to_remove as $c) {
                $query = str_replace($c, '', $query);
            }
        } else {
            $query = '*:*';
        }

        // Get the `facet` GET parameter
        $facet = $this->_request->facet;

        // Form the composite Solr query.
        if (!empty($facet)) $query .= " AND {$facet}";

        // Limit the query to public items if required
        if($limitToPublicItems) {
            $query .= ' AND public:"true"';
        }

        return $query;

    }


    /**
     * Construct the Solr search parameters.
     *
     * @return array Array of fields to pass to Solr
     */
    protected function _getParameters()
    {

        // Get a list of active facets.
        $facets = $this->_fields->getActiveFacetKeys();

        return array(
            //'fq'                  => 'itemtype:"TEI"',
            'facet'               => 'true',
            'facet.field'         => $facets,
            'facet.mincount'      => 1,
            'facet.limit'         => get_option('solr_search_facet_limit'),
            'facet.sort'          => get_option('solr_search_facet_sort'),
            'hl'                  => get_option('solr_search_hl')?'true':'false',
            'hl.snippets'         => get_option('solr_search_hl_snippets'),
            'hl.fragsize'         => get_option('solr_search_hl_fragsize'),
            'hl.maxAnalyzedChars' => get_option('solr_search_hl_max_analyzed_chars'),
            'hl.fl'               => '*_t'

        );
    }
}
