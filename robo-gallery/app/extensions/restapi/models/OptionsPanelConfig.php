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

namespace RoboGallery\app\extensions\restapi\models;

use RoboGallery\app\GalleryOptions;
use RoboGallery\app\extensions\cloneSource\CloneSource;
use RoboGallery\app\extensions\galleryType\GalleryTypeList;
use RoboGallery\app\extensions\imageManager\ImageManager;
use RoboGallery\app\extensions\proofing\Proofing;

defined('WPINC') || exit;

/**
 * The config of the settings panel (react_parts/options) for a gallery opened outside its editor: the
 * "Edit options" window of the block (react_parts/block). The same values the gallery editor gets as
 * window.robogallery_config (fields/template/content/robogrid/options.tpl.php - keep both in step), read by
 * the gallery's ID, not from the edited post. No type change: the type dialog is not on that page.
 */
class OptionsPanelConfig
{
    // the panel's files: fields/ is loaded in the admin only, its URL constant is not here
    const PANEL_DIR = 'app/extensions/fields/template/content/robogrid/';

    public static function forGallery(int $galleryId): array
    {
        $type        = \rbsGalleryUtils::getTypeGallery($galleryId);
        $optionSet   = GalleryOptions::setForType($type);
        $galleryType = GalleryTypeList::getByType($type);

        return array(
            'id'               => $galleryId,
            'restUrl'          => esc_url_raw(rest_url()),
            'wp_rest'          => wp_create_nonce('wp_rest'),
            'imagesUrl'        => esc_url_raw(ROBO_GALLERY_URL . self::PANEL_DIR),
            'blockPro'         => self::blockPro($optionSet),
            // a click on a locked Pro field opens it (FieldWrapper proLink)
            'proUrl'           => esc_url_raw(ROBO_GALLERY_URL_UPDATEPRO),
            // a key without client proofing: the Proofing tab asks to update it
            'keyUpdate'        => Proofing::keyUpdate(),
            'typeName'         => $galleryType ? (string) $galleryType['name'] : '',
            'galleryType'      => $type,
            'canChangeType'    => false,
            'isNew'            => 'auto-draft' === get_post_status($galleryId),
            'optionSet'        => $optionSet,
            'cloneSourceTitle' => self::cloneSourceTitle($galleryId, $optionSet),
            // the image manager of the Content tab (the gallery editor prints it as window.roboGalleryImageManager)
            'imageManager'     => in_array($type, ImageManager::PANEL_TYPES, true) ? ImageManager::config($type) : null,
            // what the page loads with the bundle: the hover preview of the Thumbs tab, the icons of the Hover tab
            'hoverPreview'     => GalleryTypeList::isGridScriptType($type),
            'fontAwesome'      => GalleryOptions::SET_CLASSIC === $optionSet,
        );
    }

    /**
     * PRO options are locked: as options.tpl.php decides it.
     */
    private static function blockPro(string $optionSet): bool
    {
        if (!defined('ROBO_GALLERY_TYR') || ROBO_GALLERY_TYR != 1) {
            return true;
        }

        if (defined('ROBO_GALLERY_KEY') && ROBO_GALLERY_KEY == 1 && \rbsGalleryUtils::compareVersion('3.0')) {
            return false;
        }

        // the Pro fields of the old boxes needed any key plugin, not a 3.0 one
        return GalleryOptions::SET_CLASSIC !== $optionSet;
    }

    /**
     * "Clone Gallery": the title of the gallery whose settings a classic gallery is shown with, else empty.
     * The raw title: the panel prints it as text.
     */
    private static function cloneSourceTitle(int $galleryId, string $optionSet): string
    {
        if (GalleryOptions::SET_CLASSIC !== $optionSet) {
            return '';
        }

        $sourceId = CloneSource::resolve($galleryId);
        if ($sourceId === $galleryId) {
            return '';
        }

        $title = (string) get_post_field('post_title', $sourceId, 'raw');

        return wp_strip_all_tags('' !== $title ? $title : '#' . $sourceId);
    }
}
