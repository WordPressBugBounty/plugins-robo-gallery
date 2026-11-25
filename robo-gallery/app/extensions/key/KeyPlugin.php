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

namespace RoboGallery\app\extensions\key;

defined('WPINC') || exit;

/**
 * The Pro license key is a separate plugin, robogallerykey, loaded by this
 * plugin itself (active in WordPress or not). It defines:
 * - ROBO_GALLERY_TYR = 1 (0 without it) - the Pro switch used everywhere;
 * - ROBO_GALLERY_TYR_PATH*, ROBO_GALLERY_KEY_VERSION (rbsGalleryUtils::compareVersion());
 * - ROBO_GALLERY_KEY = 1, the older switch still read by some code.
 * Its source is key/ of this repository, for development only (built into its own zip and installed as a
 * plugin of its own, never loaded from there nor part of this plugin's release).
 */
final class KeyPlugin
{
    const DIR = 'robogallerykey';

    /*
     * The oldest key this version of the plugin works with as it should: raised only when the plugin
     * needs something of a newer key, not with every release. An older key keeps Pro on, but the
     * gallery list and editor ask to update it (KeyNotice).
     */
    const REQUIRED_VERSION = '4.0.0';

    /**
     * Its main file: inside this plugin, else wp-content/plugins/robogallerykey/ or a copy renamed by a
     * reinstall (robogallerykey-0 .. -5). Never key/ of a working copy: that is the key's source, which
     * must be built and installed as a plugin like on the users' sites.
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

    /** the version of the loaded key, '' without one */
    public static function version(): string
    {
        return ROBO_GALLERY_TYR && defined('ROBO_GALLERY_KEY_VERSION') ? (string) ROBO_GALLERY_KEY_VERSION : '';
    }

    /** a key is loaded, older than this plugin needs */
    public static function needsUpdate(): bool
    {
        $version = self::version();

        return '' !== $version && version_compare($version, self::REQUIRED_VERSION, '<');
    }
}
