<?php
/* @@copyright@ */

namespace RoboGallery\app\extensions\access;

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}

use RoboGallery\app\PluginConstants;

/**
 * Class ExcludeListManager
 * Manages the list of excluded gallery IDs stored in WordPress options.
 */
class ExcludeListManager
{
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
    public function add($galleryId): void
    {
        $galleryId = (int) $galleryId;
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
    private function filterUnavailableIds( $ids): array
    {
        if (empty($ids)) {
            return [  ];
        }

        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        if( empty($placeholders)) {
            return [  ];
        }
        
        $params       = array_merge($ids, [ ROBO_GALLERY_TYPE_POST ]);

        $query = $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID IN ($placeholders) AND post_type = %s", ...$params);
        return $wpdb->get_col($query) ?: [  ];
    }
}
