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
    // NB these are all prefixed with 'rko_' when they're added to
    // Wordpress
    public static $OPTION_DEFAULTS = [
        'version' => '0.0.1',
        'max_dimension' => 2048,
        'debug' => true
    ];

    public static function initialize() {
        // Install options, if they aren't already there (or if we're in debug mode)
        if (self::$OPTION_DEFAULTS['debug']
            or (get_option('rko_version') !== self::$OPTION_DEFAULTS['version'])) {
            foreach (self::$OPTION_DEFAULTS as $key => $value) {
                add_option('rko_' . $key, $value);
            };
        };

        // Empty log
        $fh = fopen('/tmp/rko-debug.log', 'w');
        fclose($fh);

        // Install resize hook
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
        
        self::debug('Upload directory currently contains:');
        self::debug(`ls '$upload_dir'`);

        /* So we get called before Wordpress's own resize logic, so we
         * can pretty much just do the one resize and call that the
         * original, and rename the real original so we can prevent
         * HTTP access to it at the Apache level.
         */

        $max_dimension = get_option('rko_max_dimension');

        $orig_image_editor = wp_get_image_editor($image_data['file']);
        $orig_sizes = $orig_image_editor->get_size();
        self::debug('Image dimensions: ' . print_r($orig_sizes, true));

        // If the real original has no dimension greater than
        // max_dimension, we needn't mess with it.
        if (! ((isset($orig_sizes['width'])
                && $orig_sizes['width'] > $max_dimension)
               or (isset($orig_sizes['height'])
                   && $orig_sizes['height'] > $max_dimension))) {
            self::debug('Image is too small to need resizing. Done!');
            return $image_data;
        };

        // We compute a new size within the bounds of max_dimension,
        // and which preserves the aspect ratio of the original
        // image. If we can't do that and end up with integral
        // dimensions, we add as many pixels to max_dimension as are
        // required for us to do so.
        $new_sizes = self::compute_resize($max_dimension,
                                          $orig_sizes['width'],
                                          $orig_sizes['height']);

        
        
        return $image_data;
    }

    public static function compute_resize($max, $width, $height) {
        return [
            'width' => $width,
            'height' => $height
        ];
    }
};

ResizeKeepingOriginal::initialize();
