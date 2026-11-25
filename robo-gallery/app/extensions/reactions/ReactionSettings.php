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

use RoboGallery\app\GalleryOptions;

defined('WPINC') || exit;

/**
 * The Likes & Comments settings of a gallery (GalleryOptions, group "reactions"): likes
 * (who may like), likesIn (where the like buttons are: the lightbox, the polaroid panel),
 * comments (who may comment) and commentsIn (where the comment buttons are; the comments
 * open in a dialog from either). The script takes the settings of the gallery
 * embedded on the page for its sub-galleries too, so the REST routes read them from
 * that root gallery. Only the robogrid source has images to like: the posts and
 * YouTube galleries never have them.
 */
class ReactionSettings
{
    const GROUP = 'reactions';

    const LIKES_OFF   = 'off';
    const LIKES_USERS = 'users';
    const LIKES_ALL   = 'all';

    const PLACES = array('lightbox', 'polaroid');

    const COMMENTS_OFF   = 'off';
    const COMMENTS_USERS = 'users';

    public static function hasReactions(int $galleryId): bool
    {
        return GalleryOptions::SET_ROBOGRID === GalleryOptions::setOf($galleryId);
    }

    /**
     * @return string LIKES_OFF / LIKES_USERS / LIKES_ALL; off without a place for the buttons too
     */
    public static function likesMode(int $galleryId): string
    {
        if (!self::hasReactions($galleryId) || !self::likesIn($galleryId)) {
            return self::LIKES_OFF;
        }

        $value = self::value($galleryId, 'likes');
        return in_array($value, array(self::LIKES_USERS, self::LIKES_ALL), true) ? $value : self::LIKES_OFF;
    }

    /**
     * @return string[] where the like buttons are: PLACES
     */
    public static function likesIn(int $galleryId): array
    {
        return self::places($galleryId, 'likesIn');
    }

    /**
     * @return string[] where the comment buttons are: PLACES
     */
    public static function commentsIn(int $galleryId): array
    {
        return self::places($galleryId, 'commentsIn');
    }

    /**
     * @return string COMMENTS_OFF / COMMENTS_USERS; off without a place for the buttons too
     */
    public static function commentsMode(int $galleryId): string
    {
        return self::hasReactions($galleryId)
            && self::commentsIn($galleryId)
            && self::COMMENTS_USERS === self::value($galleryId, 'comments')
            ? self::COMMENTS_USERS
            : self::COMMENTS_OFF;
    }

    public static function commentsOn(int $galleryId): bool
    {
        return self::COMMENTS_OFF !== self::commentsMode($galleryId);
    }

    /**
     * @return string[] the PLACES of a multiselect option, in their order
     */
    private static function places(int $galleryId, string $name): array
    {
        $value = self::value($galleryId, $name);
        return array_values(array_intersect(self::PLACES, is_array($value) ? $value : array()));
    }

    /**
     * @return mixed the stored value, else the default
     */
    private static function value(int $galleryId, string $name)
    {
        $options = GalleryOptions::getEffective($galleryId);
        if (array_key_exists($name, $options)) {
            return $options[$name];
        }

        $config = GalleryOptions::getOptionConfig();
        return $config[$name]['default'];
    }
}
