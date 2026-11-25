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
 * Option themes: named sets of gallery settings (GalleryOptions) a gallery can
 * follow (GalleryTheme). Built-in themes come with the plugin (builtin.php,
 * read-only, a plugin update may improve them); the site's own themes are kept
 * in one option, saved from a gallery's settings.
 *
 * A theme holds every option but the access ones (who may see a gallery is
 * never taken from a theme) and the text before / after the gallery (its content). An option a theme does not set is the plugin's
 * default, so a gallery that follows a theme looks the same wherever it is.
 *
 * Themes are for the Fusion Grid galleries (SETS: its sources robogrid, posts,
 * youtube), filtered by the content type: a theme of the site is for the
 * source it was saved from ('source'), a built-in one for all of them unless
 * builtin.php names its 'sets'. A theme holds only the look they share, the
 * robogrid options: never what a source shows (the posts / youtube groups).
 */
class OptionThemes
{
    const OPTION = ROBO_GALLERY_OPTIONS . 'option_themes';

    const BUILTIN_PREFIX = 'builtin-';

    const MAX_THEMES = 100;

    const MAX_NAME = 80;

    // options a theme never sets: who may see the gallery, its texts, whether its visitors may like and comment,
    // whether its clients choose images
    const EXCLUDED_GROUPS = array('access', 'text', 'reactions', 'proofing');

    // the option sets with themes: the sources of Fusion Grid
    const SETS = array(GalleryOptions::SET_ROBOGRID, GalleryOptions::SET_POSTS, GalleryOptions::SET_YOUTUBE);

    /**
     * Who may create, change and delete the site's themes: a change reaches the
     * galleries of other users that follow the theme.
     */
    public static function canManage(): bool
    {
        $postType = get_post_type_object(ROBO_GALLERY_TYPE_POST);
        $default  = $postType ? $postType->cap->edit_others_posts : 'edit_others_posts';

        /**
         * The capability to manage the option themes of the site.
         *
         * @param string $capability
         */
        return current_user_can((string) apply_filters('robo_gallery_option_themes_capability', $default));
    }

    /**
     * @return string[] the options a theme sets
     */
    public static function themableIds(): array
    {
        $ids = array();
        foreach (GalleryOptions::getOptionsArray() as $optionId => $option) {
            if (!in_array(isset($option['group']) ? $option['group'] : '', self::EXCLUDED_GROUPS, true)) {
                $ids[] = $optionId;
            }
        }

        return $ids;
    }

    /**
     * @param string $set an option set (GalleryOptions::setOf())
     */
    public static function hasThemes($set): bool
    {
        return in_array($set, self::SETS, true);
    }

    public static function isValidId($id): bool
    {
        return is_string($id) && 1 === preg_match('/^[a-z0-9-]{1,40}$/', $id);
    }

    /**
     * @return array[] id => theme { id, name, description, builtin, sets, values }, built-in first
     */
    public static function all(): array
    {
        return self::builtin() + self::own();
    }

    /**
     * @param string $set an option set
     * @return array[] id => theme, the themes for a gallery of the set
     */
    public static function forSet($set): array
    {
        return array_filter(self::all(), function ($theme) use ($set) {
            return in_array($set, $theme['sets'], true);
        });
    }

    public static function get($id): ?array
    {
        if (!self::isValidId($id)) {
            return null;
        }
        $themes = self::all();

        return isset($themes[$id]) ? $themes[$id] : null;
    }

    /**
     * @param string $id
     * @param string $set an option set
     * @return array|null the theme, when it is for a gallery of the set
     */
    public static function getForSet($id, $set): ?array
    {
        $theme = self::get($id);

        return $theme && in_array($set, $theme['sets'], true) ? $theme : null;
    }

    /**
     * Every option of the theme: its value, or the plugin's default.
     *
     * @param array $theme
     * @return array option_id => value
     */
    public static function values(array $theme): array
    {
        $options = GalleryOptions::getOptionsArray();
        $values  = array();
        foreach (self::themableIds() as $optionId) {
            if (array_key_exists($optionId, $theme['values'])) {
                $values[$optionId] = $theme['values'][$optionId];
                continue;
            }
            // sanitized like a saved value, so the two compare equal (a color is written one way)
            $default           = GalleryOptions::sanitizeValue($options[$optionId], $options[$optionId]['default']);
            $values[$optionId] = is_wp_error($default) ? $options[$optionId]['default'] : $default;
        }

        return $values;
    }

    /**
     * A new theme of the site.
     *
     * @param string $name
     * @param array  $values option_id => value
     * @param string $set the option set of the gallery it is saved from: the theme is for that content type
     * @return array|\WP_Error the theme
     */
    public static function create($name, array $values, $set)
    {
        if (!self::hasThemes($set)) {
            return new \WP_Error('robogallery_option_theme_set', __('This gallery type has no option themes.', 'robo-gallery'), array('status' => 400));
        }

        $name = self::sanitizeName($name);
        if ('' === $name) {
            return new \WP_Error('robogallery_option_theme_name', __('The theme needs a name.', 'robo-gallery'), array('status' => 400));
        }

        $themes = self::stored();
        if (count($themes) >= self::MAX_THEMES) {
            return new \WP_Error('robogallery_option_theme_limit', __('There are too many themes. Delete one first.', 'robo-gallery'), array('status' => 400));
        }

        do {
            $id = 'u' . bin2hex(random_bytes(6));
        } while (isset($themes[$id]));

        $themes[$id] = array(
            'name'     => $name,
            'source'   => $set,
            'values'   => self::sanitizeValues($values),
            'modified' => time(),
        );
        self::store($themes);

        return self::get($id);
    }

    /**
     * Changes the name and / or the values of a theme of the site.
     *
     * @param string $id
     * @param array  $changes name, values
     * @return array|\WP_Error the theme
     */
    public static function update($id, array $changes)
    {
        $themes = self::stored();
        if (!self::isValidId($id) || !isset($themes[$id])) {
            return self::notFound();
        }

        if (array_key_exists('name', $changes)) {
            $name = self::sanitizeName($changes['name']);
            if ('' === $name) {
                return new \WP_Error('robogallery_option_theme_name', __('The theme needs a name.', 'robo-gallery'), array('status' => 400));
            }
            $themes[$id]['name'] = $name;
        }
        if (isset($changes['values']) && is_array($changes['values'])) {
            $themes[$id]['values'] = self::sanitizeValues($changes['values']);
        }
        $themes[$id]['modified'] = time();
        self::store($themes);

        return self::get($id);
    }

    /**
     * @param string $id a theme of the site
     * @return bool
     */
    public static function delete($id): bool
    {
        $themes = self::stored();
        if (!self::isValidId($id) || !isset($themes[$id])) {
            return false;
        }
        unset($themes[$id]);
        self::store($themes);

        return true;
    }

    public static function notFound(): \WP_Error
    {
        return new \WP_Error('robogallery_option_theme_not_found', __('The theme was not found.', 'robo-gallery'), array('status' => 404));
    }

    public static function sanitizeName($name): string
    {
        $name = is_string($name) ? trim(sanitize_text_field($name)) : '';

        return function_exists('mb_substr') ? mb_substr($name, 0, self::MAX_NAME) : substr($name, 0, self::MAX_NAME);
    }

    /**
     * @return array[] id => theme
     */
    private static function builtin(): array
    {
        static $themes = null;
        if (null !== $themes) {
            return $themes;
        }

        $themes = array();
        foreach (include __DIR__ . '/builtin.php' as $slug => $theme) {
            $id          = self::BUILTIN_PREFIX . $slug;
            $themes[$id] = array(
                'id'          => $id,
                'name'        => $theme['name'],
                'description' => $theme['description'],
                'builtin'     => true,
                'sets'        => isset($theme['sets']) ? array_values(array_intersect(self::SETS, $theme['sets'])) : self::SETS,
                'values'      => self::sanitizeValues($theme['values']),
            );
        }

        return $themes;
    }

    /**
     * @return array[] id => theme
     */
    private static function own(): array
    {
        $themes = array();
        foreach (self::stored() as $id => $theme) {
            $themes[$id] = array(
                'id'          => $id,
                'name'        => $theme['name'],
                'description' => '',
                'builtin'     => false,
                'sets'        => array($theme['source']),
                'values'      => $theme['values'],
            );
        }

        uasort($themes, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $themes;
    }

    /**
     * The stored themes, with what is not a valid theme left out.
     *
     * @return array id => { name, source, values, modified }
     */
    private static function stored(): array
    {
        $stored = get_option(self::OPTION, array());
        $themes = array();
        if (!is_array($stored)) {
            return $themes;
        }

        foreach ($stored as $id => $theme) {
            if (self::isValidId($id) && 0 !== strpos($id, self::BUILTIN_PREFIX) && is_array($theme)
                && isset($theme['name'], $theme['values']) && is_string($theme['name']) && is_array($theme['values'])) {
                $themes[$id] = array(
                    'name'     => $theme['name'],
                    // the content type it was saved from; Fusion Grid's own without one
                    'source'   => isset($theme['source']) && self::hasThemes($theme['source']) ? $theme['source'] : GalleryOptions::SET_ROBOGRID,
                    'values'   => $theme['values'],
                    'modified' => isset($theme['modified']) ? (int) $theme['modified'] : 0,
                );
            }
        }

        return $themes;
    }

    private static function store(array $themes): void
    {
        // read on the gallery pages only, so not loaded on every request
        if (false === get_option(self::OPTION, false)) {
            add_option(self::OPTION, $themes, '', false);
        } else {
            update_option(self::OPTION, $themes, false);
        }
    }

    /**
     * Known options a theme sets, each through its own sanitizing; an invalid value is left out (the default then).
     *
     * @param array $values option_id => value
     * @return array
     */
    private static function sanitizeValues(array $values): array
    {
        $options = GalleryOptions::getOptionsArray();
        $result  = array();
        foreach (self::themableIds() as $optionId) {
            if (!array_key_exists($optionId, $values)) {
                continue;
            }
            $value = GalleryOptions::sanitizeValue($options[$optionId], $values[$optionId]);
            if (!is_wp_error($value)) {
                $result[$optionId] = $value;
            }
        }

        return $result;
    }
}
