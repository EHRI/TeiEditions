<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8"/>
    <link id="bulma" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.4.1/css/bulma.css"
          type="text/css"/>
    <link id="maincss" rel="stylesheet" type="text/css"
          href="http://localhost/omeka/plugins/TeiEditions/teibp/css/teibp.css"/>
    <link id="customcss" rel="stylesheet" type="text/css"
          href="http://localhost/omeka/plugins/TeiEditions/teibp/css/custom.css"/>
    <link rel="stylesheet" href="http://localhost/omeka/plugins/TeiEditions/views/public/css/styles.css"
          type="text/css"/>
    <title><?php echo __("EHRI Digital Editions"); ?></title>
    <style>
    </style>
</head>
<body>

<div class="container">
    <div id="banner" class="nav content">
        <div class="nav-left">
            <h1><a href="<?php echo url("editions"); ?>">EHRI Digital Editions</a></h1>
        </div>
    </div>
</div>

<div id="wrapper" class="container">
    <div class="columns">
        <section id="sidebar" class="column is-3 content">
            <form action="<?php echo url(array()); ?>" method="get">
                <div class="field has-addons">
                    <div class="control">
                        <input class="input" id="id-q" name="q" type="text" value="<?php echo $q; ?>"
                               placeholder="Find an Edition"/>
                    </div>
                    <div class="control">
                        <a class="button">
                            Search
                        </a>
                    </div>
                </div>

                <h3 id="num-found">
                    <?php echo __(plural('One Edition found', '%s Editions Found', $results->response->numFound), $results->response->numFound); ?>
                </h3>


                <!-- Applied facets. -->
                <div id="solr-applied-facets" class="level">
                    <div class="level-left">
                        <!-- Get the applied facets. -->
                        <?php foreach (SolrSearch_Helpers_Facet::parseFacets() as $f): ?>
                            <div class="level-item">

                                <!-- Facet label. -->
                                <div class="tag is-primary">
                                    <?php $label = SolrSearch_Helpers_Facet::keyToLabel($f[0]); ?>
                                    <span class="applied-facet-label"><?php echo $label; ?></span> >
                                    <span class="applied-facet-value"><?php echo $f[1]; ?></span>

                                    <!-- Remove link. -->
                                    <?php $url = SolrSearch_Helpers_Facet::removeFacet($f[0], $f[1]); ?>
                                    <a class="delete is-small"
                                       href="<?php echo str_replace('solr-search', 'editions', $url); ?>"></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>


                <!-- Facets. -->
                <div id="solr-facets">
                    <?php foreach ($results->facet_counts->facet_fields as $name => $facets): ?>

                        <?php if (count(get_object_vars($facets)) && $name != "itemtype" && $name != "resulttype"): ?>

                            <div class="solr-facet-class">
                                <?php $label = SolrSearch_Helpers_Facet::keyToLabel($name); ?>
                                <strong><?php echo $label; ?></strong>

                                <ul>
                                    <?php foreach ($facets as $value => $count): ?>
                                        <li class="<?php echo $value; ?>">

                                            <?php $url = SolrSearch_Helpers_Facet::addFacet($name, $value); ?>

                                            <a href="<?php echo str_replace('solr-search', 'editions', $url); ?>"
                                               class="facet-value">
                                                <?php echo $value; ?>
                                            </a>

                                            (<span class="facet-count"><?php echo $count; ?></span>)

                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                    <?php endforeach; ?>
                </div>

            </form>
        </section>
        <section id="main-content" class="column content" role="main">
            <?php foreach ($results->response->docs as $doc): ?>
                <?php $item = get_db()->getTable($doc->model)->find($doc->modelid); ?>
                <div class="result box">
                    <article class="media">
                        <div class="media-left">
                            <figure class="image is-64x64">
                                <a class="item-thumbnail" href="editions/<?php echo $item->id ?>">
                                    <?php echo item_image('square_thumbnail', array(), 0, $item) ?>
                                </a>
                            </figure>
                        </div>
                        <div class="media-content">
                            <div class="content">
                                <strong><a href="editions/<?php echo $item->id ?>"><?php echo $doc->title; ?></a></strong>
                                <br>
                                Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aenean efficitur sit amet massa
                                fringilla egestas. Nullam condimentum luctus turpis.
                            </div>
                        </div>
                    </article>
                </div>

            <?php endforeach; ?>
            <?php echo pagination_links(array('partial_file' => 'edition/pagination_control.php')); ?>
        </section>
    </div>
</div>

<footer class="footer">
    <div class="container">
        <div class="content has-text-centered">
            <p>
                Powered by <a href="http://dcl.slis.indiana.edu/teibp/">TEI Boilerplate</a>.
                TEI Boilerplate is licensed under a <a href="http://creativecommons.org/licenses/by/3.0/">
                    Creative Commons Attribution 3.0 Unported License</a>.
                <a href="http://creativecommons.org/licenses/by/3.0/">
                    <img alt="Creative Commons License" style="border-width:0;"
                         src="http://i.creativecommons.org/l/by/3.0/80x15.png"/>
                </a>
            </p>
        </div>
    </div>
</footer>

</body>
</html>
