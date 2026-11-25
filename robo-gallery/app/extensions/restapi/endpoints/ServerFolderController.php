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

use RoboGallery\app\extensions\restapi\models\ImageItem;
use RoboGallery\app\extensions\restapi\models\ServerFolder;

defined('WPINC') || exit;

/**
 * The image manager's "From a server folder" (models\ServerFolder):
 *  - GET  /robogallery/v1/{gallery_id}/server-folder?path=a/b - its folders and images;
 *  - POST /robogallery/v1/{gallery_id}/server-folder/import { path, files, deleteOriginals, readFileData }
 *    - the images into the media library; answers with the new items and the failed files.
 * It does not change the gallery: the image manager adds the items to its list and saves
 * that like any other change.
 */
class ServerFolderController extends BaseController
{
    // a request copies the files and makes their sizes: a few at a time keep it short
    const MAX_FILES = 10;

    public function __construct()
    {
        $this->rest_base = '(?P<gallery_id>[0-9]+)';
    }

    public function register_routes()
    {
        $path = array(
            'type'      => 'string',
            'maxLength' => 1024,
            'default'   => '',
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/server-folder',
            array(
                'args' => array(
                    'gallery_id' => $this->galleryIdArg(),
                ),
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_items'),
                    'permission_callback' => array($this, 'permissions_check'),
                    'args'                => array('path' => $path),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/server-folder/import',
            array(
                'args' => array(
                    'gallery_id' => $this->galleryIdArg(),
                ),
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'import_items'),
                    'permission_callback' => array($this, 'permissions_check'),
                    'args'                => array(
                        'path'            => $path,
                        'files'           => array(
                            'type'     => 'array',
                            'items'    => array('type' => 'string', 'maxLength' => 255),
                            'minItems' => 1,
                            'maxItems' => self::MAX_FILES,
                            'required' => true,
                        ),
                        'deleteOriginals' => array(
                            'type'    => 'boolean',
                            'default' => false,
                        ),
                        'readFileData'    => array(
                            'type'    => 'boolean',
                            'default' => true,
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Edits the gallery, may upload files and may look into the server's folders.
     *
     * @param \WP_REST_Request $request
     * @return \WP_Error|bool
     */
    public function permissions_check($request)
    {
        $canEdit = $this->checkCanEditGallery($request, 'robogallery_rest_cannot_edit', __('Sorry, you cannot edit this gallery.', 'robo-gallery'));
        if (true !== $canEdit) {
            return $canEdit;
        }

        if (!ServerFolder::canBrowse()) {
            return new \WP_Error('robogallery_rest_cannot_browse', __('Sorry, you cannot add images from server folders.', 'robo-gallery'), array('status' => rest_authorization_required_code()));
        }

        return true;
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_Error|\WP_REST_Response
     */
    public function get_items($request)
    {
        $folder = ServerFolder::resolve((string) $request->get_param('path'));
        if (is_wp_error($folder)) {
            return $folder;
        }

        return rest_ensure_response(array_merge(
            array('path' => ServerFolder::segments($folder)),
            ServerFolder::contents($folder)
        ));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_Error|\WP_REST_Response
     */
    public function import_items($request)
    {
        $folder = ServerFolder::resolve((string) $request->get_param('path'));
        if (is_wp_error($folder)) {
            return $folder;
        }

        $galleryId = (int) $request->get_param('gallery_id');
        $delete    = (bool) $request->get_param('deleteOriginals');
        $readData  = (bool) $request->get_param('readFileData');

        $items  = array();
        $failed = array();
        foreach (array_unique((array) $request->get_param('files')) as $name) {
            $result = ServerFolder::import($folder, (string) $name, $galleryId, $delete, $readData);
            if (is_wp_error($result)) {
                $failed[] = array('file' => (string) $name, 'error' => $result->get_error_message());
                continue;
            }
            $item            = ImageItem::fromId($result['id']);
            $item['file']    = (string) $name;
            $item['deleted'] = $result['deleted'];
            // added before: the same image again, no second copy
            $item['reused'] = $result['reused'];
            $items[]         = $item;
        }

        return rest_ensure_response(array('items' => $items, 'failed' => $failed));
    }
}
