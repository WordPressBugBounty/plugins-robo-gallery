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

// the REST routes: restapi/endpoints/ImageReactionsController.php
\RoboGallery\app\extensions\reactions\ImageLikes::register();
\RoboGallery\app\extensions\reactions\ImageComments::register();
