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

namespace RoboGallery\app\extensions\listing;

defined('WPINC') || exit;

/**
 * Galleries list (edit.php?post_type=robo_gallery_table): "Views" and
 * "Shortcode" columns; clicking the shortcode copies it (js/admin/listing.js).
 * Column keys are what users' hidden-columns screen options are stored under - keep them.
 */
class GalleryListing
{
    const COLUMN_VIEWS     = 'rbs_gallery_views';
    const COLUMN_SHORTCODE = 'rbs_gallery';

    public function register(): void
    {
        add_filter('manage_' . ROBO_GALLERY_TYPE_POST . '_posts_columns', array($this, 'addColumns'));
        add_action('manage_' . ROBO_GALLERY_TYPE_POST . '_posts_custom_column', array($this, 'renderColumn'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
    }

    /**
     * @param array $columns
     * @return array
     */
    public function addColumns($columns)
    {
        $columns[self::COLUMN_VIEWS]     = __('Views', 'robo-gallery');
        $columns[self::COLUMN_SHORTCODE] = __('Shortcode', 'robo-gallery');

        return $columns;
    }

    /**
     * @param string $column
     * @param int    $post_id
     */
    public function renderColumn($column, $post_id): void
    {
        if (self::COLUMN_VIEWS === $column) {
            echo (int) get_post_meta($post_id, \RoboGallery\app\PluginConstants::VIEWS_META_KEY, true);
            return;
        }

        if (self::COLUMN_SHORTCODE === $column) {
            printf(
                '<input readonly="readonly" size="23" value="%s" class="robo-gallery-shortcode" type="text" />',
                esc_attr('[robo-gallery id=' . (int) $post_id . ']')
            );
        }
    }

    /**
     * @param string $hook
     */
    public function enqueueAssets($hook): void
    {
        $screen = get_current_screen();
        if ('edit.php' !== $hook || !$screen || ROBO_GALLERY_TYPE_POST !== $screen->post_type) {
            return;
        }

        wp_enqueue_script('robo-gallery-listing', ROBO_GALLERY_URL . 'js/admin/listing.js', array(), ROBO_GALLERY_VERSION, true);
        wp_localize_script('robo-gallery-listing', 'robo_gallery_listing', array(
            'copied' => __('ShortCode copied to clipboard!', 'robo-gallery'),
        ));

        wp_enqueue_style('robo-gallery-listing', ROBO_GALLERY_URL . 'css/admin/list.css', array(), ROBO_GALLERY_VERSION);
    }
}
