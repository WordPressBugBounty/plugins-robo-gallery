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

namespace RoboGallery\app\extensions\slider;

use RoboGallery\app\GalleryOptions;

defined('WPINC') || exit;

/**
 * Settings of the slider type, the slider set of GalleryOptions (robo-gallery-options
 * meta, the same settings panel as Fusion Grid).
 *
 * A slider saved before kept every setting in its own rsg_* meta (the old metaboxes).
 * Those meta are left as they are: until the slider is saved in the settings panel,
 * GalleryOptions::getWithValues() takes the options it has not stored from
 * legacyValues(), and the first save writes them all to robo-gallery-options.
 */
class SliderOptions
{
    /* the settings the slider script gets in its config (slider/layout.php, react_parts/slider/src/config.js);
       the size is the container's style, the order, image size and text source are applied here */
    const PUBLIC_OPTIONS = array(
        'sliderPreload', 'sliderNavButtons', 'sliderScrollbar', 'sliderPagination',
        'sliderAutoplay', 'sliderDelay', 'sliderDirection', 'sliderEffect', 'sliderPerView', 'sliderSpaceBetween',
        'sliderContent', 'sliderContentTheme',
        'customCss',
    );

    /* the rsg_* meta of the old slider metaboxes; a slider with none of them has no old settings */
    const LEGACY_META = array(
        'autoWidth', 'width', 'autoHeight', 'height', 'orderby', 'source', 'preload',
        'nav_buttons', 'nav_scrollbar', 'nav_pagination',
        'autoplay', 'delay', 'direction', 'effect', 'sliderView',
        'content', 'content_source', 'content_theme',
    );

    /**
     * @return array option_id => definition, as in GalleryOptions::getOptionConfig()
     */
    public static function getOptionConfig(): array
    {
        return [

            'sliderWidthAuto'     => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'general',
            ],

            'sliderWidthValue'    => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 100,
                'group'    => 'general',
                'params'   => ['min' => 1],
            ],

            'sliderWidthType'     => [
                'type'    => 'select',
                'options' => ['%', 'px'],
                'default' => '%',
                'group'   => 'general',
            ],

            'sliderHeightAuto'    => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'general',
            ],

            'sliderHeightValue'   => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 100,
                'group'    => 'general',
                'params'   => ['min' => 1],
            ],

            'sliderHeightType'    => [
                'type'    => 'select',
                'options' => ['vh', 'px', '%'],
                'default' => 'vh',
                'group'   => 'general',
            ],

            // the values of the old orderby field
            'sliderOrderby'       => [
                'type'    => 'select',
                'options' => ['categoryD', 'categoryU', 'titleD', 'titleU', 'dateD', 'dateU', 'random'],
                'default' => 'categoryD',
                'group'   => 'general',
            ],

            // an image size name; "original" is no registered size, so WordPress gives the full image
            'sliderImageSize'     => [
                'type'    => 'select',
                'options' => ['thumbnail', 'medium', 'medium_large', 'original'],
                'default' => 'original',
                'group'   => 'general',
            ],

            'sliderPreload'       => [
                'type'    => 'select',
                'options' => ['preload', 'off', 'lazy', 'lazy_white'],
                'default' => 'preload',
                'group'   => 'general',
            ],

            /* navigation */

            'sliderNavButtons'    => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'navigation',
            ],

            'sliderScrollbar'     => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'navigation',
            ],

            // a Swiper pagination type, or none
            'sliderPagination'    => [
                'type'    => 'select',
                'options' => ['bullets', 'fraction', 'progressbar', 'disable'],
                'default' => 'bullets',
                'group'   => 'navigation',
            ],

            /* animation */

            'sliderAutoplay'      => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => false,
                'group'    => 'animation',
            ],

            'sliderDelay'         => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 1500,
                'group'    => 'animation',
                'params'   => ['min' => 0],
            ],

            'sliderDirection'     => [
                'type'    => 'select',
                'options' => ['horizontal', 'vertical'],
                'default' => 'horizontal',
                'group'   => 'animation',
            ],

            'sliderEffect'        => [
                'type'    => 'select',
                'options' => ['slide', 'fade', 'cube', 'coverflow', 'flip', 'cards', 'creative'],
                'default' => 'slide',
                'group'   => 'animation',
            ],

            'sliderPerView'       => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 1,
                'group'    => 'animation',
                'params'   => ['min' => 1, 'max' => 10],
            ],

            'sliderSpaceBetween'  => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 0,
                'group'    => 'animation',
                'params'   => ['min' => 0, 'max' => 200],
            ],

            /* content panel */

            'sliderContent'       => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'content',
            ],

            'sliderContentSource' => [
                'type'    => 'select',
                'options' => ['title', 'caption', 'description'],
                'default' => 'title',
                'group'   => 'content',
            ],

            'sliderContentTheme'  => [
                'type'    => 'select',
                'options' => ['light', 'dark'],
                'default' => 'light',
                'group'   => 'content',
            ],

        ];
    }

    /**
     * The settings of a slider saved by the old metaboxes, as option_id => value;
     * empty when it has none (a new slider gets the defaults). A meta an old slider
     * does not have gets the value the frontend used without it, so the slider
     * looks as before.
     *
     * @param int $galleryId
     * @return array
     */
    public static function legacyValues(int $galleryId): array
    {
        $meta = get_post_meta($galleryId);
        if (!is_array($meta)) {
            return array();
        }

        $found = false;
        foreach (self::LEGACY_META as $name) {
            if (array_key_exists(ROBO_GALLERY_PREFIX . $name, $meta)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            return array();
        }

        $get = function ($name) use ($galleryId) {
            return get_post_meta($galleryId, ROBO_GALLERY_PREFIX . $name, true);
        };

        $values = array();

        // without its switch the size was used, 100% / 100vh without a size
        $values['sliderWidthAuto'] = (bool) $get('autoWidth');
        list($values['sliderWidthValue'], $values['sliderWidthType']) = self::legacySize($get('width'), '%');
        $values['sliderHeightAuto'] = (bool) $get('autoHeight');
        list($values['sliderHeightValue'], $values['sliderHeightType']) = self::legacySize($get('height'), 'vh');

        $values['sliderOrderby']   = $get('orderby') ?: 'categoryD';
        $values['sliderImageSize'] = $get('source') ?: 'medium';
        $values['sliderPreload']   = $get('preload') ?: 'preload';

        $values['sliderNavButtons'] = 'show' === $get('nav_buttons');
        $values['sliderScrollbar']  = 'show' === $get('nav_scrollbar');
        $values['sliderPagination'] = $get('nav_pagination') ?: 'disable';

        $values['sliderAutoplay']  = (bool) $get('autoplay');
        $values['sliderDelay']     = (int) $get('delay');
        $values['sliderDirection'] = $get('direction') ?: 'horizontal';
        $values['sliderEffect']    = $get('effect') ?: 'slide';

        // sliderView had no field; set by hand, it could change the slides per view and their spacing
        $view = $get('sliderView');
        $values['sliderPerView']      = is_array($view) && isset($view['slidesPerView']) && $view['slidesPerView'] > 0 ? (int) $view['slidesPerView'] : 1;
        $values['sliderSpaceBetween'] = is_array($view) && isset($view['spaceBetween']) && $view['spaceBetween'] > 0 ? (int) $view['spaceBetween'] : 0;

        // the old "desc" is "description" now; without a source the panel had no text
        $sources = array('title' => 'title', 'caption' => 'caption', 'desc' => 'description');
        $source  = $get('content_source');
        $source  = is_string($source) && isset($sources[$source]) ? $sources[$source] : '';

        $values['sliderContent']       = 'show' === $get('content') && '' !== $source;
        $values['sliderContentSource'] = $source ?: 'title';
        $values['sliderContentTheme']  = 'light' === $get('content_theme') ? 'light' : 'dark';

        // like a save: a value outside an option's list becomes its default
        $definitions = GalleryOptions::getOptionsArray(GalleryOptions::SET_SLIDER);
        foreach ($values as $optionId => $value) {
            $value              = GalleryOptions::sanitizeValue($definitions[$optionId], $value);
            $values[$optionId] = is_wp_error($value) ? $definitions[$optionId]['default'] : $value;
        }

        return $values;
    }

    /**
     * The values a slider renders with (GalleryOptions::getWithValues(): its own,
     * the old ones or the defaults), each checked again by its definition - the
     * meta can be written around the settings panel.
     *
     * @param int $galleryId
     * @return array option_id => value
     */
    public static function forFrontend(int $galleryId): array
    {
        $values = array();
        foreach (GalleryOptions::getWithValues($galleryId, GalleryOptions::SET_SLIDER) as $optionId => $option) {
            $value              = GalleryOptions::sanitizeValue($option, $option['value']);
            $values[$optionId] = is_wp_error($value) ? $option['default'] : $value;
        }

        return $values;
    }

    /**
     * The old composite size {value, type}: [number, unit], 100 of $defaultType without a valid one.
     *
     * @param mixed  $size
     * @param string $defaultType
     * @return array
     */
    private static function legacySize($size, string $defaultType): array
    {
        if (!is_array($size) || !isset($size['value'], $size['type']) || !is_numeric($size['value'])) {
            return array(100, $defaultType);
        }

        return array((int) $size['value'], $size['type']);
    }
}
