<?php
/* 
*      Robo Gallery     
*      Version: 6.0.0 - 27445
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('WPINC') || exit;

use RoboGallery\app\extensions\shortcode\GalleryShortcode;

/*
 * The global names WordPress calls and others use: the block, the Elementor widget and the popup links
 * call robo_gallery_shortcode(), a theme may remove_filter('the_content', 'roboGalleryTag'). Keep them.
 */
function robo_gallery_shortcode($attr)
{
    return GalleryShortcode::render($attr);
}

function roboGalleryTag($content)
{
    return GalleryShortcode::appendToContent($content);
}

// on plugins_loaded, as before: the_content runs it in the same order among the other plugins' filters
add_action('plugins_loaded', function () {
    (new GalleryShortcode())->register();
});
