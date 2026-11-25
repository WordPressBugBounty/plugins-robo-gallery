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

use RoboGallery\app\extensions\posts\PostItem;
use RoboGallery\app\extensions\posts\PostsOptions;
use RoboGallery\app\extensions\posts\PostsSource;

defined('WPINC') || exit;

/**
 * The Content tab of the settings panel of a posts gallery (react_parts/options);
 * the data is kept by posts\PostsSource, the query settings are options
 * (OptionsController).
 *  - /{gallery_id}/posts: GET the posts the gallery shows and the hand-picked
 *    list, PUT a new hand-picked list { manual: ids };
 *  - /{gallery_id}/posts/{post_id}/image: PUT { image } the image of a post in
 *    this gallery, 0 = found in the post again;
 *  - /{gallery_id}/posts/search: posts to add to the list (?search=, ?post_type=);
 *  - /{gallery_id}/posts/types: the post types and their taxonomies;
 *  - /{gallery_id}/posts/terms: terms of a type (?post_type=, ?search=, ?include=).
 * All need edit_post on the gallery; only what a visitor could see is listed
 * (published posts without a password, public types and taxonomies), except
 * the posts of the stored list, shown by title to who may read them.
 */
class PostsController extends BaseController
{
    const SEARCH_LIMIT = 20;
    const TERMS_LIMIT  = 50;

    public function __construct()
    {
        $this->rest_base = '(?P<gallery_id>[0-9]+)/posts';
    }

    public function register_routes()
    {
        $galleryArgs = array('gallery_id' => $this->galleryIdArg());
        $permission  = array($this, 'permissions_check');

        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            'args' => $galleryArgs,
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_items'),
                'permission_callback' => $permission,
            ),
            array(
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_manual'),
                'permission_callback' => $permission,
                'args'                => array(
                    'manual' => array(
                        'type'     => 'array',
                        'items'    => array('type' => 'integer', 'minimum' => 1),
                        'maxItems' => PostsOptions::MAX_POSTS,
                        'required' => true,
                    ),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<post_id>[0-9]+)/image', array(
            'args' => $galleryArgs + array(
                'post_id' => array('type' => 'integer', 'minimum' => 1),
            ),
            array(
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_image'),
                'permission_callback' => $permission,
                'args'                => array(
                    'image' => array(
                        'type'     => 'integer',
                        'minimum'  => 0,
                        'required' => true,
                    ),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/search', array(
            'args' => $galleryArgs,
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'search'),
                'permission_callback' => $permission,
                'args'                => array(
                    'search'    => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'),
                    'post_type' => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_key'),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/types', array(
            'args' => $galleryArgs,
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_types'),
                'permission_callback' => $permission,
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/terms', array(
            'args' => $galleryArgs,
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_terms'),
                'permission_callback' => $permission,
                'args'                => array(
                    'post_type' => array('type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_key'),
                    'search'    => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'),
                    'include'   => array(
                        'type'     => 'array',
                        'items'    => array('type' => 'integer', 'minimum' => 1),
                        'maxItems' => PostsOptions::MAX_TERMS,
                        'default'  => array(),
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
        return $this->checkCanEditGallery($request, 'robogallery_rest_cannot_edit', __('Sorry, you cannot edit this gallery.', 'robo-gallery'));
    }

    /**
     * items: the posts the gallery shows by its saved settings (with their
     * images); manual: the stored hand-picked list, also the posts it does
     * not show now (status says why).
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_items($request)
    {
        $galleryId = (int) $request['gallery_id'];

        $items = array();
        foreach (PostsSource::items($galleryId) as $item) {
            $items[] = PostItem::forEditor($item);
        }

        return rest_ensure_response(array(
            'items'  => $items,
            'manual' => $this->manualList($galleryId),
        ));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function update_manual($request)
    {
        PostsSource::saveManual((int) $request['gallery_id'], (array) $request['manual']);
        return $this->get_items($request);
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_Error|\WP_REST_Response
     */
    public function update_image($request)
    {
        $postId  = (int) $request['post_id'];
        $imageId = (int) $request['image'];

        if (!get_post($postId)) {
            return new \WP_Error('robogallery_rest_post_invalid', __('The post does not exist.', 'robo-gallery'), array('status' => 404));
        }

        // the gallery is public: an image the user may not see (of a private post) must not end up in it
        if ($imageId && (!wp_attachment_is_image($imageId) || !current_user_can('read_post', $imageId))) {
            return new \WP_Error('robogallery_rest_image_invalid', __('The image does not exist.', 'robo-gallery'), array('status' => 400));
        }

        PostsSource::setImage((int) $request['gallery_id'], $postId, $imageId);
        return $this->get_items($request);
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function search($request)
    {
        $types    = PostsSource::postTypes();
        $postType = (string) $request['post_type'];
        if ('' !== $postType && !in_array($postType, $types, true)) {
            return rest_ensure_response(array());
        }

        $args = array(
            'post_type'           => '' !== $postType ? $postType : $types,
            'post_status'         => 'publish',
            'has_password'        => false,
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
            'posts_per_page'      => self::SEARCH_LIMIT,
        );
        if ('' !== (string) $request['search']) {
            $args['s'] = (string) $request['search'];
        }

        $query = new \WP_Query($args);
        $posts = array_filter($query->posts, function ($post) {
            return $post instanceof \WP_Post;
        });
        $images = PostsSource::resolveImages($posts, array());

        $result = array();
        foreach ($posts as $post) {
            $image    = isset($images[$post->ID]) ? $images[$post->ID] : array(0, '');
            $result[] = PostItem::forEditor(array('post' => $post, 'image' => $image[0], 'imageSource' => $image[1]));
        }
        return rest_ensure_response($result);
    }

    /**
     * @return \WP_REST_Response [{ name, label, taxonomies: [{ name, label }] }]
     */
    public function get_types()
    {
        $result = array();
        foreach (PostsSource::postTypes() as $name) {
            $object     = get_post_type_object($name);
            $taxonomies = array();
            foreach (PostsSource::taxonomies($name) as $taxonomy) {
                $taxonomies[] = array('name' => $taxonomy->name, 'label' => $taxonomy->labels->name);
            }
            $result[] = array(
                'name'       => $name,
                'label'      => $object ? $object->labels->name : $name,
                'taxonomies' => $taxonomies,
            );
        }
        return rest_ensure_response($result);
    }

    /**
     * Terms of the type's taxonomies: those matching search, or the included ones
     * (the chosen terms, to show their names).
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response [{ id, name, taxonomy, taxonomyLabel }]
     */
    public function get_terms($request)
    {
        $postType = (string) $request['post_type'];
        if (!in_array($postType, PostsSource::postTypes(), true)) {
            return rest_ensure_response(array());
        }

        $taxonomies = PostsSource::taxonomies($postType);
        if (!$taxonomies) {
            return rest_ensure_response(array());
        }

        $include = PostsSource::idList((array) $request['include'], PostsOptions::MAX_TERMS);
        $args    = array(
            'taxonomy'   => array_keys($taxonomies),
            'hide_empty' => false,
            'orderby'    => 'name',
        );
        if ($include) {
            $args['include'] = $include;
        } else {
            $args['number'] = self::TERMS_LIMIT;
            if ('' !== (string) $request['search']) {
                $args['search'] = (string) $request['search'];
            }
        }

        $terms = get_terms($args);
        if (!is_array($terms)) {
            return rest_ensure_response(array());
        }

        $result = array();
        foreach ($terms as $term) {
            $result[] = array(
                'id'            => (int) $term->term_id,
                'name'          => html_entity_decode($term->name, ENT_QUOTES, 'UTF-8'),
                'taxonomy'      => $term->taxonomy,
                'taxonomyLabel' => $taxonomies[$term->taxonomy]->labels->singular_name,
            );
        }
        return rest_ensure_response($result);
    }

    /**
     * The stored list with what the editor needs to know of each post; a post
     * the user may not read is shown by its id only.
     */
    private function manualList(int $galleryId): array
    {
        $ids = PostsSource::getManual($galleryId);
        if (!$ids) {
            return array();
        }
        _prime_post_caches($ids, false, true);

        $posts = array();
        foreach ($ids as $id) {
            $post = get_post($id);
            if ($post) {
                $posts[] = $post;
            }
        }
        $images = PostsSource::resolveImages($posts, PostsSource::getImages($galleryId));

        $result = array();
        foreach ($ids as $id) {
            $post = get_post($id);
            if (!$post || !current_user_can('read_post', $id)) {
                $result[] = array('id' => $id, 'title' => '#' . $id, 'status' => $post ? 'hidden' : 'missing', 'thumb' => '');
                continue;
            }

            $image = isset($images[$id]) ? $images[$id] : array(0, '');
            $entry = PostItem::forEditor(array('post' => $post, 'image' => $image[0], 'imageSource' => $image[1]));

            // shown only when published, without a password, of a type a gallery can show
            if (!in_array($post->post_type, PostsSource::postTypes(), true)) {
                $entry['status'] = 'type';
            } elseif ('publish' !== $post->post_status) {
                $entry['status'] = $post->post_status;
            } else {
                $entry['status'] = '' !== $post->post_password ? 'password' : 'shown';
            }
            $result[] = $entry;
        }
        return $result;
    }
}
