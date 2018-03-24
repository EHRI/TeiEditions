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

    $html = [];

    foreach ($files as $file) {
        $path = $file->getWebPath();

        if (tei_editions_is_xml_file($path)) {
            $lang = tei_editions_get_language($file->original_filename, $file->original_filename);
            $html[$lang] = tei_editions_tei_to_html($path, $file_url_map);
        }
    }

    // FIXME: testing...
    //$html["Language2"] = "This is a test";

    $meta = [];
    foreach (["Date", "Publisher", "Format", "Language"] as $key) {
        $value = metadata($item, ['Dublin Core', $key]);
        if ($value) {
            $meta[$key] = $value;
        }
    }

    return ViewRenderer::render("texts.html.twig", [
            "item" => $item, "data" => $html, "metadata" => $meta
        ]
    );
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