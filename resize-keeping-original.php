<?php
/*
Plugin Name: Resize Keeping Original
Plugin URI: https://example.com/lol
Description: lol front matter to come
Author: Aaron Miller <me@aaron-miller.me>
Version: 0.0.1
Author URI: https://example.com/lol

it's a thing lol
*/


$PLUGIN_VERSION = '0.0.1';
$OPTION_DEFAULTS = [
    'version' => $PLUGIN_VERSION,
    'max_dimension' => 2048
];

if (get_option('rko_resize_version') !== $PLUGIN_VERSION) {
    foreach ($OPTION_DEFAULTS as $key => $value) {
        add_option('rko_' . $key, $value);
    };
};

add_action('wp_handle_upload', 'rko_upload_resize');

function rko_upload_resize($image_data) {
    return $image_data;
};
