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
 * Preset settings of a new gallery (post-new.php?rsg_gallery_type=<type>-<n>):
 * themes/, the defaults of a new classic gallery (classic\ClassicOptions). The
 * presets are big arrays, so they are read only when needed.
 */
class ThemePresets
{
    /** @var array|null null until first needed */
    private $presets = null;

    /**
     * The preset of a source: "gridpro-3" - type and preset number; a type alone
     * means its preset 1. Also the settings of a new classic gallery
     * (classic\ClassicOptions::defaults()).
     *
     * @param string $source
     * @return array field id without the prefix => value; empty without a preset
     */
    public function forSource(string $source): array
    {
        $typeGallery = preg_replace('/[^A-Za-z0-9-]/', '', $source);
        if (!$typeGallery) {
            return array();
        }

        $typeId = 1;
        if (false !== strpos($typeGallery, '-')) {
            $parts = explode('-', $typeGallery);
            if (count($parts) != 2) {
                return array();
            }
            list($typeGallery, $typeId) = $parts;
        }

        $presets = $this->getPresets();
        $code    = $typeGallery . '-' . $typeId;

        return isset($presets[$typeGallery][$code]) && is_array($presets[$typeGallery][$code])
            ? $presets[$typeGallery][$code]
            : array();
    }

    private function getPresets(): array
    {
        if (null !== $this->presets) {
            return $this->presets;
        }

        $this->presets = (array) apply_filters('robogallery_theme_init', $this->readPresets());

        return $this->presets;
    }

    /**
     * Presets are themes/<type>_<n>.php for the registry's types: n = 1 for a
     * type with one source, 1..source_to for numbered ones.
     *
     * @return array type => array(<type>-<n> => preset settings)
     */
    private function readPresets(): array
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
