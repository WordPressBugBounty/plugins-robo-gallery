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

use RoboGallery\app\GalleryUtils;

defined('WPINC') || exit;

/**
 * Preset settings of a new gallery: post-new.php?rsg_gallery_type=<type>-<n>
 * gives the option fields their defaults from themes/ (Pro presets from the key
 * plugin). The presets are big arrays, so they are read only then.
 */
class ThemePresets
{
    /** @var array|null null until first needed */
    private $presets = null;

    public function register(): void
    {
        add_filter('cmbre2_rbs_args_defaultvalue', array($this, 'applyPreset'), 10, 2);
    }

    /**
     * @param array  $args  CMB2 field args
     * @param object $field CMB2 field
     * @return array
     */
    public function applyPreset($args, $field)
    {
        if (empty($_GET['rsg_gallery_type']) || !is_string($_GET['rsg_gallery_type'])) {
            return $args;
        }

        $typeGallery = preg_replace('/[^A-Za-z0-9-]/', '', $_GET['rsg_gallery_type']);
        if (!$typeGallery || empty($args['_id'])) {
            return $args;
        }

        $id = preg_replace('/^' . ROBO_GALLERY_PREFIX . '/', '', $args['_id']);

        // "gridpro-3" - type and preset number; a type alone means its preset 1
        $typeId = 1;
        if (false !== strpos($typeGallery, '-')) {
            $parts = explode('-', $typeGallery);
            if (count($parts) != 2) {
                return $args;
            }
            list($typeGallery, $typeId) = $parts;
        }

        $presets = $this->getPresets();
        $code    = $typeGallery . '-' . $typeId;

        if (!isset($presets[$typeGallery][$code][$id])) {
            return $args;
        }
        $preset = $presets[$typeGallery][$code];

        $args['default'] = $preset[$id];

        if (isset($preset['fields'][$id]) && 'hide' == $preset['fields'][$id]) {
            $args['type'] = is_array($args['default']) ? 'hidden_array' : 'hidden';
        }

        return $args;
    }

    private function getPresets(): array
    {
        if (null !== $this->presets) {
            return $this->presets;
        }

        $presets = $this->readFreePresets();

        if (GalleryUtils::compareVersion('2.1') && class_exists('roboGalleryThemePro')) {
            $presets = array_merge($presets, \roboGalleryThemePro::getThemesArray());
        }

        $this->presets = (array) apply_filters('robogallery_theme_init', $presets);

        return $this->presets;
    }

    /**
     * Free presets are themes/<type>_<n>.php for the registry's types: n = 1
     * for a type with one source, 1..source_to for numbered ones.
     *
     * @return array type => array(<type>-<n> => preset settings)
     */
    private function readFreePresets(): array
    {
        $presets = array();
        $dir     = __DIR__ . '/themes/';

        foreach (GalleryTypeList::getFull() as $type => $info) {
            $count = max(1, $info['source_to']);

            for ($n = 1; $n <= $count; $n++) {
                $file = $dir . $type . '_' . $n . '.php';
                if (is_file($file)) {
                    $presets[$type][$type . '-' . $n] = include $file;
                }
            }
        }

        return $presets;
    }
}
