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

if (!defined('WPINC')) {
    exit;
}

// the output of the classic types (roboGallery); the [robo-gallery] shortcode is app/extensions/shortcode/,
// the Fusion Grid output app/extensions/fusionGrid/, the gallery post type app/extensions/postType/
function rbs_gallery_main_init()
{
    rbs_gallery_include(array('rbs_gallery_class.php'), ROBO_GALLERY_FRONTEND_PATH);
}
add_action('plugins_loaded', 'rbs_gallery_main_init');
