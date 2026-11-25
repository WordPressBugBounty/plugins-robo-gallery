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

include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'validation/init.php';

// first: its init callback must run before the other modules' (block, fields) at the same priority
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'postType/init.php';

// loaded explicitly, not autoloaded: they also define the legacy names
// RoboGallery\App\Extension\GalleryTypes\GalleryTypeList and rbsGalleryUtils (class_alias)
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'galleryType/GalleryTypeList.php';
include_once ROBO_GALLERY_APP_PATH.'GalleryUtils.php';

include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'restapi/init.php';

// the [robo-gallery] shortcode: every gallery on a page
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'shortcode/init.php';

/* extensions */
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'cssCache/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'listing/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'stats/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'reactions/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'proofing/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'sitemap/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'imageResize/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'dashboard/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'galleryType/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'fields/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'duplicate/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'cloneSource/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'classic/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'widget/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'media/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'editorButton/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'imageManager/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'block/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'popup/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'elementor/init.php';

// admin menu order: adminMenu links first, then settings (same admin_menu priority)
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'adminMenu/init.php';
include_once ROBO_GALLERY_APP_EXTENSIONS_PATH.'settings/init.php';
