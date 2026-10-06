<?php
/* 
*      Robo Gallery     
*      Version: 5.2.6 - 24868
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('WPINC') || exit;

use RoboGallery\app\extensions\key\KeyPlugin;

// included here, at file scope as before: the key plugin's top-level code must not run inside a method
$roboGalleryKeyFile = KeyPlugin::findMainFile();
if ($roboGalleryKeyFile) {
    include_once $roboGalleryKeyFile;
}
unset($roboGalleryKeyFile);

if (!defined('ROBO_GALLERY_TYR')) {
    define('ROBO_GALLERY_TYR', 0);
}
