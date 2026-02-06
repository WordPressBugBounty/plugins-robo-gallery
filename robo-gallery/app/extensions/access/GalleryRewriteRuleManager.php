<?php
/* @@copyright@ */

namespace RoboGallery\app\extensions\access;

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}

use RoboGallery\app\PluginConstants;

/**
 * Class GalleryRewriteRuleManager
 * Manages custom rewrite rules for gallery access with tokens.
 */
class GalleryRewriteRuleManager
{
    /**
     * Registers custom rewrite rules and tags for gallery access with tokens.
     */
    public function register(): void
    {
        add_rewrite_rule(
            '^gallery/([^/]+)/([^/]+)/?$',
            'index.php?post_type=' . ROBO_GALLERY_TYPE_POST . '&' . ROBO_GALLERY_TYPE_POST . '=$matches[1]&' . PluginConstants::QUERY_VAR_TOKEN . '=$matches[2]',
            'top'
        );
        add_rewrite_tag('%' . PluginConstants::QUERY_VAR_TOKEN . '%', '([^&]+)');
    }
}
