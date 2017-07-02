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

class ResizeKeepingOriginal {
    public static $OPTION_DEFAULTS = [
        'version' => '0.0.1',
        'max_dimension' => 2048,
        'debug' => true
    ];

    public static function initialize() {
        # Install options, if they aren't already there (or if we're in debug mode)
        if (self::$OPTION_DEFAULTS['debug'] ||
            get_option('rko_version') !== self::$OPTION_DEFAULTS['version']) {
            foreach (self::$OPTION_DEFAULTS as $key => $value) {
                add_option('rko_' . $key, $value);
            };
        };

        # Empty log
        $fh = fopen('/tmp/rko-debug.log', 'w');
        fclose($fh);

        # Install resize hook
        add_action('wp_handle_upload', array('ResizeKeepingOriginal', 'handle_upload'));
    }

    private static function debug($message) {
        $should_debug = get_option('rko_debug') || true;
        if (!$should_debug) return;
        
        $fh = fopen('/tmp/rko-debug.log', 'a');
        fwrite($fh, "$message\n");
        fclose($fh);
    }

    public static function handle_upload($image_data) {
        self::debug('Handler called!');
        self::debug(print_r($image_data, true));

        $upload_dir = dirname($image_data['file']);
        self::debug(`ls '$upload_dir'`);
        
        return $image_data;
    }
};

ResizeKeepingOriginal::initialize();
