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

use RoboGallery\app\PluginConstants;
use RoboGallery\app\extensions\access\GalleryAccessManager;
use RoboGallery\app\extensions\reactions\ImageComments;
use RoboGallery\app\extensions\reactions\ImageLikes;
use RoboGallery\app\extensions\reactions\Liker;
use RoboGallery\app\extensions\reactions\ReactionSettings;
use RoboGallery\app\extensions\restapi\models\GalleryModel;

defined('WPINC') || exit;

/**
 * The likes and comments of an image in the lightbox of the gallery script, for the
 * visitors of the gallery (not only its editors):
 *  - GET    /{gallery_id}/images/{image_id}/reactions             counts, the visitor's like, what the visitor may do
 *  - POST   /{gallery_id}/images/{image_id}/like, DELETE           like, unlike
 *  - GET    /{gallery_id}/images/{image_id}/comments?page=         newest first (ImageComments::PER_PAGE)
 *  - POST   /{gallery_id}/images/{image_id}/comments { content }   logged-in users only
 *  - DELETE /{gallery_id}/images/{image_id}/comments/{comment_id}  the author's own, or by who may edit it
 *  - GET    /{gallery_id}/reactions                                the likes and comment counts of all its images (the polaroid's buttons)
 *
 * gallery_id is the gallery that shows the image; root_gallery the gallery embedded on
 * the page (that one or an album above it), whose settings the script uses and whose
 * likes / comments switches decide (ReactionSettings); rg_token the visitor's token of
 * the root gallery (as robofields). Every route first checks that the visitor may view
 * both galleries and that the image is in the gallery; a refusal is always the same 404.
 */
class ImageReactionsController extends BaseController
{
    public function __construct()
    {
        $this->rest_base = '(?P<gallery_id>[0-9]+)/images/(?P<image_id>[0-9]+)';
    }

    public function register_routes()
    {
        $args = array(
            'gallery_id'   => $this->galleryIdArg(),
            'image_id'     => array(
                'type'              => 'integer',
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
            ),
            'root_gallery' => array(
                'type'              => 'integer',
                'minimum'           => 0,
                'default'           => 0,
                'sanitize_callback' => 'absint',
            ),
            PluginConstants::QUERY_VAR_TOKEN => array(
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );

        $galleryArgs = $args;
        unset($galleryArgs['image_id']);
        register_rest_route($this->namespace, '/(?P<gallery_id>[0-9]+)/reactions', array(
            'args' => $galleryArgs,
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'getGalleryReactions'),
                'permission_callback' => array($this, 'canSeeGalleryReactions'),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/reactions', array(
            'args' => $args,
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'getReactions'),
                'permission_callback' => array($this, 'canSee'),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/like', array(
            'args' => $args,
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'like'),
                'permission_callback' => array($this, 'canLike'),
            ),
            array(
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => array($this, 'unlike'),
                'permission_callback' => array($this, 'canLike'),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/comments', array(
            'args' => $args,
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'getComments'),
                'permission_callback' => array($this, 'canReadComments'),
                'args'                => array(
                    'page' => array(
                        'type'              => 'integer',
                        'minimum'           => 1,
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            ),
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'addComment'),
                'permission_callback' => array($this, 'canAddComment'),
                'args'                => array(
                    'content' => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/comments/(?P<comment_id>[0-9]+)', array(
            'args' => $args + array(
                'comment_id' => array(
                    'type'              => 'integer',
                    'minimum'           => 1,
                    'sanitize_callback' => 'absint',
                ),
            ),
            array(
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => array($this, 'deleteComment'),
                'permission_callback' => array($this, 'canSeeImage'),
            ),
        ));
    }

    /* ---------------- permissions ---------------- */

    /**
     * The visitor may view the galleries and the image is in the gallery.
     *
     * @param \WP_REST_Request $request
     * @return true|\WP_Error
     */
    public function canSeeImage($request)
    {
        $imageId = (int) $request['image_id'];
        $visible = $this->canSeeGallery($request)
            && 'attachment' === get_post_type($imageId)
            && in_array($imageId, GalleryModel::get_gallery_images((int) $request['gallery_id']), true);

        return $visible ? true : $this->notFound();
    }

    /**
     * @param \WP_REST_Request $request
     * @return true|\WP_Error
     */
    public function canSeeGalleryReactions($request)
    {
        $rootId  = $this->rootId($request);
        $visible = $this->canSeeGallery($request)
            && (ReactionSettings::LIKES_OFF !== ReactionSettings::likesMode($rootId) || ReactionSettings::commentsOn($rootId));

        return $visible ? true : $this->notFound();
    }

    /**
     * The visitor may view the gallery and the root gallery: the gallery itself or an album above it.
     *
     * @param \WP_REST_Request $request
     */
    private function canSeeGallery($request): bool
    {
        $galleryId = (int) $request['gallery_id'];
        $rootId    = $this->rootId($request);

        $ancestors = array_map('intval', get_post_ancestors($galleryId));
        $visible   = $rootId > 0
            && ($rootId === $galleryId || in_array($rootId, $ancestors, true))
            && ROBO_GALLERY_TYPE_POST === get_post_type($rootId)
            && ReactionSettings::hasReactions($galleryId)
            && ReactionSettings::hasReactions($rootId);

        if ($visible) {
            // the token is the root gallery's; a sub-gallery opens without one, as in robofields
            $access  = new GalleryAccessManager();
            $token   = (string) $request[PluginConstants::QUERY_VAR_TOKEN];
            $visible = $access->canView($rootId, '' === $token ? null : $token)
                && ($rootId === $galleryId || $access->canView($galleryId));
        }

        return $visible;
    }

    /**
     * @param \WP_REST_Request $request
     * @return true|\WP_Error
     */
    public function canSee($request)
    {
        $result = $this->canSeeImage($request);
        if (true !== $result) {
            return $result;
        }

        $rootId = $this->rootId($request);
        $on     = ReactionSettings::LIKES_OFF !== ReactionSettings::likesMode($rootId) || ReactionSettings::commentsOn($rootId);

        return $on ? true : $this->notFound();
    }

    /**
     * @param \WP_REST_Request $request
     * @return true|\WP_Error
     */
    public function canLike($request)
    {
        $result = $this->canSeeImage($request);
        if (true !== $result) {
            return $result;
        }

        $mode = ReactionSettings::likesMode($this->rootId($request));
        if (ReactionSettings::LIKES_OFF === $mode) {
            return $this->notFound();
        }

        if (is_user_logged_in()) {
            return true;
        }

        if (ReactionSettings::LIKES_ALL !== $mode) {
            return new \WP_Error('robo_gallery_like_login', __('Log in to like images.', 'robo-gallery'), array('status' => 401));
        }

        if (!Liker::takeGuestTurn()) {
            return new \WP_Error('robo_gallery_like_rate', __('Too many likes. Try again later.', 'robo-gallery'), array('status' => 429));
        }

        return true;
    }

    /**
     * @param \WP_REST_Request $request
     * @return true|\WP_Error
     */
    public function canReadComments($request)
    {
        $result = $this->canSeeImage($request);
        if (true !== $result) {
            return $result;
        }

        return ReactionSettings::commentsOn($this->rootId($request)) ? true : $this->notFound();
    }

    /**
     * @param \WP_REST_Request $request
     * @return true|\WP_Error
     */
    public function canAddComment($request)
    {
        $result = $this->canReadComments($request);
        if (true !== $result) {
            return $result;
        }

        return is_user_logged_in()
            ? true
            : new \WP_Error('robo_gallery_comment_login', __('Log in to comment.', 'robo-gallery'), array('status' => 401));
    }

    /* ---------------- callbacks ---------------- */

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getReactions($request)
    {
        $galleryId  = (int) $request['gallery_id'];
        $imageId    = (int) $request['image_id'];
        $rootId     = $this->rootId($request);
        $likesMode  = ReactionSettings::likesMode($rootId);
        $commentsOn = ReactionSettings::commentsOn($rootId);
        $loggedIn   = is_user_logged_in();
        $likesOn    = ReactionSettings::LIKES_OFF !== $likesMode;

        return $this->personal(array(
            'likesMode'    => $likesMode,
            'likes'        => $likesOn ? ImageLikes::count($galleryId, $imageId) : 0,
            'liked'        => $likesOn && ImageLikes::has($galleryId, $imageId, Liker::current()),
            'canLike'      => $likesOn && ($loggedIn || ReactionSettings::LIKES_ALL === $likesMode),
            'comments'     => $commentsOn,
            'commentCount' => $commentsOn ? ImageComments::count($galleryId, $imageId) : 0,
            'canComment'   => $commentsOn && $loggedIn,
            'loginUrl'     => $loggedIn ? '' : wp_login_url(),
            'registerUrl'  => !$loggedIn && get_option('users_can_register') ? wp_registration_url() : '',
        ));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getGalleryReactions($request)
    {
        $galleryId  = (int) $request['gallery_id'];
        $rootId     = $this->rootId($request);
        $likesMode  = ReactionSettings::likesMode($rootId);
        $likesOn    = ReactionSettings::LIKES_OFF !== $likesMode;
        $commentsOn = ReactionSettings::commentsOn($rootId);
        $loggedIn   = is_user_logged_in();
        $imageIds   = GalleryModel::get_gallery_images($galleryId);
        $likes      = $likesOn
            ? ImageLikes::ofGallery($galleryId, $imageIds, Liker::current())
            : array('likes' => array(), 'liked' => array());

        return $this->personal(array(
            'likesMode'     => $likesMode,
            // objects even when empty: image id => count (only the images that have some)
            'likes'         => (object) $likes['likes'],
            'liked'         => $likes['liked'],
            'canLike'       => $likesOn && ($loggedIn || ReactionSettings::LIKES_ALL === $likesMode),
            'comments'      => $commentsOn,
            'commentCounts' => (object) ($commentsOn ? ImageComments::countsOfGallery($galleryId, $imageIds) : array()),
            'canComment'    => $commentsOn && $loggedIn,
            'loginUrl'      => $loggedIn ? '' : wp_login_url(),
            'registerUrl'   => !$loggedIn && get_option('users_can_register') ? wp_registration_url() : '',
        ));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function like($request)
    {
        $galleryId = (int) $request['gallery_id'];
        $imageId   = (int) $request['image_id'];

        ImageLikes::add($galleryId, $imageId, Liker::current(true));

        return $this->likeState($galleryId, $imageId);
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function unlike($request)
    {
        $galleryId = (int) $request['gallery_id'];
        $imageId   = (int) $request['image_id'];
        $liker     = Liker::current();

        if ('' !== $liker) {
            ImageLikes::remove($galleryId, $imageId, $liker);
        }

        return $this->likeState($galleryId, $imageId);
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getComments($request)
    {
        $page = ImageComments::page((int) $request['gallery_id'], (int) $request['image_id'], (int) $request['page']);

        return $this->personal($page + array('canComment' => is_user_logged_in()));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function addComment($request)
    {
        $galleryId = (int) $request['gallery_id'];
        $imageId   = (int) $request['image_id'];
        $content   = $request['content'];

        $comment = ImageComments::add($galleryId, $imageId, is_string($content) ? $content : '');
        if (is_wp_error($comment)) {
            return $comment;
        }

        $response = $this->personal(array(
            'comment'      => $comment,
            'commentCount' => ImageComments::count($galleryId, $imageId),
        ));
        $response->set_status(201);

        return $response;
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function deleteComment($request)
    {
        $galleryId = (int) $request['gallery_id'];
        $imageId   = (int) $request['image_id'];

        $result = ImageComments::delete($galleryId, $imageId, (int) $request['comment_id']);
        if (is_wp_error($result)) {
            return $result;
        }

        return $this->personal(array(
            'deleted'      => true,
            'commentCount' => ImageComments::count($galleryId, $imageId),
        ));
    }

    /* ---------------- */

    private function rootId($request): int
    {
        $rootId = (int) $request['root_gallery'];
        return $rootId > 0 ? $rootId : (int) $request['gallery_id'];
    }

    private function likeState(int $galleryId, int $imageId): \WP_REST_Response
    {
        return $this->personal(array(
            'likes' => ImageLikes::count($galleryId, $imageId),
            'liked' => ImageLikes::has($galleryId, $imageId, Liker::current()),
        ));
    }

    /**
     * An answer about this visitor (the like, the own pending comments): never kept by a cache.
     */
    private function personal(array $data): \WP_REST_Response
    {
        $response = new \WP_REST_Response($data);
        $response->header('Cache-Control', 'no-store, private');

        return $response;
    }

    private function notFound(): \WP_Error
    {
        return new \WP_Error('robo_gallery_image_not_found', __('The image was not found.', 'robo-gallery'), array('status' => 404));
    }
}
