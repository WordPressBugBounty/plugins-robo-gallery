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

defined('WPINC') || exit;

/**
 * robogallery/v1/images/{ids} - thumbnail URLs for the "Images" field preview
 * in the gallery editor (app/extensions/fields/asset/fields/gallery/script.js).
 */
class ImagesController extends BaseController
{
    // all ids of the edited gallery come in one request, only the first
    // MAX_IDS get a thumbnail
    const MAX_IDS = 200;

    public function __construct()
    {
        $this->rest_base = 'images';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_empty_items'),
            'permission_callback' => array($this, 'get_items_permissions_check'),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<ids>[0-9,]+)', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_items'),
            'permission_callback' => array($this, 'get_items_permissions_check'),
        ));
    }

    /**
     * Same audience as the core media library: wp_ajax_query_attachments()
     * already lists every attachment to users with upload_files, so this
     * endpoint exposes nothing beyond what they can see there.
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function get_items_permissions_check($request)
    {
        return current_user_can('upload_files');
    }

    /**
     * @param \WP_REST_Request $request
     * @return array
     */
    public function get_empty_items($request)
    {
        return array();
    }

    /**
     * @param \WP_REST_Request $request
     * @return array
     */
    public function get_items($request)
    {
        $ids = self::get_ids($request);

        // Bigger galleries get previews for the first MAX_IDS images only; the rest
        // stay in the gallery untouched (store.set in the images field script.js
        // keeps ids that have no preview), so no error is returned here.
        if (count($ids) > self::MAX_IDS) {
            $ids = array_slice($ids, 0, self::MAX_IDS);
        }

        return array_map(array(__CLASS__, 'get_image'), $ids);
    }

    /**
     * @param \WP_REST_Request $request
     * @return int[]
     */
    private static function get_ids($request)
    {
        $ids = trim((string) $request->get_param('ids'));
        if ('' === $ids) {
            return array();
        }

        return array_map('intval', explode(',', $ids));
    }

    /**
     * @param int $attachment_id
     * @return array|string {id, url}, or an error string the field script skips
     */
    private static function get_image($attachment_id)
    {
        if (!$attachment_id) {
            return 'Error::empty input id';
        }

        $url = wp_get_attachment_thumb_url($attachment_id);
        if ($url) {
            return array('id' => $attachment_id, 'url' => $url);
        }

        return 'Error::incorrect input id';
    }
}
