<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8"/>
    <link id="bulma" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.4.1/css/bulma.css"
          type="text/css"/>
    <link id="maincss" rel="stylesheet" type="text/css"
          href="http://localhost/omeka/plugins/TeiEditions/teibp/css/teibp.css"/>
    <link id="customcss" rel="stylesheet" type="text/css"
          href="http://localhost/omeka/plugins/TeiEditions/teibp/css/custom.css"/>
    <link rel="stylesheet" href="http://localhost/omeka/plugins/TeiEditions/views/public/css/styles.css" type="text/css"/>
    <style type="text/css" id="tagusage-css"></style>
    <style type="text/css">[rendition~="#b"] {
            font-weight: bold;
        }

        [rendition~="#i"] {
            font-style: italic;
        }

        [rendition~="#u"] {
            text-decoration: underline;
        }

        [rendition~="#n"] {
            font-weight: normal;
            text-decoration: none;
            font-style: normal;
        }

        [rendition~="#mono"] {
            font-family: Monaco, Courier, monospace;
        }

        [rendition~="#super"] {
            vertical-align: super;
            font-size: 80%;
        }

        [rendition~="#sub"] {
            vertical-align: sub;
            font-size: 80%;
        }

        [rendition~="#lowercase"] {
            text-transform: lowercase;
        }

        [rendition~="#uppercase"] {
            text-transform: uppercase;
        }

        [rendition~="#capitalize"] {
            text-transform: capitalize;
        }

        [rendition~="#small-caps"] {
            font-variant: small-caps;
        }

        [rendition~="#block"] {
            display: block;
        }

        [rendition~="#blockquote"] {
            display: block;
            font-size: 90%;
            margin-left: 3em;
            padding-left: 1em;
            border-left: 1px solid gray;
            margin-top: .75em;
            margin-bottom: .75em;
            padding-top: .75em;
            padding-bottom: .75em;
        }

        [rendition~="#blockquote"]:before {
            content: "" !important;
        }

        [rendition~="#blockquote"]:after {
            content: "" !important;
        }

        [rendition~="#codeblock"] {
            display: block;
            font-size: 80%;
            margin-left: 1em;
            padding-left: 1em;
            border-left: 1px solid gray;
            margin-top: .75em;
            margin-bottom: .75em;
            padding-top: .75em;
            padding-bottom: .75em;
            font-family: Monaco, Courier, monospace;
            white-space: pre;
        }

        [rendition~="#inline"] {
            display: inline;
        }

        [rendition~="#center"] {
            display: block;
            text-align: center;
            margin-left: auto;
            margin-right: auto;
        }

        [rendition~="#left"] {
            text-align: left;
        }

        [rendition~="#right"] {
            text-align: right;
        }

        [rendition~="#justify"] {
            text-align: justify;
        }

        [rendition~="#center-block"] {
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        [rendition~="#hang"] {
            display: block;
            padding-left: 2.5em;
            text-indent: -2.5em;
        }

        [rendition~="#l-indent-01"] {
            display: block;
            padding-left: 4em;
            text-indent: -2em;
        }

        [rendition~="#l-indent-02"] {
            display: block;
            padding-left: 6em;
            text-indent: -2em;
        }

        [rendition~="#l-indent-03"] {
            display: block;
            padding-left: 8em;
            text-indent: -2em;
        }

        [rendition~="#l-indent-04"] {
            display: block;
            padding-left: 10em;
            text-indent: -2em;
        }

        [rendition~="#l-indent-05"] {
            display: block;
            padding-left: 12em;
            text-indent: -2em;
        }

        [rendition~="#l-indent-06"] {
            display: block;
            padding-left: 14em;
            text-indent: -2em;
        }

        [rendition~="#l-indent-07"] {
            display: block;
            padding-left: 16em;
            text-indent: -2em;
        }

        [rendition~="#l-indent-08"] {
            display: block;
            padding-left: 18em;
            text-indent: -2em;
        }

        [rendition~="#indent"]:before { /* text-indent:4em; */ /* The above method of describing an indentation for, say, a paragraph is preferred. The method being used is in response to a bug in some browsers whereby a block element, like a paragraph, is erroneously re-indented after interruption by another block element (like a list, which is valid inside a paragraph). */
            content: "\A0\A0\A0\A0\A0\A0";
        }

        [rendition~="#small"] {
            font-size: 90%;
        }

        [rendition~="#x-small"] {
            font-size: 85%;
        }

        [rendition~="#xx-small"] {
            font-size: 80%;
        }

        [rendition~="#large"] {
            font-size: 110%;
        }

        [rendition~="#x-large"] {
            font-size: 115%;
        }

        [rendition~="#xxx-large"] {
            font-size: 120%;
        }

        [rendition~="#bracket"] {
        }

        [rendition~="#bracket"]:before {
            content: "&lt;";
        }

        [rendition~="#bracket"]:after {
            content: "&gt;";
        }
    </style>
    <title><?php echo metadata('item', 'display_title'); ?></title>
</head>
<body>

<div class="container">
    <div class="nav content">
        <div class="nav-left">
            <h1><a href="<?php echo url("editions"); ?>">EHRI Digital Editions</a></h1>
        </div>
    </div>
</div>

<div id="wrapper" class="container">
    <div class="columns">
        <section id="sidebar" class="column is-3 content">
            <h2>Metadata</h2>
        </section>
        <section id="main-content" class="column is-6 content" role="main">
            <?php echo $xml; ?>
        </section>
        <section id="data" class="column content">
            <h3>Some stuff</h3>
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
                    <img alt="Creative Commons License" style="border-width:0;" src="http://i.creativecommons.org/l/by/3.0/80x15.png"/>
                </a>
            </p>
        </div>
    </div>
</footer>
<script type="text/javascript" src="http://localhost/omeka/plugins/TeiEditions/teibp/js/teibp.js"></script>
</body>
</html>
