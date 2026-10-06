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

namespace RoboGallery\app\extensions\activation;

if (!defined('WPINC')) {
    die; // Exit if accessed directly
}

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

        // a missing type gets grid here (FIELD_DEFAULTS), an empty one below
        $this->addMissingDefaults();
        $this->fixEmptyTypes();

        // "Clone Gallery" links to deleted galleries (before sources were unlinked on delete)
        \RoboGallery\app\extensions\cloneSource\CloneSource::removeInvalidReferences();

        $this->removeCacheTable();
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
