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

use RoboGallery\app\extensions\restapi\models\ImageImporter;
use RoboGallery\app\extensions\restapi\models\ImageItem;
use RoboGallery\app\extensions\restapi\models\VideoImporter;
use RoboGallery\app\extensions\restapi\models\YouTubePlaylist;

defined('WPINC') || exit;

/**
 * POST /robogallery/v1/{gallery_id}/images/import { url } - imports one image
 * from a link into the media library (models\ImageImporter) and answers with
 * the new image (models\ImageItem). It does not change the gallery: the image
 * manager adds the image to its list and saves that like any other change.
 * POST .../images/video { url } - a video the same way: its cover as the image,
 * its link as the image's video link (models\VideoImporter).
 */
class ImageImportController extends BaseController
{
    public function __construct()
    {
        $this->rest_base = '(?P<gallery_id>[0-9]+)';
    }

    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/images/import',
            array(
                'args' => array(
                    'gallery_id' => $this->galleryIdArg(),
                ),
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'create_item'),
                    'permission_callback' => array($this, 'create_item_permissions_check'),
                    'args'                => array(
                        'url' => array(
                            'type'      => 'string',
                            'format'    => 'uri',
                            'maxLength' => 2048,
                            'required'  => true,
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/images/video',
            array(
                'args' => array(
                    'gallery_id' => $this->galleryIdArg(),
                ),
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array($this, 'preview_video'),
                    'permission_callback' => array($this, 'create_item_permissions_check'),
                    'args'                => array(
                        'url' => array(
                            'type'      => 'string',
                            'format'    => 'uri',
                            'maxLength' => 2048,
                            'required'  => true,
                        ),
                    ),
                ),
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'create_video'),
                    'permission_callback' => array($this, 'create_item_permissions_check'),
                    'args'                => array(
                        'url' => array(
                            'type'      => 'string',
                            'format'    => 'uri',
                            'maxLength' => 2048,
                            'required'  => true,
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/images/playlist',
            array(
                'args' => array(
                    'gallery_id' => $this->galleryIdArg(),
                ),
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_playlist'),
                    'permission_callback' => array($this, 'create_item_permissions_check'),
                    'args'                => array(
                        'url' => array(
                            'type'      => 'string',
                            'format'    => 'uri',
                            'maxLength' => 2048,
                            'required'  => true,
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * GET .../images/video?url= - what adding the video would do (models\VideoImporter::preview()),
     * nothing is saved: the check step of the image manager's dialog.
     *
     * @param \WP_REST_Request $request
     * @return \WP_Error|\WP_REST_Response
     */
    public function preview_video($request)
    {
        $result = VideoImporter::preview((string) $request->get_param('url'), (int) $request->get_param('gallery_id'));

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    /**
     * The videos of a YouTube playlist (models\YouTubePlaylist), as links the image
     * manager then adds one by one through .../images/video.
     *
     * @param \WP_REST_Request $request
     * @return \WP_Error|\WP_REST_Response
     */
    public function get_playlist($request)
    {
        $playlistId = YouTubePlaylist::playlistId((string) $request->get_param('url'));
        if ('' === $playlistId) {
            return new \WP_Error('robogallery_playlist_bad_url', __('Use the link of a YouTube playlist: youtube.com/playlist?list=…', 'robo-gallery'), array('status' => 400));
        }
        // a mix (RD...) is made for each viewer: neither the API nor the feed gives it
        if (0 === strpos($playlistId, 'RD')) {
            return new \WP_Error('robogallery_playlist_mix', __('This is a YouTube mix, made for each viewer: it cannot be read. Use a playlist instead.', 'robo-gallery'), array('status' => 400));
        }

        $result = YouTubePlaylist::videos($playlistId);
        if (is_wp_error($result)) {
            return $result;
        }

        $videos = array();
        foreach ($result['videos'] as $video) {
            $videos[] = array(
                'id'    => $video['id'],
                'title' => $video['title'],
                'url'   => 'https://www.youtube.com/watch?v=' . $video['id'],
            );
        }

        return rest_ensure_response(array(
            'videos'   => $videos,
            'complete' => $result['complete'],
            'source'   => $result['source'],
        ));
    }

    /**
     * Edits the gallery and may upload files, as for an upload in the editor.
     *
     * @param \WP_REST_Request $request
     * @return \WP_Error|bool
     */
    public function create_item_permissions_check($request)
    {
        $canEdit = $this->checkCanEditGallery($request, 'robogallery_rest_cannot_edit', __('Sorry, you cannot edit this gallery.', 'robo-gallery'));
        if (true !== $canEdit) {
            return $canEdit;
        }

        if (!current_user_can('upload_files')) {
            return new \WP_Error('robogallery_rest_cannot_upload', __('Sorry, you cannot upload files.', 'robo-gallery'), array('status' => rest_authorization_required_code()));
        }

        return true;
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_Error|\WP_REST_Response
     */
    public function create_item($request)
    {
        $id = ImageImporter::import((string) $request->get_param('url'), (int) $request->get_param('gallery_id'));
        if (is_wp_error($id)) {
            return $id;
        }

        $response = rest_ensure_response(ImageItem::fromId($id));
        $response->set_status(201);

        return $response;
    }

    /**
     * A video as a gallery image (models\VideoImporter): the item, with reused when
     * the video was in the library already (200 then, 201 for a new image).
     *
     * @param \WP_REST_Request $request
     * @return \WP_Error|\WP_REST_Response
     */
    public function create_video($request)
    {
        $result = VideoImporter::import((string) $request->get_param('url'), (int) $request->get_param('gallery_id'));
        if (is_wp_error($result)) {
            return $result;
        }

        $item             = ImageItem::fromId($result['id']);
        $item['reused']   = $result['reused'];
        $item['provider'] = $result['provider'];

        $response = rest_ensure_response($item);
        $response->set_status($result['reused'] ? 200 : 201);

        return $response;
    }
}
