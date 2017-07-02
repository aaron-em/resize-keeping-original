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

        // Install resize hook
        add_action('wp_handle_upload', array('ResizeKeepingOriginal', 'handle_upload'));
    }

    private static function debug($message) {
        $should_debug = get_option('rko_debug') || true;
        if (!$should_debug) return;
        
        $fh = fopen('/tmp/rko-debug.log', 'a');
        fwrite($fh, strftime("%F %T") . " $message\n");
        fclose($fh);
    }

    public static function handle_upload($image_data) {
        self::debug('Handler called!');
        self::debug(print_r($image_data, true));

        $upload_dir = dirname($image_data['file']);
        
        /* So we get called before Wordpress's own resize logic, so we
         * can pretty much just do the one resize and call that the
         * original, and rename the real original so we can prevent
         * HTTP access to it at the Apache level.
         */

        $max_dimension = get_option('rko_max_dimension');

        $image_editor = wp_get_image_editor($image_data['file']);

        # Early exit if this isn't a media type we want to handle.
        if (! in_array($image_data['type'],
                       ['image/png', 'image/gif', 'image/jpg', 'image/jpeg'])) {
            self::debug('Not a media type we handle.');
            return $image_data;
        };

        # Early exit if we couldn't get an image editor.
        if (!$image_editor || is_wp_error($image_editor)) {
            self::debug('Whoops, couldn\'t get image editor.');
            self::debug(print_r($image_editor, true));
            return $image_data;
        };
        
        $orig_sizes = $image_editor->get_size();
        self::debug('Image dimensions: ' . print_r($orig_sizes, true));

        // If the real original has no dimension greater than
        // max_dimension, we needn't mess with it further.
        if (! ((isset($orig_sizes['width'])
                && $orig_sizes['width'] > $max_dimension)
               or (isset($orig_sizes['height'])
                   && $orig_sizes['height'] > $max_dimension))) {
            self::debug('Image is too small to need resizing.');
            return $image_data;
        };

        // We compute a new size, bounded approximately by
        // max_dimension - see compute_resize for details.
        $new_sizes = self::compute_resize($max_dimension,
                                          $orig_sizes['width'],
                                          $orig_sizes['height']);

        // We copy the original file to a new name, including the
        // string '-ORIGINAL-' followed by its true dimensions. This
        // gives us something to key off in our Apache configuration
        // rule protecting these files from HTTP access.
        
        // We rely on Wordpress's upload handling to give us a unique
        // filename from which to start. No overwrite checking is
        // performed.
        $fileinfo = pathinfo($image_data['file']);
        $new_filename = $fileinfo['filename']
                      . '-' . 'ORIGINAL'
                      . '-' . $orig_sizes['width']
                      . 'x' . $orig_sizes['height']
                      . '.' . $fileinfo['extension'];
        $new_pathname = $fileinfo['dirname'] . '/' . $new_filename;
        self::debug("Will copy original to $new_pathname");
        if (!copy($image_data['file'], $new_pathname)) {
            self::debug('Failed to copy file! ( ._.) Bombing out...');
            throw new Exception('Aborting bogus upload');
        };
        self::debug("Copied image successfully.");

        // We then resize the uploaded file to match the dimensions
        // computed earlier.
        $image_editor->resize($new_sizes['width'], $new_sizes['height'], false);
        self::debug('Resized image');

        // If we're going to mess with the quality, here's where we would do it.

        // Then we save the image.
        $saved_image_data = $image_editor->save($image_data['file']);
        self::debug('Saved image data:');
        self::debug(print_r($saved_image_data, true));

        // Finally, we return the image_data array and let Wordpress
        // create further resizes from that.

        // Note that this will potentially cause quality problems, in
        // that those are double-resized. We'll evaluate that once it
        // happens; it may be desirable to avoid releasing images of
        // excessively high quality. If it's unsatisfactory, we'll
        // handle all of the resizes ourself.

        return $image_data;
    }

    public static function is_integral($n) {
        return ($n === floor($n));
    }

    public static function compute_resize($max, $width, $height) {
        $new_width = 0;
        $new_height = 0;
        $dimension = ($width > $height ? $width : $height);
        $factor = 0;
        $tries = 0;
        self::debug("Computing resize from {$width}x{$height} to max $max");

        do {
            ++$tries;
            $factor = $max / $dimension;
            $new_width = $width * $factor;
            $new_height = $height * $factor;
            self::debug("Try $tries: max $max, factor $factor, new dimensions {$new_width}x{$new_height}");
            ++$max;
        } while (! (self::is_integral($new_width)
                    && self::is_integral($new_height)));

        self::debug("Sticking with {$new_width}x{$new_height}");
        
        return [
            'width' => $new_width,
            'height' => $new_height
        ];
    }
};

ResizeKeepingOriginal::initialize();
