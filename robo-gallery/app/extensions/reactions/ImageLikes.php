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

namespace RoboGallery\app\extensions\reactions;

defined('WPINC') || exit;

/**
 * The likes of the images of a gallery: the only reader and writer of the gallery
 * meta META, one row per like, "<image id>:<liker>" (Liker: "u<user id>" or
 * "g<hash>" of a guest). The likes belong to the gallery: the same image in another
 * gallery has its own. The meta is protected (validation\ProtectedMeta, rsg_ prefix)
 * and not copied with the gallery (duplicate\GalleryDuplicate).
 */
class ImageLikes
{
    const META = ROBO_GALLERY_PREFIX . 'like';

    public static function register(): void
    {
        add_action('deleted_user', array(__CLASS__, 'deleteOfUser'));
        add_action('delete_attachment', array(__CLASS__, 'deleteOfImage'));
    }

    public static function count(int $galleryId, int $imageId): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s AND meta_value LIKE %s",
            $galleryId,
            self::META,
            $wpdb->esc_like($imageId . ':') . '%'
        ));
    }

    /**
     * The likes of all the images of a gallery in one query, for the like buttons of its cards.
     *
     * @param int[] $imageIds the gallery's images: rows of images taken out of it are left out
     * @return array{likes: array<int, int>, liked: int[]} image id => count (only the liked ones), the liker's images
     */
    public static function ofGallery(int $galleryId, array $imageIds, string $liker): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT SUBSTRING_INDEX(meta_value, ':', 1) AS image, COUNT(*) AS likes
             FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s GROUP BY image",
            $galleryId,
            self::META
        ));

        $inGallery = array_flip($imageIds);
        $likes     = array();
        foreach ($rows as $row) {
            $imageId = (int) $row->image;
            if (isset($inGallery[$imageId])) {
                $likes[$imageId] = (int) $row->likes;
            }
        }

        $liked = array();
        if ('' !== $liker) {
            $values = $wpdb->get_col($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s AND meta_value LIKE %s",
                $galleryId,
                self::META,
                '%:' . $wpdb->esc_like($liker)
            ));
            foreach ($values as $value) {
                $imageId = (int) strtok($value, ':');
                if (isset($inGallery[$imageId])) {
                    $liked[] = $imageId;
                }
            }
        }

        return array('likes' => $likes, 'liked' => array_values(array_unique($liked)));
    }

    public static function has(int $galleryId, int $imageId, string $liker): bool
    {
        return '' !== $liker && self::metaIds($galleryId, self::value($imageId, $liker)) !== array();
    }

    public static function add(int $galleryId, int $imageId, string $liker): void
    {
        $value = self::value($imageId, $liker);
        if (self::metaIds($galleryId, $value)) {
            return;
        }

        add_post_meta($galleryId, self::META, $value);

        // two requests at once (a double click) may both have added it: one stays
        $ids = self::metaIds($galleryId, $value);
        foreach (array_slice($ids, 1) as $metaId) {
            delete_metadata_by_mid('post', $metaId);
        }
    }

    public static function remove(int $galleryId, int $imageId, string $liker): void
    {
        delete_post_meta($galleryId, self::META, self::value($imageId, $liker));
    }

    /**
     * A deleted user's likes go too (WordPress keeps their comments, with the name).
     *
     * @param int $userId
     */
    public static function deleteOfUser($userId): void
    {
        global $wpdb;

        self::deleteValues($wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
            self::META,
            '%:' . $wpdb->esc_like('u' . (int) $userId)
        )));
    }

    /**
     * An image deleted from the media library: its likes in every gallery.
     *
     * @param int $imageId
     */
    public static function deleteOfImage($imageId): void
    {
        global $wpdb;

        self::deleteValues($wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
            self::META,
            $wpdb->esc_like((int) $imageId . ':') . '%'
        )));
    }

    /**
     * @param string[] $values
     */
    private static function deleteValues(array $values): void
    {
        foreach ($values as $value) {
            // every gallery with that row; also clears the meta cache of each
            delete_metadata('post', 0, self::META, $value, true);
        }
    }

    private static function value(int $imageId, string $liker): string
    {
        return $imageId . ':' . $liker;
    }

    /**
     * @return int[] the meta ids of that like, oldest first
     */
    private static function metaIds(int $galleryId, string $value): array
    {
        global $wpdb;

        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s AND meta_value = %s ORDER BY meta_id",
            $galleryId,
            self::META,
            $value
        )));
    }
}
