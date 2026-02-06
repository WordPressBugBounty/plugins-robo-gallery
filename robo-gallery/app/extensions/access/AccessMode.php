<?php
/* @@copyright@ */

namespace RoboGallery\app\extensions\access;

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}

use RoboGallery\app\PluginConstants;

/**
 * Class AccessMode
 * Handles access restrictions for galleries.
 */
class AccessMode
{
    private GalleryAccessManager $accessManager;
    private GalleryRewriteRuleManager $rewriteManager;
    private FilterPrivateGallery $filterPrivateGallery;
    private AccessMetaManager $metaManager;
    private AccessPermalinkHandler $permalinkHandler;

    /**
     * Constructor.
     * Initializes hooks when the class is instantiated.
     */
    public function __construct(
        GalleryAccessManager $accessManager,
        AccessPermalinkHandler $permalinkHandler,
        GalleryRewriteRuleManager $rewriteManager,
        FilterPrivateGallery $filterPrivateGallery,
        AccessMetaManager $metaManager
    ) {
        $this->accessManager        = $accessManager;
        $this->rewriteManager       = $rewriteManager;
        $this->filterPrivateGallery = $filterPrivateGallery;
        $this->metaManager          = $metaManager;
        $this->permalinkHandler     = $permalinkHandler;

        $this->registerHooks();
    }

    /**
     * Register WordPress hooks for access control.
     *
     * @return void
     */
    public function registerHooks()
    {
        $this->registerPostSaveHook();

        $this->registerTemplateRedirectHook();

        $this->registerRewriteRules();

        $this->registerPermalinkFilter();

        $this->registerMetadataFilter();
    }

    /**
     * Register the post save hook to handle token generation.
     *
     * @return void
     */
    private function registerPostSaveHook(): void
    {
        add_action('save_post_' . ROBO_GALLERY_TYPE_POST, [ $this, 'on_save_post' ], 10, 3);
    }

    /**
     * Register the template redirect hook to check access before rendering.
     *
     * @return void
     */
    private function registerTemplateRedirectHook(): void
    {
        add_action('template_redirect', [ $this, 'check_access' ]);
    }

    /**
     * Register the rewrite rules for gallery access with tokens.
     *
     * @return void
     */
    private function registerRewriteRules(): void
    {
        add_action('init', [ $this, 'add_rewrite_rule' ]);
    }

    /**
     * Register the permalink filter to modify gallery links.
     *
     * @return void
     */
    private function registerPermalinkFilter(): void
    {
        add_filter('post_type_link', [ $this, 'on_post_type_link' ], 10, 2);
    }

    /**
     * Register the metadata filter to handle access mode changes.
     *
     * @return void
     */
    private function registerMetadataFilter(): void
    {
        add_filter('update_post_metadata', [ $this, 'on_update_post_metadata' ], 10, 5);
    }

    /**
     * Modify the permalink to include access token if applicable.
     *
     * @param string  $permalink The current permalink.
     * @param WP_Post $post      The post object.
     * @return string Modified permalink.
     */
    public function on_post_type_link($permalink, $post): string
    {
        return $this->permalinkHandler->modify_permalink($permalink, $post);
    }

    /**
     * Handle changes to post metadata, specifically access mode changes.
     *
     * @param mixed    $check
     * @param int      $object_id
     * @param string   $meta_key
     * @param array    $meta_value
     * @param mixed    $prev_value
     * @return mixed
     */
    public function on_update_post_metadata($check, $object_id, $meta_key, $meta_value, $prev_value)
    {
        return $this->metaManager->handle_access_mode_change($check, $object_id, $meta_key, $meta_value, $prev_value);
    }

    /**
     * Handle post save to generate access token if needed.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an update.
     * @return void
     */
    public function on_save_post($post_id, $post, $update): void
    {
        $this->metaManager->handle_token_generation($post_id, $post, $update);
    }

    /**
     * Add custom rewrite rule and query variable for unlisted galleries.
     *
     * @return void
     */
    public function add_rewrite_rule(): void
    {
        $this->rewriteManager->register();
    }

    /**
     * Check access permissions before rendering a gallery post.
     *
     * @return void
     */
    public function check_access(): void
    {
        global $post;
        if (! $post || get_post_type($post) !== ROBO_GALLERY_TYPE_POST) {
            return;
        }
        $this->accessManager->checkAccess($post->ID);
    }

}
