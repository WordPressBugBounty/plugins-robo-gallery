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

namespace RoboGallery\app\extensions\key;

defined('WPINC') || exit;

/**
 * The Pro license key is a separate plugin, robogallerykey, loaded by this
 * plugin itself (active in WordPress or not). It defines:
 * - ROBO_GALLERY_TYR = 1 (0 without it) - the Pro switch used everywhere;
 * - ROBO_GALLERY_TYR_PATH*, ROBO_GALLERY_KEY_VERSION (rbsGalleryUtils::compareVersion());
 * - ROBO_GALLERY_KEY = 1, the older switch still read by some code.
 */
final class KeyPlugin
{
    const DIR = 'robogallerykey';

    /**
     * Its main file: inside this plugin, else wp-content/plugins/robogallerykey/
     * or a copy renamed by a reinstall (robogallerykey-0 .. -5).
     *
     * @return string|false
     */
    public static function findMainFile()
    {
        $file = self::DIR . '.php';

        $candidates = array(ROBO_GALLERY_PATH . $file, WP_PLUGIN_DIR . '/' . self::DIR . '/' . $file);
        for ($i = 0; $i < 6; $i++) {
            $candidates[] = WP_PLUGIN_DIR . '/' . self::DIR . '-' . $i . '/' . $file;
        }

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return false;
    }
}
