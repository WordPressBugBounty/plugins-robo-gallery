<?php
/* @@copyright@ */

namespace RoboGallery\app\extensions\access;

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}

use RoboGallery\app\PluginConstants;
/**
 * Class GalleryPermalinkModifier
 * Modifies gallery permalinks to include access tokens.
 */
class GalleryPermalinkModifier
{
    /**
     * Adds a token to the given permalink.
     *
     * @param string $permalink The original permalink.
     * @param string $token The token to add.
     * @return string The modified permalink with the token included.
     */
    public function add_token_to_permalink(string $permalink, string $token): string
    {
        $url_parts = wp_parse_url($permalink);

        $scheme   = isset($url_parts['scheme']) ? $url_parts['scheme'] . '://' : '';
        $host     = $url_parts['host'] ?? '';
        $port     = isset($url_parts['port']) ? ':' . $url_parts['port'] : '';
        $path     = isset($url_parts['path']) ? $url_parts['path'] : '';
        $query    = isset($url_parts['query']) ? $url_parts['query'] : '';
        $fragment = isset($url_parts['fragment']) ? '#' . $url_parts['fragment'] : '';

        $is_ugly_permalink = ($path === '/' && !empty($query));

        if ($is_ugly_permalink) {
            $query_parts = [];
            parse_str($query, $query_parts);
            $query_parts[PluginConstants::QUERY_VAR_TOKEN] = $token;
            $query = http_build_query($query_parts);
            $query = !empty($query) ? '?' . $query : '';
        } else {
            $path = trailingslashit($path) . $token;
        }

        return $scheme . $host . $port . $path . $query . $fragment;
    }
}