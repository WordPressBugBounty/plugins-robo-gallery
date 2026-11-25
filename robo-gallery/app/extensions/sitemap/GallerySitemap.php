<?php
/* 
*      Robo Gallery     
*      Version: 6.0.0 - 27445
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace RoboGallery\app\extensions\sitemap;

defined('WPINC') || exit;

/**
 * Settings -> SEO & Feeds -> "Galleries in sitemaps": switched off, the gallery
 * pages leave the sitemaps of WordPress (wp-sitemap.xml), Yoast SEO and Rank Math.
 * On by default - the public post type was always listed there.
 *
 * Which galleries are listed when it is on is access\ListingFilter's: the
 * direct-link-only ones never are.
 */
class GallerySitemap
{
    // option name without ROBO_GALLERY_PREFIX (settings\SettingsPage)
    const OPTION = 'sitemapGalleries';

    public static function isEnabled(): bool
    {
        return '1' === (string) get_option(ROBO_GALLERY_PREFIX . self::OPTION, '1');
    }

    public function register(): void
    {
        add_filter('wp_sitemaps_post_types', array($this, 'filterCorePostTypes'));
        add_filter('wpseo_sitemap_exclude_post_type', array($this, 'excludePostType'), 10, 2);
        add_filter('rank_math/sitemap/exclude_post_type', array($this, 'excludePostType'), 10, 2);
    }

    /**
     * @param \WP_Post_Type[] $postTypes
     * @return \WP_Post_Type[]
     */
    public function filterCorePostTypes($postTypes)
    {
        if (!self::isEnabled()) {
            unset($postTypes[ROBO_GALLERY_TYPE_POST]);
        }

        return $postTypes;
    }

    /**
     * Yoast SEO and Rank Math: true leaves the post type out.
     *
     * @param bool   $exclude
     * @param string $postType
     * @return bool
     */
    public function excludePostType($exclude, $postType)
    {
        return ROBO_GALLERY_TYPE_POST === $postType && !self::isEnabled() ? true : $exclude;
    }
}
