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

namespace RoboGallery\app\extensions\settings;

defined('WPINC') || exit;

/**
 * Galleries are cloned by the free plugin Duplicate Post RB (wordpress.org); the
 * Tools tab of the settings installs and activates it in one click. How a gallery
 * is copied by it: duplicate/GalleryDuplicate.php.
 */
class DuplicatePlugin extends CompanionPlugin
{
    const SLUG = 'duplicate-post-rb';
    const FILE = 'duplicate-post-rb/duplicate-post-rb.php';

    const ACTION     = 'robo_gallery_enable_duplicate_post';
    const RESULT_ARG = 'robo_gallery_duplicate_post';
    const TAB        = 'tools';

    // the first version with the hooks that copy a gallery's images and settings
    const MIN_VERSION = '2.5.0';

    /**
     * Read from its constant, not get_plugins(): the supported types filter of
     * GalleryDuplicate asks this on the requests of the plugin, on the site too.
     */
    public static function isReady(): bool
    {
        return defined('RB_DUPLICATE_POST_VERSION')
            && version_compare(RB_DUPLICATE_POST_VERSION, self::MIN_VERSION, '>=');
    }

    public static function isOutdated(): bool
    {
        return defined('RB_DUPLICATE_POST_VERSION') && !self::isReady();
    }
}
