<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8"/>
    <link id="bulma" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.4.1/css/bulma.css"
          type="text/css"/>
    <link id="maincss" rel="stylesheet" type="text/css"
          href="http://localhost/omeka/plugins/TeiEditions/teibp/css/teibp.css"/>
    <link id="customcss" rel="stylesheet" type="text/css"
          href="http://localhost/omeka/plugins/TeiEditions/teibp/css/custom.css"/>
    <title><?php echo __("EHRI Digital Editions"); ?></title>

    <script type="text/javascript">
      var _gaq = _gaq || [];
      //include analytics account below.
      _gaq.push(['_setAccount', 'UA-XXXXXXXX-X']);
      _gaq.push(['_trackPageview']);

      (function () {
        var ga = document.createElement('script');
        ga.type = 'text/javascript';
        ga.async = true;
        ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
        var s = document.getElementsByTagName('script')[0];
        s.parentNode.insertBefore(ga, s);
      })();
    </script>
</head>
<body>

<div class="columns">
    <section id="sidebar" class="column is-3">
        Sidebar
    </section>
    <section id="main-content" class="column" role="main">
        <h1>Editions</h1>
        <h2 id="num-found">
            <?php echo $results->response->numFound; ?> results
        </h2>

        <?php foreach ($results->response->docs as $doc): ?>

        <div class="result">
            <?php $url = SolrSearch_Helpers_View::getDocumentUrl($doc); ?>

            <?php $item = get_db()->getTable($doc->model)->find($doc->modelid); ?>
            <h3><a href="editions/<?php echo $item->id ?>"><?php echo $doc->title; ?></a></h3>

        </div>

        <?php endforeach; ?>
    </section>
</div>
</body>
</html>
