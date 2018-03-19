<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8"/>
    <link id="bulma" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.4.1/css/bulma.css"   type="text/css"/>
    <link id="maincss" rel="stylesheet" type="text/css" href="<?php echo web_path_to('teibp/css/teibp.css'); ?>"/>
    <link rel="stylesheet" href="<?php echo web_path_to('css/styles.css'); ?>" type="text/css"/>
    <link rel="stylesheet" href="<?php echo web_path_to('css/rendition.css'); ?>" type="text/css"/>
    <style type="text/css" id="tagusage-css"></style>
    <script
            src="https://code.jquery.com/jquery-3.2.1.min.js"
            integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4="
            crossorigin="anonymous"></script>
    <script src="<?php echo web_path_to("js/tmpl.min.js");?>"></script>
    <title><?php echo metadata('item', 'display_title'); ?></title>
</head>
<body>

<?php echo $this->partial('edition/header.php'); ?>

<script type="text/x-templ" id="authority-tmpl">
    <div class="access-point authority" id="access-point-{%=o.data.id%}">
        <h4 class="title is-4">{%=o.data.item.eng.name%}</h4>
        <p class="is-small">{%=o.data.item.eng.biographicalHistory%}</p>
    </div>
</script>

<script type="text/x-templ" id="place-tmpl">
    <div class="access-point place" id="access-point-{%=o.geonameId%}">
        <h4 class="title is-4">{%=o.name%} <span class="subtitle is-6">{%=o.countryName%}</span></h4>
        <p class="is-small">{%=o.lat%}/{%=o.lng%}</p>
        {% if(o.wikipediaURL) { %}
            <a class="is-small" href="https://{%=o.wikipediaURL%}" title="Wikipedia">Show Wikipedia Entry</a>
        {% } %}
    </div>
</script>

<script type="text/x-templ" id="subject-tmpl">
    <div class="access-point subject" id="access-point-{%=o.data.item.id%}">
        <h4 class="title is-4">{%=o.data.item.eng.name%}</h4>
    </div>
</script>

<script>
    jQuery(function($) {

        function graphql(query, variables, callback) {
            $.ajax({
                url: "https://portal.ehri-project.eu/api/graphql",
                data: JSON.stringify({query: query, variables: JSON.stringify(variables)}),
                type: "POST",
                dataType: "json",
                contentType: "application/json; charset=utf-8",
                success: callback
            });
        }

        var matchers = {
            '.+geonames\\.org\/(\\d+)\/.+': function(id) {
                var url = "https://secure.geonames.org/getJSON?geonameId=" + id + "&lang=en&username=EhriAdmin";
                $.get(url, function(data) {
                    $("#access-points-places").append($(tmpl("place-tmpl", data)));
                });
            },

            '.+portal\\.ehri-project\\.eu\/authorities\/([^\/]+).*': function(id) {
                var query = "query get($id: ID!) {\n" +
                            "  item: HistoricalAgent(id: $id) {\n" +
                            "    eng: description(languageCode: \"eng\") {\n" +
                            "      name\n" +
                            "      biographicalHistory\n" +
                            "    }\n" +
                            "  }\n" +
                            "}";

                graphql(query, {id: id}, function(data) {
                    $("#access-points-authorities").append($(tmpl("authority-tmpl", data)));
                });
            },

            '.+portal\\.ehri-project\\.eu\/keywords\/([^\/]+).*': function(id) {
                var query = "query get($id: ID!) {\n" +
                            "  item: CvocConcept(id: $id) {\n" +
                            "    eng: description(languageCode: \"eng\") {\n" +
                            "      name\n" +
                            "      scopeNote\n" +
                            "      note\n" +
                            "      definition\n" +
                            "    }\n" +
                            "  }\n" +
                            "}";

                graphql(query, {id: id}, function(data) {
                    $("#access-points-subjects").append($(tmpl("subject-tmpl", data)));
                });
            }
        };

        function addAccessPoint(url) {
            for (regexp in matchers) {
                if (matchers.hasOwnProperty(regexp)) {
                    var match = url.match(regexp);
                    if (match !== null && match.length > 1) {
                        matchers[regexp](match[1]);
                    }
                }
            }
        }

        $("a", $("abstract")).each(function(i) {
            addAccessPoint(this.href);
        });

//        addAccessPoint("https://portal.ehri-project.eu/authorities/ehri_pers-000258");
//        addAccessPoint("https://portal.ehri-project.eu/keywords/ehri_terms-217")
    });
</script>

<div id="wrapper" class="container">
    <div class="columns">
        <section id="sidebar" class="column is-3">
            <div class="card">
                <header class="card-header">
                    <div class="card-header-title">Metadata</div>
                </header>
                <div class="card-content">
                    <div class="content">
                        <dl class="item-metadata">
                            <?php foreach (array("Author", "Encoding Description", "Source Details", "Publisher", "Publication Date") as $elem): ?>

                            <?php endforeach; ?>
                        </dl>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="card-footer-item is-pulled-left">
                        <a href="<?php echo tei_editions_get_tei_path($item); ?>">XML</a>
                    </div>
                </div>
            </div>
        </section>
        <section id="main-content" class="column is-6" role="main">
            <div class="content box">
                <?php echo tei_editions_render_item($item); ?>
            </div>
        </section>
        <section id="data" class="column content">
            <div class="card">
                <header class="card-header">
                    <div class="card-header-title">Mentioned in this Document</div>
                </header>
                <div class="card-content">
                    <div class="content">
                        <div id="access-points">
                            <div id="access-points-places"></div>
                            <div id="access-points-authorities"></div>
                            <div id="access-points-subjects"></div>
                        </div>
                    </div>
                </div>
            </div>

        </section>
    </div>
</div>


<footer class="footer">
    <div class="container">
        <?php echo $this->partial('edition/footer_text.php'); ?>
    </div>
</footer>
<script type="text/javascript" src="<?php echo web_path_to('teibp/js/teibp.js'); ?>"></script>
</body>
</html>
