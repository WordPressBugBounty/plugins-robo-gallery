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

use RoboGallery\app\extensions\galleryType\GalleryTypeList;
use RoboGallery\app\extensions\restapi\models\AlbumTree;

defined('WPINC') || exit;

/**
 * /robogallery/v1/{gallery_id}/hierarchy - the album hierarchy of the galleries
 * of the gallery's type (models\AlbumTree), for the hierarchy window of the
 * gallery settings (post_parent / menu_order, as the removed "Album Hierarchy" box):
 *  - GET: { revision, type, typeName, maxDepth, tree };
 *  - PUT { revision, tree: [{ id, children }] }: the new arrangement, answered
 *    with the saved tree and `skipped` (galleries the user may not move);
 *    409 robogallery_hierarchy_conflict with data.current when the tree changed
 *    meanwhile, 400 robogallery_hierarchy_invalid for a tree that is not the
 *    loaded galleries or puts one deeper than maxDepth.
 * Only users who may edit the gallery.
 */
class AlbumHierarchyController extends BaseController
{
    public function __construct()
    {
        $this->rest_base = '(?P<gallery_id>[0-9]+)/hierarchy';
    }

    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                'args' => array(
                    'gallery_id' => $this->galleryIdArg(),
                ),
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_item'),
                    'permission_callback' => array($this, 'get_item_permissions_check'),
                ),
                array(
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'update_item'),
                    'permission_callback' => array($this, 'update_item_permissions_check'),
                    'args'                => array(
                        'revision' => array(
                            'type'     => 'string',
                            'required' => true,
                        ),
                        'tree'     => array(
                            'type'     => 'array',
                            'required' => true,
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_Error|true
     */
    public function get_item_permissions_check($request)
    {
        return $this->checkCanEditGallery($request, 'robogallery_rest_cannot_view', __('Sorry, you cannot view this resource.', 'robo-gallery'));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_Error|true
     */
    public function update_item_permissions_check($request)
    {
        return $this->checkCanEditGallery($request, 'robogallery_rest_cannot_edit', __('Sorry, you cannot edit this resource.', 'robo-gallery'));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_item($request)
    {
        return rest_ensure_response($this->payload(new AlbumTree((int) $request['gallery_id'])));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function update_item($request)
    {
        $tree = new AlbumTree((int) $request['gallery_id']);

        if (!hash_equals($tree->revision(), (string) $request['revision'])) {
            return new \WP_Error(
                'robogallery_hierarchy_conflict',
                __('The album hierarchy was changed meanwhile. Check it and save again.', 'robo-gallery'),
                array('status' => 409, 'current' => $this->payload($tree))
            );
        }

        $nodes = $request['tree'];
        if (!$tree->isValid($nodes)) {
            return new \WP_Error(
                'robogallery_hierarchy_invalid',
                __('The album hierarchy is not valid.', 'robo-gallery'),
                array('status' => 400)
            );
        }

        $skipped = $tree->save($nodes);

        $payload            = $this->payload($tree);
        $payload['skipped'] = $skipped;

        return rest_ensure_response($payload);
    }

    private function payload(AlbumTree $tree): array
    {
        $payload = $tree->describe();
        $type    = GalleryTypeList::getByType($payload['type']);

        $payload['typeName'] = $type ? $type['name'] : $payload['type'];

        return $payload;
    }
}
