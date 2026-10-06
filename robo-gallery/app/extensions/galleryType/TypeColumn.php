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

namespace RoboGallery\app\extensions\galleryType;

defined('WPINC') || exit;

/**
 * "Type" column of the galleries list, right after the title. The post type's
 * own column filters also run when Quick Edit re-renders a row (admin-ajax
 * inline-save), so the updated row keeps the column.
 */
class TypeColumn
{
    // stored in users' hidden-columns screen options - keep it
    const COLUMN = 'RoboGalleryThemeColumnType';

    public function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_filter('manage_' . ROBO_GALLERY_TYPE_POST . '_posts_columns', array($this, 'addColumn'));
        add_action('manage_' . ROBO_GALLERY_TYPE_POST . '_posts_custom_column', array($this, 'renderColumn'), 10, 2);
    }

    /**
     * @param array $columns
     * @return array
     */
    public function addColumn($columns)
    {
        $label = __('Type', 'robo-gallery');

        if (!isset($columns['title'])) {
            $columns[self::COLUMN] = $label;
            return $columns;
        }

        $result = array();
        foreach ($columns as $key => $value) {
            if (self::COLUMN === $key) {
                continue;
            }
            $result[$key] = $value;
            if ('title' === $key) {
                $result[self::COLUMN] = $label;
            }
        }

        return $result;
    }

    /**
     * @param string $column
     * @param int    $postId
     */
    public function renderColumn($column, $postId): void
    {
        if (self::COLUMN !== $column || !(int) $postId) {
            return;
        }

        $type   = \RoboGallery\app\GalleryUtils::getTypeGallery((int) $postId);
        $source = (string) get_post_meta((int) $postId, ROBO_GALLERY_PREFIX . 'gallery_type_source', true);

        printf('<strong>%s</strong>', esc_html($this->getLabel($type, $source)));
    }

    /**
     * Name of a stored type (letters only, an empty one is grid - getTypeGallery()).
     * Older versions saved the editor's hidden field unfiltered, so an unknown
     * value is only shown escaped.
     */
    private function getLabel(string $type, string $source): string
    {
        $typeInfo = GalleryTypeList::getByType($type);
        if (!$typeInfo) {
            return ucfirst($type);
        }

        $label = $typeInfo['name'];
        if ('custom' === $type && 'custom-342' === $source) {
            $label .= ' V2';
        }

        return $label;
    }
}
