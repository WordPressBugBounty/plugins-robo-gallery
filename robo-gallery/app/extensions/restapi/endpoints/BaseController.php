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
 * Base class of the plugin's own routes (robogallery/v1/...), with what the
 * per-gallery routes (/{gallery_id}/...) share: the gallery_id argument and
 * the "may edit this gallery" permission check.
 *
 * @package RoboGallery\RestApi
 * @extends  WP_REST_Controller
 */
abstract class BaseController extends \WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'robogallery/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = '';

	/**
	 * The gallery_id route argument: an existing gallery.
	 *
	 * @return array
	 */
	protected function galleryIdArg() {
		return array(
			'description'       => __( 'Robo Gallery ID.', 'robo-gallery' ),
			'type'              => 'integer',
			'sanitize_callback' => array( $this, 'sanitize_gallery_id' ),
			'validate_callback' => array( $this, 'validate_gallery_id' ),
		);
	}

	/**
	 * @param mixed            $param
	 * @param \WP_REST_Request $request
	 * @param string           $key
	 * @return int
	 */
	public function sanitize_gallery_id( $param, $request, $key ) {
		return intval( $param );
	}

	/**
	 * @param mixed            $param
	 * @param \WP_REST_Request $request
	 * @param string           $key
	 * @return bool an existing gallery
	 */
	public function validate_gallery_id( $param, $request, $key ) {
		if ( ! is_numeric( $param ) || $param <= 0 ) {
			return false;
		}
		$post = get_post( $param );
		if ( $post == null || get_class( $post ) !== 'WP_Post' || $post->post_type !== ROBO_GALLERY_TYPE_POST ) {
			return false;
		}
		return true;
	}

	/**
	 * @param \WP_REST_Request $request
	 * @param string           $code    error code when denied
	 * @param string           $message error message when denied
	 * @return \WP_Error|true
	 */
	protected function checkCanEditGallery( $request, $code, $message ) {
		if ( ! current_user_can( 'edit_post', $request->get_param( 'gallery_id' ) ) ) {
			return new \WP_Error( $code, $message, array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}
}
