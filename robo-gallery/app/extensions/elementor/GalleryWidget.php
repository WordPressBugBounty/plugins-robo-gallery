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

namespace RoboGallery\app\extensions\elementor;

defined('WPINC') || exit;

/**
 * The "Robo Gallery" widget of Elementor: a gallery, or a button / link that opens it in a popup
 * (extensions/popup). Rendered by the [robo-gallery] shortcode, so it has the same access checks
 * and output as the block.
 *
 * Stored in the page's _elementor_data: widget robo-gallery, settings galleryid, popup, popup_text -
 * keep the names.
 *
 * Loaded only from the elementor/widgets/register action (ElementorIntegration): Widget_Base exists then.
 */
class GalleryWidget extends \Elementor\Widget_Base
{
    const NAME = 'robo-gallery';

    public function get_name()
    {
        return self::NAME;
    }

    public function get_title()
    {
        return esc_html__('Robo Gallery', 'robo-gallery');
    }

    public function get_icon()
    {
        return 'eicon-gallery-grid';
    }

    public function get_categories()
    {
        return array('general');
    }

    public function get_keywords()
    {
        return array('robo', 'gallery', 'images', 'photos', 'slider', 'youtube');
    }

    /**
     * Elementor 3.22+ keeps a widget's HTML in its element cache unless it is dynamic. A gallery's
     * output depends on the visitor (access, token, REST nonce) and counts the view: never cached.
     */
    protected function is_dynamic_content(): bool
    {
        return true;
    }

    protected function register_controls()
    {
        $this->start_controls_section('section_gallery', array(
            'label' => esc_html__('Robo Gallery', 'robo-gallery'),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ));

        $this->add_control('galleryid', array(
            'label'   => esc_html__('Gallery', 'robo-gallery'),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'options' => self::galleryOptions(),
            'default' => '0',
        ));

        $this->add_control('popup', array(
            'label'       => esc_html__('Show as', 'robo-gallery'),
            'type'        => \Elementor\Controls_Manager::SELECT,
            'options'     => array(
                ''       => esc_html__('Gallery on the page', 'robo-gallery'),
                'button' => esc_html__('Button that opens a popup', 'robo-gallery'),
                'link'   => esc_html__('Link that opens a popup', 'robo-gallery'),
            ),
            'default'     => '',
            'description' => esc_html__('Popups are for Fusion Grid galleries (images, posts, YouTube); other types are shown on the page.', 'robo-gallery'),
        ));

        $this->add_control('popup_text', array(
            'label'       => esc_html__('Text', 'robo-gallery'),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'placeholder' => esc_html__('Open gallery', 'robo-gallery'),
            'default'     => '',
            'condition'   => array('popup!' => ''),
        ));

        $this->add_control('links', array(
            'type'            => \Elementor\Controls_Manager::RAW_HTML,
            'raw'             => sprintf(
                '<a href="%s" target="_blank" rel="noopener">%s</a> | <a href="%s" target="_blank" rel="noopener">%s</a>',
                esc_url(admin_url('post-new.php?post_type=' . ROBO_GALLERY_TYPE_POST . '&showDialog=1')),
                esc_html__('Create gallery', 'robo-gallery'),
                esc_url(admin_url('edit.php?post_type=' . ROBO_GALLERY_TYPE_POST)),
                esc_html__('All galleries', 'robo-gallery')
            ),
            'content_classes' => 'elementor-descriptor',
        ));

        $this->end_controls_section();
    }

    /**
     * The galleries a widget can show, as the block lists them: published, private and scheduled
     * (a draft shows nothing on the site). Elementor prints the option labels as HTML: escaped here.
     *
     * @return array id => label
     */
    private static function galleryOptions(): array
    {
        $options = array('0' => esc_html__('Select a gallery', 'robo-gallery'));

        $galleries = get_posts(array(
            'post_type'              => ROBO_GALLERY_TYPE_POST,
            'post_status'            => array('publish', 'private', 'future'),
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'numberposts'            => -1,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));

        $notes = array(
            'private' => __('private', 'robo-gallery'),
            'future'  => __('scheduled', 'robo-gallery'),
        );

        foreach ($galleries as $gallery) {
            $title = trim(wp_strip_all_tags($gallery->post_title));
            /* translators: 1: gallery title, 2: gallery ID */
            $label = sprintf(__('%1$s [ID: %2$d]', 'robo-gallery'), '' !== $title ? $title : __('(no title)', 'robo-gallery'), $gallery->ID);
            if (isset($notes[$gallery->post_status])) {
                $label .= ' (' . $notes[$gallery->post_status] . ')';
            }
            $options[(string) $gallery->ID] = esc_html($label);
        }

        return $options;
    }

    protected function render()
    {
        $settings  = $this->get_settings_for_display();
        $galleryId = isset($settings['galleryid']) && is_numeric($settings['galleryid']) ? (int) $settings['galleryid'] : 0;
        $editor    = self::isEditor();

        if ($galleryId <= 0 || ROBO_GALLERY_TYPE_POST !== get_post_type($galleryId)) {
            // a hint in the editor only; visitors get nothing
            if ($editor) {
                echo '<div class="elementor-alert elementor-alert-info">'
                    . esc_html__('Robo Gallery: select a gallery in the widget settings.', 'robo-gallery')
                    . '</div>';
            }
            return;
        }

        if (!function_exists('robo_gallery_shortcode')) {
            return;
        }

        $attr = array('id' => $galleryId);

        // GalleryPopup checks the values
        if (isset($settings['popup']) && is_string($settings['popup']) && '' !== $settings['popup']) {
            $attr['popup']      = $settings['popup'];
            $attr['popup_text'] = isset($settings['popup_text']) && is_string($settings['popup_text']) ? $settings['popup_text'] : '';
        }

        // the editor puts the widget's HTML into its preview with every change: the gallery's
        // script and style tags go in with it (the enqueued ones of an AJAX render are never printed)
        if ($editor) {
            $attr['assetsIncludeForced'] = 1;
        }

        // the shortcode's output: escaped where it is built
        echo robo_gallery_shortcode($attr); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private static function isEditor(): bool
    {
        $elementor = \Elementor\Plugin::$instance;

        return ($elementor->editor && $elementor->editor->is_edit_mode())
            || ($elementor->preview && $elementor->preview->is_preview_mode());
    }
}
