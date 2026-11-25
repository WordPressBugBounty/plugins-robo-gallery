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

namespace RoboGallery\app\extensions\restapi\endpoints;

use RoboGallery\app\extensions\settings\SettingsPage;
use RoboGallery\app\extensions\galleryType\GalleryTypeList;
use RoboGallery\app\extensions\youtube\VideoItem;
use RoboGallery\app\extensions\youtube\YoutubeClient;
use RoboGallery\app\extensions\youtube\YoutubeSource;

defined('WPINC') || exit;

/**
 * The Content tab of the settings panel of a YouTube gallery (youtubegrid,
 * react_parts/options); the data is kept by youtube\YoutubeSource, the number
 * and order of videos are options (OptionsController).
 *  - /{gallery_id}/youtube: GET the sources, their videos the gallery needs with
 *    their changes (the number of videos of the settings and a few more; ?all=1 all
 *    of them), the videos of each source, whether it has more and why it has none,
 *    whether the API key is set (?refresh=1 asks YouTube again instead of the
 *    cache); PUT { text } the pasted
 *    links and ids, their types are found by YoutubeSource::sourcesFromText()
 *    (unrecognized: the parts that are none);
 *  - /{gallery_id}/youtube/videos/{video_id}: PUT the changes of a video (image,
 *    title, description, hidden, action, link, target - all of them, missing = none);
 *  - /{gallery_id}/youtube/order: PUT { order } video ids in the order set in the panel.
 * Every answer is the GET one (all of them take ?all=1). All need edit_post on the gallery.
 */
class YoutubeController extends BaseController
{
    public function __construct()
    {
        $this->rest_base = '(?P<gallery_id>[0-9]+)/youtube';
    }

    public function register_routes()
    {
        // every answer is the GET one: ?all=1 keeps it the full list the panel shows
        $galleryArgs = array(
            'gallery_id' => $this->galleryIdArg(),
            'all'        => array('type' => 'boolean', 'default' => false),
        );
        $permission = array($this, 'permissions_check');

        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            'args' => $galleryArgs,
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_items'),
                'permission_callback' => $permission,
                'args'                => array(
                    'refresh' => array('type' => 'boolean', 'default' => false),
                ),
            ),
            array(
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_sources'),
                'permission_callback' => $permission,
                'args'                => array(
                    'text' => array(
                        'type'      => 'string',
                        'maxLength' => YoutubeSource::MAX_VALUE_LENGTH * YoutubeSource::MAX_SOURCES,
                        'required'  => true,
                    ),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/videos/(?P<video_id>[A-Za-z0-9_-]{11})', array(
            'args' => $galleryArgs,
            array(
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_video'),
                'permission_callback' => $permission,
                'args'                => array(
                    'image'       => array('type' => 'integer', 'minimum' => 0, 'default' => 0),
                    'title'       => array('type' => 'string', 'maxLength' => YoutubeSource::MAX_TITLE, 'default' => ''),
                    'description' => array('type' => 'string', 'maxLength' => YoutubeSource::MAX_DESCRIPTION, 'default' => ''),
                    'hidden'      => array('type' => 'boolean', 'default' => false),
                    'action'      => array('type' => 'string', 'enum' => YoutubeSource::ACTIONS, 'default' => 'lightbox'),
                    'link'        => array('type' => 'string', 'maxLength' => YoutubeSource::MAX_LINK, 'default' => ''),
                    'target'      => array('type' => 'string', 'enum' => YoutubeSource::TARGETS, 'default' => 'self'),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/order', array(
            'args' => $galleryArgs,
            array(
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_order'),
                'permission_callback' => $permission,
                'args'                => array(
                    'order' => array(
                        'type'     => 'array',
                        'items'    => array('type' => 'string', 'pattern' => '^[A-Za-z0-9_-]{11}$'),
                        'maxItems' => YoutubeSource::MAX_CHANGED,
                        'required' => true,
                    ),
                ),
            ),
        ));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_Error|bool
     */
    public function permissions_check($request)
    {
        // the panel doesn't ask without the key; this also keeps the routes from reaching YouTube
        if (GalleryTypeList::isLocked(YoutubeSource::TYPE)) {
            return new \WP_Error('robogallery_rest_cannot_edit', __('Sorry, you cannot edit this gallery.', 'robo-gallery'), array('status' => 403));
        }

        return $this->checkCanEditGallery($request, 'robogallery_rest_cannot_edit', __('Sorry, you cannot edit this gallery.', 'robo-gallery'));
    }

    /**
     * sources, videos (the ones the gallery needs, with ?all=1 all of the sources, in the gallery's
     * order; shown: the gallery shows it), errors (source index => why it has no videos), counts
     * (source index => its loaded videos), more (source index => true: it has more than were loaded),
     * apiKey, settingsUrl (for who may set the key).
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_items($request)
    {
        $galleryId = (int) $request['gallery_id'];
        $result    = YoutubeSource::items($galleryId, true, (bool) $request['refresh'], (bool) $request['all']);

        return rest_ensure_response(array(
            'sources'      => YoutubeSource::getSources($galleryId),
            'videos'       => array_map(array(VideoItem::class, 'forEditor'), $result['items']),
            'errors'       => (object) $result['errors'],
            'counts'       => (object) $result['counts'],
            'more'         => (object) $result['more'],
            'unrecognized' => array(),
            'apiKey'       => YoutubeClient::hasKey(),
            'settingsUrl'  => current_user_can('manage_options')
                ? esc_url_raw(admin_url('edit.php?post_type=' . ROBO_GALLERY_TYPE_POST . '&page=' . SettingsPage::SLUG . '&tab=youtube'))
                : '',
        ));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function update_sources($request)
    {
        $parsed = YoutubeSource::sourcesFromText((string) $request['text']);
        YoutubeSource::saveSources((int) $request['gallery_id'], $parsed['sources']);

        $request['refresh'] = false;
        $response = $this->get_items($request);
        $response->data['unrecognized'] = $parsed['unrecognized'];
        return $response;
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_Error|\WP_REST_Response
     */
    public function update_video($request)
    {
        $imageId = (int) $request['image'];
        // the gallery is public: an image the user may not see (of a private post) must not end up in it
        if ($imageId && (!wp_attachment_is_image($imageId) || !current_user_can('read_post', $imageId))) {
            return new \WP_Error('robogallery_rest_image_invalid', __('The image does not exist.', 'robo-gallery'), array('status' => 400));
        }

        $action = (string) $request['action'];
        $link   = trim((string) $request['link']);
        if ('link' === $action && '' === esc_url_raw($link, array('http', 'https'))) {
            return new \WP_Error('robogallery_rest_link_invalid', __('The link must be a web address (http or https).', 'robo-gallery'), array('status' => 400));
        }

        YoutubeSource::setChanges((int) $request['gallery_id'], (string) $request['video_id'], array(
            'image'       => $imageId,
            'title'       => $request['title'],
            'description' => $request['description'],
            'hidden'      => $request['hidden'],
            'action'      => $request['action'],
            'link'        => $request['link'],
            'target'      => $request['target'],
        ));
        $request['refresh'] = false;
        return $this->get_items($request);
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function update_order($request)
    {
        YoutubeSource::saveOrder((int) $request['gallery_id'], $request['order']);
        $request['refresh'] = false;
        return $this->get_items($request);
    }
}
