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

namespace RoboGallery\app\extensions\duplicate;

use RoboGallery\app\PluginConstants;
use RoboGallery\app\extensions\access\ExcludeListManager;

defined('WPINC') || exit;

/**
 * "Clone" / "New Draft" row actions in the galleries list: copies a gallery
 * with its settings and images (all post meta) as a new gallery.
 * Kept for compatibility: the admin action names in the links, the
 * roboGalleryDuplicate_getCopyLink filter, the robo_gallery_clone_gallery
 * action (meta is copied on it) and the _robogallery_original meta.
 */
class GalleryDuplicate
{
    const NONCE_KEY    = 'robo-gallery-duplicate'; // query arg
    const NONCE_ACTION = 'duplicate_gallery_';     // + gallery ID

    const ACTION_CLONE = 'roboGalleryDuplicate_saveNewPost';
    const ACTION_DRAFT = 'roboGalleryDuplicate_saveNewPostDraft';

    const ORIGINAL_META_KEY = '_robogallery_original';

    // statuses a copy may keep; anything else (trash, auto-draft...) becomes a draft
    const COPY_STATUSES = array('publish', 'future', 'private', 'pending', 'draft');

    public function register(): void
    {
        // galleries are hierarchical, so the list uses page_row_actions
        add_filter('post_row_actions', array($this, 'addRowActions'), 10, 2);
        add_filter('page_row_actions', array($this, 'addRowActions'), 10, 2);

        add_action('admin_action_' . self::ACTION_CLONE, array($this, 'handleClone'));
        add_action('admin_action_' . self::ACTION_DRAFT, array($this, 'handleDraft'));

        add_action('robo_gallery_clone_gallery', array($this, 'copyMeta'), 10, 2);

        if (isset($_GET['robo-gallery-after-clone'])) {
            add_action('admin_notices', array($this, 'renderClonedNotice'));
        }
    }

    /**
     * @param array    $actions
     * @param \WP_Post $post
     * @return array
     */
    public function addRowActions($actions, $post)
    {
        if (!$this->canCopy($post)) {
            return $actions;
        }

        $actions['clone'] = sprintf(
            '<a href="%s" title="%s">%s</a>',
            esc_url($this->getCopyLink($post->ID, 'display', false)),
            esc_attr__('Clone this item', 'robo-gallery'),
            esc_html__('Clone', 'robo-gallery')
        );
        $actions['edit_as_new_draft'] = sprintf(
            '<a href="%s" title="%s">%s</a>',
            esc_url($this->getCopyLink($post->ID)),
            esc_attr__('Copy to a new draft', 'robo-gallery'),
            esc_html__('New Draft', 'robo-gallery')
        );

        return $actions;
    }

    /**
     * @param int    $id
     * @param string $context passed to the roboGalleryDuplicate_getCopyLink filter
     * @param bool   $draft   true: new draft, false: copy with the same status
     * @return string '' when the gallery can't be copied by the current user
     */
    public function getCopyLink($id, $context = 'display', $draft = true)
    {
        $post = get_post($id);
        if (!$this->canCopy($post)) {
            return '';
        }

        $url = add_query_arg(array(
            'action'        => $draft ? self::ACTION_DRAFT : self::ACTION_CLONE,
            'post'          => $post->ID,
            self::NONCE_KEY => wp_create_nonce(self::NONCE_ACTION . $post->ID),
        ), admin_url('admin.php'));

        return apply_filters('roboGalleryDuplicate_getCopyLink', $url, $post->ID, $context);
    }

    public function handleClone(): void
    {
        $this->handleCopy(false);
    }

    public function handleDraft(): void
    {
        $this->handleCopy(true);
    }

    public function renderClonedNotice(): void
    {
        echo '<div class="notice notice-success is-dismissible"><p>'
            . esc_html__('Gallery cloned successfully ', 'robo-gallery')
            . '</p></div>';
    }

    /**
     * robo_gallery_clone_gallery: every meta of the source except per-post
     * bookkeeping (edit locks, old slugs, the view counter, the secret token -
     * the copy got its own on insert, sharing it would open the source's link).
     *
     * @param int      $new_id
     * @param \WP_Post $post
     */
    public function copyMeta($new_id, $post): void
    {
        $skip = array(
            '_edit_lock',
            '_edit_last',
            '_wp_old_slug',
            '_wp_old_date',
            '_wpas_done_all', // Jetpack Publicize
            '_wpas_mess',
            'gallery_views_count',
            PluginConstants::TOKEN_META_KEY,
            self::ORIGINAL_META_KEY,
        );

        foreach ((array) get_post_custom_keys($post->ID) as $meta_key) {
            if (in_array($meta_key, $skip, true) || 0 === strpos($meta_key, '_wpas_done_')) {
                continue;
            }

            foreach ((array) get_post_custom_values($meta_key, $post->ID) as $meta_value) {
                // add_post_meta() unslashes, so slash first or backslashes in values are lost
                add_post_meta($new_id, $meta_key, wp_slash(maybe_unserialize($meta_value)));
            }
        }
    }

    /**
     * @param \WP_Post $post
     * @param string   $status '' = the source's status
     * @return int the new gallery ID
     */
    public function createCopy($post, $status = '')
    {
        $status = '' === $status ? $post->post_status : $status;
        if (!in_array($status, self::COPY_STATUSES, true)) {
            $status = 'draft';
        }

        // wp_insert_post() doesn't check capabilities, so a user who can't publish
        // (e.g. Contributor) must not get a published/scheduled/private copy.
        $post_type_object = get_post_type_object($post->post_type);
        if (
            in_array($status, array('publish', 'future', 'private'), true)
            && (!$post_type_object || !current_user_can($post_type_object->cap->publish_posts))
        ) {
            $status = 'draft';
        }

        $title = '' === $post->post_title ? __('Untitled') : $post->post_title; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress

        // wp_insert_post() expects slashed data
        $new_id = wp_insert_post(wp_slash(array(
            'menu_order'            => $post->menu_order,
            'comment_status'        => $post->comment_status,
            'ping_status'           => $post->ping_status,
            'post_author'           => get_current_user_id(),
            'post_content'          => $post->post_content,
            'post_content_filtered' => $post->post_content_filtered,
            'post_excerpt'          => $post->post_excerpt,
            'post_mime_type'        => $post->post_mime_type,
            'post_parent'           => $post->post_parent,
            'post_password'         => $post->post_password,
            'post_status'           => $status,
            'post_title'            => trim('copy ' . $title),
            'post_type'             => $post->post_type,
        )), true);

        if (is_wp_error($new_id) || !$new_id) {
            wp_die(esc_html__('Copy creation failed.', 'robo-gallery'));
        }

        if ('publish' === $status || 'future' === $status) {
            wp_update_post(array(
                'ID'        => $new_id,
                'post_name' => wp_unique_post_slug($post->post_name, $new_id, $status, $post->post_type, $post->post_parent),
            ));
        }

        do_action('robo_gallery_clone_gallery', $new_id, $post);

        // Meta is copied with add_post_meta(), which skips the update_post_metadata
        // hook that normally puts direct-link-only galleries on the exclude list,
        // so such a copy would otherwise show up in listings/search/sitemap.
        (new ExcludeListManager())->syncGallery((int) $new_id);

        update_post_meta($new_id, self::ORIGINAL_META_KEY, $post->ID);

        return $new_id;
    }

    /**
     * A copy carries the source's images, settings and password, so copying
     * requires the same right as opening the source in the editor.
     *
     * @param mixed $post
     */
    private function canCopy($post): bool
    {
        return $post instanceof \WP_Post
            && ROBO_GALLERY_TYPE_POST === $post->post_type
            && current_user_can('edit_posts')
            && current_user_can('edit_post', $post->ID);
    }

    private function handleCopy(bool $draft): void
    {
        $id = isset($_REQUEST['post']) ? absint($_REQUEST['post']) : 0;
        if (!$id) {
            wp_die(esc_html__('No gallery to copy has been supplied!', 'robo-gallery'));
        }

        check_admin_referer(self::NONCE_ACTION . $id, self::NONCE_KEY);

        $post = get_post($id);
        if (!$post) {
            wp_die(esc_html__('Copy creation failed, could not find original:', 'robo-gallery') . ' ' . esc_html($id));
        }
        if (!$this->canCopy($post)) {
            wp_die(esc_html__('Sorry, you are not allowed to copy this gallery.', 'robo-gallery'), '', array('response' => 403));
        }

        $new_id = $this->createCopy($post, $draft ? 'draft' : '');

        if ($draft) {
            $redirect = add_query_arg(array('cloned' => 1, 'ids' => $post->ID), admin_url('post.php?action=edit&post=' . $new_id));
        } else {
            $redirect = add_query_arg(
                array('robo-gallery-after-clone' => 1, 'cloned' => 1, 'ids' => $post->ID),
                admin_url('edit.php?post_type=' . ROBO_GALLERY_TYPE_POST)
            );
        }

        wp_safe_redirect($redirect);
        exit;
    }
}
