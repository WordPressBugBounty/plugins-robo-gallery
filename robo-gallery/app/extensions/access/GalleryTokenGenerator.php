<?php
/* @@copyright@ */

namespace RoboGallery\app\extensions\access;

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}
/**
 * Class GalleryTokenGenerator
 * Generates random alphanumeric tokens for gallery access.
 */
class GalleryTokenGenerator
{
    /**
     * Generate a random alphanumeric token.
     *
     * @param int $length The length of the token.
     * @return string The generated token.
     */
    public function generate(int $length = 16): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $token;
    }
}
