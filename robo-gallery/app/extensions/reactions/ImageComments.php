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

namespace RoboGallery\app\extensions\reactions;

defined('WPINC') || exit;

/**
 * The comments on the images of a gallery: WordPress comments of the type TYPE on
 * the gallery post, the image in the comment meta IMAGE_META. The only reader and
 * writer of them; they are made by wp_new_comment(), so flood and duplicate checks,
 * the Discussion settings (moderation, disallowed words) and anti-spam plugins work
 * as for any comment, and they are moderated on the Comments screen.
 *
 * They are as private as the gallery (access\GalleryAccessManager, checked by the REST
 * routes), so they are kept out of every other place WordPress shows comments: the
 * comment queries outside the admin (the gallery page's comments, widgets, blocks),
 * the comment feeds, core REST and the gallery's comment count.
 */
class ImageComments
{
    const TYPE = 'robo_gallery_image';

    const IMAGE_META = ROBO_GALLERY_PREFIX . 'image_id';

    const PER_PAGE = 20;

    const MAX_LENGTH = 2000;

    public static function register(): void
    {
        add_action('pre_get_comments', array(__CLASS__, 'hideFromQueries'));
        add_filter('comment_feed_where', array(__CLASS__, 'hideFromFeeds'));
        add_filter('rest_request_before_callbacks', array(__CLASS__, 'hideFromCoreRest'), 10, 3);
        add_filter('pre_wp_update_comment_count_now', array(__CLASS__, 'postCommentCount'), 10, 3);
        add_action('delete_attachment', array(__CLASS__, 'deleteOfImage'));
    }

    /**
     * @return array{comments: array[], total: int, pages: int} newest first
     */
    public static function page(int $galleryId, int $imageId, int $page): array
    {
        $userId = get_current_user_id();
        $query  = new \WP_Comment_Query();
        $found  = $query->query(self::queryArgs($galleryId, $imageId) + array(
            'status'             => 'approve',
            // the visitor's own comments that wait for moderation, marked so
            'include_unapproved' => $userId ? array($userId) : array(),
            'orderby'            => array('comment_date_gmt', 'comment_ID'),
            'order'              => 'DESC',
            'number'             => self::PER_PAGE,
            'paged'              => max(1, $page),
            'no_found_rows'      => false,
        ));

        return array(
            'comments' => array_map(array(__CLASS__, 'item'), $found),
            'total'    => (int) $query->found_comments,
            'pages'    => (int) $query->max_num_pages,
        );
    }

    /**
     * @return int the approved comments of the image
     */
    public static function count(int $galleryId, int $imageId): int
    {
        return (int) get_comments(self::queryArgs($galleryId, $imageId) + array(
            'status' => 'approve',
            'count'  => true,
        ));
    }

    /**
     * The approved comments of all the images of a gallery in one query, for the comment buttons of its cards.
     *
     * @param int[] $imageIds the gallery's images: comments of images taken out of it are left out
     * @return array<int, int> image id => count (only the images that have some)
     */
    public static function countsOfGallery(int $galleryId, array $imageIds): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT cm.meta_value AS image, COUNT(*) AS comments
             FROM {$wpdb->comments} c
             JOIN {$wpdb->commentmeta} cm ON cm.comment_id = c.comment_ID AND cm.meta_key = %s
             WHERE c.comment_post_ID = %d AND c.comment_type = %s AND c.comment_approved = '1'
             GROUP BY cm.meta_value",
            self::IMAGE_META,
            $galleryId,
            self::TYPE
        ));

        $inGallery = array_flip($imageIds);
        $counts    = array();
        foreach ($rows as $row) {
            $imageId = (int) $row->image;
            if (isset($inGallery[$imageId])) {
                $counts[$imageId] = (int) $row->comments;
            }
        }

        return $counts;
    }

    /**
     * A comment of the current (logged-in) user.
     *
     * @param string $content plain text
     * @return array|\WP_Error the new comment as item()
     */
    public static function add(int $galleryId, int $imageId, string $content)
    {
        $user = wp_get_current_user();
        if (!$user->exists()) {
            return new \WP_Error('robo_gallery_comment_login', __('Log in to comment.', 'robo-gallery'), array('status' => 401));
        }

        $content = trim(sanitize_textarea_field($content));
        if ('' === $content) {
            return new \WP_Error('robo_gallery_comment_empty', __('The comment is empty.', 'robo-gallery'), array('status' => 400));
        }
        if (mb_strlen($content) > self::MAX_LENGTH) {
            return new \WP_Error(
                'robo_gallery_comment_long',
                /* translators: %d: the most characters a comment may have */
                sprintf(__('The comment is longer than %d characters.', 'robo-gallery'), self::MAX_LENGTH),
                array('status' => 400)
            );
        }

        $commentId = wp_new_comment(wp_slash(array(
            'comment_post_ID'      => $galleryId,
            'comment_type'         => self::TYPE,
            'comment_parent'       => 0,
            'comment_content'      => $content,
            'user_id'              => $user->ID,
            'comment_author'       => $user->display_name,
            'comment_author_email' => $user->user_email,
            'comment_author_url'   => $user->user_url,
            'comment_meta'         => array(self::IMAGE_META => $imageId),
        )), true);

        if (!$commentId) {
            return new \WP_Error('robo_gallery_comment_failed', __('The comment could not be saved.', 'robo-gallery'), array('status' => 500));
        }
        if (is_wp_error($commentId)) {
            // the messages of wp_allow_comment(): "duplicate", "too fast"
            $status = 'comment_duplicate' === $commentId->get_error_code() ? 409 : 429;
            return new \WP_Error($commentId->get_error_code(), $commentId->get_error_message(), array('status' => $status));
        }

        $comment = get_comment($commentId);
        if (!$comment || 'spam' === $comment->comment_approved || 'trash' === $comment->comment_approved) {
            return new \WP_Error('robo_gallery_comment_refused', __('The comment was not accepted.', 'robo-gallery'), array('status' => 403));
        }

        return self::item($comment);
    }

    /**
     * Into the trash: by its author or by who may edit it.
     *
     * @return true|\WP_Error
     */
    public static function delete(int $galleryId, int $imageId, int $commentId)
    {
        $comment = self::find($galleryId, $imageId, $commentId);
        if (!$comment) {
            return new \WP_Error('robo_gallery_comment_not_found', __('The comment was not found.', 'robo-gallery'), array('status' => 404));
        }
        if (!self::canDelete($comment)) {
            return new \WP_Error('robo_gallery_comment_forbidden', __('You cannot delete this comment.', 'robo-gallery'), array('status' => 403));
        }

        wp_trash_comment($comment);

        return true;
    }

    /**
     * @return array the form the gallery's script shows; the text is plain (the script shows it as text)
     */
    public static function item(\WP_Comment $comment): array
    {
        $timestamp = strtotime($comment->comment_date_gmt . ' GMT');

        return array(
            'id'        => (int) $comment->comment_ID,
            'author'    => html_entity_decode(wp_strip_all_tags($comment->comment_author), ENT_QUOTES, 'UTF-8'),
            'avatar'    => get_option('show_avatars') ? (string) get_avatar_url($comment, array('size' => 64)) : '',
            'date'      => gmdate('c', $timestamp),
            'dateText'  => wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp),
            // a comment edited on the Comments screen may have HTML
            'content'   => html_entity_decode(wp_strip_all_tags($comment->comment_content), ENT_QUOTES, 'UTF-8'),
            'pending'   => '1' !== (string) $comment->comment_approved,
            'canDelete' => self::canDelete($comment),
        );
    }

    /* ---------------- kept out of the rest of WordPress ---------------- */

    /**
     * Outside the admin a comment query gets these comments only when it asks for their type.
     *
     * @param \WP_Comment_Query $query
     */
    public static function hideFromQueries($query): void
    {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        $vars  = $query->query_vars;
        $asked = array_merge((array) $vars['type'], (array) $vars['type__in']);
        if (in_array(self::TYPE, $asked, true)) {
            return;
        }

        // an empty value would become the old empty comment type, excluded with it
        $notIn = array_filter((array) $vars['type__not_in'], 'strlen');
        $query->query_vars['type__not_in'] = array_merge($notIn, array(self::TYPE));
    }

    /**
     * @param string $where
     * @return string
     */
    public static function hideFromFeeds($where)
    {
        global $wpdb;

        return $where . $wpdb->prepare(" AND {$wpdb->comments}.comment_type != %s", self::TYPE);
    }

    /**
     * Core's /wp/v2/comments lists them only for editors (the type parameter), but a single
     * comment is given to anyone who can read its post: not these, unless the user may edit it.
     *
     * @param mixed            $response
     * @param array            $handler
     * @param \WP_REST_Request $request
     * @return mixed
     */
    public static function hideFromCoreRest($response, $handler, $request)
    {
        if (!$request instanceof \WP_REST_Request || 1 !== preg_match('#^/wp/v2/comments/(\d+)#', $request->get_route(), $match)) {
            return $response;
        }

        $comment = get_comment((int) $match[1]);
        if ($comment && self::TYPE === $comment->comment_type && !current_user_can('edit_comment', $comment->comment_ID)) {
            return new \WP_Error('rest_comment_invalid_id', __('Invalid comment ID.', 'robo-gallery'), array('status' => 404));
        }

        return $response;
    }

    /**
     * The gallery's comment count (shown by themes) without the comments of its images.
     *
     * @param int|null $count
     * @param int      $old
     * @param int      $postId
     * @return int|null
     */
    public static function postCommentCount($count, $old, $postId)
    {
        if (null !== $count || ROBO_GALLERY_TYPE_POST !== get_post_type($postId)) {
            return $count;
        }

        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_approved = '1' AND comment_type != %s",
            $postId,
            self::TYPE
        ));
    }

    /**
     * An image deleted from the media library: its comments in every gallery.
     *
     * @param int $imageId
     */
    public static function deleteOfImage($imageId): void
    {
        $ids = get_comments(array(
            'type'       => self::TYPE,
            'meta_key'   => self::IMAGE_META,
            'meta_value' => (int) $imageId,
            'status'     => 'all',
            'fields'     => 'ids',
            'number'     => 0,
        ));

        foreach ($ids as $id) {
            wp_delete_comment((int) $id, true);
        }
    }

    /* ---------------- */

    private static function queryArgs(int $galleryId, int $imageId): array
    {
        return array(
            'post_id'    => $galleryId,
            'type'       => self::TYPE,
            'meta_key'   => self::IMAGE_META,
            'meta_value' => $imageId,
        );
    }

    /**
     * @return \WP_Comment|null a comment of that image in that gallery, not in the trash or spam
     */
    private static function find(int $galleryId, int $imageId, int $commentId)
    {
        $comment = get_comment($commentId);
        if (!$comment
            || self::TYPE !== $comment->comment_type
            || $galleryId !== (int) $comment->comment_post_ID
            || $imageId !== (int) get_comment_meta($commentId, self::IMAGE_META, true)
            || !in_array((string) $comment->comment_approved, array('0', '1'), true)
        ) {
            return null;
        }

        return $comment;
    }

    private static function canDelete(\WP_Comment $comment): bool
    {
        $userId = get_current_user_id();
        if (!$userId) {
            return false;
        }

        return $userId === (int) $comment->user_id || current_user_can('edit_comment', $comment->comment_ID);
    }
}
