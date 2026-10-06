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

namespace RoboGallery\app\extensions\galleryType;

defined('WPINC') || exit;

/**
 * "Change gallery type" in the editor: the type dialog sends the editor to
 * post.php?action=edit&_rgnonce=..&robo-gallery-newtype=<source>&post=<id>
 * (TypeDialog builds the link), the new type is saved and a notice is shown.
 */
class TypeChange
{
    // query arg carrying the nonce in the type dialog's changeUrl
    const NONCE_NAME = '_rgnonce';

    const NEW_TYPE_ARG = 'robo-gallery-newtype';

    public static function getNonceAction($postId): string
    {
        return 'robo_gallery_change_type_' . (int) $postId;
    }

    public function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('wp_loaded', array($this, 'changeType'), 999);
        add_action('admin_notices', array($this, 'showNotice'));
    }

    public function changeType(): void
    {
        $newSource = isset($_GET[self::NEW_TYPE_ARG]) && is_string($_GET[self::NEW_TYPE_ARG]) ? $_GET[self::NEW_TYPE_ARG] : '';
        if (!$newSource || !GalleryTypeList::isValidSource($newSource)) {
            return;
        }

        $postId = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        if (!$postId || !current_user_can('edit_post', $postId) || get_post_type($postId) !== ROBO_GALLERY_TYPE_POST) {
            return;
        }

        // The change is a plain GET link, so without a nonce any page could make
        // a logged-in editor switch a gallery's type (CSRF).
        $nonce = isset($_GET[self::NONCE_NAME]) ? sanitize_text_field(wp_unslash($_GET[self::NONCE_NAME])) : '';
        if (!wp_verify_nonce($nonce, self::getNonceAction($postId))) {
            wp_nonce_ays('');
        }

        $type = GalleryTypeList::getTypeBySource($newSource);
        if (!$type) {
            return;
        }

        update_post_meta($postId, ROBO_GALLERY_PREFIX . 'gallery_type', $type);
        update_post_meta($postId, ROBO_GALLERY_PREFIX . 'gallery_type_source', $newSource);

        if (wp_redirect(get_edit_post_link($postId, 'edit'))) {
            set_transient(self::noticeKey($postId), __('Gallery type has been successfully changed.', 'robo-gallery'));
            exit;
        }
    }

    public function showNotice(): void
    {
        $postId = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        if (!$postId || !current_user_can('edit_post', $postId)) {
            return;
        }

        $message = get_transient(self::noticeKey($postId));
        if (!$message) {
            return;
        }

        delete_transient(self::noticeKey($postId));
        printf(
            '<div class="notice notice-success is-dismissible robogallery_change_gallery_type_notice"><p>%s</p></div>',
            esc_html($message)
        );
    }

    private static function noticeKey($postId): string
    {
        return get_current_user_id() . '_change_gallery_' . (int) $postId . '_type_ok';
    }
}
