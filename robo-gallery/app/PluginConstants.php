<?php
/* 
*      Robo Gallery     
*      Version: 5.2.6 - 24868
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace RoboGallery\app;

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}

/**
 * Plugin-wide keys of stored data and URLs (meta keys, option names, query
 * vars) - one place, so they are never spelled twice. The gallery post type
 * itself is ROBO_GALLERY_TYPE_POST (robogallery.php).
 */
interface PluginConstants
{
    /**
     * Meta key constants
     */
    public const OPTIONS_KEY = 'robo-gallery-options'; // Meta key for gallery options (from v5)
    public const VIEWS_META_KEY = 'gallery_views_count'; // Meta key for the gallery view counter (stats\ViewCounter)

    /**
     * Access mode constants
     */
    public const ACCESS_DIRECT_LINK_ONLY = 'accessDirectLinkOnly'; // Option: hidden from listings, opens only by its own link
    public const ACCESS_REQUIRE_TOKEN    = 'accessRequireToken';   // Option: link-only gallery opens only with the token in its URL
    public const TOKEN_META_KEY          = '_robogallery_token';   // Meta key for generated token

    /**
     * Options constants
     */
    public const EXCLUDE_OPTION_NAME = 'robo_gallery_exclude_galleries'; // Option name for excluded galleries list

    /**
     * Query var constants
     */
    public const QUERY_VAR_TOKEN = 'rg_token'; // Token query var: share link, ?rg_token= and the REST param
    public const SHARE_PATH      = 'share/t';  // Pretty share link: /<gallery base>/share/t/<token>/
}
