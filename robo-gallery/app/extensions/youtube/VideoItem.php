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

use RoboGallery\app\extensions\restapi\models\GridItem;

defined('WPINC') || exit;

/**
 * A video of a YouTube gallery in the forms its readers need: the Fusion Grid
 * script (forGrid(): the shape of a /wp/v2/media item with a video link, so the
 * grid plays it in its lightbox), the settings panel (forEditor()) and the
 * static gallery (forStatic()).
 */
class VideoItem
{
    /*
     * The grid keys its items by number: a video gets 10^12 + crc32 of its id, far
     * above the ids of posts and attachments (the grid's cache holds both), the same on
     * every load (deep links, the way Back returns to a card).
     */
    const GRID_ID_BASE = 1000000000000;

    public static function gridId(string $videoId): int
    {
        return self::GRID_ID_BASE + (int) sprintf('%u', crc32($videoId));
    }

    public static function watchUrl(string $videoId): string
    {
        return 'https://www.youtube.com/watch?v=' . $videoId;
    }

    /**
     * @param array $item    of YoutubeSource::items()
     * @param array $options values of YoutubeSource::options()
     */
    public static function forGrid(array $item, array $options): array
    {
        $video   = $item['video'];
        $changes = $item['changes'];
        $title   = self::title($item);
        $text    = self::description($item, (int) ($options['youtubeTextLength'] ?? 20));
        $action  = isset($changes['action']) ? $changes['action'] : 'lightbox';

        $sizes = isset($changes['image']) ? GridItem::attachmentSizes((int) $changes['image']) : null;
        if (!$sizes) {
            $sizes = array();
            foreach ($video['thumbs'] as $size => $thumb) {
                $sizes[$size] = GridItem::size($thumb['url'], (int) $thumb['width'], (int) $thumb['height']);
            }
        }

        $link = '';
        if ('youtube' === $action) {
            $link = self::watchUrl($video['id']);
        } elseif ('link' === $action) {
            $link = isset($changes['link']) ? (string) $changes['link'] : '';
        }

        return array(
            'id'            => self::gridId($video['id']),
            'robofields'    => array(
                'title'       => $title,
                'description' => $text,
                'caption'     => $text,
                'alt'         => $title,
            ),
            'media_details' => GridItem::mediaDetails($sizes),
            // the per-image fields of the grid: a video link plays in the lightbox, a link opens
            'meta'          => array(
                'rsg_gallery_tags'       => '',
                'rsg_gallery_link'       => $link,
                'rsg_gallery_type_link'  => isset($changes['target']) && 'blank' === $changes['target'] ? 1 : 0,
                'rsg_gallery_video_link' => 'lightbox' === $action ? self::watchUrl($video['id']) : '',
                'rsg_gallery_col'        => 0,
            ),
        );
    }

    /**
     * A video as the settings panel lists it: YouTube's texts and the changes apart.
     *
     * @param array $item of YoutubeSource::items()
     */
    public static function forEditor(array $item): array
    {
        $video   = $item['video'];
        $changes = $item['changes'];

        $youtubeThumb = isset($video['thumbs']['medium']['url']) ? $video['thumbs']['medium']['url'] : '';
        $thumb        = $youtubeThumb;
        if (isset($changes['image'])) {
            $custom = wp_get_attachment_image_src((int) $changes['image'], 'thumbnail');
            if ($custom) {
                $thumb = (string) $custom[0];
            }
        }

        return array(
            'id'           => $video['id'],
            'title'        => $video['title'],
            'description'  => $video['description'],
            'published'    => $video['published'],
            'source'       => isset($video['source']) ? (int) $video['source'] : 0,
            // the cover the gallery shows (the chosen one), and YouTube's for going back to it
            'thumb'        => $thumb,
            'youtubeThumb' => $youtubeThumb,
            'url'          => self::watchUrl($video['id']),
            'changes'      => (object) $changes,
            'hidden'       => (bool) $item['hidden'],
            'shown'        => (bool) $item['shown'],
        );
    }

    /**
     * A video for StaticGallery::render(), in the form of the old YouTube source's
     * items: its thumbnail links to the video (or to the video's own link).
     *
     * @param array $item of YoutubeSource::items()
     */
    public static function forStatic(array $item): array
    {
        $video   = $item['video'];
        $changes = $item['changes'];

        $thumb = $video['thumbs']['large'];
        if (isset($changes['image'])) {
            $custom = wp_get_attachment_image_src((int) $changes['image'], 'thumbnail');
            if ($custom) {
                $thumb = array('url' => (string) $custom[0], 'width' => (int) $custom[1], 'height' => (int) $custom[2]);
            }
        }

        $data             = new \stdClass();
        $data->post_title = self::title($item);

        return array(
            'galleryType' => 'youtube',
            'thumb'       => $thumb['url'],
            'sizeW'       => (int) $thumb['width'],
            'sizeH'       => (int) $thumb['height'],
            'videolink'   => isset($changes['action'], $changes['link']) && 'link' === $changes['action'] ? $changes['link'] : self::watchUrl($video['id']),
            'data'        => $data,
        );
    }

    private static function title(array $item): string
    {
        return isset($item['changes']['title']) ? $item['changes']['title'] : $item['video']['title'];
    }

    private static function description(array $item, int $words): string
    {
        if ($words < 1) {
            return '';
        }
        $text = isset($item['changes']['description']) ? $item['changes']['description'] : $item['video']['description'];
        return trim(wp_trim_words($text, $words, '…'));
    }
}
