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

use RoboGallery\app\extensions\restapi\models\YouTubePlaylist;

defined('WPINC') || exit;

/**
 * The videos of one source of a YouTube gallery: a channel (its id, @handle or
 * link), a playlist (id or link) or a list of videos (ids or links).
 *
 * With the plugin's API key (Settings -> YouTube API, rsg_youtubeApiKey) through
 * the Data API: up to MAX_PER_SOURCE videos, handles and user names. Without it
 * what YouTube gives without a key: the public feed of a channel id or playlist
 * (its 15 newest videos) and oEmbed for single videos (title and thumbnail, no
 * description or date); a handle cannot be resolved.
 *
 * Only the videos asked for are loaded (fetch() $limit; an API page holds 50):
 * a channel of thousands of videos costs one request, not four.
 *
 * The result of a source (its videos, whether it has more, or why there are
 * none) is kept in a transient, whatever the limit (a bigger one loads again): the hours of Settings -> YouTube API (rsg_youtubeCacheTime), a
 * failure only minutes, so a broken key does not cost a request per page view
 * and a fixed one is used soon. Only checked data is cached: private and
 * deleted videos of a playlist are left out.
 */
class YoutubeClient
{
    const API = 'https://www.googleapis.com/youtube/v3/';

    const MAX_PER_SOURCE = 200;

    // oEmbed is one request per video
    const MAX_WITHOUT_KEY = 50;

    const TIMEOUT = 10;

    const CACHE_ERROR = 5 * MINUTE_IN_SECONDS;

    const VIDEO_ID = '/^[A-Za-z0-9_-]{11}$/';

    /* the longest title and description kept (YouTube allows 100 / 5000 characters): the
       cache, the REST answers and the panel stay small; a card shows a few words anyway */
    const MAX_TITLE       = 200;
    const MAX_DESCRIPTION = 2000;

    public static function apiKey(): string
    {
        return sanitize_text_field((string) get_option(ROBO_GALLERY_PREFIX . 'youtubeApiKey', ''));
    }

    public static function hasKey(): bool
    {
        return '' !== self::apiKey();
    }

    /**
     * @param array $source type (YoutubeSource::SOURCE_TYPES), value
     * @param bool  $refresh ask YouTube again instead of the cache
     * @param int   $limit   the videos needed, up to MAX_PER_SOURCE
     * @return array videos (see video()), at most $limit; more: a bigger limit would give more;
     *               error: '' or why there are none
     */
    public static function fetch(array $source, bool $refresh = false, int $limit = self::MAX_PER_SOURCE): array
    {
        $limit    = max(1, min(self::MAX_PER_SOURCE, $limit));
        $key      = self::apiKey();
        // the number changes with the form of the cached videos: older entries are not read
        $cacheKey = ROBO_GALLERY_PREFIX . 'ytg2_' . md5($source['type'] . '|' . $source['value'] . '|' . $key);

        if (!$refresh) {
            $cached = get_transient($cacheKey);
            // enough when it has all of them or as many as needed; an entry without "more" was loaded in full
            if (is_array($cached) && isset($cached['videos'], $cached['error']) && is_array($cached['videos'])
                && (empty($cached['more']) || count($cached['videos']) >= $limit)) {
                return self::limited($cached, $limit);
            }
        }

        try {
            $result = self::load($source, $key, $limit) + array('error' => '');
        } catch (YoutubeError $error) {
            $result = array('videos' => array(), 'more' => false, 'error' => $error->getMessage());
        }

        set_transient($cacheKey, $result, '' === $result['error'] ? self::cacheTime() : self::CACHE_ERROR);
        return self::limited($result, $limit);
    }

    /* the cache keeps all that was loaded (a whole API page), maybe more than is needed now */
    private static function limited(array $result, int $limit): array
    {
        return array(
            'videos' => array_slice($result['videos'], 0, $limit),
            'more'   => !empty($result['more']) || count($result['videos']) > $limit,
            'error'  => (string) $result['error'],
        );
    }

    private static function cacheTime(): int
    {
        $hours = (int) get_option(ROBO_GALLERY_PREFIX . 'youtubeCacheTime', 12);
        return ($hours > 0 ? $hours : 12) * HOUR_IN_SECONDS;
    }

    /**
     * @return array videos: $limit or more when the source has them; more: a bigger limit would give more
     * @throws YoutubeError
     */
    private static function load(array $source, string $key, int $limit): array
    {
        switch ($source['type']) {
            case 'channel':
                return self::channel((string) $source['value'], $key, $limit);
            case 'playlist':
                return self::playlist((string) $source['value'], $key, $limit);
            case 'videos':
                return self::videos(self::videoIds((string) $source['value']), $key, $limit);
        }
        throw new YoutubeError(__('Unknown source type.', 'robo-gallery'));
    }

    /*
    ====================================================================
    ======Sources
    ====================================================================
     */

    private static function channel(string $value, string $key, int $limit): array
    {
        $channel = self::channelRef($value);
        if (!$channel) {
            throw new YoutubeError(__('Not a channel: use its id (UC...), @handle or link.', 'robo-gallery'));
        }

        if ('' === $key) {
            if ('id' !== $channel['kind']) {
                throw new YoutubeError(__('A channel by its @handle or user name needs the YouTube API key. Without it, use the channel id (UC...).', 'robo-gallery'));
            }
            return array('videos' => self::feed('channel_id=' . rawurlencode($channel['value'])), 'more' => false);
        }

        // every channel's uploads are the playlist with its id starting with UU instead of UC
        if ('id' === $channel['kind']) {
            return self::playlistItems('UU' . substr($channel['value'], 2), $key, $limit);
        }

        $uploads = self::uploadsPlaylist($channel, $key);
        if ('' === $uploads) {
            throw new YoutubeError(__('The channel was not found.', 'robo-gallery'));
        }
        return self::playlistItems($uploads, $key, $limit);
    }

    private static function playlist(string $value, string $key, int $limit): array
    {
        $value = trim($value);
        $id    = preg_match('/^[A-Za-z0-9_-]{10,64}$/', $value) ? $value : YouTubePlaylist::playlistId($value);
        if ('' === $id) {
            throw new YoutubeError(__('Not a playlist: use its id (PL...) or link.', 'robo-gallery'));
        }

        return '' === $key
            ? array('videos' => self::feed('playlist_id=' . rawurlencode($id)), 'more' => false)
            : self::playlistItems($id, $key, $limit);
    }

    /**
     * @param string[] $ids
     */
    private static function videos(array $ids, string $key, int $limit): array
    {
        if (!$ids) {
            throw new YoutubeError(__('No video ids or links.', 'robo-gallery'));
        }

        // without the key never more than MAX_WITHOUT_KEY: a bigger limit gives more only below it
        $max  = '' === $key ? self::MAX_WITHOUT_KEY : self::MAX_PER_SOURCE;
        $take = min($limit, $max);
        $more = count($ids) > $take && $take < $max;
        $ids  = array_slice($ids, 0, $take);

        if ('' === $key) {
            $videos = array();
            foreach ($ids as $id) {
                $video = self::oembed($id);
                if ($video) {
                    $videos[] = $video;
                }
            }
            return array('videos' => $videos, 'more' => $more);
        }

        $videos = array();
        foreach (array_chunk($ids, 50) as $chunk) {
            $answer = self::api('videos', array('part' => 'snippet,status', 'id' => implode(',', $chunk)), $key);
            foreach (self::items($answer) as $item) {
                $video = self::apiVideo(isset($item['id']) ? $item['id'] : '', $item);
                if ($video) {
                    $videos[$video['id']] = $video;
                }
            }
        }

        // in the order of the list, not of the answer
        $ordered = array();
        foreach ($ids as $id) {
            if (isset($videos[$id])) {
                $ordered[] = $videos[$id];
            }
        }
        return array('videos' => $ordered, 'more' => $more);
    }

    /*
    ====================================================================
    ======Data API
    ====================================================================
     */

    private static function uploadsPlaylist(array $channel, string $key): string
    {
        $lookups = 'user' === $channel['kind']
            ? array('forUsername' => $channel['value'])
            : array('forHandle' => '@' . ltrim($channel['value'], '@'));

        $answer = self::api('channels', array('part' => 'contentDetails') + $lookups, $key);
        $items  = self::items($answer);

        // a bare name: a handle first, then the old user name
        if (!$items && 'handle' === $channel['kind'] && !empty($channel['bare'])) {
            $items = self::items(self::api('channels', array('part' => 'contentDetails', 'forUsername' => $channel['value']), $key));
        }

        return isset($items[0]['contentDetails']['relatedPlaylists']['uploads']) && is_string($items[0]['contentDetails']['relatedPlaylists']['uploads'])
            ? $items[0]['contentDetails']['relatedPlaylists']['uploads'] : '';
    }

    private static function playlistItems(string $playlistId, string $key, int $limit): array
    {
        $videos = array();
        $token  = '';

        do {
            $answer = self::api('playlistItems', array(
                'part'       => 'snippet,contentDetails,status',
                'maxResults' => 50,
                'playlistId' => $playlistId,
                'pageToken'  => $token,
            ), $key);

            foreach (self::items($answer) as $item) {
                $id = isset($item['contentDetails']['videoId']) ? $item['contentDetails']['videoId'] : '';
                if (isset($item['contentDetails']['videoPublishedAt'])) {
                    $item['snippet']['publishedAt'] = $item['contentDetails']['videoPublishedAt'];
                }
                $video = self::apiVideo($id, $item);
                if ($video) {
                    $videos[] = $video;
                }
            }

            $token = isset($answer['nextPageToken']) && is_string($answer['nextPageToken']) ? $answer['nextPageToken'] : '';
        } while ('' !== $token && count($videos) < $limit);

        $videos = array_slice($videos, 0, self::MAX_PER_SOURCE);
        return array('videos' => $videos, 'more' => '' !== $token && count($videos) < self::MAX_PER_SOURCE);
    }

    /**
     * A video of an API answer, null when it cannot be shown (private, deleted, no thumbnail).
     */
    private static function apiVideo($id, array $item)
    {
        if (!is_string($id) || !preg_match(self::VIDEO_ID, $id)) {
            return null;
        }
        if (isset($item['status']['privacyStatus']) && !in_array($item['status']['privacyStatus'], array('public', 'unlisted'), true)) {
            return null;
        }

        $snippet = isset($item['snippet']) && is_array($item['snippet']) ? $item['snippet'] : array();
        $thumbs  = isset($snippet['thumbnails']) && is_array($snippet['thumbnails']) ? self::apiThumbs($snippet['thumbnails']) : null;
        if (!$thumbs) {
            return null;
        }

        return self::video(
            $id,
            isset($snippet['title']) ? $snippet['title'] : '',
            isset($snippet['description']) ? $snippet['description'] : '',
            isset($snippet['publishedAt']) ? $snippet['publishedAt'] : '',
            $thumbs
        );
    }

    /**
     * medium: 16:9 320 px; large and full: the biggest (maxres 1280 px when the video has it).
     */
    private static function apiThumbs(array $thumbnails)
    {
        $found = array();
        foreach (array('medium', 'high', 'standard', 'maxres') as $name) {
            if (empty($thumbnails[$name]['url']) || !is_string($thumbnails[$name]['url'])) {
                continue;
            }
            $url = esc_url_raw($thumbnails[$name]['url'], array('https'));
            if ($url) {
                $found[$name] = array(
                    'url'    => $url,
                    'width'  => isset($thumbnails[$name]['width']) ? (int) $thumbnails[$name]['width'] : 0,
                    'height' => isset($thumbnails[$name]['height']) ? (int) $thumbnails[$name]['height'] : 0,
                );
            }
        }
        if (!$found) {
            return null;
        }

        $biggest = isset($found['maxres']) ? $found['maxres'] : (isset($found['high']) ? $found['high'] : reset($found));
        return array(
            'medium' => isset($found['medium']) ? $found['medium'] : $biggest,
            'large'  => $biggest,
            'full'   => $biggest,
        );
    }

    /**
     * @return array the decoded answer
     * @throws YoutubeError
     */
    private static function api(string $endpoint, array $params, string $key): array
    {
        $params = array_filter($params, function ($value) {
            return '' !== $value;
        });
        $url = add_query_arg(array_map('rawurlencode', $params + array('key' => $key)), self::API . $endpoint);

        $json = json_decode(self::get($url), true);
        if (is_array($json) && !empty($json['error'])) {
            $message = isset($json['error']['message']) && is_string($json['error']['message']) ? $json['error']['message'] : 'unknown';
            throw new YoutubeError(sprintf(__('YouTube API error: %s', 'robo-gallery'), wp_strip_all_tags($message)));
        }
        if (!is_array($json)) {
            throw new YoutubeError(__('YouTube gave an answer that cannot be read.', 'robo-gallery'));
        }
        return $json;
    }

    private static function items(array $answer): array
    {
        return isset($answer['items']) && is_array($answer['items']) ? array_filter($answer['items'], 'is_array') : array();
    }

    /*
    ====================================================================
    ======Without a key
    ====================================================================
     */

    /**
     * The public feed of a channel or playlist: its 15 newest videos.
     *
     * @param string $query channel_id=... or playlist_id=...
     */
    private static function feed(string $query): array
    {
        if (!function_exists('simplexml_load_string')) {
            throw new YoutubeError(__('The server cannot read the YouTube feed (no SimpleXML): set the YouTube API key.', 'robo-gallery'));
        }

        $body = self::get('https://www.youtube.com/feeds/videos.xml?' . $query);

        $previous = libxml_use_internal_errors(true);
        // no LIBXML_NOENT: entities are not expanded; LIBXML_NONET: nothing is loaded
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$xml) {
            throw new YoutubeError(__('The YouTube feed cannot be read.', 'robo-gallery'));
        }

        $videos = array();
        foreach ($xml->entry as $entry) {
            $id    = (string) $entry->children('http://www.youtube.com/xml/schemas/2015')->videoId;
            $media = $entry->children('http://search.yahoo.com/mrss/')->group;
            if (!preg_match(self::VIDEO_ID, $id)) {
                continue;
            }
            $videos[] = self::video(
                $id,
                $media ? (string) $media->title : (string) $entry->title,
                $media ? (string) $media->description : '',
                (string) $entry->published,
                self::idThumbs($id)
            );
        }
        return $videos;
    }

    /**
     * One video by oEmbed (title and thumbnail); null when it is not available.
     */
    private static function oembed(string $id)
    {
        try {
            $json = json_decode(self::get('https://www.youtube.com/oembed?format=json&url=' . rawurlencode('https://www.youtube.com/watch?v=' . $id)), true);
        } catch (YoutubeError $error) {
            return null;
        }
        if (!is_array($json) || !isset($json['title'])) {
            return null;
        }
        return self::video($id, (string) $json['title'], '', '', self::idThumbs($id));
    }

    /**
     * The thumbnails every video has: mqdefault (16:9) and hqdefault (4:3).
     */
    private static function idThumbs(string $id): array
    {
        $base = 'https://i.ytimg.com/vi/' . $id . '/';
        $high = array('url' => $base . 'hqdefault.jpg', 'width' => 480, 'height' => 360);
        return array(
            'medium' => array('url' => $base . 'mqdefault.jpg', 'width' => 320, 'height' => 180),
            'large'  => $high,
            'full'   => $high,
        );
    }

    /*
    ====================================================================
    ======Helpers
    ====================================================================
     */

    /**
     * @throws YoutubeError
     */
    private static function get(string $url): string
    {
        $response = wp_safe_remote_get($url, array('timeout' => self::TIMEOUT));
        if (is_wp_error($response)) {
            throw new YoutubeError(sprintf(__('YouTube could not be reached: %s', 'robo-gallery'), $response->get_error_message()));
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        // the Data API explains its errors in the body (read by api())
        if (200 !== $code && false === strpos($body, '"error"')) {
            throw new YoutubeError(404 === $code
                ? __('Not found on YouTube.', 'robo-gallery')
                : sprintf(__('YouTube answered with HTTP %d.', 'robo-gallery'), $code));
        }
        return $body;
    }

    /**
     * A video in the form the gallery keeps: checked texts, thumbnails with sizes.
     */
    private static function video(string $id, string $title, string $description, string $published, array $thumbs): array
    {
        $time = '' !== $published ? strtotime($published) : false;

        return array(
            'id'          => $id,
            'title'       => sanitize_text_field(mb_substr($title, 0, self::MAX_TITLE)),
            'description' => sanitize_textarea_field(mb_substr($description, 0, self::MAX_DESCRIPTION)),
            'published'   => $time ? gmdate('c', $time) : '',
            'thumbs'      => $thumbs,
        );
    }

    /**
     * What a pasted link or id is, so the user does not have to say it:
     *  - a playlist: youtube.com/playlist?list=..., an id starting with PL, UU, OL, LL, FL, RD...;
     *  - a video: youtu.be/..., watch?v=... (also with &list=: the video, not its playlist),
     *    shorts / embed / live links, an 11 character id;
     *  - a channel: @handle, an id starting with UC, /channel/, /@..., /user/ links, any other name.
     *
     * @return array|null type (channel, playlist, videos), value (the playlist / video id, the
     *                    channel as written); null when it is none of them
     */
    public static function detect(string $token)
    {
        $token = trim($token);
        if ('' === $token) {
            return null;
        }

        if (preg_match('#^https?://#i', $token)) {
            $path = rtrim((string) wp_parse_url($token, PHP_URL_PATH), '/');
            if ('/playlist' === $path) {
                $list = YouTubePlaylist::playlistId($token);
                return '' !== $list ? array('type' => 'playlist', 'value' => $list) : null;
            }
            $video = self::videoId($token);
            if ('' !== $video) {
                return array('type' => 'videos', 'value' => $video);
            }
            return self::channelRef($token) ? array('type' => 'channel', 'value' => $token) : null;
        }

        if (preg_match('/^(PL|UU|OL|LL|FL|RD|UL|PU)[A-Za-z0-9_-]{10,62}$/', $token)) {
            return array('type' => 'playlist', 'value' => $token);
        }
        if (preg_match(self::VIDEO_ID, $token)) {
            return array('type' => 'videos', 'value' => $token);
        }
        return self::channelRef($token) ? array('type' => 'channel', 'value' => $token) : null;
    }

    /**
     * A channel reference: kind id (UC...), handle (@name) or user (an old user name).
     * A bare name is taken as a handle (bare: a user name is tried after it).
     *
     * @return array|null kind, value, bare
     */
    public static function channelRef(string $value)
    {
        $value = trim($value);

        if (preg_match('#^https?://#i', $value)) {
            $path = trim((string) wp_parse_url($value, PHP_URL_PATH), '/');
            $host = (string) wp_parse_url($value, PHP_URL_HOST);
            if (!preg_match('/(^|\.)youtube\.com$/i', $host)) {
                return null;
            }
            if (preg_match('#^channel/(UC[A-Za-z0-9_-]{22})#', $path, $match)) {
                return array('kind' => 'id', 'value' => $match[1]);
            }
            if (preg_match('#^(@[A-Za-z0-9._-]{3,100})#', $path, $match)) {
                return array('kind' => 'handle', 'value' => $match[1]);
            }
            if (preg_match('#^user/([A-Za-z0-9._-]{1,100})#', $path, $match)) {
                return array('kind' => 'user', 'value' => $match[1]);
            }
            return null;
        }

        if (preg_match('/^UC[A-Za-z0-9_-]{22}$/', $value)) {
            return array('kind' => 'id', 'value' => $value);
        }
        if (preg_match('/^@[A-Za-z0-9._-]{3,100}$/', $value)) {
            return array('kind' => 'handle', 'value' => $value);
        }
        if (preg_match('/^[A-Za-z0-9._-]{3,100}$/', $value)) {
            return array('kind' => 'handle', 'value' => $value, 'bare' => true);
        }
        return null;
    }

    /**
     * Video ids of a list of ids and links (youtu.be, watch?v=, shorts, embed, live),
     * separated by commas, spaces or new lines; unique, in order.
     *
     * @return string[]
     */
    public static function videoIds(string $value): array
    {
        $ids = array();
        foreach (preg_split('/[\s,;]+/', $value) as $part) {
            $id = self::videoId($part);
            if ('' !== $id) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    public static function videoId(string $value): string
    {
        $value = trim($value);
        if (preg_match(self::VIDEO_ID, $value)) {
            return $value;
        }
        if (!preg_match('#^https?://#i', $value)) {
            return '';
        }

        $host = strtolower((string) wp_parse_url($value, PHP_URL_HOST));
        $path = (string) wp_parse_url($value, PHP_URL_PATH);

        if (preg_match('/^(www\.)?youtu\.be$/', $host) && preg_match('#^/([A-Za-z0-9_-]{11})#', $path, $match)) {
            return $match[1];
        }
        if (!preg_match('/(^|\.)youtube(-nocookie)?\.com$/', $host)) {
            return '';
        }
        if (preg_match('#^/(shorts|embed|live|v)/([A-Za-z0-9_-]{11})#', $path, $match)) {
            return $match[2];
        }
        parse_str((string) wp_parse_url($value, PHP_URL_QUERY), $query);
        return isset($query['v']) && is_string($query['v']) && preg_match(self::VIDEO_ID, $query['v']) ? $query['v'] : '';
    }
}
