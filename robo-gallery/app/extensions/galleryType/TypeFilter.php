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

namespace RoboGallery\app\extensions\galleryType;

defined('WPINC') || exit;

/**
 * "All types" filter above the galleries list (?rsg_type=). It offers only the
 * types the site's galleries have. A type is read as getTypeGallery() reads it:
 * letters only, empty or missing meta is grid. Older versions saved the type
 * unfiltered, so the query matches every stored value that reads as the chosen type.
 */
class TypeFilter
{
    const QUERY_ARG = 'rsg_type';

    /** @var array|null type => stored meta values */
    private $storedValues = null;

    public function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('restrict_manage_posts', array($this, 'renderFilter'), 10, 2);
        add_action('pre_get_posts', array($this, 'filterQuery'));
    }

    /**
     * @param string $postType
     * @param string $which
     */
    public function renderFilter($postType, $which = 'top'): void
    {
        if (ROBO_GALLERY_TYPE_POST !== $postType || 'top' !== $which) {
            return;
        }

        $types = $this->getStoredValues();
        if (count($types) < 2) {
            return;
        }

        $selected = $this->getRequested();
        $labels   = array();
        foreach (array_keys($types) as $type) {
            $typeInfo      = GalleryTypeList::getByType($type);
            $labels[$type] = $typeInfo ? $typeInfo['name'] : ucfirst($type);
        }
        natcasesort($labels);

        printf(
            '<label class="screen-reader-text" for="filter-by-%1$s">%2$s</label><select name="%1$s" id="filter-by-%1$s"><option value="">%3$s</option>',
            esc_attr(self::QUERY_ARG),
            esc_html__('Filter by type', 'robo-gallery'),
            esc_html__('All types', 'robo-gallery')
        );
        foreach ($labels as $type => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($type),
                selected($selected, $type, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    /**
     * @param \WP_Query $query
     */
    public function filterQuery($query): void
    {
        global $pagenow;

        if (!($query instanceof \WP_Query) || !$query->is_main_query() || 'edit.php' !== $pagenow
            || ROBO_GALLERY_TYPE_POST !== $query->get('post_type')) {
            return;
        }

        $type = $this->getRequested();
        if ('' === $type) {
            return;
        }

        $types  = $this->getStoredValues();
        $values = isset($types[$type]) ? $types[$type] : array($type);
        $clause = array(
            'key'     => ROBO_GALLERY_PREFIX . 'gallery_type',
            'value'   => $values,
            'compare' => 'IN',
        );

        if (ROBO_GALLERY_TYPE_GRID === $type) {
            $clause = array(
                'relation' => 'OR',
                $clause,
                array(
                    'key'     => ROBO_GALLERY_PREFIX . 'gallery_type',
                    'compare' => 'NOT EXISTS',
                ),
            );
        }

        $metaQuery   = (array) $query->get('meta_query');
        $metaQuery[] = $clause;
        $query->set('meta_query', $metaQuery);
    }

    private function getRequested(): string
    {
        if (empty($_GET[self::QUERY_ARG]) || !is_string($_GET[self::QUERY_ARG])) {
            return '';
        }

        return preg_replace('/[^A-Za-z]/', '', wp_unslash($_GET[self::QUERY_ARG]));
    }

    /**
     * Stored rsg_gallery_type values of the galleries, grouped by the type they read as.
     *
     * @return array
     */
    private function getStoredValues(): array
    {
        global $wpdb;

        if (null !== $this->storedValues) {
            return $this->storedValues;
        }

        $values = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status <> 'auto-draft'",
            ROBO_GALLERY_PREFIX . 'gallery_type',
            ROBO_GALLERY_TYPE_POST
        ));

        $this->storedValues = array();
        foreach ((array) $values as $value) {
            $this->storedValues[GalleryTypeList::sanitizeType($value)][] = (string) $value;
        }

        return $this->storedValues;
    }
}
