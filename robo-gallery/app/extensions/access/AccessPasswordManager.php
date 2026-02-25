<?php
/* @@copyright@ */

namespace RoboGallery\app\extensions\access;

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}

/**
 * Class AccessPasswordManager
 * Manages setting and clearing passwords for gallery posts.
 */
class AccessPasswordManager
{
    /**
     * Sets the password for a given post ID.
     *
     * @param int $post_id The ID of the post to update.
     * @param string $password The password to set. If empty, clears the password.
     */
    public function set_password($post_id, $password = ''): void
    {
        $password = (string) $password;
        if (strlen($password) > 256) {
            $password = substr($password, 0, 256);
        }

        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return;
        }

        $data = [
            'ID'            => $post_id,
            'post_password' => $password ? $password : ''
        ];
        wp_update_post($data);
    }

    /**
     * Clears the password for a given post ID.
     *
     * @param int $post_id The ID of the post to update.
     */
    public function clear_password($post_id): void
    {
        $this->set_password($post_id, '');
    }

}