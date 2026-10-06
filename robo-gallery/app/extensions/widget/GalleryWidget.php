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

namespace RoboGallery\app\extensions\widget;

if (!defined('WPINC')) {
    exit;
}

/**
 * Classic "Robo Gallery Widget": shows one gallery (a chosen one, or the
 * latest / a random / the most viewed one) through the [robo-gallery]
 * shortcode, so the usual access checks apply.
 */
class GalleryWidget extends \WP_Widget
{
    // Saved widgets are stored under this id (option widget_rbs_widget,
    // sidebars_widgets) - changing it would drop every configured widget.
    const ID_BASE = 'rbs_widget';

    // "Gallery" choices besides a gallery ID (stored in the widget settings).
    const LATEST      = -99;
    const RANDOM      = -98;
    const MOST_VIEWED = -97;

    public static function register(): void
    {
        register_widget(self::class);
    }

    public function __construct()
    {
        parent::__construct(
            self::ID_BASE,
            __('Robo Gallery Widget', 'robo-gallery'),
            array('description' => __('Publish gallery on your website.', 'robo-gallery'))
        );
    }

    public function widget($args, $instance)
    {
        $title     = apply_filters('widget_title', isset($instance['title']) ? $instance['title'] : '', $instance, $this->id_base);
        $galleryId = $this->resolveGalleryId(isset($instance['galleryId']) ? (int) $instance['galleryId'] : 0);

        echo wp_kses_post($args['before_widget']);

        if (!empty($title)) {
            echo wp_kses_post($args['before_title'] . $title . $args['after_title']);
        }

        if ($galleryId > 0) {
            echo do_shortcode('[robo-gallery id="' . $galleryId . '"]');
        }

        echo wp_kses_post($args['after_widget']);
    }

    public function form($instance)
    {
        $title     = isset($instance['title']) ? $instance['title'] : __('Gallery Widget', 'robo-gallery');
        $galleryId = isset($instance['galleryId']) ? (int) $instance['galleryId'] : 0;

        if (!class_exists('rbsImageWidgetGallery') && !ROBO_GALLERY_TYR) {
            ?>
            <p>
                <?php esc_html_e('You need to install new version of the widget to make it work. Install free or paid version of the Image Widget.', 'robo-gallery'); ?>
            </p>
            <p style="text-align: center; margin-bottom: 0;">
                <a class="button" href="<?php echo esc_url(self_admin_url('plugin-install.php?tab=search&type=term&s=image-widget-rb')); ?>" style="margin-bottom: 12px;"><?php esc_html_e('Install Free Version', 'robo-gallery'); ?></a>
                <a class="button button-primary" href="<?php echo esc_url(ROBO_GALLERY_URL_UPDATEPRO); ?>" target="_blank"><?php esc_html_e('Install Paid Version', 'robo-gallery'); ?></a>
            </p>
            <?php
        }
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title'); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress ?>:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('galleryId')); ?>"><?php esc_html_e('Gallery:'); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress ?></label>
            <?php $this->galleryDropdown($galleryId); ?>
        </p>
        <p>
            <?php esc_html_e('Configure it in', 'robo-gallery'); ?>
            <a target="_blank" href="<?php echo esc_url(admin_url('edit.php?post_type=' . ROBO_GALLERY_TYPE_POST)); ?>"><?php esc_html_e('Robo Gallery plugin', 'robo-gallery'); ?></a>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance)
    {
        return array(
            'title'     => !empty($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '',
            'galleryId' => !empty($new_instance['galleryId']) ? (int) $new_instance['galleryId'] : 0,
        );
    }

    /**
     * A gallery ID as is; the latest / random / most viewed choice resolved to
     * a published gallery (the listing filters keep direct-link-only ones out).
     *
     * @param int $galleryId
     * @return int 0 when there is none
     */
    private function resolveGalleryId(int $galleryId): int
    {
        if (!in_array($galleryId, array(self::LATEST, self::RANDOM, self::MOST_VIEWED), true)) {
            return $galleryId;
        }

        $query = array(
            'numberposts' => 1,
            'post_type'   => ROBO_GALLERY_TYPE_POST,
            'post_status' => 'publish',
            'fields'      => 'ids',
        );

        if (self::LATEST === $galleryId) {
            $query['orderby'] = 'date';
            $query['order']   = 'DESC';
        } elseif (self::RANDOM === $galleryId) {
            $query['orderby'] = 'rand';
        } else {
            $query['orderby']  = 'meta_value_num';
            $query['meta_key'] = \RoboGallery\app\PluginConstants::VIEWS_META_KEY;
            $query['order']    = 'DESC';
        }

        $ids = get_posts($query);
        return isset($ids[0]) ? (int) $ids[0] : 0;
    }

    /**
     * @param int $selected
     */
    private function galleryDropdown(int $selected): void
    {
        $args = array(
            'sort_order'   => 'ASC',
            'sort_column'  => 'post_title',
            'hierarchical' => 0,
            'selected'     => $selected,
            'post_type'    => ROBO_GALLERY_TYPE_POST,
            'post_status'  => 'publish',
        );
        ?>
        <select id="<?php echo esc_attr($this->get_field_id('galleryId')); ?>" name="<?php echo esc_attr($this->get_field_name('galleryId')); ?>">
            <option value="<?php echo (int) self::LATEST; ?>" <?php selected($selected, self::LATEST); ?>><?php echo esc_html('- ' . __('Latest Gallery', 'robo-gallery')); ?></option>
            <option value="<?php echo (int) self::RANDOM; ?>" <?php selected($selected, self::RANDOM); ?>><?php echo esc_html('- ' . __('Random Gallery', 'robo-gallery')); ?></option>
            <option value="<?php echo (int) self::MOST_VIEWED; ?>" <?php selected($selected, self::MOST_VIEWED); ?>><?php echo esc_html('- ' . __('Most Viewed Gallery', 'robo-gallery')); ?></option>
            <option value="-100" disabled>-----------------------------</option>
            <?php echo walk_page_dropdown_tree(get_pages($args), 0, $args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the core walker escapes titles and values ?>
        </select>
        <?php
    }
}
