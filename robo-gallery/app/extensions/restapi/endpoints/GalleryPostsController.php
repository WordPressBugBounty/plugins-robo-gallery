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

use RoboGallery\app\extensions\access\ExcludeListManager;
use RoboGallery\app\extensions\access\GalleryAccessManager;

if (!defined('WPINC')) {
    exit;
}

/**
 * REST controller of the gallery post type (rest_controller_class in its
 * register_post_type()). Routes and response format are the core ones
 * (/wp/v2/robogallery, /wp/v2/robogallery/{id}); only read access is narrowed
 * by the plugin's direct-link-only / token options. WordPress's own rules
 * (status, post password, write capabilities) stay with the parent class.
 *
 * Consumers: the robogrid frontend script reads single galleries
 * (_fields=id,date,date_gmt,slug,robofields&root_gallery=…&rg_token=…).
 */
class GalleryPostsController extends \WP_REST_Posts_Controller
{
    /**
     * Single gallery: a token gallery requested without its token (rg_token)
     * answers like a missing post. A gallery locked only by the WP post
     * password keeps the core behaviour (title + protected content).
     *
     * @param \WP_REST_Request $request
     * @return true|\WP_Error
     */
    public function get_item_permissions_check($request)
    {
        $allowed = parent::get_item_permissions_check($request);
        if (true !== $allowed) {
            return $allowed;
        }

        $id    = (int) $request['id'];
        $token = $request->get_param('rg_token');
        $token = is_string($token) ? sanitize_text_field($token) : null;

        $accessManager = new GalleryAccessManager();
        if ($accessManager->canView($id, $token) || $accessManager->needsPasswordForm($id, $token)) {
            return true;
        }

        return new \WP_Error(
            'rest_post_invalid_id',
            __('Invalid post ID.'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
            array('status' => 404)
        );
    }

    /**
     * No galleries are created over REST (POST /wp/v2/robogallery): the type
     * (rsg_gallery_type) and the preset settings are written only by the gallery
     * editor, so such a gallery would have none. Nothing in the plugin uses it;
     * reading and updating stay with the core rules.
     *
     * @param \WP_REST_Request $request
     * @return \WP_Error
     */
    public function create_item_permissions_check($request)
    {
        return new \WP_Error(
            'rest_cannot_create',
            __('Sorry, galleries can only be created in the gallery editor.', 'robo-gallery'),
            array('status' => rest_authorization_required_code())
        );
    }

    /**
     * Collection: direct-link-only galleries are left out at query level, so
     * X-WP-Total and pagination stay right (filtering items after the query
     * would not).
     *
     * @param array            $prepared_args
     * @param \WP_REST_Request $request
     * @return array
     */
    protected function prepare_items_query($prepared_args = array(), $request = null)
    {
        $query_args = parent::prepare_items_query($prepared_args, $request);

        if (GalleryAccessManager::canSeeAllInListings()) {
            return $query_args;
        }

        $excluded_ids = (new ExcludeListManager())->getExcluded();
        if (empty($excluded_ids)) {
            return $query_args;
        }

        $not_in = isset($query_args['post__not_in']) ? array_filter(array_map('intval', (array) $query_args['post__not_in'])) : array();
        $query_args['post__not_in'] = array_values(array_unique(array_merge($not_in, $excluded_ids)));

        return $query_args;
    }
}
