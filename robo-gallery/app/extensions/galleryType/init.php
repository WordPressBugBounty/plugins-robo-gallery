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

use RoboGallery\app\extensions\galleryType\GalleryScreens;
use RoboGallery\app\extensions\galleryType\ThemePresets;
use RoboGallery\app\extensions\galleryType\TypeChange;
use RoboGallery\app\extensions\galleryType\TypeColumn;
use RoboGallery\app\extensions\galleryType\TypeDialog;

// GalleryTypeList (the type registry) is loaded earlier by app/app.php: other modules read it at load time.
(new TypeDialog())->register();
(new TypeChange())->register();
(new TypeColumn())->register();
(new GalleryScreens())->register();
(new ThemePresets())->register();
