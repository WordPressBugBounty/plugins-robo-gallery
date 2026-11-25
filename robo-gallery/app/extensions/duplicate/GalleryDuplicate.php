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

namespace RoboGallery\app\extensions\duplicate;

use RoboGallery\app\PluginConstants;
use RoboGallery\app\extensions\cssCache\CssCache;
use RoboGallery\app\extensions\proofing\Proofing;
use RoboGallery\app\extensions\reactions\ImageLikes;
use RoboGallery\app\extensions\settings\DuplicatePlugin;

defined('WPINC') || exit;

/**
 * Galleries are cloned by the plugin Duplicate Post RB (settings\DuplicatePlugin).
 * Without it the galleries list has a "Clone" link to the Tools tab of the settings,
 * for the users who can install it; the former copy links go there too.
 *
 * With it, what a copy of a gallery must get is set here through its hooks, which
 * it also runs over REST, WP-CLI and cron: see GalleryCopyTransformer. Kept for
 * compatibility: the roboGalleryDuplicate_getCopyLink filter, the
 * robo_gallery_clone_gallery action and the _robogallery_original meta.
 */
class GalleryDuplicate
{
    // the former copy links
    const ACTION_CLONE = 'roboGalleryDuplicate_saveNewPost';
    const ACTION_DRAFT = 'roboGalleryDuplicate_saveNewPostDraft';

    const ORIGINAL_META_KEY = '_robogallery_original';

    public function register(): void
    {
        add_filter('rb_duplicate_post_supported_types', array($this, 'filterSupportedTypes'));
        add_filter('rb_duplicate_post_excluded_meta_keys', array($this, 'filterExcludedMetaKeys'));
        add_filter('rb_duplicate_post_new_post_data', array($this, 'filterNewPostData'), 10, 2);
        add_filter('rb_duplicate_post_after_copy_transformers', array($this, 'filterAfterCopyTransformers'));

        if (!is_admin()) {
            return;
        }

        // galleries are hierarchical, so the list uses page_row_actions
        add_filter('post_row_actions', array($this, 'addRowAction'), 10, 2);
        add_filter('page_row_actions', array($this, 'addRowAction'), 10, 2);

        add_action('admin_action_' . self::ACTION_CLONE, array($this, 'redirectOldLink'));
        add_action('admin_action_' . self::ACTION_DRAFT, array($this, 'redirectOldLink'));
    }

    /**
     * @param array    $actions
     * @param \WP_Post $post
     * @return array
     */
    public function addRowAction($actions, $post)
    {
        if (!$post instanceof \WP_Post || ROBO_GALLERY_TYPE_POST !== $post->post_type || !$this->canSetUp()) {
            return $actions;
        }

        $actions['clone'] = sprintf(
            '<a href="%s" title="%s">%s</a>',
            esc_url(apply_filters('roboGalleryDuplicate_getCopyLink', DuplicatePlugin::tabUrl(), $post->ID, 'display')),
            esc_attr__('Install Duplicate Post RB to clone galleries', 'robo-gallery'),
            esc_html__('Clone', 'robo-gallery')
        );

        return $actions;
    }

    public function redirectOldLink(): void
    {
        wp_safe_redirect(current_user_can('manage_options')
            ? DuplicatePlugin::tabUrl()
            : admin_url('edit.php?post_type=' . ROBO_GALLERY_TYPE_POST));
        exit;
    }

    /**
     * Versions before DuplicatePlugin::MIN_VERSION don't run the hooks below: they
     * would make copies without images and settings, so they offer no copy of a gallery.
     *
     * @param array $types
     * @return array
     */
    public function filterSupportedTypes($types)
    {
        if (DuplicatePlugin::isOutdated()) {
            unset($types[ROBO_GALLERY_TYPE_POST]);
        }

        return $types;
    }

    /**
     * Per-gallery bookkeeping: the copy has its own view count, likes and CSS cache id (the
     * source's would name the source's CSS files) and token (got on insert; the
     * source's would open the source's link), no proofing clients (their links would open
     * the copy too) or their picks. The filter has no post: these keys are
     * only on galleries.
     *
     * @param array $keys
     * @return array
     */
    public function filterExcludedMetaKeys($keys)
    {
        // the proofing metas also without the key: a copy made then must not open by the clients' links later
        return array_merge((array) $keys, array(
            PluginConstants::VIEWS_META_KEY,
            PluginConstants::TOKEN_META_KEY,
            self::ORIGINAL_META_KEY,
            ImageLikes::META,
            ROBO_GALLERY_PREFIX . CssCache::META,
        ), Proofing::META_KEYS);
    }

    /**
     * A copy carries the source's images, so a profile that doesn't copy passwords
     * must not leave the copy of a protected gallery open.
     *
     * @param array $data    the fields of the new post
     * @param mixed $context \rbDuplicatePost\Contexts\TransformerContext
     * @return array
     */
    public function filterNewPostData($data, $context)
    {
        if (GalleryCopyTransformer::supports($context)) {
            $data['post_password'] = get_post_field('post_password', $context->get_source()->get_post_id());
        }

        return $data;
    }

    /**
     * @param array $transformers
     * @return array
     */
    public function filterAfterCopyTransformers($transformers)
    {
        $transformers   = (array) $transformers;
        $transformers[] = new GalleryCopyTransformer();

        return $transformers;
    }

    /**
     * Whether the Clone link leads the current user anywhere: the settings need
     * manage_options, the button what its plugin status asks for.
     */
    private function canSetUp(): bool
    {
        return !DuplicatePlugin::isReady()
            && current_user_can('manage_options')
            && DuplicatePlugin::canEnable(DuplicatePlugin::status());
    }
}
