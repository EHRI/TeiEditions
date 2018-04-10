<?php

require_once dirname(__FILE__) . '/../vendor/autoload.php';

class ViewRenderer {
    static $loader;
    static $twig;
    static $iso;

    /**
     * @param $template
     * @param array $args
     * @return string
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public static function render($template, $args = []) {
        if (!isset(self::$loader)) {
            self::$loader = new Twig_Loader_Filesystem(dirname(__FILE__) . "/../templates");
            self::$twig = new Twig_Environment(self::$loader);
            self::$twig->addExtension(new Twig_Extensions_Extension_I18n());
            self::$twig->getExtension('Twig_Extension_Core')->setTimezone('Europe/Amsterdam');
            self::$twig->getExtension('Twig_Extension_Core')->setDateFormat('d/m/Y', '%d days');
            self::$twig->addExtension(new Twig_Extensions_Extension_Text());
            self::$twig->addExtension(new Twig_Extensions_Extension_I18n());
            self::$iso = $iso = new Matriphe\ISO639\ISO639;
            self::$twig->addFilter(new Twig_SimpleFilter("lang2name", function($code) {
                $lang = self::$iso->languageByCode1($code);
                return $lang ? $lang : $code;
            }));
        }
        return self::$twig->render($template, $args);
    }
}

/**
 * A shortcode function for rendering an item summary via it's identifier.
 *
 * @param $args
 * @param $view
 * @return string
 */
function tei_editions_item_shortcode($args, $view)
{
    try {
        $identifier = $args["identifier"];
        $item = tei_editions_get_item_by_identifier($identifier);
        if (is_null($item)) {
            return "<div class='shortcode-error'>Unable to find item with identifier: \"$identifier\"</div>";
        }
        return tei_editions_render_search_item($item);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return "<div class='shortcode-error'>Error rendering shortcode...</div>";
    }
}

/**
 * @param Item $item
 * @return string
 * @throws Twig_Error_Loader
 * @throws Twig_Error_Runtime
 * @throws Twig_Error_Syntax
 */
function tei_editions_render_search_item(Item $item)
{
    $url = record_url($item);
    $img = record_image($item);
    $ident = metadata($item, ['Dublin Core', "Identifier"], ['no_escape' => true]);
    $desc = metadata($item, ['Dublin Core', "Description"], ['no_escape' => true]);
    $src = metadata($item, ['Dublin Core', "Source"], ['no_escape' => true]);
    $meta = [];
    foreach (["Date", "Creator", "Coverage"] as $key) {
        $value = metadata($item, ['Dublin Core', $key], ['no_escape' => true]);
        if ($value) {
            $meta[$key] = $value;
        }
    }

    return ViewRenderer::render("search_item.html.twig", [
            "item" => $item, "metadata" => $meta, "url" => $url,
            "record_image" => $img, "title" => metadata($item, "display_title"),
            "identifier" => $ident, "description" => $desc, "source" => $src
        ]
    );
}

/**
 * @param $item
 * @return string
 * @throws Twig_Error_Loader
 * @throws Twig_Error_Runtime
 * @throws Twig_Error_Syntax
 */
function tei_editions_render_item_text($item)
{
    if (is_string($item)) {
        $view = get_view();
        $item = $view->{$view->singularize($item)};
    }

    $files = $item->getFiles();

    $file_url_map = array();
    foreach ($files as $file) {
        $file_url_map[basename($file->original_filename)] = $file->getWebPath();
    }

    $text_html = [];

    $exhibit = null;

    foreach ($files as $file) {
        $path = $file->getWebPath();

        if (tei_editions_is_xml_file($path)) {
            if ($exhibit === null && plugin_is_active('Neatline')) {
                $proxy = new TeiEditionsDocumentProxy($path);
                $exhibit = tei_editions_get_neatline_exhibit($proxy);
            }
            $lang = tei_editions_get_language($file->original_filename, $file->original_filename);
            $text_html[$lang] = tei_editions_tei_to_html($path, $file_url_map);
        }
    }

    // FIXME: testing...
    //$html["Language2"] = "This is a test";

    $ident = metadata($item, ['Dublin Core', "Identifier"], ['no_escape' => true]);
    $desc = metadata($item, ['Dublin Core', "Description"], ['no_escape' => true]);
    $src = metadata($item, ['Dublin Core', "Source"], ['no_escape' => true]);
    $meta = [];
    foreach (["Date", "Creator", "Coverage"] as $key) {
        $value = metadata($item, ['Dublin Core', $key], ['no_escape' => true]);
        if ($value) {
            $meta[$key] = $value;
        }
    }

    $images = [];
    foreach ($item->getFiles() as $file) {
        if (!tei_editions_is_xml_file($file)) {
            $res = '';
            if ($file->metadata != '') {
                $info = json_decode($file->metadata, true);
                if (isset($info["video"]) and isset($info["video"]["resolution_x"])) {
                    $res = $info["video"]["resolution_x"] . 'x' . $info["video"]["resolution_y"];
                }
            }
            $images[] = [
                "path" => $file->getWebPath(),
                "thumb" => $file->getWebPath("thumbnail"),
                "name" => $file->original_filename,
                "resolution" => $res
            ];
        }
    }

    return ViewRenderer::render("texts.html.twig", [
            "item" => $item, "data" => $text_html, "metadata" => $meta,
            "exhibit" => $exhibit, "identifier" => $ident,
            "description" => $desc, "source" => $src,
            "images" => $images
        ]
    );
}

/**
 * Get an Item by its DC Identifier.
 *
 * @param string $identifier the identifier value
 * @return Item|null
 * @throws Omeka_Record_Exception|Exception
 */
function tei_editions_get_item_by_identifier($identifier)
{
    $element = get_db()->getTable('Element')->findBy([
        'name' => 'Identifier'
    ])[0]; // hack!
    $text = get_db()->getTable('ElementText')->findBy([
        'element_id' => $element->id,
        'text' => $identifier
    ]);
    if (!empty($text)) {
        $item = get_db()->getTable('Item')->find($text[0]->record_id);
        if (!is_null($item) && $item !== false) {
            return $item;
        }
    }

    return null;
}


/**
 * @param TeiEditionsDocumentProxy $doc
 * @return NeatlineExhibit
 * @throws Omeka_Record_Exception
 */
function tei_editions_get_neatline_exhibit(TeiEditionsDocumentProxy $doc)
{
    $exhibits = get_db()->getTable('NeatlineExhibit')->findBy(['slug' => $doc->recordId()]);
    return empty($exhibits) ? null : $exhibits[0];
}

function tei_editions_render_string_list($list, $class_name = "")
{
    $out = "";
    if (!is_null($list) && is_array($list) && !empty($list)) {
        $out .= "<ul class=\"$class_name\">";
        foreach ($list as $value) {
            $out .= "<li>$value</li>";
        }
        $out .= "</ul>";
        return $out;
    }

    return $out;
}

function tei_editions_render_properties($data, $messages, $only_keys = null, $class_name = "")
{
    $out = "";
    $keys = $only_keys == null ? array_keys($data) : $only_keys;
    if ($keys) {
        $out .= "<dl class=\"$class_name\">";
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && !empty($data[$key])) {
                $out .= "<dt>" . $messages[$key] . "</dt>";
                $out .= "<dd>" . $data[$key] . "</dd>";
            }
        }
        $out .= "</dl>";
        return $out;
    }
    return $out;
}

function tei_editions_render_map($data, $width = 425, $height = 350)
{
    if (isset($data["latitude"]) and isset($data["longitude"])) {
        // TODO
    }
    return "";
}