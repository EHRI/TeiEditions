<?php


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