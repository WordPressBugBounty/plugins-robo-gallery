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

use RoboGallery\app\extensions\restapi\models\OptionsPanelConfig;

defined('WPINC') || exit;

/**
 * /robogallery/v1/{gallery_id}/options-panel - GET: the config of the gallery's settings panel
 * (models\OptionsPanelConfig), for the "Edit options" window of the block editor, which mounts the panel
 * of the gallery editor. Only users who may edit the gallery; the options themselves are
 * OptionsController's.
 */
class OptionsPanelController extends BaseController
{
    public function __construct()
    {
        $this->rest_base = '(?P<gallery_id>[0-9]+)/options-panel';
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
            )
        );
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_Error|true
     */
    public function get_item_permissions_check($request)
    {
        return $this->checkCanEditGallery($request, 'robogallery_rest_cannot_edit', __('Sorry, you cannot edit this resource.', 'robo-gallery'));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_item($request)
    {
        return rest_ensure_response(OptionsPanelConfig::forGallery((int) $request['gallery_id']));
    }
}
