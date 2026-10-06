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

namespace RoboGallery\app\extensions\stats;

use RoboGallery\app\PluginConstants;

defined('WPINC') || exit;

/**
 * Gallery view counter (PluginConstants::VIEWS_META_KEY), called by the
 * [robo-gallery] shortcode (includes/frontend/rbs_gallery_frontend.php) for every
 * gallery the visitor may view - the block and the gallery page render through it too.
 *
 * Not counted: users who can edit the gallery (their own previews, the block
 * editor), bots / link previews / scripts, browser prefetch, the admin, and
 * the same gallery twice in one request. Filter robo_gallery_count_view
 * (bool $count, int $galleryId) overrides the decision.
 *
 * Writes: one atomic UPDATE per view. With a persistent object cache (Redis,
 * Memcached) views are collected there and written in batches - every
 * robo_gallery_views_batch views (default 10) or when a gallery wasn't written
 * for FLUSH_INTERVAL seconds; views not yet written can be lost if the cache
 * is flushed. Counters in the admin can then lag behind by up to one batch.
 * Pages served by a full-page cache don't run PHP and aren't counted.
 */
class ViewCounter
{
    const CACHE_GROUP    = 'robo_gallery_views';
    const BATCH_SIZE     = 10;
    const FLUSH_INTERVAL = 300;

    // user agents that aren't people: crawlers, link previews, monitors, HTTP libraries
    const BOT_PATTERN = '~bot|crawl|spider|slurp|mediapartners|preview|facebookexternalhit|embedly|vkshare|whatsapp|telegram|pinterest|quora|outbrain|w3c_validator|lighthouse|headless|pingdom|uptime|monitor|scan|curl|wget|python|java/|go-http|httpclient|okhttp|axios|node-fetch|libwww|feed~i';

    /** @var int[] galleries counted in this request */
    private static array $counted = array();

    public function count(int $galleryId): void
    {
        if ($galleryId <= 0 || isset(self::$counted[$galleryId])) {
            return;
        }
        self::$counted[$galleryId] = true;

        if (!apply_filters('robo_gallery_count_view', $this->shouldCount($galleryId), $galleryId)) {
            return;
        }

        if (!wp_using_ext_object_cache()) {
            $this->write($galleryId, 1);
            return;
        }

        $pending = $this->addPending($galleryId);
        $batch   = max(1, (int) apply_filters('robo_gallery_views_batch', self::BATCH_SIZE));

        // wp_cache_add() only succeeds when the key is missing: "not written for FLUSH_INTERVAL"
        $intervalPassed = wp_cache_add('flushed_' . $galleryId, 1, self::CACHE_GROUP, self::FLUSH_INTERVAL);

        if ($pending >= $batch || $intervalPassed) {
            // take exactly what was counted; views added meanwhile stay for the next write
            wp_cache_decr('pending_' . $galleryId, $pending, self::CACHE_GROUP);
            $this->write($galleryId, $pending);
        }
    }

    private function shouldCount(int $galleryId): bool
    {
        if (is_admin() && !wp_doing_ajax()) {
            return false;
        }

        if (current_user_can('edit_post', $galleryId)) {
            return false;
        }

        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        if ('' === $userAgent || preg_match(self::BOT_PATTERN, $userAgent)) {
            return false;
        }

        // speculative loads (Chrome/Firefox prefetch & prerender) aren't views
        foreach (array('HTTP_SEC_PURPOSE', 'HTTP_PURPOSE', 'HTTP_X_MOZ', 'HTTP_X_PURPOSE') as $header) {
            if (isset($_SERVER[$header]) && preg_match('~prefetch|preview|prerender~i', (string) $_SERVER[$header])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return int views collected in the object cache and not written yet
     */
    private function addPending(int $galleryId): int
    {
        $key     = 'pending_' . $galleryId;
        $pending = wp_cache_incr($key, 1, self::CACHE_GROUP);

        if (false === $pending) {
            $pending = wp_cache_add($key, 1, self::CACHE_GROUP) ? 1 : (int) wp_cache_incr($key, 1, self::CACHE_GROUP);
        }

        return (int) $pending;
    }

    /**
     * One atomic UPDATE (no read-modify-write, so simultaneous views aren't lost);
     * the meta row is created on the first view.
     */
    private function write(int $galleryId, int $views): void
    {
        global $wpdb;

        if ($views <= 0) {
            return;
        }

        // a non-numeric value restarts the count: casting it would fail in MySQL strict mode
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->postmeta}
                SET meta_value = IF(meta_value REGEXP '^[0-9]+$', CAST(meta_value AS UNSIGNED) + %d, %d)
              WHERE post_id = %d AND meta_key = %s",
            $views,
            $views,
            $galleryId,
            PluginConstants::VIEWS_META_KEY
        ));

        if (!$updated) {
            add_post_meta($galleryId, PluginConstants::VIEWS_META_KEY, $views, true);
        }

        wp_cache_delete($galleryId, 'post_meta');
    }
}
