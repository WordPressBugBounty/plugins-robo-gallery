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

namespace RoboGallery\app;

use RoboGallery\app\extensions\validation\CssColor;

defined('WPINC') || exit;

/**
 * Gallery settings stored in the robo-gallery-options meta (robogrid/v5
 * galleries): definitions and defaults (getOptionConfig), reading and saving
 * (getStored / getWithValues / save) and sanitizing (sanitizeValue). The one
 * place that reads/writes this meta: the REST options controller (read/write),
 * the robofields REST field and the access extension (read).
 */
class GalleryOptions
{

    /**
     * @return array option_id => definition (type, sanitize, default, group, options/params)
     */
    public static function getOptionConfig()
    {

        return [

            'widthAuto'                       => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'general',
            ],

            'align'                           => [
                'type'    => 'select',
                'options' => ['left', 'right', 'center', 'no'],
                'default' => 'center',
                'group'   => 'general',
            ],

            'widthValue'                      => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 100,
                'group'    => 'general',
            ],

            'widthType'                       => [
                'type'    => 'select',
                'options' => ['%', 'px', 'rem', 'em', 'vw'],
                'default' => '%',
                'group'   => 'general',
            ],

            'maxWidthValue'                   => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 100,
                'group'    => 'general',
            ],

            'maxWidthType'                    => [
                'type'    => 'select',
                'options' => ['%', 'px', 'rem', 'em', 'vw'],
                'default' => '%',
                'group'   => 'general',
            ],

            'orderby'                         => [
                'type'    => 'select',
                'options' => ['order', 'orderU', 'random', 'title', 'titleU', 'date', 'dateU'],
                'default' => 'order',
                'group'   => 'general',
            ],

            'layout'                          => [
                'type'    => 'select',
                'options' => ['grid', 'masonry', 'columns', 'rows'],
                'default' => 'grid',
                'group'   => 'general',
            ],

            'layoutAdjustment'                => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'general',
            ],

            'targetRowHeight'                 => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 500,
                'group'    => 'general',
                'params'   => ['min' => 50, 'max' => 1000],
            ],

            'columns'                         => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 0,
                'group'    => 'general',
                'params'   => ['min' => 0, 'max' => 100],
            ],

            'spacing'                         => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 10,
                'group'    => 'general',
                'params'   => ['min' => 0, 'max' => 20],
            ],

            'loadingColor'                    => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => '#686868',
                'group'    => 'thumbnails',
            ],

            'loadingSize'                     => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'group'    => 'thumbnails',
                'default'  => 5,
                'params'   => ['min' => 1, 'max' => 20],
            ],

            'shadow'                          => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 0,
                'group'    => 'general',
                'params'   => ['min' => 0, 'max' => 24],
            ],

            /* thumbnails  */

            'hoverInvert'                     => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'thumbnails',
            ],

            'hoverHighlight'                  => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'thumbnails',
            ],

            'hoverEffect'                     => [
                'type'    => 'select',
                'options' => ['zoe', 'lily', 'sadie', 'static', 'disable'],
                'default' => 'lily',
                'group'   => 'thumbnails',
            ],

            'hoverTitleColor'                 => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => '#ffffff',
                'group'    => 'thumbnails',
            ],
            'hoverTitleBackgroundColor'       => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => 'rgba(0, 0, 0, 0.71)',
                'group'    => 'thumbnails',
            ],

            'hoverDescriptionColor'           => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => '#000000',
                'group'    => 'thumbnails',
            ],

            'hoverDescriptionBackgroundColor' => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => 'rgba(255, 255, 255, 0.67)',
                'group'    => 'thumbnails',
            ],

            'hoverBackgroundColor'            => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => 'rgba(0, 0, 0, 0.71)',
                'group'    => 'thumbnails',
            ],

            'hoverColor'                      => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => 'rgba(255, 255, 255, 0.67)',
                'group'    => 'thumbnails',
            ],


            'titleSource'                     => [
                'type'    => 'select',
                'options' => ['title', 'caption', 'description', 'disable'],
                'default' => 'title',
                'group'   => 'thumbnails',
            ],

            'descriptionSource'               => [
                'type'    => 'select',
                'options' => ['title', 'caption', 'description', 'disable'],
                'default' => 'description',
                'group'   => 'thumbnails',
            ],

            /* Polaroid panel */

            'polaroidMode'                    => [
                'type'    => 'select',
                'options' => ['top', 'left', 'bottom', 'right', 'disable'],
                'default' => 'disable',
                'group'   => 'polaroid',
            ],

            'polaroidTitleSource'             => [
                'type'    => 'select',
                'options' => ['title', 'caption', 'description', 'disable'],
                'default' => 'title',
                'group'   => 'polaroid',
            ],

            'polaroidDescriptionSource'       => [
                'type'    => 'select',
                'options' => ['title', 'caption', 'description', 'disable'],
                'default' => 'description',
                'group'   => 'polaroid',
            ],

            'polaroidTextColor'               => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => '#000000',
                'group'    => 'polaroid',
            ],

            'polaroidBackgroundColor'         => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => '#ffffff',
                'group'    => 'polaroid',
            ],

            'polaroidDescriptionSize'         => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 50,
                'group'    => 'polaroid',
                'params'   => ['min' => 10, 'max' => 90],
            ],

            /* Album */

            'albumHideCoverImage'             => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'album',
            ],

            'albumIconColor'                  => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => '#ffffff',
                'group'    => 'album',
            ],

            'albumIcon'                       => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Folder',
                'group'    => 'album',
            ],


            'navigationInterfaceColor'        => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => 'rgb(25,118,210)',
                'group'    => 'navigation',
            ],

            'pagination'                      => [
                'type'    => 'select',
                'options' => ['loadmore', 'pagination', 'disable'],
                'default' => 'loadmore',
                'group'   => 'navigation',
            ],

            'imagesPerPage'                   => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 12,
                'group'    => 'navigation',
            ],

            'breadcrumbs'                     => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'navigation',
            ],

            'topMenuMode'                     => [
                'type'    => 'select',
                'options' => ['off', 'compact', 'wide'],
                'default' => 'wide',
                'group'   => 'navigation',
            ],

            'sideMenu'                        => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'navigation',
            ],

            'rootGalleryInMenu'               => [
                'type'    => 'select',
                'options' => ['title', 'label', 'hide'],
                'default' => 'title',
                'group'   => 'navigation',
            ],

            'rootGalleryLabel'                => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Root Gallery',
                'group'    => 'navigation',
            ],

            'infiniteScroll'                  => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'navigation',
            ],

            /* ======================================================= */

            'autoPlay'                        => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => false,
                'group'    => 'lightbox',
            ],

            'timeout'                         => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 1500,
                'group'    => 'lightbox',
            ],

            'lightboxTitleSource'             => [
                'type'    => 'select',
                'options' => ['title', 'caption', 'description', 'disable'],
                'default' => 'title',
                'group'   => 'lightbox',
            ],

            'lightboxDescriptionSource'       => [
                'type'    => 'select',
                'options' => ['title', 'caption', 'description', 'disable'],
                'default' => 'description',
                'group'   => 'lightbox',
            ],

            'lightboxButtons'                 => [
                'type'     => 'multiselect',
                'sanitize' => 'string',
                'options'  => ['fullscreen', 'zoom', 'slideshow', 'share', 'download'],
                'default'  => ['fullscreen', 'zoom'],
                'group'    => 'lightbox',
            ],



            'labelButtonLoadMore'                 => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Load More',
                'group'    => 'labels',
            ],

            'labelButtonUp'                 => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Up',
                'group'    => 'labels',
            ],

            'labelButtonMenu'                 => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Menu',
                'group'    => 'labels',
            ],

            'labelSidebarMenuTitle'                 => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Gallery Menu',
                'group'    => 'labels',
            ],


            'accessDirectLinkOnly'                   => [
                'type'    => 'checkbox',
                'sanitize' => 'boolean',
                'default' => false,
                'group'   => 'access',
            ],

            'accessRequireToken'                     => [
                'type'    => 'checkbox',
                'sanitize' => 'boolean',
                'default' => true,
                'group'   => 'access',
            ],


            // no plugin password option: password protection is WordPress's own
            // post_password (Publish box -> Visibility)

        ];

    }

    /**
     * Definitions with option_id and a default sanitize rule filled in.
     *
     * @return array option_id => definition
     */
    public static function getOptionsArray()
    {
        $options = self::getOptionConfig();
        foreach ($options as $option_id => $option) {
            $options[$option_id]['option_id'] = $option_id;
            if (!isset($options[$option_id]['sanitize'])) {
                $options[$option_id]['sanitize'] = 'string';
            }
        }
        return $options;
    }

    /**
     * The gallery's stored settings as saved (no defaults).
     *
     * @param int $galleryId
     * @return array option_id => value
     */
    public static function getStored($galleryId)
    {
        $stored = get_post_meta((int) $galleryId, PluginConstants::OPTIONS_KEY, true);
        return is_array($stored) ? $stored : array();
    }

    /**
     * Definitions with the gallery's value in 'value' (stored, or the default).
     *
     * @param int $galleryId
     * @return array option_id => definition + value
     */
    public static function getWithValues($galleryId)
    {
        $stored = self::getStored($galleryId);
        $result = array();

        foreach (self::getOptionsArray() as $option_id => $option) {
            $option['value']      = array_key_exists($option_id, $stored) ? $stored[$option_id] : $option['default'];
            $result[$option_id] = $option;
        }

        return $result;
    }

    /**
     * Saves values (already sanitized, see sanitizeValue()) over the gallery's
     * current settings. The whole array is written with only the known options,
     * so options removed from the config disappear on the next save.
     *
     * @param int   $galleryId
     * @param array $values option_id => value
     */
    public static function save($galleryId, array $values)
    {
        $options = array();
        foreach (self::getWithValues($galleryId) as $option_id => $option) {
            $options[$option_id] = $option['value'];
        }

        foreach ($values as $option_id => $value) {
            $options[$option_id] = $value;
        }

        // update_post_meta() also creates the meta when it's missing, and unlike
        // add_post_meta() it always runs the update_post_metadata filter - which
        // access\ExcludeListManager needs to put a direct-link-only gallery on the
        // exclude list on its very first settings save.
        update_post_meta((int) $galleryId, PluginConstants::OPTIONS_KEY, $options);
    }

    /**
     * Validates/sanitizes an incoming value by the option's definition.
     *
     * @param array $option definition (from getOptionsArray())
     * @param mixed $value
     * @return mixed|\WP_Error
     */
    public static function sanitizeValue(array $option, $value)
    {
        if ('multiselect' === $option['type']) {
            return self::sanitizeMultiselect($value, $option);
        }

        return self::sanitizeText($value, $option);
    }

    /**
     * @param mixed $values
     * @param array $option
     * @return array|\WP_Error only the values listed in the option's 'options'
     */
    private static function sanitizeMultiselect($values, $option)
    {
        if (empty($values)) {
            return array();
        }

        if (!is_array($values)) {
            return new \WP_Error(
                'rest_options_value_invalid',
                __('An invalid setting value was passed.', 'robo-gallery'),
                array('status' => 400)
            );
        }

        $final_values = array();
        $allow_items  = $option['options'];
        foreach ($values as $value) {
            if (in_array($value, $allow_items, true)) {
                $final_values[] = $value;
            }
        }

        return $final_values;
    }

    /**
     * A color setting ('sanitize' => 'color'): a valid CSS color, else the
     * option's default. Used when saving and when handing stored values out
     * (restapi GalleryFields), so values saved before this check are covered too.
     *
     * @param mixed $value
     * @param array $option
     * @return string
     */
    public static function sanitizeColor($value, array $option): string
    {
        return CssColor::sanitize(is_string($value) ? $value : '', (string) $option['default']);
    }

    /**
     * By the option's 'sanitize' rule (boolean / integer with min-max / color / string);
     * a value outside the option's 'options' list falls back to the default.
     *
     * @param mixed $value
     * @param array $option
     * @return mixed
     */
    private static function sanitizeText($value, $option)
    {
        if (!isset($option['sanitize'])) {
            $option['sanitize'] = 'string';
        }

        switch ($option['sanitize']) {
            case 'boolean':
                $value = $value ? true : false;
                break;

            case 'integer':
                $value = (int) $value;
                if (isset($option['params']['min']) && $value < $option['params']['min']) {
                    $value = $option['params']['min'];
                }
                if (isset($option['params']['max']) && $value > $option['params']['max']) {
                    $value = $option['params']['max'];
                }
                break;

            case 'color':
                $value = self::sanitizeColor($value, $option);
                break;

            case 'string':
            default:
                $value = sanitize_text_field($value);
        }

        if (isset($option['options']) && !in_array($value, $option['options'], true)) {
            $value = $option['default'];
        }

        return $value;
    }
}
