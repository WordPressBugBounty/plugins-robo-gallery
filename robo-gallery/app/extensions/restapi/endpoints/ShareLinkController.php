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

namespace RoboGallery\app\extensions\restapi\endpoints;

use RoboGallery\app\extensions\access\GalleryAccessManager;
use RoboGallery\app\extensions\access\GalleryToken;
use RoboGallery\app\extensions\access\ShareLinks;

defined('WPINC') || exit;

/**
 * /robogallery/v1/{gallery_id}/share-link - the gallery's secret share link,
 * for the access settings in the gallery editor:
 *  - GET:  the current link;
 *  - POST: a new link (new token) - the old link stops working at once.
 * Both answer with the same object (see get_item_schema()). Only users who
 * may edit the gallery; the link is the secret itself.
 */
class ShareLinkController extends BaseController
{
    private GalleryToken $token;

    public function __construct()
    {
        $this->rest_base = '(?P<gallery_id>[0-9]+)/share-link';
        $this->token     = new GalleryToken();
    }

    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                'args'   => array(
                    'gallery_id' => $this->galleryIdArg(),
                ),
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_item'),
                    'permission_callback' => array($this, 'get_item_permissions_check'),
                ),
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'create_item'),
                    'permission_callback' => array($this, 'create_item_permissions_check'),
                ),
                'schema' => array($this, 'get_public_item_schema'),
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
    public function create_item_permissions_check($request)
    {
        return $this->checkCanEditGallery($request, 'robogallery_rest_cannot_edit', __('Sorry, you cannot edit this resource.', 'robo-gallery'));
    }

    /**
     * The current link ('' if the gallery has no token yet - POST creates one).
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_item($request)
    {
        return rest_ensure_response($this->describe((int) $request['gallery_id']));
    }

    /**
     * New token, so a new link; links with the old token stop working.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function create_item($request)
    {
        $gallery_id = (int) $request['gallery_id'];
        $this->token->regenerate($gallery_id);

        return rest_ensure_response($this->describe($gallery_id));
    }

    /**
     * @param int $gallery_id
     * @return array
     */
    private function describe($gallery_id)
    {
        $access = new GalleryAccessManager($this->token);
        $token  = $this->token->get($gallery_id);

        return array(
            'url'                  => '' === $token ? '' : ShareLinks::getShareUrl($token),
            'accessDirectLinkOnly' => $access->isDirectLinkOnly($gallery_id),
            'accessRequireToken'   => $access->requiresToken($gallery_id),
        );
    }

    public function get_item_schema()
    {
        return $this->add_additional_fields_schema(array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'robogallery-share-link',
            'type'       => 'object',
            'properties' => array(
                'url'                  => array(
                    'description' => __('Share link of the gallery; empty when the gallery has no token yet.', 'robo-gallery'),
                    'type'        => 'string',
                    'format'      => 'uri',
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
                'accessDirectLinkOnly' => array(
                    'description' => __('The gallery is hidden from listings and opens only by its link.', 'robo-gallery'),
                    'type'        => 'boolean',
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
                'accessRequireToken'   => array(
                    'description' => __('The gallery opens only through the share link (with accessDirectLinkOnly).', 'robo-gallery'),
                    'type'        => 'boolean',
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
            ),
        ));
    }
}
