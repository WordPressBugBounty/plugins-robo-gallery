<?php 
/* 
*      Robo Gallery     
*      Version: 5.0.2 - 91160
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'WPINC' ) ) exit;

include_once plugin_dir_path( __FILE__ ).'class.addons.php';

new rbsGalleryAddons( ROBO_GALLERY_TYPE_POST );