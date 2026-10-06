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

namespace RoboGallery\app\extensions\cloneSource;

defined('WPINC') || exit;

/**
 * "Clone Gallery" (meta rsg_options): a gallery can render with the settings
 * of another gallery of the same type (its source) instead of its own.
 *
 * - The editor field (includes/options/rbs_gallery_options_copy.php) offers
 *   only galleries the user can edit, plus the current source.
 * - Saving accepts none, the unchanged value or such a gallery.
 * - A source that doesn't exist (anymore) or isn't a gallery is ignored on the
 *   frontend - the gallery uses its own settings - and the reference is removed
 *   when the source is deleted and once on plugin update (activation\Upgrade).
 */
class CloneSource
{
    // galleries listed in the editor field
    const LIST_LIMIT = 100;

    public function register(): void
    {
        add_action('deleted_post', array($this, 'onPostDeleted'));
    }

    public static function metaKey(): string
    {
        return ROBO_GALLERY_PREFIX . 'options';
    }

    /**
     * @return int the gallery whose settings $galleryId renders with
     */
    public static function resolve(int $galleryId): int
    {
        $sourceId = (int) get_post_meta($galleryId, self::metaKey(), true);

        return self::isValidSource($sourceId, $galleryId) ? $sourceId : $galleryId;
    }

    public static function isValidSource(int $sourceId, int $galleryId): bool
    {
        return $sourceId > 0
            && $sourceId !== $galleryId
            && ROBO_GALLERY_TYPE_POST === get_post_type($sourceId);
    }

    /**
     * Editor field choices: galleries of the same type the user can edit. The
     * current source is always kept, otherwise saving the form (with a source
     * the user can't edit, or beyond LIST_LIMIT) would silently unlink it.
     *
     * @return array gallery ID => title
     */
    public static function choices(int $galleryId, int $currentSourceId): array
    {
        $galleries = get_posts(array(
            'post_type'      => ROBO_GALLERY_TYPE_POST,
            'meta_key'       => ROBO_GALLERY_PREFIX . 'gallery_type',
            // an empty stored type is grid: galleries saved as grid are offered for it
            'meta_value'     => \RoboGallery\app\GalleryUtils::getTypeGallery($galleryId),
            'post__not_in'   => array($galleryId),
            'orderby'        => 'title',
            'order'          => 'ASC',
            'posts_per_page' => self::LIST_LIMIT,
        ));

        $choices = array();
        foreach ($galleries as $gallery) {
            if (current_user_can('edit_post', $gallery->ID)) {
                $choices[$gallery->ID] = $gallery->post_title;
            }
        }

        if (self::isValidSource($currentSourceId, $galleryId) && !isset($choices[$currentSourceId])) {
            $choices = array($currentSourceId => get_the_title($currentSourceId)) + $choices;
        }

        return $choices;
    }

    /**
     * CMB2 sanitization_cb of the field.
     *
     * @param mixed                  $value
     * @param array                  $args
     * @param \CMBRE2_Field|null     $field
     * @return string
     */
    public static function sanitize($value, $args = array(), $field = null): string
    {
        $galleryId = $field ? (int) $field->object_id : 0;
        $current   = (int) get_post_meta($galleryId, self::metaKey(), true);

        // not a number: a forged request - keep what was stored
        if (!is_scalar($value) || !preg_match('/^\d+$/', trim((string) $value))) {
            return (string) $current;
        }

        $new = (int) $value;
        if ($new === $current || 0 === $new) {
            return (string) $new;
        }

        // only what the field offers: a gallery of the same type the user can edit
        if (
            !self::isValidSource($new, $galleryId)
            || !current_user_can('edit_post', $new)
            || \RoboGallery\app\GalleryUtils::getTypeGallery($new) !== \RoboGallery\app\GalleryUtils::getTypeGallery($galleryId)
        ) {
            return (string) $current;
        }

        return (string) $new;
    }

    /**
     * deleted_post: galleries that used the deleted post as their source become
     * regular galleries.
     *
     * @param int $postId
     */
    public function onPostDeleted($postId): void
    {
        delete_metadata('post', 0, self::metaKey(), (string) (int) $postId, true);
    }

    /**
     * Removes references to missing galleries, non-galleries and the gallery itself.
     *
     * @return int references removed
     */
    public static function removeInvalidReferences(): int
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value NOT IN ('', '0')",
            self::metaKey()
        ));

        $removed = 0;
        foreach ($rows as $row) {
            if (!self::isValidSource((int) $row->meta_value, (int) $row->post_id)) {
                delete_post_meta((int) $row->post_id, self::metaKey());
                $removed++;
            }
        }

        return $removed;
    }
}
