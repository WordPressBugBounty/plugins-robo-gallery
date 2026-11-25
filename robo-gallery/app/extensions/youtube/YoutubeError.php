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

namespace RoboGallery\app\extensions\youtube;

defined('WPINC') || exit;

/**
 * Why a source has no videos (YoutubeClient); its message is shown to the
 * gallery's editors in the settings panel.
 */
class YoutubeError extends \Exception
{
}
