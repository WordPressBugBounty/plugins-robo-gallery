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

namespace RoboGallery\app\extensions\access;

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}

use RoboGallery\app\GalleryOptions;
use RoboGallery\app\PluginConstants;

/**
 * Class ExcludeListManager
 * The list of direct-link-only gallery IDs (a WordPress option), which the
 * listing filters exclude, and keeping it in sync with the galleries' option.
 */
class ExcludeListManager
{
    public function register(): void
    {
        add_filter('update_post_metadata', [$this, 'onOptionsUpdate'], 10, 5);
    }

    /**
     * update_post_metadata: sync the list whenever gallery options are saved.
     * Synced by state rather than by change, so galleries that were missed
     * earlier heal on their next save; add()/remove() only write the option
     * when the list actually changes.
     *
     * @param mixed  $check
     * @param int    $object_id
     * @param string $meta_key
     * @param mixed  $meta_value
     * @param mixed  $prev_value
     * @return mixed $check unchanged
     */
    public function onOptionsUpdate($check, $object_id, $meta_key, $meta_value, $prev_value)
    {
        if (false === $check) {
            return false; // another handler has denied the update
        }

        if (PluginConstants::OPTIONS_KEY !== $meta_key || ! is_array($meta_value)
            || ! isset($meta_value[PluginConstants::ACCESS_DIRECT_LINK_ONLY])
        ) {
            return $check;
        }

        $this->sync((int) $object_id, (bool) $meta_value[PluginConstants::ACCESS_DIRECT_LINK_ONLY]);

        return $check;
    }

    /**
     * Put the gallery on / off the list by its stored option - for code that
     * writes the options without update_post_meta() (e.g. add_post_meta() when
     * duplicating a gallery).
     *
     * @param int $galleryId
     */
    public function syncGallery(int $galleryId): void
    {
        $options = GalleryOptions::getStored($galleryId);
        $this->sync($galleryId, !empty($options[PluginConstants::ACCESS_DIRECT_LINK_ONLY]));
    }

    private function sync(int $galleryId, bool $directLinkOnly): void
    {
        if ($directLinkOnly) {
            $this->add($galleryId);
        } else {
            $this->remove($galleryId);
        }
    }

    /**
     * Retrieves the list of excluded gallery IDs.
     *
     * @return array An array of excluded gallery IDs.
     */
    public function getExcluded(): array
    {
        $excludedIDs = get_option(PluginConstants::EXCLUDE_OPTION_NAME, [  ]);
        if (! is_array($excludedIDs)) {
            $excludedIDs = [  ];
        }
        return array_map('intval', $excludedIDs);
    }

    /**
     * Sets the list of excluded gallery IDs.
     *
     * @param array $ids An array of gallery IDs to exclude.
     */
    public function setExcluded($ids): void
    {
        if (! is_array($ids)) {
            $ids = [  ];
        }
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, fn($id) => $id > 0);
        $ids = $this->filterUnavailableIds($ids);
        update_option(PluginConstants::EXCLUDE_OPTION_NAME, array_unique($ids));
    }

    /**
     * Adds a gallery ID to the exclusion list.
     *
     * @param int $galleryId The gallery ID to add.
     */
    public function add(int $galleryId): void
    {
        if ($galleryId <= 0) {
            return;
        }

        $excludedIDs = $this->getExcluded();
        if (in_array($galleryId, $excludedIDs, true)) {
            return;
        }

        $excludedIDs[  ] = $galleryId;

        $this->setExcluded($excludedIDs);
    }

    /**
     * Removes a gallery ID from the exclusion list.
     *
     * @param int $galleryId The gallery ID to remove.
     */
    public function remove(int $galleryId): void
    {
        if ($galleryId <= 0) {
            return;
        }

        $excluded = $this->getExcluded();
        $key      = array_search($galleryId, $excluded, true);
        if (false === $key) {
            return;
        }

        unset($excluded[ $key ]);
        $this->setExcluded($excluded);
    }

    /**
     * Filters out IDs that do not correspond to existing gallery posts.
     *
     * @param array $ids An array of gallery IDs to filter.
     * @return array An array of valid gallery IDs.
     */
    private function filterUnavailableIds(array $ids): array
    {
        if (empty($ids)) {
            return [  ];
        }

        global $wpdb;
        $ids = array_map('intval', $ids);

        // one %d per id, built inside the prepared string so the query stays checkable
        return $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE ID IN (" . implode(',', array_fill(0, count($ids), '%d')) . ') AND post_type = %s',
            array_merge($ids, [ ROBO_GALLERY_TYPE_POST ])
        )) ?: [  ];
    }
}
