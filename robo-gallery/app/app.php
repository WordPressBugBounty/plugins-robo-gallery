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

include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'validation/init.php';

// loaded explicitly, not autoloaded: they also define the legacy names
// RoboGallery\App\Extension\GalleryTypes\GalleryTypeList and rbsGalleryUtils (class_alias)
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'galleryType/GalleryTypeList.php';
include_once ROBO_GALLERY_APP_PATH.'GalleryUtils.php';

include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'restapi/init.php';

/* extensions */
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'listing/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'imageResize/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'dashboard/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'galleryType/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'fields/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'duplicate/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'cloneSource/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'widget/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'media/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'editorButton/init.php';

// admin menu order: adminMenu links first, then settings (same admin_menu priority)
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'adminMenu/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'settings/init.php';
