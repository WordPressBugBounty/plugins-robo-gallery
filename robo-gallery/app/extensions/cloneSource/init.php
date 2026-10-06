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

// posts can be deleted outside the admin too (REST, cron, WP-CLI)
(new \RoboGallery\app\extensions\cloneSource\CloneSource())->register();
