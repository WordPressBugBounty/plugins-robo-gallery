<?php
/* @@copyright@ */

namespace RoboGallery\app\extensions\access;

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}
use RoboGallery\app\PluginConstants;

/**
 * Class GalleryAccessManager
 * Manages access control for galleries based on their settings.
 */
class GalleryAccessManager
{

    /* Check if the gallery is set to private mode */
    public function isPrivate($post_id): bool
    {
        $options = get_post_meta($post_id, PluginConstants::OPTIONS_KEY, true);
        return !empty($options[PluginConstants::ACCESS_MODE_PRIVATE]);
    }

    /* Check if the gallery uses token-based access */
    public function hasTokenMode($post_id): bool
    {
        $options = get_post_meta($post_id, PluginConstants::OPTIONS_KEY, true);
        return !empty($options[PluginConstants::ACCESS_MODE_TOKEN]);
    }

    /* Enforce access control based on gallery settings */
    public function checkAccess($post_id): void
    {
        if (!is_singular(ROBO_GALLERY_TYPE_POST)) {
            return;
        }

        // global $post;
        // if (!$post || $post->ID !== $post_id) {
        //     return;
        // }

        if (!$this->isPrivate($post_id)) {
            return;
        }

        if ($this->canBypassAccess($post_id)) {
            return;
        }

        if ($this->hasTokenMode($post_id)) {
            $token = get_post_meta($post_id, PluginConstants::TOKEN_META_KEY, true);
            if(empty($token)) {
                // show error if token is not exists but access mode is token
                wp_die(esc_html__('Access denied.[ token not exists ]', 'robo-gallery'), 'Error 403', ['response' => 403]);
            }
            $url_token = sanitize_text_field(get_query_var( PluginConstants::QUERY_VAR_TOKEN ));

            if (!hash_equals($token, $url_token)) {
                //wp_redirect( home_url() ); exit;
                wp_die(esc_html__('Access denied.', 'robo-gallery'), 'Error 403', ['response' => 403]);
            }
            return;
        }

        return ;

        // TODO:  Implement other access modes (e.g., user-based) here
        //wp_die(esc_html__('Access denied.', 'robo-gallery'), 'Error 403', ['response' => 403]);


    // Selected users mode: check user authentication and permission
    // if ($mode === 'users') {

    //     $allowedUsers = [];
    //     if (isset($options['accessUsers'])) {
    //         $allowedUsers = (array) $options['accessUsers'];
    //     }

    //     if (! is_user_logged_in()) {
    //         //auth_redirect();
    //         wp_die('You do not have access to this gallery.', 'Error 403', ['response' => 403]);
    //     }
    //     $user_id = get_current_user_id();

    //     if (! in_array($user_id, $allowedUsers, true)) {
    //         wp_die('You do not have access to this gallery.', 'Error 403', ['response' => 403]);
    //     }

    //     return; // User is allowed, grant access
    // }

    }

    private function canBypassAccess($post_id): bool
    {
        if (is_user_logged_in() && (is_preview() || current_user_can('edit_post', $post_id))) {
            return true;
        }
        return false;
    }
}