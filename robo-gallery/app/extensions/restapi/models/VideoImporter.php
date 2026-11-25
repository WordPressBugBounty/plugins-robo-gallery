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

namespace RoboGallery\app\extensions\restapi\models;

use RoboGallery\app\extensions\media\MediaFields;

defined('WPINC') || exit;

/**
 * Adds a video to the media library as a gallery image: its cover is the image,
 * its link goes into the image's video link (rsg_gallery_video_link), so the
 * gallery opens the video in its lightbox.
 *
 * The video is checked with oEmbed of the core (WP_oEmbed, its own list of
 * providers, no discovery - so no request goes to an address of the user's
 * choice): a removed or private video gives no answer and is refused. The cover
 * is downloaded with the checks of ImageImporter.
 *
 * Only the providers the galleries play: YouTube and Vimeo everywhere, TikTok and
 * Spotify in robogrid (react_parts/grid player.js).
 */
class VideoImporter
{
    const VIDEO_META = ROBO_GALLERY_PREFIX . 'gallery_video_link';

    // provider => link patterns, as the core oEmbed list has them
    const PROVIDERS = array(
        'youtube' => array('#^https?://((m|www)\.)?youtube\.com/(watch|shorts/|live/)#i', '#^https?://youtu\.be/#i'),
        'vimeo'   => array('#^https?://(.+\.)?vimeo\.com/#i'),
        'tiktok'  => array('#^https?://(www\.)?tiktok\.com/#i'),
        'spotify' => array('#^https?://open\.spotify\.com/#i'),
    );

    // the providers the classic galleries open (magnific: youtube, vimeo)
    const EVERY_GALLERY = array('youtube', 'vimeo');

    const NAMES = array(
        'youtube' => 'YouTube',
        'vimeo'   => 'Vimeo',
        'tiktok'  => 'TikTok',
        'spotify' => 'Spotify',
    );

    // an oEmbed answer is kept this long: the check of the image manager and the adding that follows ask once
    const OEMBED_CACHE_SECONDS = 600;

    /**
     * What adding the video would do, without adding it: the first step of the image
     * manager's dialog.
     *
     * @return array|\WP_Error { url, provider, title, thumbnail, hasCover, existingId }
     *   existingId: the image of the library with this video already, 0 for none
     */
    public static function preview(string $url, int $galleryId)
    {
        $checked = self::check($url, $galleryId);
        if (is_wp_error($checked)) {
            return $checked;
        }
        list($url, $provider) = $checked;

        $existing = self::findExisting($url);
        if ($existing) {
            $preview = wp_get_attachment_image_src($existing, 'medium');
            return array(
                'url'        => $url,
                'provider'   => self::NAMES[$provider],
                'title'      => (string) get_post_field('post_title', $existing, 'raw'),
                'thumbnail'  => $preview ? $preview[0] : '',
                'hasCover'   => true,
                'existingId' => $existing,
            );
        }

        $data = self::oembed($url);
        if (is_wp_error($data)) {
            return $data;
        }
        $thumbnail = !empty($data->thumbnail_url) && is_string($data->thumbnail_url) ? esc_url_raw($data->thumbnail_url, array('http', 'https')) : '';

        return array(
            'url'        => $url,
            'provider'   => self::NAMES[$provider],
            'title'      => isset($data->title) && is_string($data->title) ? sanitize_text_field($data->title) : '',
            'thumbnail'  => $thumbnail,
            'hasCover'   => '' !== $thumbnail,
            'existingId' => 0,
        );
    }

    /**
     * @return array|\WP_Error { id, reused, provider } - reused: the video was in the library already
     */
    public static function import(string $url, int $galleryId)
    {
        $checked = self::check($url, $galleryId);
        if (is_wp_error($checked)) {
            return $checked;
        }
        list($url, $provider) = $checked;

        // the same video added before: its image is used again, not a second copy of the cover
        $existing = self::findExisting($url);
        if ($existing) {
            return array('id' => $existing, 'reused' => true, 'provider' => self::NAMES[$provider]);
        }

        $data = self::oembed($url);
        if (is_wp_error($data)) {
            return $data;
        }
        // the video is there, its cover is not: TikTok's oEmbed gives none (only a player).
        // The image manager then lets the user choose one; url: the link to store with it.
        if (empty($data->thumbnail_url) || !is_string($data->thumbnail_url)) {
            return new \WP_Error(
                'robogallery_video_no_cover',
                /* translators: %s: video service name */
                sprintf(__('The video is there, but %s gives no cover for it: choose an image for it.', 'robo-gallery'), self::NAMES[$provider]),
                array('status' => 422, 'url' => MediaFields::sanitizeUrl($url), 'provider' => self::NAMES[$provider])
            );
        }

        $title = isset($data->title) && is_string($data->title) ? sanitize_text_field($data->title) : '';
        $name  = sanitize_title('' !== $title ? $title : $provider . '-video');

        $id = new \WP_Error('robogallery_video_cover', '');
        // YouTube has a bigger cover than oEmbed gives (480x360, black bars); not every video has it
        $youtubeId = 'youtube' === $provider ? self::youtubeId($url) : '';
        if ('' !== $youtubeId) {
            $id = ImageImporter::importImage('https://i.ytimg.com/vi/' . $youtubeId . '/maxresdefault.jpg', $galleryId, $title, $name);
        }
        if (is_wp_error($id)) {
            $id = ImageImporter::importImage($data->thumbnail_url, $galleryId, $title, $name);
        }
        if (is_wp_error($id)) {
            return $id;
        }

        update_post_meta($id, self::VIDEO_META, MediaFields::sanitizeUrl($url));

        return array('id' => (int) $id, 'reused' => false, 'provider' => self::NAMES[$provider]);
    }

    /**
     * The link as it is stored, and its provider - or why it cannot be added to this gallery.
     *
     * @return array|\WP_Error [ url, provider ]
     */
    private static function check(string $url, int $galleryId)
    {
        $url = esc_url_raw(trim($url), array('http', 'https'));
        if ('' === $url) {
            return self::error('robogallery_video_bad_url', __('This is not a valid http(s) link.', 'robo-gallery'));
        }

        $provider = self::provider($url);
        if ('' === $provider) {
            return self::error('robogallery_video_unsupported', __('Use a YouTube, Vimeo, TikTok or Spotify link of one video.', 'robo-gallery'));
        }
        if (!in_array($provider, self::EVERY_GALLERY, true) && 'robogrid' !== \rbsGalleryUtils::getTypeGallery($galleryId)) {
            /* translators: %s: video service name */
            return self::error('robogallery_video_not_here', sprintf(__('%s videos play in Fusion Grid galleries only; this gallery opens YouTube and Vimeo.', 'robo-gallery'), self::NAMES[$provider]));
        }

        return array(self::cleanUrl($url, $provider), $provider);
    }

    /**
     * The oEmbed answer for a video (core providers, no discovery), kept for a while;
     * a missing or private video is not kept: it may be made public meanwhile.
     *
     * @return object|\WP_Error
     */
    private static function oembed(string $url)
    {
        $cacheKey = ROBO_GALLERY_PREFIX . 'oembed_' . md5($url);
        $cached   = get_transient($cacheKey);
        if (is_array($cached)) {
            return (object) $cached;
        }

        $data = _wp_oembed_get_object()->get_data($url, array('discover' => false));
        if (!is_object($data)) {
            return self::error('robogallery_video_unavailable', __('The video was not found or is not public: removed, private and age-restricted videos cannot be added.', 'robo-gallery'), 404);
        }

        set_transient($cacheKey, get_object_vars($data), self::OEMBED_CACHE_SECONDS);

        return $data;
    }

    private static function provider(string $url): string
    {
        foreach (self::PROVIDERS as $provider => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $url)) {
                    return $provider;
                }
            }
        }

        return '';
    }

    /**
     * A TikTok link without what the app adds when sharing (?is_from_webapp=1&sender_device=pc):
     * the video is the path, and the same video then gives the same stored link.
     */
    private static function cleanUrl(string $url, string $provider): string
    {
        if ('tiktok' !== $provider) {
            return $url;
        }
        $parts = wp_parse_url($url);

        return isset($parts['scheme'], $parts['host'], $parts['path']) ? $parts['scheme'] . '://' . $parts['host'] . $parts['path'] : $url;
    }

    /**
     * The video id of a YouTube link, as the frontend finds it (v=, youtu.be/) and /shorts/, /live/.
     */
    private static function youtubeId(string $url): string
    {
        return preg_match('#(?:[?&]v=|youtu\.be/|/shorts/|/live/)([A-Za-z0-9_-]{6,})#', $url, $m) ? $m[1] : '';
    }

    /**
     * An image of the library with this video link already.
     */
    private static function findExisting(string $url): int
    {
        $ids = get_posts(array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'meta_key'       => self::VIDEO_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one lookup when a video is added
            'meta_value'     => MediaFields::sanitizeUrl($url), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- one lookup when a video is added
            'fields'         => 'ids',
            'numberposts'    => 1,
        ));

        return $ids ? (int) $ids[0] : 0;
    }

    private static function error(string $code, string $message, int $status = 400): \WP_Error
    {
        return new \WP_Error($code, $message, array('status' => $status));
    }
}
