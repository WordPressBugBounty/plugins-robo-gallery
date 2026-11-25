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

defined('WPINC') || exit;

/**
 * The videos of a YouTube playlist, for the image manager to add one by one
 * (models\VideoImporter). With the YouTube API key of the plugin's settings
 * (rsg_youtubeApiKey, the same as the youtube gallery type uses) the whole
 * playlist through the Data API, up to MAX_VIDEOS; without it the playlist's
 * public feed, which has its 15 newest videos only. Answers are kept in a
 * transient for a while, as the youtube source does.
 */
class YouTubePlaylist
{
    const MAX_VIDEOS = 200;
    const CACHE_SECONDS = 600;
    const TIMEOUT = 10;

    /**
     * The playlist id of a YouTube link with one: youtube.com/playlist?list=..., a video in
     * a playlist (watch?v=...&list=..., youtu.be/...?list=...); '' for any other link.
     */
    public static function playlistId(string $url): string
    {
        $parts = wp_parse_url(trim($url));
        if (!is_array($parts) || empty($parts['host']) || empty($parts['query'])) {
            return '';
        }
        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
        $isYoutube = preg_match('/^(www\.|m\.|music\.)?youtube\.com$/i', $parts['host']) && in_array($path, array('/playlist', '/watch'), true);
        $isShort   = preg_match('/^(www\.)?youtu\.be$/i', $parts['host']) && preg_match('#^/[A-Za-z0-9_-]{11}$#', $path);
        if (!$isYoutube && !$isShort) {
            return '';
        }
        parse_str($parts['query'], $query);
        $id = isset($query['list']) && is_string($query['list']) ? $query['list'] : '';

        return preg_match('/^[A-Za-z0-9_-]{10,64}$/', $id) ? $id : '';
    }

    /**
     * @return array|\WP_Error { videos: [{ id, title }], complete, source: api|feed }
     */
    public static function videos(string $playlistId)
    {
        $key       = sanitize_text_field((string) get_option(ROBO_GALLERY_PREFIX . 'youtubeApiKey'));
        $cacheKey  = ROBO_GALLERY_PREFIX . 'yt_playlist_' . md5($playlistId . '|' . $key);
        $cached    = get_transient($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $result = '' !== $key ? self::fromApi($playlistId, $key) : self::fromFeed($playlistId);
        if (!is_wp_error($result)) {
            set_transient($cacheKey, $result, self::CACHE_SECONDS);
        }

        return $result;
    }

    /**
     * The whole playlist through the Data API, page by page.
     */
    private static function fromApi(string $playlistId, string $key)
    {
        $videos   = array();
        $pageToken = '';

        do {
            $url = 'https://www.googleapis.com/youtube/v3/playlistItems?part=snippet,status&maxResults=50'
                . '&playlistId=' . rawurlencode($playlistId)
                . '&key=' . rawurlencode($key)
                . ('' !== $pageToken ? '&pageToken=' . rawurlencode($pageToken) : '');

            $response = wp_safe_remote_get($url, array('timeout' => self::TIMEOUT));
            if (is_wp_error($response)) {
                return self::error('robogallery_playlist_unreachable', __('YouTube could not be reached.', 'robo-gallery') . ' ' . $response->get_error_message());
            }

            $json = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($json) || !empty($json['error']) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
                $code = (int) wp_remote_retrieve_response_code($response);
                if (404 === $code) {
                    return self::error('robogallery_playlist_not_found', __('The playlist was not found or is private.', 'robo-gallery'), 404);
                }
                $message = is_array($json) && isset($json['error']['message']) && is_string($json['error']['message'])
                    ? wp_strip_all_tags($json['error']['message']) : 'HTTP ' . $code;
                /* translators: %s: the error of the YouTube API */
                return self::error('robogallery_playlist_api_error', sprintf(__('YouTube API error: %s', 'robo-gallery'), $message));
            }

            foreach (isset($json['items']) && is_array($json['items']) ? $json['items'] : array() as $item) {
                $id = isset($item['snippet']['resourceId']['videoId']) ? $item['snippet']['resourceId']['videoId'] : '';
                // deleted and private videos stay in a playlist without their data
                $public = !isset($item['status']['privacyStatus']) || in_array($item['status']['privacyStatus'], array('public', 'unlisted'), true);
                if (is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) && $public) {
                    $videos[] = array(
                        'id'    => $id,
                        'title' => isset($item['snippet']['title']) && is_string($item['snippet']['title']) ? sanitize_text_field($item['snippet']['title']) : '',
                    );
                }
            }

            $pageToken = isset($json['nextPageToken']) && is_string($json['nextPageToken']) ? $json['nextPageToken'] : '';
        } while ('' !== $pageToken && count($videos) < self::MAX_VIDEOS);

        return array(
            'videos'   => array_slice($videos, 0, self::MAX_VIDEOS),
            'complete' => '' === $pageToken && count($videos) <= self::MAX_VIDEOS,
            'source'   => 'api',
        );
    }

    /**
     * The playlist's public feed: its 15 newest videos, no key needed. Read with
     * plain patterns, not an XML parser - nothing of the answer is ever loaded or
     * resolved, only the video ids and titles are taken.
     */
    private static function fromFeed(string $playlistId)
    {
        $response = wp_safe_remote_get('https://www.youtube.com/feeds/videos.xml?playlist_id=' . rawurlencode($playlistId), array('timeout' => self::TIMEOUT));
        if (is_wp_error($response)) {
            return self::error('robogallery_playlist_unreachable', __('YouTube could not be reached.', 'robo-gallery') . ' ' . $response->get_error_message());
        }
        if (200 !== (int) wp_remote_retrieve_response_code($response)) {
            return self::error('robogallery_playlist_not_found', __('The playlist was not found or is private.', 'robo-gallery'), 404);
        }

        $videos = array();
        preg_match_all('#<entry>(.*?)</entry>#s', wp_remote_retrieve_body($response), $entries);
        foreach ($entries[1] as $entry) {
            if (!preg_match('#<yt:videoId>([A-Za-z0-9_-]{11})</yt:videoId>#', $entry, $id)) {
                continue;
            }
            $title    = preg_match('#<title>(.*?)</title>#s', $entry, $t) ? sanitize_text_field(html_entity_decode($t[1], ENT_QUOTES | ENT_XML1, 'UTF-8')) : '';
            $videos[] = array('id' => $id[1], 'title' => $title);
        }

        return array('videos' => $videos, 'complete' => false, 'source' => 'feed');
    }

    private static function error(string $code, string $message, int $status = 400): \WP_Error
    {
        return new \WP_Error($code, $message, array('status' => $status));
    }
}
