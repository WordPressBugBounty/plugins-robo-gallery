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

use RoboGallery\app\GalleryOptions;

defined('WPINC') || exit;

/**
 * The videos of a YouTube gallery (type youtubegrid). The only reader and writer
 * of three meta of the gallery:
 *  - rsg_youtubeSources: where the videos come from, a list of { type, value }:
 *    channel (id, @handle, user name or link), playlist (id), videos (ids, one per
 *    line); the videos of all sources, each once. The panel sends the pasted text,
 *    the types are found here (sourcesFromText());
 *  - rsg_youtubeVideos: video id => what is changed for it in this gallery:
 *    image (an attachment instead of YouTube's thumbnail), title, description,
 *    hidden, action (lightbox: plays in the lightbox, youtube: opens it on
 *    youtube.com, link: opens link), target (self / blank) - kept when the video
 *    leaves the sources;
 *  - rsg_youtubeOrder: video ids in the order set in the panel (order "custom").
 *
 * The old YouTube type (youtube, includes/frontend/.../source/type/youtube.php)
 * has meta of its own and is not read here.
 */
class YoutubeSource
{
    const TYPE = 'youtubegrid';

    const SOURCES_META = ROBO_GALLERY_PREFIX . 'youtubeSources';
    const VIDEOS_META  = ROBO_GALLERY_PREFIX . 'youtubeVideos';
    const ORDER_META   = ROBO_GALLERY_PREFIX . 'youtubeOrder';

    const SOURCE_TYPES = array('channel', 'playlist', 'videos');
    const ACTIONS      = array('lightbox', 'youtube', 'link');
    const TARGETS      = array('self', 'blank');

    // the videos loaded over the number the gallery shows (items())
    const SPARE = 10;

    const MAX_SOURCES      = 20;
    const MAX_VALUE_LENGTH = 5000;
    const MAX_CHANGED      = 1000;
    // an own title / description is as long as YouTube's may be (YoutubeClient)
    const MAX_TITLE        = YoutubeClient::MAX_TITLE;
    const MAX_DESCRIPTION  = YoutubeClient::MAX_DESCRIPTION;
    const MAX_LINK         = 2000;

    /*
    ====================================================================
    ======Stored data
    ====================================================================
     */

    /**
     * @return array[] type, value
     */
    public static function getSources(int $galleryId): array
    {
        return self::sanitizeSources(get_post_meta($galleryId, self::SOURCES_META, true));
    }

    public static function saveSources(int $galleryId, $sources): void
    {
        update_post_meta($galleryId, self::SOURCES_META, wp_slash(self::sanitizeSources($sources)));
    }

    /**
     * @param mixed $sources
     * @return array[] the valid sources, at most MAX_SOURCES
     */
    public static function sanitizeSources($sources): array
    {
        $result = array();
        foreach (is_array($sources) ? $sources : array() as $source) {
            if (!is_array($source) || !isset($source['type'], $source['value']) || !is_string($source['value'])) {
                continue;
            }
            $type  = (string) $source['type'];
            $value = trim(sanitize_textarea_field(substr($source['value'], 0, self::MAX_VALUE_LENGTH)));
            if ('' !== $value && in_array($type, self::SOURCE_TYPES, true)) {
                $result[] = array('type' => $type, 'value' => $value);
            }
        }
        return array_slice($result, 0, self::MAX_SOURCES);
    }

    /**
     * The sources of pasted links and ids (one per line, or separated by spaces / commas),
     * each of its type (YoutubeClient::detect()); videos next to each other are one source.
     *
     * @return array sources: [type, value], unrecognized: what is none of them
     */
    public static function sourcesFromText(string $text): array
    {
        $sources      = array();
        $unrecognized = array();

        foreach (preg_split('/[\s,;]+/', substr($text, 0, self::MAX_VALUE_LENGTH * self::MAX_SOURCES)) as $token) {
            if ('' === $token) {
                continue;
            }
            $source = YoutubeClient::detect($token);
            if (!$source) {
                $unrecognized[] = sanitize_text_field($token);
                continue;
            }

            $last = count($sources) - 1;
            if ('videos' === $source['type'] && $last >= 0 && 'videos' === $sources[$last]['type']) {
                $sources[$last]['value'] .= "\n" . $source['value'];
                continue;
            }
            // the same channel / playlist pasted twice
            if (!in_array($source, $sources, true)) {
                $sources[] = $source;
            }
        }

        return array('sources' => self::sanitizeSources($sources), 'unrecognized' => array_slice($unrecognized, 0, 50));
    }

    /**
     * @return array[] video id => the changes of the video (sanitizeChanges())
     */
    public static function getChanges(int $galleryId): array
    {
        $stored = get_post_meta($galleryId, self::VIDEOS_META, true);
        $result = array();
        foreach (is_array($stored) ? $stored : array() as $videoId => $changes) {
            if (is_string($videoId) && preg_match(YoutubeClient::VIDEO_ID, $videoId) && is_array($changes)) {
                $changes = self::sanitizeChanges($changes);
                if ($changes) {
                    $result[$videoId] = $changes;
                }
            }
        }
        return $result;
    }

    /**
     * Sets what is changed for a video; no changes remove its entry.
     */
    public static function setChanges(int $galleryId, string $videoId, array $changes): void
    {
        $all     = self::getChanges($galleryId);
        $changes = self::sanitizeChanges($changes);
        if ($changes) {
            $all[$videoId] = $changes;
        } else {
            unset($all[$videoId]);
        }

        update_post_meta($galleryId, self::VIDEOS_META, wp_slash(array_slice($all, 0, self::MAX_CHANGED, true)));
    }

    /**
     * Only what differs from the video as YouTube gives it: no empty texts, the
     * default action and target are left out.
     */
    public static function sanitizeChanges(array $changes): array
    {
        $result = array();

        if (!empty($changes['image']) && (int) $changes['image'] > 0) {
            $result['image'] = (int) $changes['image'];
        }
        if (isset($changes['title']) && is_string($changes['title'])) {
            $title = sanitize_text_field(mb_substr($changes['title'], 0, self::MAX_TITLE));
            if ('' !== $title) {
                $result['title'] = $title;
            }
        }
        if (isset($changes['description']) && is_string($changes['description'])) {
            $description = trim(sanitize_textarea_field(mb_substr($changes['description'], 0, self::MAX_DESCRIPTION)));
            if ('' !== $description) {
                $result['description'] = $description;
            }
        }
        if (!empty($changes['hidden'])) {
            $result['hidden'] = true;
        }

        $action = isset($changes['action']) && in_array($changes['action'], self::ACTIONS, true) ? $changes['action'] : 'lightbox';
        // a page to open: web addresses only (no mailto:, ftp: and the like), not too long
        $link = isset($changes['link']) && is_string($changes['link']) && strlen($changes['link']) <= self::MAX_LINK
            ? esc_url_raw(trim($changes['link']), array('http', 'https')) : '';
        // a link action without a link plays in the lightbox
        if ('link' === $action && '' === $link) {
            $action = 'lightbox';
        }
        if ('lightbox' !== $action) {
            $result['action'] = $action;
            if ('link' === $action) {
                $result['link'] = $link;
            }
            if (isset($changes['target']) && 'blank' === $changes['target']) {
                $result['target'] = 'blank';
            }
        }

        return $result;
    }

    /**
     * @return string[] video ids in the order set in the panel
     */
    public static function getOrder(int $galleryId): array
    {
        return self::sanitizeOrder(get_post_meta($galleryId, self::ORDER_META, true));
    }

    public static function saveOrder(int $galleryId, $order): void
    {
        update_post_meta($galleryId, self::ORDER_META, self::sanitizeOrder($order));
    }

    private static function sanitizeOrder($order): array
    {
        $ids = array();
        foreach (is_array($order) ? $order : array() as $id) {
            if (is_string($id) && preg_match(YoutubeClient::VIDEO_ID, $id)) {
                $ids[$id] = $id;
            }
        }
        return array_slice(array_values($ids), 0, self::MAX_CHANGED);
    }

    /**
     * The values of the youtube options of the gallery (stored, else the defaults).
     *
     * @return array option_id => value
     */
    public static function options(int $galleryId): array
    {
        $values = array();
        foreach (GalleryOptions::getWithValues($galleryId, GalleryOptions::SET_YOUTUBE) as $optionId => $option) {
            if ('youtube' === $option['group']) {
                $values[$optionId] = $option['value'];
            }
        }
        return $values;
    }

    /*
    ====================================================================
    ======Videos
    ====================================================================
     */

    /**
     * The videos of all sources, each once (the first source that has it), and why
     * a source has none.
     *
     * @param int    $needed the videos the gallery needs (sourceLimit()); 0: all there are
     * @param string $order  youtubeOrder
     * @return array videos: [video + source (index)], errors: source index => message,
     *               counts: source index => its loaded videos (before duplicates are left out),
     *               more: source index => true when it has more than were loaded
     */
    public static function fetchAll(int $galleryId, bool $refresh = false, int $needed = 0, string $order = 'source'): array
    {
        $videos = array();
        $errors = array();
        $counts = array();
        $more   = array();

        foreach (self::getSources($galleryId) as $index => $source) {
            $result         = YoutubeClient::fetch($source, $refresh, self::sourceLimit($source, $order, $needed));
            $counts[$index] = count($result['videos']);
            if ($result['more']) {
                $more[$index] = true;
            }
            if ('' !== $result['error']) {
                $errors[$index] = $result['error'];
            }
            foreach ($result['videos'] as $video) {
                if (is_array($video) && isset($video['id']) && !isset($videos[$video['id']])) {
                    $videos[$video['id']] = $video + array('source' => $index);
                }
            }
        }

        return array('videos' => array_values($videos), 'errors' => $errors, 'counts' => $counts, 'more' => $more);
    }

    /**
     * How many videos of a source to load. Only the first ones when they are the ones
     * the gallery shows: in the order of the sources, or the newest of a channel (its
     * uploads come newest first, so the newest of all sources are among them). Sorting
     * by date or title, and the custom order (a video moved up may be far down in its
     * source), need all of them.
     */
    private static function sourceLimit(array $source, string $order, int $needed): int
    {
        $first = 'source' === $order || ('newest' === $order && 'channel' === $source['type']);
        return $needed > 0 && $first ? $needed : YoutubeClient::MAX_PER_SOURCE;
    }

    /**
     * The videos in the gallery's order with their changes. shown: the gallery shows the
     * video - not hidden and within the number of videos of the settings.
     *
     * Loaded are the number of videos of the settings, as many more as are hidden (they
     * may be among the first) and SPARE more (a video in two sources counts once), or
     * all of them with $full (the panel's "Show all videos").
     *
     * @param bool $all  also the videos the gallery does not show (the panel lists them)
     * @param bool $full all videos of the sources, not only the ones the gallery needs
     * @return array items: [video, changes, hidden, shown], errors, counts and more: of fetchAll()
     */
    public static function items(int $galleryId, bool $all = false, bool $refresh = false, bool $full = false): array
    {
        $options = self::options($galleryId);
        $changes = self::getChanges($galleryId);
        $order   = isset($options['youtubeOrder']) ? (string) $options['youtubeOrder'] : 'source';
        $count   = max(1, min(YoutubeOptions::MAX_VIDEOS, (int) ($options['youtubeCount'] ?? 24)));

        $hiddenCount = count(array_filter($changes, function ($videoChanges) {
            return !empty($videoChanges['hidden']);
        }));
        $fetched = self::fetchAll($galleryId, $refresh, $full ? 0 : $count + $hiddenCount + self::SPARE, $order);

        $videos = self::order($fetched['videos'], $order, self::getOrder($galleryId));

        $items = array();
        $shown = 0;
        foreach ($videos as $video) {
            $videoChanges = isset($changes[$video['id']]) ? $changes[$video['id']] : array();
            $hidden       = !empty($videoChanges['hidden']);
            $isShown      = !$hidden && $shown < $count;
            if ($isShown) {
                ++$shown;
            }
            if ($isShown || $all) {
                $items[] = array('video' => $video, 'changes' => $videoChanges, 'hidden' => $hidden, 'shown' => $isShown);
            }
        }

        return array('items' => $items, 'errors' => $fetched['errors'], 'counts' => $fetched['counts'], 'more' => $fetched['more']);
    }

    /**
     * @param array[]  $videos  in the order of the sources
     * @param string   $order   youtubeOrder
     * @param string[] $custom  ids of the panel's order
     */
    public static function order(array $videos, string $order, array $custom): array
    {
        switch ($order) {
            case 'newest':
            case 'oldest':
                $sign = 'newest' === $order ? -1 : 1;
                // stable: videos without a date keep their place among themselves, at the end
                usort($videos, function ($a, $b) use ($sign) {
                    if ('' === $a['published'] || '' === $b['published']) {
                        return ('' === $a['published']) - ('' === $b['published']);
                    }
                    return $sign * strcmp($a['published'], $b['published']);
                });
                return $videos;

            case 'title':
                usort($videos, function ($a, $b) {
                    return strcasecmp($a['title'], $b['title']);
                });
                return $videos;

            case 'custom':
                $position = array_flip($custom);
                $first    = array();
                $rest     = array();
                foreach ($videos as $video) {
                    if (isset($position[$video['id']])) {
                        $first[$position[$video['id']]] = $video;
                    } else {
                        $rest[] = $video;
                    }
                }
                ksort($first);
                return array_merge(array_values($first), $rest);
        }
        return $videos;
    }
}
