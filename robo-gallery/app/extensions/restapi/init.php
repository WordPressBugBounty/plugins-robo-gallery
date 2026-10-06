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

defined( 'WPINC' ) || exit;

/*
 * REST API of the plugin (namespace RoboGallery\app\extensions\restapi,
 * autoloaded). Everything is registered in Bootstrap.php.
 */
( new \RoboGallery\app\extensions\restapi\Bootstrap() )->run();
