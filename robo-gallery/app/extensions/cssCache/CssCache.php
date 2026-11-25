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

namespace RoboGallery\app\extensions\cssCache;

defined('WPINC') || exit;

/**
 * The compiled CSS of the classic galleries (includes/frontend/modules/class/scss.php)
 * is cached in cache/css/robo_gallery_css_id<gallery>_<cache_id>.css. The cache id is
 * the gallery's rsg_cache_id meta: a save gives it a new one, so the next view compiles
 * a new file, and the files of the old id are removed here instead of piling up.
 * save_post also runs when a gallery is created, so it gives a new gallery its id.
 * The settings panel saves over REST without save_post: ClassicOptions::save() calls renew().
 * Registered outside the admin too: galleries are also saved over REST and WP-CLI.
 */
class CssCache
{
    const META = 'cache_id';

    public function register(): void
    {
        add_action('save_post', array($this, 'renewCacheId'), 10, 1);
        add_action('before_delete_post', array($this, 'deleteGalleryFiles'));
    }

    /**
     * @param int $postId
     */
    public function renewCacheId($postId): void
    {
        if (ROBO_GALLERY_TYPE_POST !== get_post_type($postId)) {
            return;
        }

        self::renew((int) $postId);
    }

    /**
     * Removes the CSS of the old cache id - this gallery's and of the galleries that
     * clone its settings (they use the source's cache id) - and sets a new id.
     *
     * @param int $galleryId
     */
    public static function renew(int $galleryId): void
    {
        $key = ROBO_GALLERY_PREFIX . self::META;

        foreach ((array) get_post_meta($galleryId, $key) as $oldCacheId) {
            // the id goes into a glob pattern
            if (is_string($oldCacheId) && preg_match('/^[a-zA-Z0-9]{1,32}$/', $oldCacheId)) {
                self::deleteFiles('robo_gallery_css_id*_' . $oldCacheId . '.css');
            }
        }

        // galleries saved before had two values (an extra one added on wp_insert_post)
        delete_post_meta($galleryId, $key);
        add_post_meta($galleryId, $key, uniqid());
    }

    /**
     * @param int $postId
     */
    public function deleteGalleryFiles($postId): void
    {
        if (ROBO_GALLERY_TYPE_POST !== get_post_type($postId)) {
            return;
        }

        self::deleteFiles('robo_gallery_css_id' . (int) $postId . '_*.css');
    }

    private static function deleteFiles(string $pattern): void
    {
        $files = glob(ROBO_GALLERY_CACHE_CSS_PATH . $pattern);
        if (!is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                wp_delete_file($file);
            }
        }
    }
}
