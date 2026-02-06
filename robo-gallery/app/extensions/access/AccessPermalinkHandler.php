<?php
/* @@copyright@ */

namespace RoboGallery\app\extensions\access;

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}

use WP_Post, RoboGallery\app\PluginConstants;

/**
 * Class AccessPermalinkHandler
 * Handles logic for generating and modifying gallery permalinks with access tokens.
 *
 * This includes:
 * - Checking if post is accessible for editing
 * - Verifying access mode and token presence
 * - Applying token to permalink via modifier
 */
class AccessPermalinkHandler
{
    private GalleryPermalinkModifier $permalinkModifier;

    public function __construct(GalleryPermalinkModifier $permalinkModifier)
    {
        $this->permalinkModifier = $permalinkModifier;
    }

    /**
     * Modifies gallery permalink to include token if conditions are met.
     *
     * @param string $permalink The current permalink.
     * @param WP_Post $post The post object.
     * @return string Modified permalink.
     */
    public function modify_permalink($permalink, $post): string
    {
        // Only for our post type
        if (get_post_type($post) !== ROBO_GALLERY_TYPE_POST) {
            return $permalink;
        }

        $id = (int) $post->ID;
        if ($id <= 0) {
            return $permalink;
        }

        // User must have permission to edit
        if (! current_user_can('edit_post', $id)) {
            return $permalink;
        }

        $options         = get_post_meta($id, PluginConstants::OPTIONS_KEY, true);
        $isPrivateEnable = ! empty($options[ PluginConstants::ACCESS_MODE_PRIVATE ]);
        $isTokenEnable   = ! empty($options[ PluginConstants::ACCESS_MODE_TOKEN ]);

        if (! $isPrivateEnable || ! $isTokenEnable) {
            return $permalink;
        }

        $token = get_post_meta($id, PluginConstants::TOKEN_META_KEY, true);
        if (! $token) {
            return $permalink;
        }

        return $this->permalinkModifier->add_token_to_permalink($permalink, $token);
    }
}
