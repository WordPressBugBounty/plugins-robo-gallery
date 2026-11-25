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

// links in the text that open a gallery in a popup (GalleryPopup itself is called by the renderer)
(new \RoboGallery\app\extensions\popup\PopupLinks())->register();
