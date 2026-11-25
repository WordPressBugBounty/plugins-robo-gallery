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

namespace RoboGallery\app\extensions\galleryType;

use RoboGallery\app\GalleryOptions;
use RoboGallery\app\GalleryUtils;

defined('WPINC') || exit;

/**
 * The layout picked in the type dialog for a new Fusion Grid gallery (any of
 * its sources): post-new.php?rsg_gallery_type=<type>&rsg_gallery_layout=<layout>.
 * It is written into the options of the auto-draft post-new.php creates, so
 * the settings panel opens with it and the gallery shows it even if its
 * settings are never changed.
 */
class NewGalleryLayout
{
    const ARG = ROBO_GALLERY_PREFIX . 'gallery_layout';

    public function register(): void
    {
        add_action('wp_insert_post', array($this, 'apply'), 10, 3);
    }

    /**
     * No nonce, like the type arg: post-new.php creates the auto-draft on a GET.
     * Only a value of the layout option is taken, only for the user's own new draft.
     *
     * @param int      $postId
     * @param \WP_Post $post
     * @param bool     $update
     */
    public function apply($postId, $post, $update): void
    {
        global $pagenow;

        if ($update || 'post-new.php' !== $pagenow || empty($_GET[self::ARG])
            || ROBO_GALLERY_TYPE_POST !== $post->post_type || 'auto-draft' !== $post->post_status) {
            return;
        }

        // a new gallery's type is still the request's (GalleryUtils), its meta is saved with the post form
        $type = GalleryUtils::getTypeGallery();
        if (!GalleryTypeList::isGridScriptType($type) || !current_user_can('edit_post', (int) $postId)) {
            return;
        }

        $layout = sanitize_key(wp_unslash($_GET[self::ARG]));
        $config = GalleryOptions::getOptionConfig();
        if (!in_array($layout, $config['layout']['options'], true)) {
            return;
        }

        GalleryOptions::save((int) $postId, array('layout' => $layout), GalleryOptions::setForType($type));
    }
}
