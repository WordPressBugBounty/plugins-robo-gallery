<?php
/* @@copyright@ */

namespace RoboGallery\app\extensions\access;

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}

use RoboGallery\app\extensions\access\ExcludeListManager;

/**
 * Class FilterPrivateGallery
 * Handles access restrictions for galleries.
 */
class FilterPrivateGallery
{
    /**
     * @var ExcludeListManager
     */
    private ExcludeListManager $excludeManager;

    /**
     * Constructor.
     * Initializes hooks when the class is instantiated.
     */
    public function __construct($excludeManager)
    {
        $this->excludeManager = $excludeManager;
        $this->actions();
    }

    /**
     * Register WordPress hooks for access control.
     *
     * @return void
     */
    public function actions()
    {
        // disable hide galleries in  search result
        add_filter('pre_get_posts', [$this, 'exclude_private_from_search'], 10, 1);

        // Exclude private galleries from sitemap queries.
        add_filter('wp_sitemaps_posts_query_args', [$this, 'exclude_private_from_sitemap_query'], 10, 2);

        // Exclude private galleries from sitemap URLs.
        add_filter('wp_sitemaps_posts_pre_url_list', [$this, 'exclude_private_from_sitemap_urls'], 10, 2);
    }

    /**
     * Update the search to have filtered items in the search or removed.
     *
     * @param \WP_Query $query Search query.
     * @return \WP_Query
     */
    public function exclude_private_from_search($query)
    {
        if (! ($query instanceof \WP_Query)) {
            return $query;
        }

        $apply = ! is_admin() && $query->is_main_query() && $query->is_search(); //(defined('DOING_AJAX') && DOING_AJAX))

        /**
         * Allow customization of whether to apply the gallery exclusion filter in search results.
         *
         * @param bool     $apply
         * @param \WP_Query $query
         */
        $apply = (bool) apply_filters('robo_gallery_filter_search', $apply, $query);

        if (! $apply) {
            return $query;
        }
        $excluded_ids = $this->excludeManager->getExcluded();

        if (! empty($excluded_ids)) {

            $query->set('post__not_in', array_values($excluded_ids));
        }

        return $query;
    }

    /**
     * Exclude private galleries from sitemap URLs.
     *
     * @param array  $url_list  List of URLs in the sitemap.
     * @param string $post_type Post type being queried.
     * @return array
     */
    public function exclude_private_from_sitemap_urls($url_list, $post_type)
    {
        if (ROBO_GALLERY_TYPE_POST !== $post_type) {
            return $url_list;
        }

        $excluded_ids = $this->excludeManager->getExcluded();

        if (empty($excluded_ids)) {
            return $url_list;
        }

        $url_list = array_filter(
            $url_list,
            function ($entry) use ($excluded_ids) {
                if (! isset($entry['loc'])) {
                    return true;
                }

                $post_id = url_to_postid($entry['loc']);

                return ! in_array($post_id, $excluded_ids, true);
            }
        );
        return array_values($url_list);
    }

    /**
     * Exclude private galleries from sitemap.
     *
     * @param array  $args Arguments for WP_Query.
     * @param string $post_type Post type being queried.
     * @return array
     */
    public function exclude_private_from_sitemap_query($args, $post_type)
    {
        if (ROBO_GALLERY_TYPE_POST !== $post_type) {
            return $args;
        }

        $excluded_ids = $this->excludeManager->getExcluded();

        if (! empty($excluded_ids)) {
            if (isset($args['post__not_in']) && is_array($args['post__not_in'])) {
                $args['post__not_in'] = array_merge($args['post__not_in'], $excluded_ids);
            } else {
                $args['post__not_in'] = $excluded_ids;
            }
        }

        return $args;
    }

}
