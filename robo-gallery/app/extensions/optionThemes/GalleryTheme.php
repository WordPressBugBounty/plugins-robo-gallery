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

namespace RoboGallery\app\extensions\optionThemes;

use RoboGallery\app\GalleryOptions;

defined('WPINC') || exit;

/**
 * A gallery that follows an option theme (OptionThemes): the theme's values,
 * except the options changed in the gallery itself (its overrides, the values
 * that differ from the theme). The gallery's own settings stay in the
 * robo-gallery-options meta as before; this adds two meta:
 *  - THEME_META: the theme id;
 *  - OVERRIDES_META: the options of the gallery's own, recorded when its
 *    settings are saved (recordOverrides()), so a later change of the theme
 *    reaches all the other options.
 * A theme that no longer exists, or is not for the gallery's content type (its
 * type was changed since), is ignored: the gallery's own settings again.
 */
class GalleryTheme
{
    const THEME_META = '_robogallery_option_theme';

    const OVERRIDES_META = '_robogallery_option_overrides';

    public static function themeId(int $galleryId): string
    {
        $id = get_post_meta($galleryId, self::THEME_META, true);

        return OptionThemes::isValidId($id) ? $id : '';
    }

    /**
     * @return array|null the theme the gallery follows
     */
    public static function theme(int $galleryId): ?array
    {
        $id = self::themeId($galleryId);

        return '' === $id ? null : OptionThemes::getForSet($id, GalleryOptions::setOf($galleryId));
    }

    /**
     * @return string[]
     */
    public static function overrides(int $galleryId): array
    {
        $overrides = get_post_meta($galleryId, self::OVERRIDES_META, true);

        return is_array($overrides) ? array_values(array_filter($overrides, 'is_string')) : array();
    }

    /**
     * @return array|null option_id => value of the theme (every option it sets), null without a theme
     */
    public static function themeValues(int $galleryId): ?array
    {
        $theme = self::theme($galleryId);

        return $theme ? OptionThemes::values($theme) : null;
    }

    /**
     * The settings the gallery shows: $stored with the theme's values over every option it does not override.
     *
     * @param int   $galleryId
     * @param array $stored the gallery's own settings (GalleryOptions::getStored())
     * @return array option_id => value
     */
    public static function apply(int $galleryId, array $stored): array
    {
        $themeValues = self::themeValues($galleryId);
        if (null === $themeValues) {
            return $stored;
        }

        $overrides = array_flip(self::overrides($galleryId));
        foreach ($themeValues as $optionId => $value) {
            if (!isset($overrides[$optionId]) || !array_key_exists($optionId, $stored)) {
                $stored[$optionId] = $value;
            }
        }

        return $stored;
    }

    /**
     * The gallery follows the theme from now on, with none of its own changes.
     */
    public static function link(int $galleryId, string $themeId): void
    {
        update_post_meta($galleryId, self::THEME_META, $themeId);
        delete_post_meta($galleryId, self::OVERRIDES_META);
    }

    /**
     * The gallery stops following its theme and keeps the look: what it shows becomes its own settings.
     */
    public static function unlink(int $galleryId): void
    {
        $effective = GalleryOptions::getEffective($galleryId);

        delete_post_meta($galleryId, self::THEME_META);
        delete_post_meta($galleryId, self::OVERRIDES_META);

        GalleryOptions::save($galleryId, $effective);
    }

    public static function clearOverrides(int $galleryId): void
    {
        delete_post_meta($galleryId, self::OVERRIDES_META);
    }

    /**
     * Called with the values of a settings save: an option that differs from the
     * theme becomes the gallery's own, one equal to it follows the theme again.
     *
     * @param int   $galleryId
     * @param array $values option_id => sanitized value
     */
    public static function recordOverrides(int $galleryId, array $values): void
    {
        $themeValues = self::themeValues($galleryId);
        if (null === $themeValues) {
            return;
        }

        $overrides = array_flip(self::overrides($galleryId));
        foreach ($values as $optionId => $value) {
            if (!array_key_exists($optionId, $themeValues)) {
                continue;
            }
            if (self::same($value, $themeValues[$optionId])) {
                unset($overrides[$optionId]);
            } else {
                $overrides[$optionId] = true;
            }
        }

        if ($overrides) {
            update_post_meta($galleryId, self::OVERRIDES_META, array_keys($overrides));
        } else {
            delete_post_meta($galleryId, self::OVERRIDES_META);
        }
    }

    /**
     * @return int[] theme id => number of galleries that follow it
     */
    public static function galleryCounts(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.meta_value AS theme, COUNT(*) AS galleries FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status <> 'trash'
             GROUP BY pm.meta_value",
            self::THEME_META,
            ROBO_GALLERY_TYPE_POST
        ));

        $counts = array();
        foreach ((array) $rows as $row) {
            $counts[(string) $row->theme] = (int) $row->galleries;
        }

        return $counts;
    }

    /**
     * @return int[] the galleries that follow the theme (any status)
     */
    public static function galleriesUsing(string $themeId): array
    {
        return array_map('intval', get_posts(array(
            'post_type'      => ROBO_GALLERY_TYPE_POST,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => self::THEME_META,
            'meta_value'     => $themeId,
        )));
    }

    /* both sanitized by the same rule; a list may come in another order */
    private static function same($a, $b): bool
    {
        if (is_array($a) || is_array($b)) {
            $a = (array) $a;
            $b = (array) $b;
            sort($a);
            sort($b);
        }

        return $a === $b;
    }
}
