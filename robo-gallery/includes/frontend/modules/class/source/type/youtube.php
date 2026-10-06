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

if (!defined('WPINC')) {
    exit;
}

/**
 * Videos of a YouTube channel / user / playlist / list of ids (YouTube Data API v3).
 *
 * The API answer is checked and reduced to a list of playable videos before it
 * is cached: a playlist also returns deleted and private videos (no thumbnails,
 * "Deleted video" titles), and an error answer (bad key, quota) has no items at
 * all - neither may reach the gallery or sit in the cache for hours.
 */
class RoboYoutubeSource
{

    // the API returns 50 results per request at most
    const MAX_RESULTS = 50;

    // how long an empty list / a failed request is cached: short, but a broken
    // key must not cost an API call on every page view
    const CACHE_TIME_EMPTY = 15 * MINUTE_IN_SECONDS;
    const CACHE_TIME_ERROR = 5 * MINUTE_IN_SECONDS;

    // preferred first: "high" (480x360) is what the gallery always used
    const THUMB_SIZES = array('high', 'medium', 'standard', 'default', 'maxres');

    private $core    = null;
    private $gallery = null;

    private $id = 0;

    // the gallery whose settings are used (CloneSource), as in the other sources
    private $options_id = 0;

    private $resourceId   = '';
    private $resourceType = 'user';

    private $cache_key_prefix = 'robo_gallery_yt_';

    private $resourceCountMax = 50;
    private $youtubeCacheTime = 12;

    private $api_key = '';

    private $errors = array();

    private $items = array();
    private $tags  = array();
    private $cats  = array();

    private $incorrectParams = false;

    public function __construct($id, $core)
    {
        $this->core    = $core;
        $this->gallery = $core->gallery;

        $this->id = $id;

        $this->options_id = $this->core->gallery->options_id;

        $this->initResource();

        if (!$this->isCorrectParams()) {
            $this->incorrectParams = true;
            return;
        }

        $this->initItems();
    }

    public function getItems()
    {return $this->items;}
    public function getTags()
    {return $this->tags;}
    public function getCats()
    {return $this->cats;}

    /**
     * Why there are no videos (settings, API answer) - shown to the gallery's editors only.
     */
    public function getErrors()
    {return $this->errors;}

    private function initResource()
    {
        $this->api_key          = sanitize_text_field(get_option(ROBO_GALLERY_PREFIX . 'youtubeApiKey'));
        $this->youtubeCacheTime = (int) get_option(ROBO_GALLERY_PREFIX . 'youtubeCacheTime');

        $this->resourceCountMax = (int) get_post_meta($this->id, ROBO_GALLERY_PREFIX . 'youtube_count_max', true);

        $this->resourceType = sanitize_text_field(get_post_meta($this->id, ROBO_GALLERY_PREFIX . 'galleryYoutubeType', true));
        $this->resourceId   = sanitize_text_field(get_post_meta($this->id, ROBO_GALLERY_PREFIX . 'galleryYoutubeValue', true));
    }

    private function isCorrectParams()
    {
        if ($this->resourceCountMax < 1 || $this->resourceCountMax > self::MAX_RESULTS) {
            $this->resourceCountMax = self::MAX_RESULTS;
        }

        if ($this->youtubeCacheTime < 1) {
            $this->youtubeCacheTime = 12;
        }

        if (!$this->api_key) {
            $this->errors[] = 'Youtube  api is empty';
            return false;
        }

        if (!$this->resourceType || !$this->resourceId) {
            $this->errors[] = 'Youtube source is empty';
            return false;
        }

        if (in_array($this->resourceType, array('user', 'playlist', 'channel', 'ids')) === false) {
            $this->errors[] = 'type Youtube source is incorrect';
            return false;
        }

        switch (substr($this->resourceId, 0, 2)) {
            case 'UC':
                if ($this->resourceType != 'channel') {
                    $this->errors[] = 'type Youtube channel source is incorrect';
                    return false;
                }
                break;
            case 'PL':
                if ($this->resourceType != 'playlist') {
                    $this->errors[] = 'type Youtube playlist source is incorrect';
                    return false;
                }
                break;
        }
        return true;
    }

    /*
    ====================================================================
    ======Cache
    ====================================================================
     */

    /**
     * Everything the request is built from is in the key (the API key only as
     * part of the hash): a changed source, count or key is fetched anew.
     */
    private function getCacheKey()
    {
        return ROBO_GALLERY_PREFIX . 'yt_' . hash('sha256', $this->cache_key_prefix
            . $this->resourceType . '|' . $this->resourceCountMax . '|' . $this->resourceId . '|' . $this->api_key);
    }

    public function initItems()
    {
        $cacheKey = $this->getCacheKey();
        $cached   = get_transient($cacheKey);

        // only lists this class stored are trusted (older raw API answers are ignored)
        if (is_array($cached) && isset($cached['videos']) && is_array($cached['videos'])) {
            $videos = $cached['videos'];
            // a cached failure keeps its reason for the editors' message
            if (!empty($cached['errors']) && is_array($cached['errors'])) {
                $this->errors = $cached['errors'];
            }
        } else {
            $videos = $this->fetchVideos();

            if (false === $videos) {
                set_transient($cacheKey, array('videos' => array(), 'errors' => $this->errors), self::CACHE_TIME_ERROR);
                return;
            }

            set_transient(
                $cacheKey,
                array('videos' => $videos),
                $videos ? $this->youtubeCacheTime * HOUR_IN_SECONDS : self::CACHE_TIME_EMPTY
            );
        }

        $this->items = array();
        foreach ($videos as $video) {
            $this->items[] = $this->buildItem($video);
        }
    }

    /*
    ====================================================================
    ======Request and answer
    ====================================================================
     */

    /**
     * @return array|false the playable videos (possibly none), false when the
     *                     request failed or the answer is not a video list
     */
    private function fetchVideos()
    {
        $apiUrl = $this->getApiUrl($this->resourceId);
        if (!$apiUrl) {
            $this->errors[] = 'Youtube error input data';
            return false;
        }

        $answer = $this->getJsonRequest($apiUrl);
        if (!$answer) {
            return false;
        }

        if (!isset($answer['items']) || !is_array($answer['items'])) {
            $this->errors[] = 'Youtube answer has no items list';
            return false;
        }

        $videos = array();
        foreach ($answer['items'] as $item) {
            $video = $this->parseVideo($item);
            if ($video) {
                $videos[] = $video;
            }
        }
        return $videos;
    }

    /**
     * @return array|false decoded JSON of a successful answer
     */
    private function getJsonRequest($apiUrl, $params = array())
    {
        if (!$apiUrl) {
            $this->errors[] = 'Youtube request error - api url is empty';
            return false;
        }

        $request = wp_safe_remote_get($apiUrl, $params + array('timeout' => 10));

        if (is_wp_error($request)) {
            $this->errors[] = 'wp request error: ' . $request->get_error_message();
            return false;
        }

        $json = json_decode(wp_remote_retrieve_body($request), true);

        // an API error comes as {"error": {"code": 403, "message": "..."}} with a non-200 status
        if (is_array($json) && !empty($json['error'])) {
            $this->errors[] = 'Youtube API error: ' . (isset($json['error']['message']) && is_string($json['error']['message'])
                ? $json['error']['message'] : 'unknown');
            return false;
        }

        if (200 !== (int) wp_remote_retrieve_response_code($request) || !is_array($json)) {
            $this->errors[] = 'Youtube answer is not valid (HTTP ' . (int) wp_remote_retrieve_response_code($request) . ')';
            return false;
        }

        return $json;
    }

    /**
     * One API item as a video the gallery can show, or null: not a video, no
     * valid id, private, or without a usable thumbnail (deleted / private
     * videos of a playlist have none).
     */
    private function parseVideo($item)
    {
        if (!is_array($item) || !$this->isVideo($item)) {
            return null;
        }

        $videoId = $this->getVideoId($item);
        if (!is_string($videoId) || !preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId)) {
            return null;
        }

        // requested for playlists and ids ("status" part); search results have no status
        if (isset($item['status']['privacyStatus']) && !in_array($item['status']['privacyStatus'], array('public', 'unlisted'), true)) {
            return null;
        }

        $snippet = isset($item['snippet']) && is_array($item['snippet']) ? $item['snippet'] : array();

        $thumb = $this->pickThumbnail($snippet);
        if (!$thumb) {
            return null;
        }

        return array(
            'id'          => $videoId,
            'title'       => isset($snippet['title']) && is_string($snippet['title']) ? sanitize_text_field($snippet['title']) : '',
            'description' => isset($snippet['description']) && is_string($snippet['description']) ? sanitize_textarea_field($snippet['description']) : '',
            'thumb'       => $thumb['url'],
            'width'       => $thumb['width'],
            'height'      => $thumb['height'],
        );
    }

    /**
     * @return array|null url / width / height of the best available thumbnail
     */
    private function pickThumbnail($snippet)
    {
        if (!isset($snippet['thumbnails']) || !is_array($snippet['thumbnails'])) {
            return null;
        }

        foreach (self::THUMB_SIZES as $size) {
            if (!isset($snippet['thumbnails'][$size]['url']) || !is_string($snippet['thumbnails'][$size]['url'])) {
                continue;
            }

            $url = esc_url_raw($snippet['thumbnails'][$size]['url'], array('http', 'https'));
            if (!$url) {
                continue;
            }

            $thumb  = $snippet['thumbnails'][$size];
            $width  = isset($thumb['width']) ? (int) $thumb['width'] : 0;
            $height = isset($thumb['height']) ? (int) $thumb['height'] : 0;

            return array(
                'url'    => $url,
                // the layout needs a ratio: YouTube thumbnails are 4:3 when the size is missing
                'width'  => $width > 0 ? $width : 480,
                'height' => $height > 0 ? $height : 360,
            );
        }
        return null;
    }

    /**
     * A checked video in the form the gallery layout expects for an item.
     */
    private function buildItem($video)
    {
        $item = array(
            'id'        => $video['id'],
            'videolink' => 'https://www.youtube.com/watch?v=' . $video['id'],
        );

        $item['data']               = new stdClass();
        $item['data']->post_excerpt = '';
        $item['data']->post_content = $video['description'];
        $item['data']->post_title   = $video['title'];

        $item['image'] = $video['thumb'];
        $item['thumb'] = $video['thumb'];
        $item['sizeW'] = $video['width'];
        $item['sizeH'] = $video['height'];

        $item['link']        = '';
        $item['typelink']    = '';
        $item['col']         = '';
        $item['effect']      = '';
        $item['alt']         = '';
        $item['tags']        = null;
        $item['catid']       = $this->id;
        $item['galleryType'] = 'youtube';

        return $item;
    }

    private function isVideo($item)
    {
        switch ($this->resourceType) {
            case 'ids':
                return isset($item['kind']) && $item['kind'] == 'youtube#video';

            case 'playlist':
                return isset($item['kind']) && $item['kind'] == 'youtube#playlistItem';

            default:
                return isset($item['id']['kind']) && $item['id']['kind'] == 'youtube#video';
        }
    }

    private function getVideoId($item)
    {
        switch ($this->resourceType) {
            case 'playlist':
                return isset($item['snippet']['resourceId']['videoId']) ? $item['snippet']['resourceId']['videoId'] : '';

            case 'ids':
                return isset($item['id']) ? $item['id'] : '';

            default:
                return isset($item['id']['videoId']) ? $item['id']['videoId'] : '';
        }
    }

    /*
    ====================================================================
    ======API urls
    ====================================================================
     */

    private function getApiUrl($resourceId)
    {
        switch ($this->resourceType) {
            case 'channel':
                return $this->urlChannel($resourceId);
            case 'playlist':
                return $this->urlPlayList($resourceId);
            case 'user':
                return $this->urlUserName($resourceId);
            case 'ids':
                return $this->urlIds($resourceId);
        }
        return false;
    }

    private function urlUserName($resourceId)
    {
        $idArray = $this->getIdArray($resourceId);
        if (!is_array($idArray) || !count($idArray)) {
            $this->errors[] = 'Youtube user name error input data';
            return false;
        }

        $url_one = 'https://www.googleapis.com/youtube/v3/channels?part=id'
        . '&forUsername=' . urlencode($idArray[0])
        . '&key=' . urlencode($this->api_key);

        $res = $this->getJsonRequest($url_one);

        if (!$res || !isset($res['items'][0]['id']) || !is_string($res['items'][0]['id'])) {
            $this->errors[] = 'Youtube user not found';
            return false;
        }

        return 'https://www.googleapis.com/youtube/v3/search?part=snippet,id'
        . '&key=' . urlencode($this->api_key)
        . '&maxResults=' . $this->resourceCountMax
        . '&channelId=' . urlencode($res['items'][0]['id'])
        . '&type=video'
        . '&order=date';
    }

    private function urlIds($resourceId)
    {
        $idArray = $this->getIdArray($resourceId);
        if (!is_array($idArray) || !count($idArray)) {
            $this->errors[] = 'Youtube ids error input data';
            return false;
        }

        // one comma-separated "id" parameter, as the API documents it
        return 'https://www.googleapis.com/youtube/v3/videos?part=snippet,id,status'
        . '&key=' . urlencode($this->api_key)
        . '&id=' . urlencode(implode(',', array_slice($idArray, 0, self::MAX_RESULTS)));
    }

    private function urlChannel($resourceId)
    {
        $idArray = $this->getIdArray($resourceId);

        if (!is_array($idArray) || !count($idArray)) {
            $this->errors[] = 'Youtube channel id error input data';
            return false;
        }

        return 'https://www.googleapis.com/youtube/v3/search?part=snippet,id'
        . '&key=' . urlencode($this->api_key)
        . '&maxResults=' . $this->resourceCountMax
        . '&order=date'
        . '&type=video'
        . '&channelId=' . urlencode($idArray[0]);
    }

    private function urlPlayList($resourceId)
    {
        $idArray = $this->getIdArray($resourceId);

        if (!is_array($idArray) || !count($idArray)) {
            $this->errors[] = 'Youtube playlist id error input data';
            return false;
        }

        return 'https://www.googleapis.com/youtube/v3/playlistItems?part=snippet,id,status'
        . '&key=' . urlencode($this->api_key)
        . '&maxResults=' . $this->resourceCountMax
        . '&playlistId=' . urlencode($idArray[0]);
    }

    /*
    ====================================================================
    ======Helper
    ====================================================================
     */

    /**
     * The saved value split into ids (it may be a list separated by
     * , ; | . spaces or new lines).
     */
    private function getIdArray($resourceId)
    {
        $ids = array();
        foreach (preg_split('/[|;,. \n]/', (string) $resourceId) as $value) {
            if (trim($value) !== '') {
                $ids[] = trim($value);
            }
        }
        return $ids;
    }

}
