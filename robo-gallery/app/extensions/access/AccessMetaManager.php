<?php
/* @@copyright@ */

namespace RoboGallery\app\extensions\access;

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}
use WP_Post, RoboGallery\app\PluginConstants;

/**
 * Class AccessMetaManager
 * Handles all business logic related to access metadata changes.
 *
 * This includes:
 * - Generating tokens on post save
 * - Handling changes in accessModePrivate and accessModePassword
 * - Managing password updates via wp_update_post
 */
class AccessMetaManager
{
    private GalleryTokenGenerator $tokenGenerator;
    private AccessPasswordManager $passwordManager;
    private ExcludeListManager $excludeManager;

    public function __construct(
        GalleryTokenGenerator $tokenGenerator,
        AccessPasswordManager $passwordManager,
        ExcludeListManager $excludeManager
    ) {
        $this->tokenGenerator  = $tokenGenerator;
        $this->passwordManager = $passwordManager;
        $this->excludeManager  = $excludeManager;
    }

    /**
     * Generate token if not exists and post is of correct type.
     *
     * @param int      $post_id The post ID.
     * @param WP_Post  $post    The post object.
     * @param bool     $update  Whether this is an update.
     * @return void
     */
    public function handle_token_generation($post_id, $post, $update): void
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return;
        }

        $parent_id = wp_is_post_revision($post_id);
        if (false !== $parent_id) {
            $post_id = $parent_id;
        }

        if (get_post_type($post_id) !== ROBO_GALLERY_TYPE_POST) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $token = get_post_meta($post_id, PluginConstants::TOKEN_META_KEY, true);
        if ($update && ! empty($token)) {
            return;
        }

        $token_new = $this->tokenGenerator->generate();
        update_post_meta($post_id, PluginConstants::TOKEN_META_KEY, $token_new);
    }

    /**
     * Handle change in access mode or password.
     *
     * @param mixed    $check
     * @param int      $object_id
     * @param string   $meta_key
     * @param array    $meta_value
     * @param mixed    $prev_value
     * @return mixed
     */
    public function handle_access_mode_change($check, $object_id, $meta_key, $meta_value, $prev_value)
    {
        if ($check === false) {
            return false; // Short-circuit if another handler has denied the update
        }

        $object_id = (int) $object_id;

        if (PluginConstants::OPTIONS_KEY !== $meta_key) {
            return $check;
        }

        if (! is_array($meta_value) || ! isset($meta_value[ PluginConstants::ACCESS_MODE_PRIVATE ])) {
            return $check;
        }

        $isPrivateNow    = (bool) $meta_value[ PluginConstants::ACCESS_MODE_PRIVATE ];
        $oldOptions      = get_post_meta($object_id, PluginConstants::OPTIONS_KEY, true);
        $isPrivateBefore = isset($oldOptions[ PluginConstants::ACCESS_MODE_PRIVATE ]) ? (bool) $oldOptions[ PluginConstants::ACCESS_MODE_PRIVATE ] : false;

        // No change — handle password only
        if ($isPrivateNow === $isPrivateBefore) {
            if ($isPrivateNow && isset($meta_value[  PluginConstants::ACCESS_MODE_PASSWORD  ]) && $meta_value[ PluginConstants::ACCESS_MODE_PASSWORD ]) {
                $this->passwordManager->set_password($object_id, $meta_value[ PluginConstants::ACCESS_MODE_PASSWORD ]);
            } else {
                $this->passwordManager->clear_password($object_id);
            }
            return $check;
        }

        // Change detected
        if ($isPrivateNow) {
            $this->excludeManager->add($object_id);
            if (isset($meta_value[ PluginConstants::ACCESS_MODE_PASSWORD ]) && $meta_value[ PluginConstants::ACCESS_MODE_PASSWORD ]) {
                $this->passwordManager->set_password($object_id, $meta_value[ PluginConstants::ACCESS_MODE_PASSWORD ]);
            }
        } else {
            $this->excludeManager->remove($object_id);
            $this->passwordManager->clear_password($object_id);
        }

        return $check;
    }
}
