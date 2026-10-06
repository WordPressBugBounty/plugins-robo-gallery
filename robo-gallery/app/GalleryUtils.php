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

use RoboGallery\app\extensions\galleryType\GalleryTypeList;

defined('WPINC') || exit;

/**
 * Helpers of the gallery editor and the Pro upsell: the edited gallery and its
 * type/source (from the request of a new gallery, else from its meta), the
 * license key version, the Pro/update buttons.
 *
 * Used everywhere under its former name rbsGalleryUtils (alias below).
 */
final class GalleryUtils
{
    const TYPE_FIELD = ROBO_GALLERY_PREFIX . 'gallery_type';

    const SOURCE_FIELD = ROBO_GALLERY_PREFIX . 'gallery_type_source';

    /**
     * The gallery being edited: ?post= of the editor, post_ID when it is saved.
     */
    public static function getIdGallery(): int
    {
        $id = 0;

        if (isset($_GET['post']) && is_scalar($_GET['post'])) {
            $id = (int) $_GET['post'];
        }

        if (isset($_POST['post_ID']) && is_scalar($_POST['post_ID'])) {
            $id = (int) $_POST['post_ID'];
        }

        return $id;
    }

    /**
     * Type of the gallery ("grid", "gridpro"...): its meta, or ?rsg_gallery_type=
     * of a new gallery, "grid" by default.
     *
     * Letters only: meta can be written raw (core custom fields) and the type
     * ends up in inline JS and in a template path.
     *
     * With a gallery ID only its stored type is read (an empty or missing one is
     * grid), never the request: frontend and other code reading a given gallery
     * must not let ?rsg_gallery_type= change it.
     *
     * @param int $galleryId the edited gallery when 0
     */
    public static function getTypeGallery($galleryId = 0): string
    {
        if ($galleryId) {
            return GalleryTypeList::sanitizeType(get_post_meta((int) $galleryId, self::TYPE_FIELD, true));
        }

        return self::fromRequestOrMeta($galleryId, self::TYPE_FIELD, self::TYPE_FIELD, '/[^A-Za-z]/', 'grid');
    }

    /**
     * Source of the gallery ("grid", "gridpro-3"...): its meta, or
     * ?rsg_gallery_type= of a new gallery, empty by default.
     *
     * @param int $galleryId the edited gallery when 0
     */
    public static function getSourceGallery($galleryId = 0): string
    {
        return self::fromRequestOrMeta($galleryId, self::TYPE_FIELD, self::SOURCE_FIELD, '/[^A-Za-z0-9-]/', '');
    }

    /**
     * Readable source of the edited gallery: "Grid", "Grid Pro 3", "Fusion Grid".
     * A numbered source of a known type keeps its number even past the
     * registry's count (wallstylepro-7 saved before it had 6); anything else
     * unknown (custom-342) is shown as it is.
     */
    public static function getFullSourceGallery(): string
    {
        $source = self::getSourceGallery();

        $sources = GalleryTypeList::getAllSources();
        if (isset($sources[$source]) && !$sources[$source]['source_to']) {
            return $sources[$source]['name'];
        }

        // numbered source: name + number ("gridpro-3" => "Grid Pro 3")
        if (preg_match('/^([a-z]+)-(\d+)$/', $source, $m)) {
            $type = GalleryTypeList::getByType($m[1]);
            if ($type && $type['source_to']) {
                return $type['name'] . ' ' . $m[2];
            }
        }

        return ucfirst($source);
    }

    /**
     * The license key plugin is at least this version.
     */
    public static function compareVersion($version): bool
    {
        if (!ROBO_GALLERY_TYR || !defined('ROBO_GALLERY_KEY_VERSION')) {
            return false;
        }

        return version_compare(ROBO_GALLERY_KEY_VERSION, $version, '>=');
    }

    /**
     * "Update license key" button, Pro only.
     */
    public static function getUpdateButton($label): string
    {
        if (!ROBO_GALLERY_TYR) {
            return '';
        }

        return '<div class="content small-12 columns text-center" style="margin: 25px 0 -5px;">
					<a href="' . esc_url(ROBO_GALLERY_URL_UPDATEKEY) . '" target="_blank" class="hollow warning button">' . esc_html($label) . '</a>
				</div>';
    }

    /**
     * "Get Pro" button, Free only.
     */
    public static function getProButton($label): string
    {
        if (ROBO_GALLERY_TYR) {
            return '';
        }

        return '<a href="' . esc_url(ROBO_GALLERY_URL_UPDATEPRO) . '" target="_blank" class=" warning button strong " style="white-space: normal; line-height: 17px;">' . esc_html($label) . '</a>';
    }

    /**
     * The request arg of a new gallery, overridden by the gallery's meta when
     * there is a gallery; both cleaned by $pattern.
     */
    private static function fromRequestOrMeta($galleryId, string $requestArg, string $metaKey, string $pattern, string $default): string
    {
        $value = $default;

        if (!empty($_GET[$requestArg]) && is_string($_GET[$requestArg])) {
            $value = preg_replace($pattern, '', $_GET[$requestArg]);
        }

        $galleryId = $galleryId ? (int) $galleryId : self::getIdGallery();
        if ($galleryId) {
            $stored = preg_replace($pattern, '', (string) get_post_meta($galleryId, $metaKey, true));
            if ($stored) {
                $value = $stored;
            }
        }

        return $value;
    }
}

// the class's former name, used across the plugin
class_alias(GalleryUtils::class, 'rbsGalleryUtils');
