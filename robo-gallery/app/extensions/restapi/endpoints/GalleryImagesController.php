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

use RoboGallery\app\extensions\restapi\models\GalleryModel;
use RoboGallery\app\extensions\restapi\models\ImageItem;

defined('WPINC') || exit;

/**
 * /robogallery/v1/{gallery_id}/images - the image list of a gallery for the
 * image manager (react_parts/options/src/imageManager): GET the images with their
 * data, PUT a new list { ids, revision }. .../images/{image_id}/galleries - the other galleries
 * that show an image. Stored in rsg_galleryImages, an array of ID strings -
 * the same as the old Images field saves with the post form.
 *
 * revision is a hash of the stored list: a PUT made from an older list (another
 * tab, the post form saved in between) gets 409 with the current one instead of
 * overwriting it.
 */
class GalleryImagesController extends BaseController
{
    const META_KEY = ROBO_GALLERY_PREFIX . 'galleryImages';

    // far above real galleries; keeps one request from storing an endless list
    const MAX_IMAGES = 10000;

    // the "Used in" list of an image: enough to see where it is, not a report
    const MAX_LISTED_GALLERIES = 100;

    public function __construct()
    {
        $this->rest_base = '(?P<gallery_id>[0-9]+)';
    }

    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/images',
            array(
                'args' => array(
                    'gallery_id' => $this->galleryIdArg(),
                ),
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_items'),
                    'permission_callback' => array($this, 'get_items_permissions_check'),
                ),
                array(
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'update_items'),
                    'permission_callback' => array($this, 'update_items_permissions_check'),
                    'args'                => array(
                        'ids'      => array(
                            'type'     => 'array',
                            'items'    => array('type' => 'integer', 'minimum' => 1),
                            'maxItems' => self::MAX_IMAGES,
                            'required' => true,
                        ),
                        'revision' => array(
                            'type'     => 'string',
                            'required' => true,
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/images/(?P<image_id>[0-9]+)/galleries',
            array(
                'args' => array(
                    'gallery_id' => $this->galleryIdArg(),
                    'image_id'   => array(
                        'type'    => 'integer',
                        'minimum' => 1,
                    ),
                ),
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_image_galleries'),
                    'permission_callback' => array($this, 'get_items_permissions_check'),
                ),
            )
        );

        // POST: the list goes in the body (it may be long), nothing is changed
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/images/duplicates',
            array(
                'args' => array(
                    'gallery_id' => $this->galleryIdArg(),
                ),
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'find_duplicates'),
                    'permission_callback' => array($this, 'duplicates_permissions_check'),
                    'args'                => array(
                        'ids' => array(
                            'type'     => 'array',
                            'items'    => array('type' => 'integer', 'minimum' => 1),
                            'maxItems' => self::MAX_IMAGES,
                            'required' => true,
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Edits the gallery and may see the media library (the files are compared).
     *
     * @param \WP_REST_Request $request
     * @return \WP_Error|bool
     */
    public function duplicates_permissions_check($request)
    {
        $canEdit = $this->checkCanEditGallery($request, 'robogallery_rest_cannot_edit', __('Sorry, you cannot edit this gallery.', 'robo-gallery'));
        if (true !== $canEdit) {
            return $canEdit;
        }

        return current_user_can('upload_files')
            ? true
            : new \WP_Error('robogallery_rest_cannot_upload', __('Sorry, you cannot see the media library.', 'robo-gallery'), array('status' => rest_authorization_required_code()));
    }

    /**
     * The images of the list that are the same: the same file content (by size first,
     * then md5 of the files of one size - most files are never read), or images of the
     * same video link. Each group keeps the order of the list; groups of one are left out.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response { groups: [{ ids, reason: file|video }] }
     */
    public function find_duplicates($request)
    {
        $ids = array_values(array_unique(array_map('intval', (array) $request->get_param('ids'))));
        _prime_post_caches($ids, false, true);

        $bySize  = array();
        $byVideo = array();
        foreach ($ids as $id) {
            if ('attachment' !== get_post_type($id)) {
                continue;
            }
            $video = (string) get_post_meta($id, ROBO_GALLERY_PREFIX . 'gallery_video_link', true);
            if ('' !== $video) {
                $byVideo[untrailingslashit(strtolower($video))][] = $id;
            }
            $file = get_attached_file($id);
            if ($file && is_file($file)) {
                $bySize[(int) filesize($file)][] = array($id, $file);
            }
        }

        $groups = array();
        $taken  = array();
        foreach ($byVideo as $group) {
            if (count($group) > 1) {
                $groups[] = array('ids' => $group, 'reason' => 'video');
                $taken    = array_merge($taken, $group);
            }
        }

        foreach ($bySize as $files) {
            if (count($files) < 2) {
                continue;
            }
            $byHash = array();
            foreach ($files as $entry) {
                list($id, $file) = $entry;
                if (!in_array($id, $taken, true)) {
                    $byHash[(string) md5_file($file)][] = $id;
                }
            }
            foreach ($byHash as $group) {
                if (count($group) > 1) {
                    $groups[] = array('ids' => $group, 'reason' => 'file');
                }
            }
        }

        // in the order of the list: the first image of a group comes first
        $position = array_flip($ids);
        usort($groups, function ($a, $b) use ($position) {
            return $position[$a['ids'][0]] - $position[$b['ids'][0]];
        });

        return rest_ensure_response(array('groups' => $groups));
    }

    /**
     * The other galleries that show an image: what deleting its file from the
     * library would change. Only galleries the user may read are listed; an
     * edit link only for those they may edit.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_image_galleries($request)
    {
        global $wpdb;

        $galleryId = (int) $request->get_param('gallery_id');
        $imageId   = (int) $request->get_param('image_id');

        // the meta is a serialized array of ID strings: s:2:"60"; - the quotes end the number
        $like = '%' . $wpdb->esc_like('"' . $imageId . '"') . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one search over the meta of all galleries, on demand in the editor
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
             WHERE p.post_type = %s
               AND p.post_status IN ('publish', 'future', 'draft', 'pending', 'private')
               AND p.ID <> %d
               AND m.meta_key = %s
               AND m.meta_value LIKE %s
             ORDER BY p.post_title
             LIMIT %d",
            ROBO_GALLERY_TYPE_POST,
            $galleryId,
            self::META_KEY,
            $like,
            self::MAX_LISTED_GALLERIES
        ));

        $galleries = array();
        foreach (array_map('intval', $ids) as $id) {
            // LIKE only finds candidates; the stored list decides
            if (!in_array($imageId, self::storedIds($id), true) || !current_user_can('read_post', $id)) {
                continue;
            }
            $galleries[] = array(
                'id'      => $id,
                'title'   => (string) get_post_field('post_title', $id, 'raw'), // the app shows it as text
                'status'  => get_post_status($id),
                'editUrl' => current_user_can('edit_post', $id) ? (string) get_edit_post_link($id, 'raw') : '',
            );
        }

        return rest_ensure_response($galleries);
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_Error|bool
     */
    public function get_items_permissions_check($request)
    {
        return $this->checkCanEditGallery($request, 'robogallery_rest_cannot_view', __('Sorry, you cannot edit this gallery.', 'robo-gallery'));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_Error|bool
     */
    public function update_items_permissions_check($request)
    {
        return $this->checkCanEditGallery($request, 'robogallery_rest_cannot_edit', __('Sorry, you cannot edit this gallery.', 'robo-gallery'));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_items($request)
    {
        return rest_ensure_response(self::payload((int) $request->get_param('gallery_id')));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_Error|\WP_REST_Response
     */
    public function update_items($request)
    {
        $galleryId = (int) $request->get_param('gallery_id');
        $stored    = self::storedIds($galleryId);

        if ((string) $request->get_param('revision') !== self::revision($stored)) {
            return new \WP_Error(
                'robogallery_images_conflict',
                __('The gallery images were changed in another window. Reload them and try again.', 'robo-gallery'),
                array('status' => 409, 'current' => self::payload($galleryId))
            );
        }

        // the order of the first occurrence; the schema already made them positive integers
        $ids = array_values(array_unique(array_map('intval', (array) $request->get_param('ids'))));

        // ids already in the gallery may stay even if deleted from the library (the
        // user removes them on purpose); new ones must be images the user may add
        $added = array_values(array_diff($ids, $stored));
        if ($added) {
            if (!current_user_can('upload_files')) {
                return new \WP_Error(
                    'robogallery_images_cannot_add',
                    __('Sorry, you cannot add images from the media library.', 'robo-gallery'),
                    array('status' => rest_authorization_required_code())
                );
            }

            $invalid = array_values(array_filter($added, array(__CLASS__, 'isNotImage')));
            if ($invalid) {
                return new \WP_Error(
                    'robogallery_images_invalid',
                    __('Some of the selected files are not images of the media library.', 'robo-gallery'),
                    array('status' => 400, 'ids' => $invalid)
                );
            }
        }

        update_post_meta($galleryId, self::META_KEY, array_map('strval', $ids));

        // no save_post here (the post itself is not saved): clean_post_cache is what the
        // object cache and page cache plugins listen to, so the gallery page is not left stale
        clean_post_cache($galleryId);

        /**
         * The image list of a gallery was saved by the image manager (REST).
         *
         * @param int   $galleryId
         * @param int[] $ids the new list
         */
        do_action('robo_gallery_images_saved', $galleryId, $ids);

        return rest_ensure_response(self::payload($galleryId));
    }

    /**
     * @return array { revision, items }
     */
    private static function payload(int $galleryId): array
    {
        $ids = self::storedIds($galleryId);

        return array(
            'revision' => self::revision($ids),
            'items'    => ImageItem::fromIds($ids),
        );
    }

    /**
     * @return int[] the stored order, as the frontend reads it
     */
    private static function storedIds(int $galleryId): array
    {
        return GalleryModel::get_gallery_images($galleryId);
    }

    private static function revision(array $ids): string
    {
        return md5(implode(',', $ids));
    }

    private static function isNotImage(int $id): bool
    {
        $post = get_post($id);

        return !$post || 'attachment' !== $post->post_type || 0 !== strpos((string) get_post_mime_type($post), 'image/');
    }
}
