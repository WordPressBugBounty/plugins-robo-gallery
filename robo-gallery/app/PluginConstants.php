<?php
/* @@copyright@ */

namespace RoboGallery\app;

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}

/**
 * Interface PluginConstants
 * Defines all plugin-wide constants in one place.
 *
 * This ensures consistency and avoids duplication across classes.
 */
interface PluginConstants
{
    /**
     * Main constants
     */
    /* IMPORTANT it's duplicate  */
    public const TYPE_POST = 'robo_gallery_table'; // Gallery custom post type name — used in register_post_type

    /**
     * Meta key constants
     */
    public const OPTIONS_KEY = 'robo-gallery-options'; // Meta key for gallery options (from v5)

    /**
     * Access mode constants
     */
    public const ACCESS_MODE_PRIVATE = 'accessModePrivate';  // Meta key value for private access mode
    public const ACCESS_MODE_PASSWORD = 'accessModePassword';  // Meta key value for password access mode
    public const ACCESS_MODE_TOKEN = 'accessModeToken';  // Meta key value for  token access mode
    public const TOKEN_META_KEY      = '_robogallery_token'; // Meta key for generated token

    /**
     * Options constants
     */
    public const EXCLUDE_OPTION_NAME = 'robo_gallery_exclude_galleries'; // Option name for excluded galleries list

    /**
     * Query var constants
     */
    public const QUERY_VAR_TOKEN = 'robogallery_token'; // Query var name for token in URL

    /**
     * Rewrite rule constants
     */
    public const REWRITE_BASE = 'gallery'; // Rewrite rule constants
}
