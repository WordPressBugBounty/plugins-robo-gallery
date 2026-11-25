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

namespace RoboGallery\app\extensions\access;

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}

/**
 * Keeps direct-link-only galleries out of every frontend listing: site search,
 * widgets, Query Loop, feeds, legacy albums, REST collections and
 * /wp/v2/search (REST requests aren't is_admin()), previous/next links and the
 * sitemaps (core, Yoast SEO, Rank Math); adds noindex on their own pages.
 * Users who can edit others' galleries still see them in listings.
 * The token galleries are on the same list: a token needs direct link only.
 */
class ListingFilter
{
    private ExcludeListManager $excludeManager;
    private GalleryAccessManager $access;

    public function __construct(ExcludeListManager $excludeManager, GalleryAccessManager $access)
    {
        $this->excludeManager = $excludeManager;
        $this->access         = $access;
    }

    public function register(): void
    {
        add_action('pre_get_posts', [$this, 'excludeFromQueries'], 10, 1);

        // "Previous/next" links on neighbouring gallery pages use direct SQL, not WP_Query.
        add_filter('get_previous_post_where', [$this, 'excludeFromAdjacent'], 10, 5);
        add_filter('get_next_post_where', [$this, 'excludeFromAdjacent'], 10, 5);

        // The core sitemap, also for logged-in editors (pre_get_posts lets them see all).
        add_filter('wp_sitemaps_posts_query_args', [$this, 'excludeFromSitemap'], 10, 2);

        // Yoast SEO and Rank Math read the posts of their sitemaps with their own SQL: no WP_Query hook reaches them.
        add_filter('wpseo_exclude_from_sitemap_by_post_ids', [$this, 'excludeFromYoastSitemap']);
        add_filter('rank_math/sitemap/entry', [$this, 'excludeFromRankMathSitemap'], 10, 3);

        // In case the link leaks or another sitemap lists the gallery. Rank Math prints its own robots meta instead
        // of the core one, so it gets the same answer through its own filter.
        add_filter('wp_robots', [$this, 'noindex']);
        add_filter('rank_math/frontend/robots', [$this, 'noindexRankMath']);
    }

    /**
     * pre_get_posts: add the direct-link-only galleries to post__not_in of listing queries.
     *
     * @param \WP_Query $query
     * @return void
     */
    public function excludeFromQueries($query)
    {
        if (! ($query instanceof \WP_Query)) {
            return;
        }

        $apply = $this->isListingQuery($query) && ! GalleryAccessManager::canSeeAllInListings();

        if ($query->is_search()) {
            /**
             * Back-compat: customize whether direct-link-only galleries are hidden from search.
             *
             * @param bool      $apply
             * @param \WP_Query $query
             */
            $apply = (bool) apply_filters('robo_gallery_filter_search', $apply, $query);
        }

        /**
         * Customize whether direct-link-only galleries are hidden from this query.
         *
         * @param bool      $apply
         * @param \WP_Query $query
         */
        $apply = (bool) apply_filters('robo_gallery_exclude_private', $apply, $query);

        if (! $apply) {
            return;
        }

        $excluded_ids = $this->excludeManager->getExcluded();
        if (empty($excluded_ids)) {
            return;
        }

        // merge, don't overwrite, what the query (or another plugin) already excludes
        $not_in = array_filter(array_map('intval', (array) $query->get('post__not_in')));
        $query->set('post__not_in', array_values(array_unique(array_merge($not_in, $excluded_ids))));
    }

    /**
     * Skip direct-link-only galleries in previous/next post links between galleries.
     *
     * @param string        $where
     * @param bool          $in_same_term
     * @param array|string  $excluded_terms
     * @param string        $taxonomy
     * @param \WP_Post|null $post
     * @return string
     */
    public function excludeFromAdjacent($where, $in_same_term, $excluded_terms, $taxonomy, $post)
    {
        if (! ($post instanceof \WP_Post) || ROBO_GALLERY_TYPE_POST !== $post->post_type || GalleryAccessManager::canSeeAllInListings()) {
            return $where;
        }

        $excluded_ids = $this->excludeManager->getExcluded();
        if (empty($excluded_ids)) {
            return $where;
        }

        return $where . ' AND p.ID NOT IN (' . implode(',', array_map('intval', $excluded_ids)) . ')';
    }

    /**
     * wp_sitemaps_posts_query_args for the gallery post type.
     *
     * @param array  $args
     * @param string $post_type
     * @return array
     */
    public function excludeFromSitemap($args, $post_type)
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

    /**
     * wpseo_exclude_from_sitemap_by_post_ids: the post ids Yoast SEO leaves out of every sitemap.
     *
     * @param array $ids
     * @return array
     */
    public function excludeFromYoastSitemap($ids)
    {
        $ids = is_array($ids) ? $ids : [  ];

        return array_values(array_unique(array_merge(array_map('intval', $ids), $this->excludeManager->getExcluded())));
    }

    /**
     * rank_math/sitemap/entry: an empty answer leaves the entry out. Rank Math reads the posts with its own SQL, so
     * the post may be a plain row object, not a WP_Post.
     *
     * @param array|false $url
     * @param string      $type
     * @param object|null $object
     * @return array|false
     */
    public function excludeFromRankMathSitemap($url, $type, $object)
    {
        if ('post' !== $type || ! is_object($object) || empty($object->ID)) {
            return $url;
        }

        if (isset($object->post_type) && ROBO_GALLERY_TYPE_POST !== $object->post_type) {
            return $url;
        }

        return in_array((int) $object->ID, $this->excludeManager->getExcluded(), true) ? false : $url;
    }

    /**
     * wp_robots: noindex for direct-link-only galleries (with or without token).
     *
     * @param array $robots
     * @return array
     */
    public function noindex($robots)
    {
        if (! is_array($robots) || ! $this->isHiddenGalleryPage()) {
            return $robots;
        }

        return wp_robots_no_robots($robots);
    }

    /**
     * rank_math/frontend/robots: the same as noindex() in Rank Math's format ('index' => 'noindex', ...).
     *
     * @param array $robots
     * @return array
     */
    public function noindexRankMath($robots)
    {
        if (! is_array($robots) || ! $this->isHiddenGalleryPage()) {
            return $robots;
        }

        $robots['index']  = 'noindex';
        $robots['follow'] = 'nofollow';

        return $robots;
    }

    /* the page of a direct-link-only gallery (with or without token) */
    private function isHiddenGalleryPage(): bool
    {
        return is_singular(ROBO_GALLERY_TYPE_POST) && $this->access->isDirectLinkOnly(get_queried_object_id());
    }

    /**
     * Frontend/REST query that may list galleries. wp-admin (incl. admin-ajax)
     * lists everything; a singular query is the gallery's own page, which must
     * stay reachable by its link.
     *
     * @param \WP_Query $query
     * @return bool
     */
    private function isListingQuery(\WP_Query $query): bool
    {
        if (is_admin() || $query->is_singular()) {
            return false;
        }

        $post_type = $query->get('post_type');
        if (empty($post_type)) {
            // search and archives resolve the post types later
            return $query->is_search() || $query->is_archive();
        }

        return 'any' === $post_type || in_array(ROBO_GALLERY_TYPE_POST, (array) $post_type, true);
    }
}
