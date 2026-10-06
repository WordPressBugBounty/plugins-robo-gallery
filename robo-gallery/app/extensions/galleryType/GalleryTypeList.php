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

if (!defined('ROBO_GALLERY_TYPE_GRID')) {
    define('ROBO_GALLERY_TYPE_GRID', 'grid');
}

/**
 * Registry of the gallery layout types. A type has one source ("grid"), or
 * numbered sources for its presets ("gridpro-1".."gridpro-8"). The meta
 * rsg_gallery_type holds the type, rsg_gallery_type_source the source; a saved
 * source is not limited to this list (custom-342 exists on sites).
 */
class GalleryTypeList
{
    public static $types = array();

    public static function addType(&$types, $type, $name = '', $source_to = 0)
    {
        $types[$type] = array(
            'type'      => $type,
            'source'    => $source_to ? $type . '-' : $type,
            'source_to' => $source_to ? (int) $source_to : 0,
            'name'      => $name ? $name : ucfirst($type),
        );
    }

    public static function getFull()
    {
        if (count(self::$types)) {
            return self::$types;
        }

        $types = array();

        /* Free version */
        self::addType($types, 'grid');
        self::addType($types, 'masonry');
        self::addType($types, 'polaroid');
        self::addType($types, 'mosaic');
        self::addType($types, 'youtube');
        self::addType($types, 'slider');
        self::addType($types, 'custom');

        /* Pro version: the number is how many presets the key plugin has (includes/themes/<type>_<n>.php);
           the type dialog bundle (build/, its source is outside this repo) lists the same numbers */
        self::addType($types, 'mosaicpro', 'Mosaic Pro', 6);
        self::addType($types, 'masonrypro', 'Masonry Pro', 8);
        self::addType($types, 'gridpro', 'Grid Pro', 8);
        self::addType($types, 'youtubepro', 'Youtube Pro', 6);
        self::addType($types, 'polaroidpro', 'Polaroid Pro', 8);
        self::addType($types, 'wallstylepro', 'Wallstyle Pro', 6);

        /* Version 5 */
        self::addType($types, 'robogrid', 'Fusion Grid');

        self::$types = $types;
        return $types;
    }

    public static function getTypes()
    {
        return array_column(self::getFull(), 'type');
    }

    /**
     * @return array source => type info with that source
     */
    public static function getAllSources()
    {
        $sources = array();

        foreach (self::getFull() as $t) {
            if (!$t['source_to']) {
                $sources[$t['source']] = $t;
                continue;
            }

            for ($i = 1; $i <= $t['source_to']; $i++) {
                $fullSource                     = $t['source'] . $i;
                $sources[$fullSource]           = $t;
                $sources[$fullSource]['source'] = $fullSource;
            }
        }

        return $sources;
    }

    public static function getSources()
    {
        return array_keys(self::getAllSources());
    }

    public static function getByType($name)
    {
        $types = self::getFull();

        return isset($types[$name]) ? $types[$name] : false;
    }

    public static function getTypeBySource($source)
    {
        $sources = self::getAllSources();

        return isset($sources[$source]) ? $sources[$source]['type'] : false;
    }

    public static function getSourceByName($name)
    {
        $type = self::getByType($name);

        return $type ? $type['source'] : false;
    }

    public static function isValidType($type)
    {
        return in_array($type, self::getTypes(), true);
    }

    public static function isValidSource($source)
    {
        return in_array($source, self::getSources(), true);
    }

    public static function sanitizeSource($source)
    {
        return preg_replace('/[^a-z0-9_-]/i', '', (string) $source);
    }

    /**
     * The stored type (rsg_gallery_type): letters only, as rbsGalleryUtils::getTypeGallery()
     * reads it. Empty means grid everywhere (frontend, editor), so it is saved as grid.
     * A non-empty type that isn't in the registry is kept: old sites have such values.
     *
     * @param mixed $type
     * @return string
     */
    public static function sanitizeType($type)
    {
        $type = is_scalar($type) ? preg_replace('/[^A-Za-z]/', '', (string) $type) : '';
        return '' === $type ? ROBO_GALLERY_TYPE_GRID : $type;
    }
}

// the registry's former name
class_alias(GalleryTypeList::class, 'RoboGallery\App\Extension\GalleryTypes\GalleryTypeList');
