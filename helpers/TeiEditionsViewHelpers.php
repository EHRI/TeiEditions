<?php
/**
 * TeiEditions
 *
 * @copyright Copyright 2018 King's College London Department of Digital Humanities
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

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
            self::$iso = new Matriphe\ISO639\ISO639;
            self::$twig->addFilter(new Twig_SimpleFilter("lang2name", function($code) {
                $lang = self::$iso->languageByCode1($code);
                return $lang ? __($lang) : $code;
            }));
        }
        return self::$twig->render($template, $args);
    }
}

/**
 * A shortcode function for rendering an index list for a given element.
 *
 * @param $args
 * @param $view
 * @return string
 * @throws Twig_Error_Loader
 * @throws Twig_Error_Runtime
 * @throws Twig_Error_Syntax
 */
function tei_editions_index_shortcode($args, $view)
{
    $element = $args["element"];
    return ViewRenderer::render("index_page.html.twig", [
        "element" => $element,
        "items" => tei_editions_get_elements($element)
    ]);
}

/**
 * A shortcode function for rendering a list of recent items.
 *
 * @param $args
 * @param $view
 * @return string
 * @throws
 */
function tei_editions_recent_items_shortcode($args, $view)
{
    $html = "<div class=\"recently-added-wrapper\">\n";
    foreach (get_recent_items($args["num"]) as $item) {
        $html .= tei_editions_render_item_summary($item) . "\n";
    }
    $html .= "</div>\n";
    return $html;
}

/**
 * A shortcode function for rendering a group of item summaries via
 * a comma-delimited list of identifiers.
 *
 * @param $args
 * @param $view
 * @return string
 */
function tei_editions_items_shortcode($args, $view)
{
    return sprintf(
        "<div class=\"editions-item-group\">%s</div>",
        implode("\n", array_map(function($identifier) use ($view) {
            return tei_editions_item_shortcode(["identifier" => trim($identifier)], $view);
        }, explode(',', $args["identifiers"])))
    );
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
        return sprintf(
            "<div class=\"editions-item\">%s</div>",
            tei_editions_render_item_summary($item)
        );
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
function tei_editions_render_item_summary(Item $item)
{
    $url = record_url($item);
    $img = record_image($item);
    $options = ['no_escape' => true];
    $title = metadata($item, "display_title", $options);
    $ident = metadata($item, ['Dublin Core', "Identifier"], $options);
    $desc = metadata($item, ['Dublin Core', "Description"], $options);
    $src = metadata($item, ['Dublin Core', "Source"], $options);
    $meta = [];
    foreach (["Date", "Creator", "Coverage"] as $key) {
        $value = metadata($item, ['Dublin Core', $key], $options);
        if ($value) {
            $meta[$key] = $value;
        }
    }

    return ViewRenderer::render("item_summary.html.twig", [
            "item" => $item, "metadata" => $meta, "url" => $url,
            "record_image" => $img, "title" => $title,
            "identifier" => $ident, "description" => $desc, "source" => $src
        ]
    );
}

function tei_editions_render_document_references($item)
{
    $content = "";
    if (($tei = tei_editions_get_main_tei($item)) and
        plugin_is_active('EhriData')) {

        $doc = TeiEditionsDocumentProxy::fromUriOrPath($tei->getWebPath());
        foreach ($doc->manuscriptIds() as $url) {
            $id = substr($url, strrpos($url, '/') + 1);
            $content .= "[ehri_item_data id=$id]\n";
        }
    }
    return get_view()->shortcodes($content);
}

/**
 * @param $item
 * @return string
 * @throws Twig_Error_Loader
 * @throws Twig_Error_Runtime
 * @throws Twig_Error_Syntax
 */
function tei_editions_render_document_images($item)
{
    $images = [];
    foreach ($item->getFiles() as $file) {
        if (preg_match('/.+\.(png|jpg|tif)$/', $file->original_filename)) {
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

    return ViewRenderer::render("images.twig.html", ["images" => $images]);
}

/**
 * @param $item
 * @return string
 * @throws Twig_Error_Loader
 * @throws Twig_Error_Runtime
 * @throws Twig_Error_Syntax
 */
function tei_editions_render_item_metadata($item)
{
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

    return ViewRenderer::render("metadata.html.twig", [
            "item" => $item,
            "metadata" => $meta,
            "identifier" => $ident,
            "description" => $desc,
            "source" => $src
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
function tei_editions_render_document_texts($item) {
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
    foreach ($files as $file) {
        $path = $file->getWebPath();

        if (tei_editions_is_xml_file($path)) {
            $lang = tei_editions_get_language($file->original_filename, $file->original_filename);
            $text_html[$lang] = tei_editions_tei_to_html($path, $file_url_map);
        }
    }

    return ViewRenderer::render("document_texts.twig.html", ["data" => $text_html]);
}

function tei_editions_get_elements($element_name)
{
    $db = get_db();
    return $db->query("SELECT DISTINCT text
                      FROM {$db->prefix}element_texts t
                      JOIN {$db->prefix}elements e
                        ON t.element_id = e.id
                      WHERE e.name  = ?
                      ORDER BY text",
        ["name" => $element_name]
    )->fetchAll($style = 0, $col = 0);
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
function tei_editions_get_neatline_exhibit(Item $item)
{
    $exhibits = get_db()->getTable('NeatlineExhibit')
        ->findBy(['slug' => metadata($item, ['Dublin Core', 'Identifier'])]);
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