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

namespace RoboGallery\app\extensions\access;

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}

use RoboGallery\app\PluginConstants;

/**
 * The per-gallery secret of "require token" galleries (meta _robogallery_token):
 * the only place that reads, compares, creates or looks it up.
 */
class GalleryToken
{
    private const LENGTH = 16;
    private const CHARS  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public function register(): void
    {
        add_action('save_post_' . ROBO_GALLERY_TYPE_POST, [$this, 'onSaveGallery'], 10, 3);
    }

    /**
     * Every gallery gets a token when it is created (and one is added on save
     * if it is missing), so switching "require token" on needs no extra step.
     *
     * @param int      $post_id
     * @param \WP_Post $post
     * @param bool     $update
     */
    public function onSaveGallery($post_id, $post, $update): void
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return;
        }

        $parent_id = wp_is_post_revision($post_id);
        if (false !== $parent_id) {
            $post_id = $parent_id;
        }

        if (get_post_type($post_id) !== ROBO_GALLERY_TYPE_POST || ! current_user_can('edit_post', $post_id)) {
            return;
        }

        if ($update && '' !== $this->get($post_id)) {
            return;
        }

        $this->regenerate($post_id);
    }

    /**
     * @param int $galleryId
     * @return string '' when the gallery has none
     */
    public function get($galleryId): string
    {
        $token = get_post_meta((int) $galleryId, PluginConstants::TOKEN_META_KEY, true);
        return is_string($token) ? $token : '';
    }

    /**
     * @param int    $galleryId
     * @param string $given
     * @return bool timing-safe comparison; never true for a gallery without token
     */
    public function matches($galleryId, string $given): bool
    {
        $stored = $this->get($galleryId);
        return '' !== $stored && hash_equals($stored, $given);
    }

    /**
     * New token for the gallery; links with the old one stop working.
     *
     * @param int $galleryId
     * @return string the new token
     */
    public function regenerate($galleryId): string
    {
        $token = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $token .= self::CHARS[random_int(0, strlen(self::CHARS) - 1)];
        }

        update_post_meta((int) $galleryId, PluginConstants::TOKEN_META_KEY, $token);
        return $token;
    }

    /**
     * The gallery a token belongs to. Direct lookup: a WP_Query here would go
     * through the listing filters, which hide exactly these galleries.
     *
     * @param string $token
     * @return int 0 when unknown
     */
    public function findGallery(string $token): int
    {
        if ('' === $token) {
            return 0;
        }

        global $wpdb;
        // The column collation may match case-insensitively; access checks
        // compare the token exactly (matches()) afterwards.
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
             WHERE m.meta_key = %s AND m.meta_value = %s AND p.post_type = %s
             LIMIT 1",
            PluginConstants::TOKEN_META_KEY,
            $token,
            ROBO_GALLERY_TYPE_POST
        ));
    }
}
