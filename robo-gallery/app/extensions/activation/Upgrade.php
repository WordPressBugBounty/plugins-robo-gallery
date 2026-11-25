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

namespace RoboGallery\app\extensions\activation;

if (!defined('WPINC')) {
    die; // Exit if accessed directly
}

use RoboGallery\app\extensions\access\ExcludeListManager;
use RoboGallery\app\GalleryOptions;
use RoboGallery\app\PluginConstants;

/**
 * Runs once per new plugin version (was includes/rbs_class_update.php):
 * adds the settings introduced over the versions to galleries that don't have
 * them yet, asks Install for the post-update refresh (rewrite rules flush,
 * overview page) and removes data older versions left behind.
 */
class Upgrade
{
    /**
     * Gallery settings (meta rsg_<name>) and their defaults, by the plugin
     * version that introduced them. Only added where missing - existing values
     * are never changed.
     */
    private const FIELD_DEFAULTS = array(
        '1.3.5' => array(
            'lightboxCounter' => 1,
        ),
        '1.3.6' => array(
            'lightboxClose' => 1,
        ),
        '1.3.7' => array(
            'lightboxArrow' => 1,
        ),
        '1.3.8' => array(
            'menuSelfImages' => 1,
        ),
        '2.5.2' => array(
            'lightboxCounterText' => ' of ',
        ),
        '2.5.3' => array(
            'lightboxSocialFacebook'   => 1,
            'lightboxSocialTwitter'    => 1,
            'lightboxSocialGoogleplus' => 1,
            'lightboxSocialPinterest'  => 1,
            'lightboxSocialVK'         => 0,
        ),
        '3.0.0' => array(
            'gallery_type' => 'grid',
        ),
    );

    public function register(): void
    {
        // before the gallery post type (init, 10) and Install::maybeRefresh (init, 99),
        // so a refresh requested here happens in the same request
        add_action('init', array($this, 'run'), 9);
    }

    public function run(): void
    {
        // kept for compatibility (not read by the plugin itself)
        if (get_option('RoboGalleryInstallVersion', 0) != ROBO_GALLERY_VERSION) {
            update_option('RoboGalleryInstallDate', time());
            update_option('RoboGalleryInstallVersion', ROBO_GALLERY_VERSION);
        }

        if (get_option('rbs_gallery_db_version', 0) == ROBO_GALLERY_VERSION) {
            return;
        }

        Install::requestRefresh();
        update_option('rbs_gallery_db_version', ROBO_GALLERY_VERSION);

        // first: until then the access of these galleries rests on the read fallback alone
        $this->migrateLegacyAccess();

        // a missing type gets grid here (FIELD_DEFAULTS), an empty one below
        $this->addMissingDefaults();
        $this->fixEmptyTypes();

        // "Clone Gallery" links to deleted galleries (before sources were unlinked on delete)
        \RoboGallery\app\extensions\cloneSource\CloneSource::removeInvalidReferences();

        $this->removeCacheTable();
        $this->removeOutputCache();

        // the removed "Express panel" setting (an Update box of the gallery editor)
        delete_option(ROBO_GALLERY_PREFIX . 'expressPanel');

        // the removed Settings -> Statistics switch: the statistics panel is always on now
        delete_option(ROBO_GALLERY_OPTIONS . 'addon_stats');

        // the removed SEO Optimization option: its hidden SEO markup was no longer rendered, and its
        // stored 0 was mostly the saved default, not a choice - noscriptImages (StaticGallery) replaces it
        delete_option(ROBO_GALLERY_PREFIX . 'seo');

        // the removed "Clone Block" setting: the Clone Gallery box is on every classic gallery now
        delete_option(ROBO_GALLERY_PREFIX . 'cloneBlock');
    }

    /**
     * The access options of the older versions (GalleryOptions::LEGACY_ACCESS_KEYS: Private,
     * Token Mode...) as the current ones: a private gallery stays private, a token gallery
     * keeps asking for its token (the same _robogallery_token). The old keys are dropped -
     * accessModePassword held the post password in clear. getStored() reads them as the new
     * ones (GalleryOptions::readLegacyAccess()), and keeps the new ones of a gallery saved
     * since. Safe to repeat.
     */
    private function migrateLegacyAccess(): void
    {
        global $wpdb;

        // the serialized meta names its keys: only the galleries that may have old ones
        $ids = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT m.post_id FROM {$wpdb->postmeta} m
             INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
             WHERE p.post_type = %s AND m.meta_key = %s AND m.meta_value LIKE %s",
            ROBO_GALLERY_TYPE_POST,
            PluginConstants::OPTIONS_KEY,
            '%' . $wpdb->esc_like('accessMode') . '%'
        )));
        if (!$ids) {
            return;
        }

        $excludeList = new ExcludeListManager();
        foreach ($ids as $id) {
            $options = GalleryOptions::getStored($id);
            if (!array_intersect_key($options, array_flip(GalleryOptions::LEGACY_ACCESS_KEYS))) {
                continue;
            }

            // update_post_meta() unslashes: the other options' texts stay as they are
            update_post_meta($id, PluginConstants::OPTIONS_KEY, wp_slash(array_diff_key($options, array_flip(GalleryOptions::LEGACY_ACCESS_KEYS))));
            // the list of the galleries kept out of listings, also when the meta did not change
            $excludeList->syncGallery($id);
        }
    }

    /**
     * An empty rsg_gallery_type is read as grid everywhere, but the editor shows the
     * stored value back and kept saving it empty; the meta-box conditions of the
     * fields framework read it raw. Store it as grid. Safe to repeat.
     */
    private function fixEmptyTypes(): void
    {
        global $wpdb;

        $ids = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT m.post_id FROM {$wpdb->postmeta} m
             INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
             WHERE p.post_type = %s AND m.meta_key = %s AND TRIM(m.meta_value) = ''",
            ROBO_GALLERY_TYPE_POST,
            ROBO_GALLERY_PREFIX . 'gallery_type'
        )));
        if (!$ids) {
            return;
        }

        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_value = %s
             WHERE meta_key = %s AND TRIM(meta_value) = '' AND post_id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ')',
            array_merge(array(ROBO_GALLERY_TYPE_GRID, ROBO_GALLERY_PREFIX . 'gallery_type'), $ids)
        ));

        // written around the object cache
        foreach ($ids as $id) {
            wp_cache_delete($id, 'post_meta');
        }
    }

    /**
     * The YouTube source keeps its API answers in transients now: drop the old
     * cache table, its options and its hourly cleanup event. Safe to repeat.
     */
    private function removeCacheTable(): void
    {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}robogallery_cache");

        foreach (array('dbcache_version', 'dbcache_time') as $name) {
            delete_option(ROBO_GALLERY_PREFIX . $name);
            delete_site_option(ROBO_GALLERY_PREFIX . $name);
        }

        wp_clear_scheduled_hook(ROBO_GALLERY_PREFIX . 'clear_db_cache_hook');
    }

    /**
     * The gallery output cache is removed: its per-gallery switch (meta rsg_cache),
     * its timeout setting (option rsg_cache) and the HTML it kept (transients
     * rsg_cache_id<gallery>). Not the meta rsg_cache_id - it names the gallery's
     * CSS file. Safe to repeat.
     */
    private function removeOutputCache(): void
    {
        global $wpdb;

        delete_option(ROBO_GALLERY_PREFIX . 'cache');
        delete_metadata('post', 0, ROBO_GALLERY_PREFIX . 'cache', '', true);

        foreach (array('_transient_', '_transient_timeout_') as $prefix) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like($prefix . ROBO_GALLERY_PREFIX . 'cache_id') . '%'
            ));
        }
    }

    /**
     * Adds only the settings a gallery doesn't have (like add_post_meta(...,
     * unique)). This runs on the first request after an update, which may be a
     * visitor's: so no per-gallery calls (12 settings x every gallery used to
     * be one query each) but one read and batched inserts. A new install has
     * no galleries yet - one empty read. (Every listed version is older than
     * the current one, so all of them apply.)
     */
    private function addMissingDefaults(): void
    {
        global $wpdb;

        $defaults = array();
        foreach (self::FIELD_DEFAULTS as $versionFields) {
            foreach ($versionFields as $name => $value) {
                $defaults[ROBO_GALLERY_PREFIX . $name] = (string) $value;
            }
        }

        $galleryIds = $this->getGalleryIds();
        if (!$galleryIds) {
            return;
        }

        // which of these settings each gallery already has
        $existing = $wpdb->get_results($wpdb->prepare(
            "SELECT m.post_id, m.meta_key FROM {$wpdb->postmeta} m
             INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
             WHERE p.post_type = %s AND m.meta_key IN (" . implode(',', array_fill(0, count($defaults), '%s')) . ')',
            array_merge(array(ROBO_GALLERY_TYPE_POST), array_keys($defaults))
        ));
        $has = array();
        foreach ((array) $existing as $row) {
            $has[(int) $row->post_id][$row->meta_key] = true;
        }

        // rows of (post_id, meta_key, meta_value)
        $rows = array();
        foreach ($galleryIds as $galleryId) {
            foreach ($defaults as $key => $value) {
                if (!isset($has[$galleryId][$key])) {
                    $rows[] = array((int) $galleryId, $key, $value);
                }
            }
        }

        // one prepared INSERT per 500 rows, each row a (%d, %s, %s) group: three
        // replacements per group, which the sniff counts as one
        foreach (array_chunk($rows, 500) as $chunk) {
            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode(',', array_fill(0, count($chunk), '(%d, %s, %s)')),
                array_merge(...$chunk)
            ));
        }

        // the meta was written around the object cache
        if ($rows) {
            foreach ($galleryIds as $galleryId) {
                wp_cache_delete($galleryId, 'post_meta');
            }
        }
    }

    /**
     * All galleries, directly from the DB: a WP_Query would go through the
     * listing filters (access\ListingFilter hides direct-link-only galleries on
     * frontend requests) and was capped at 999 published galleries.
     *
     * @return int[]
     */
    private function getGalleryIds(): array
    {
        global $wpdb;

        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = %s AND post_status NOT IN ('trash', 'auto-draft', 'inherit')",
            ROBO_GALLERY_TYPE_POST
        )));
    }
}
